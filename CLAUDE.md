# wapplersystems/form — working notes

A hard fork of the TYPO3 system extension `form` (upstream subtree split
<https://github.com/TYPO3-CMS/form>). It **replaces `typo3/cms-form`** via Composer, keeps
the extension key `form` on disk and the `TYPO3\CMS\Form\…` namespace, and is therefore a
drop-in: everything referencing EXT:form — YAML mixins, form editor JS, Fluid paths, FAL
config — keeps working. It also `replace`s `wapplersystems/form_extended`, which it has
absorbed; a parallel install is impossible on purpose (both ship a `FormEditorController`
XCLASS).

**Read `README.md` before changing behaviour.** It documents every fork addition with the
reasoning: events, cross-field validators, the spam shield, the mail log, the validation
log, the password-policy endpoint, the site-sender feature. This file covers only what you
need to *work* in the repository.

## Layout

- `Classes/` — standard upstream layout (`Domain/`, `Controller/`, `Mvc/`, `ViewHelpers/`,
  …). Fork additions live in the **same** directories under **distinct class names**, so
  upstream cherry-picks land cleanly. Fork-only subtrees worth knowing: `Event/` (the extra
  PSR-14 events), `Validation/` (cross-field validators), `Enum/`, `Task/`, `Command/`.
- `Configuration/Form/` — the prototype YAML. Editor definitions for the form editor live
  under `Base/FormElements/Form.yaml`; editor indices are API for third parties, do not
  renumber them.
- `Resources/Private/Frontend|Backend|Templates|Partials/` — Fluid. `Frontend/` is the
  frontend rendering, `Templates/Backend/` the backend modules.
- `Build/` — TypeScript sources for the form editor and the frontend scripts.
- `Documentation/Images/` — the README screenshots.

## Commands

```bash
# Unit tests — fine on PHP 8.4 and 8.5
vendor/bin/phpunit -c Build/phpunit/UnitTests.xml

# Functional tests — SQLite, no DB container needed
typo3DatabaseDriver=pdo_sqlite vendor/bin/phpunit -c Build/phpunit/FunctionalTests.xml

# Editor/frontend JavaScript: tsc → rewrite import specifiers → copy into Resources/Public
cd Build && npm install && npm run build
```

**Functional tests need PHP 8.4.** On 8.5 the TYPO3 14.3 functional bootstrap fails every
test with "did not close its own output buffers" — a PHP-version problem, not ours (the
pre-existing `CountValidatorTest` fails identically). If the local PHP is 8.5, do not try
to interpret functional failures locally; push and read the CI job, which runs 8.4.

After rebuilding JS, reload the backend with the cache bypassed — the backend serves ES
modules and caches them hard.

## Conventions

- **Fluid comments** use the block ViewHelper `<f:comment>…</f:comment>`, never HTML
  comments — those reach the client. Exception: functional markers other systems parse
  (`<!--TYPO3SEARCH_begin-->`).
- **Commit subjects** carry a tag: `[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]`,
  `[SECURITY]`. The body explains *why*, not what the diff already shows. A commit touching
  an upstream file must say why the upstream file had to change — adding a hook or event
  and contributing it back is nearly always preferable.
- **User-visible changes get a line in the README changelog** (`## Changelog`, newest month
  first, short SHA at the end). Pure test/CI/docs commits do not.
- **New editor UI** goes through the form editor's existing extension points (TypeScript
  under `Build/Sources/TypeScript/form/`, partials registered via `formEditorPartials`),
  not by patching upstream templates.
- Every public API surface the fork adds gets a PSR-14 event so downstream extensions can
  consume it without subclassing.

## Upstream sync and releases

- `.github/workflows/upstream-sync.yml` proposes **one pull request per pending upstream
  commit** daily (branch `upstream-sync/<sha>`, label `upstream-sync`). Merge reviewed ones
  with `gh pr merge <n> --merge`. **Never merge `upstream/14.3` wholesale.**
- Before merging an `[upstream]` PR, check whether it depends on a sibling core change that
  the pinned `typo3/cms-*` release does not ship yet — grep `vendor/typo3/` for the symbol.
  A patch referencing a not-yet-released CSS custom property or API silently regresses.
- **Release tags are annotated and `v`-prefixed** (`v14.3.11`). In this repository the
  `v14.3.x` namespace belongs to *fork* releases; core tags are deliberately not pushed to
  `origin`. Tag on `release/v14`, push the tag, Packagist picks it up.

## Traps this codebase has already hit

Each of these cost a debugging session; the fix is in the code, the reason is here.

- **GET forms in backend modules lose the request token.** A GET submission replaces the
  action's query string, so the token is gone, `RouteDispatcher` throws
  `MissingRequestTokenException` and the login route renders the *whole backend* inside the
  module frame. Use `AbstractFormLogController::buildFilterFormTarget()`: bare path as
  action, every query parameter re-emitted as a hidden field.
- **`selected="{f:if(condition: …)}"` always selects.** An empty attribute value still
  renders `selected=""`, which HTML treats as selected — so every option is selected and
  the browser shows the last. Render two distinct `<option>` variants instead.
- **Scheduler tasks are built by the scheduler, not the container.** Any service such a
  task resolves via `makeInstance` must be `public: true` in `Configuration/Services.yaml`.
- **Functional tests that need the form service** must set
  `protected array $coreExtensionsToLoad = ['form'];`, otherwise the extension's tables are
  never created. Tests that *render a backend module* additionally need a request carrying
  `normalizedParams` and a `LanguageService` in `$GLOBALS['LANG']` — see
  `Tests/Functional/Controller/FormLogFilterFormTest.php`.
- **The form log module must stay on the second level of the module menu.**
  `BackendModuleValidator` makes the last visited third-level module the landing page of
  its parent; as a child of *Forms* the log permanently replaced the form list, which the
  two-level menu then could not reach at all.
- **The frontend must work on fully cached pages.** Anything per-visitor (spam challenge
  token, fill-time measurement) is therefore stateless or measured client-side. Do not
  reach for the session.
- **The RTE sanitiser resolves translation overlays back to their property.** Overlay
  values live under `renderingOptions.translation.overrides.<lang>.<property>`; sanitising
  them by their literal path strips markup that the untranslated property is allowed to
  carry.
