#!/usr/bin/env bash
#
# Propose pending upstream cherry-picks as PRs.
#
# Inputs (env): BASE, UPSTREAM_BRANCH, MAX_PRS, DRY_RUN, GH_TOKEN.
# Detection of "pending" uses `git cherry` (patch-id match) against origin/$BASE,
# so commits already cherry-picked into the fork (with the same diff) are excluded
# automatically. PRs already proposed are detected via the
# `<!-- upstream-sha:<sha> -->` marker in their body, scoped to label `upstream-sync`.

set -euo pipefail

SKIP_PATTERNS_FILE=".github/upstream-sync-skip.txt"
SKIP_SHAS_FILE=".github/upstream-sync-skip-shas.txt"
UPSTREAM_REF="upstream/${UPSTREAM_BRANCH}"

echo "::group::Configuration"
echo "Base branch:     $BASE  (origin/$BASE)"
echo "Upstream branch: $UPSTREAM_BRANCH  ($UPSTREAM_REF)"
echo "Max PRs:         $MAX_PRS"
echo "Dry run:         $DRY_RUN"
echo "::endgroup::"

# ---------- Build skip regex from patterns file ----------
skip_regex=""
if [[ -f "$SKIP_PATTERNS_FILE" ]]; then
  while IFS= read -r line || [[ -n "$line" ]]; do
    [[ -z "${line// }" || "$line" =~ ^[[:space:]]*# ]] && continue
    skip_regex+="${skip_regex:+|}(${line})"
  done < "$SKIP_PATTERNS_FILE"
fi
echo "Skip regex: ${skip_regex:-<none>}"

# ---------- Skip SHA set ----------
declare -A skip_shas=()
if [[ -f "$SKIP_SHAS_FILE" ]]; then
  while IFS= read -r line || [[ -n "$line" ]]; do
    [[ -z "${line// }" || "$line" =~ ^[[:space:]]*# ]] && continue
    full=$(git rev-parse --verify "${line}^{commit}" 2>/dev/null || echo "")
    [[ -n "$full" ]] && skip_shas["$full"]=1
  done < "$SKIP_SHAS_FILE"
fi

# ---------- Pending commits ----------
mapfile -t pending < <(git cherry "origin/$BASE" "$UPSTREAM_REF" | awk '/^\+/{print $2}')
if [[ ${#pending[@]} -eq 0 ]]; then
  echo "No pending upstream commits — fork is up-to-date with $UPSTREAM_REF (by patch-id)."
  exit 0
fi
echo "Found ${#pending[@]} pending upstream commit(s)."

# ---------- Already-proposed SHAs (any state, label-scoped) ----------
declare -A already=()
proposed_json=$(gh pr list --label upstream-sync --state all --limit 300 --json body 2>/dev/null || echo '[]')
while IFS= read -r sha; do
  [[ -n "$sha" ]] && already["$sha"]=1
done < <(echo "$proposed_json" | jq -r '.[] | .body // ""' | grep -oE 'upstream-sha:[a-f0-9]{7,40}' | cut -d: -f2)
echo "Already-proposed SHAs found in existing PRs: ${#already[@]}"

# ---------- Main loop ----------
created=0
skipped=0
for sha in "${pending[@]}"; do
  if (( created >= MAX_PRS )); then
    echo "::notice::Reached max_prs=$MAX_PRS — remaining commits will be picked up next run."
    break
  fi

  subject=$(git log -1 --format='%s' "$sha")
  short=$(git rev-parse --short=10 "$sha")
  branch="upstream-sync/${short}"

  if [[ -n "${skip_shas[$sha]:-}" ]]; then
    echo "skip $short  (SHA in $SKIP_SHAS_FILE)  — $subject"
    skipped=$((skipped+1)); continue
  fi
  if [[ -n "$skip_regex" ]] && grep -Eq -- "$skip_regex" <<<"$subject"; then
    echo "skip $short  (subject matches skip pattern)  — $subject"
    skipped=$((skipped+1)); continue
  fi
  # Match both full and shortened SHA forms recorded in PR bodies.
  matched_existing=0
  for k in "${!already[@]}"; do
    if [[ "$sha" == "$k"* || "$k" == "$sha"* ]]; then matched_existing=1; break; fi
  done
  if (( matched_existing )); then
    echo "skip $short  (PR already exists)  — $subject"
    skipped=$((skipped+1)); continue
  fi
  # Branch exists on origin but no PR carries the marker → orphan from a
  # previous failed run. Overwrite it instead of skipping (self-healing).
  orphan_branch=0
  if git ls-remote --exit-code --heads origin "$branch" >/dev/null 2>&1; then
    orphan_branch=1
    echo "info $short  (orphan branch origin/$branch will be overwritten — no PR carries its marker)"
  fi

  echo "::group::Process $short — $subject"

  # Recreate the branch from base — local-only at first, we push later.
  git checkout -B "$branch" "origin/$BASE" --

  conflict=0
  if git cherry-pick -x "$sha"; then
    echo "Clean cherry-pick."
  else
    conflict=1
    echo "::warning::Cherry-pick had conflicts — committing WIP state for manual resolution."
    git add -A
    msg=$(printf '%s\n\n(cherry picked from commit %s)\n\n[CONFLICT — manual resolution required]\n' \
          "$(git log -1 --format='%B' "$sha")" "$sha")
    git -c commit.gpgsign=false commit --no-verify --allow-empty -m "$msg"
    git_dir=$(git rev-parse --git-dir)
    rm -f "$git_dir/CHERRY_PICK_HEAD" "$git_dir/MERGE_MSG" 2>/dev/null || true
  fi

  if [[ "$DRY_RUN" == "true" ]]; then
    echo "DRY-RUN — would push $branch and open PR."
    git checkout --detach >/dev/null 2>&1 || true
    git branch -D "$branch" >/dev/null 2>&1 || true
    echo "::endgroup::"
    continue
  fi

  if (( orphan_branch )); then
    git push --force-with-lease --set-upstream origin "$branch"
  else
    git push --set-upstream origin "$branch"
  fi

  # Build label set
  declare -a labels=("upstream-sync")
  (( conflict )) && labels+=("needs-conflict-resolution")
  case "$subject" in
    *"[SECURITY]"*) labels+=("security") ;;
    *"[BUGFIX]"*)   labels+=("bugfix") ;;
  esac
  [[ "$subject" == *"[!!!]"* ]] && labels+=("breaking-change")
  declare -a label_args=()
  for l in "${labels[@]}"; do label_args+=(--label "$l"); done

  reviewed_on=$(git log -1 --format='%b' "$sha" | awk -F': ' 'tolower($1)=="reviewed-on"{print $2; exit}')
  bulletin=$(git   log -1 --format='%b' "$sha" | awk -F': ' 'tolower($1)=="security-bulletin"{print $2; exit}')
  cve=$(git        log -1 --format='%b' "$sha" | awk -F': ' 'tolower($1)=="security-references"{print $2; exit}')
  resolves=$(git   log -1 --format='%b' "$sha" | awk -F': ' 'tolower($1)=="resolves"{print $2; exit}')
  upstream_url="https://github.com/TYPO3-CMS/form/commit/${sha}"

  body_file=$(mktemp)
  {
    printf 'Automatisch vorgeschlagener Cherry-pick aus [TYPO3-CMS/form @ %s](https://github.com/TYPO3-CMS/form/tree/%s).\n\n' \
           "$UPSTREAM_BRANCH" "$UPSTREAM_BRANCH"
    if (( conflict )); then
      printf '> **⚠ Conflict detected** — diese PR enthält Konfliktmarker und ist als Draft markiert.\n'
      printf '> Bitte lokal auschecken, manuell auflösen, dann das Label `needs-conflict-resolution` entfernen und die PR aus dem Draft-Status nehmen.\n\n'
    fi
    printf '## Upstream-Commit\n\n'
    # bash 5.2 printf builtin (on github-runners) rejects `--`. Pass the
    # leading dash inside the data argument with a `%s\n` format instead.
    printf '%s\n' "- **SHA**: [\`$sha\`]($upstream_url)"
    printf '%s\n' "- **Author**: $(git log -1 --format='%an' "$sha")"
    printf '%s\n' "- **Date**: $(git log -1 --format='%ai' "$sha")"
    [[ -n "$reviewed_on" ]] && printf '%s\n' "- **Reviewed-on (Gerrit)**: $reviewed_on"
    [[ -n "$resolves" ]]    && printf '%s\n' "- **Resolves**: $resolves"
    [[ -n "$bulletin" ]]    && printf '%s\n' "- **Security-Bulletin**: $bulletin"
    [[ -n "$cve" ]]         && printf '%s\n' "- **Security-References**: $cve"
    printf '\n## Commit-Message\n\n<details><summary>Upstream-Commit-Message anzeigen</summary>\n\n```\n'
    git log -1 --format='%B' "$sha"
    printf '```\n\n</details>\n\n'
    printf '---\n'
    printf 'Erzeugt von `.github/workflows/upstream-sync.yml`.\n'
    printf 'Um diesen Commit künftig nicht erneut vorzuschlagen, schließe die PR ohne Merge — der Marker unten bleibt erhalten.\n\n'
    printf '<!-- DO NOT EDIT — upstream-sha:%s -->\n' "$sha"
  } > "$body_file"

  draft_args=()
  (( conflict )) && draft_args=(--draft)

  if gh pr create \
        --base "$BASE" \
        --head "$branch" \
        --title "[upstream] $subject" \
        --body-file "$body_file" \
        "${draft_args[@]}" \
        "${label_args[@]}"; then
    created=$((created+1))
  else
    echo "::error::Failed to create PR for $short"
  fi
  rm -f "$body_file"
  echo "::endgroup::"
done

echo "----"
echo "Summary: created=$created skipped=$skipped pending_total=${#pending[@]}"