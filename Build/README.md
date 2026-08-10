# Form editor JavaScript build (fork)

TypeScript sources for the form editor / form manager / form wizard, taken over
from the TYPO3 monorepo build so the fork can rebuild its own editor JS.

```
Build/
  Sources/TypeScript/form/**   ← the form editor TS (30 files), owned by the fork
  types/_shims.d.ts            ← build-only shims (~labels, lodash-es, global $)
  tsconfig.json                ← compiles the fork's form TS → ESM
  package.json                 ← `npm run build`
  JavaScript/**                ← build output (staging, git-ignored-worthy)
  node_modules                 → symlink to ../../../../source/Build/node_modules
```

## Build

```bash
cd packages/wapplersystems/form/Build
npm run build      # compile (tsc) → rewrite imports (.js) → deploy to ../Resources/Public/JavaScript
```

Steps (also runnable individually): `npm run compile` (tsc → `./JavaScript`),
`npm run rewrite` (append `.js` to `@typo3/…` + relative + dynamic-`import()`
specifiers, via `rewrite-imports.mjs`), `npm run deploy` (copy into
`../Resources/Public/JavaScript/`).

Result: 30 runtime-ready ESM modules deployed. **Verified in the backend** (form
editor + form manager load with 0 console errors / 0 failed requests).

## Reuse of the monorepo toolchain (hybrid approach — see plan V0)

To avoid duplicating the ~16k-package toolchain, the build **reuses**
`source/Build`:

1. **node_modules** — symlinked. Must contain TypeScript **≥ 6** (the sources use
   `NoInfer` etc.). `source/Build/package.json` declares `typescript: ^6.0.2`; if
   the install is stale (5.x), run `cd source/Build && npm install typescript@^6`.
2. **`@typo3/*` type surface** — the form TS imports `@typo3/backend|core|rte-ckeditor`
   modules. These are provided as **generated declarations** at
   `source/Build/.dts/` (tsconfig `paths: @typo3/* → ../../../../source/Build/.dts/*`).
   Regenerate when TYPO3 sources change:
   ```bash
   cd source/Build && ./node_modules/.bin/tsc --project tsconfig.dts.json   # → .dts/
   ```
   `@typo3/form/*` is mapped to the fork's own sources (kept fully typed).
3. **Ambient globals** — `TYPO3`, `bootstrap-src`, `ckeditor5-bundle`, `jquery`
   via `source/Build/types` + `@types`.

## Known gap (full V0, later)

The deployed output is **readable (non-minified) ESM**. The original upstream
assets were additionally **minified** (terser) by the monorepo build. This
pipeline does the import-rewrite and deploy but **not** minification — readable
output is fine functionally (and better for debugging editor work); adding an
optional minify step is the remaining V0 polish. A backup of the original
minified assets is at `/tmp/shipped-js-backup` for the current session.
