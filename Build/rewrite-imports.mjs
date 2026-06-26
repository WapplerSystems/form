#!/usr/bin/env node
/**
 * Minimal import-rewrite for the fork form build: append `.js` to every
 * `@typo3/...` ES module specifier (TYPO3's importmap serves files with the
 * extension). Bare npm packages (`lodash-es`) and the runtime `~labels/*`
 * virtual modules are left untouched — matching the shipped output.
 *
 * Usage: node rewrite-imports.mjs <dir>
 * Rewrites *.js in <dir> in place.
 */
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = process.argv[2];
if (!root) {
  console.error('usage: node rewrite-imports.mjs <dir>');
  process.exit(1);
}

// Rewrite ES module specifiers that resolve to local files: `@typo3/…` (other
// sysexts + the fork's own modules) and relative `./` / `../` imports. Append
// `.js` (TYPO3's importmap / static file serving needs the extension). Bare npm
// packages (`lodash-es`) and runtime virtual modules (`~labels/*`) are left as-is.
// Matches static `from '…'`, static side-effect `import '…'`, and dynamic
// `import('…')` (the `\(?` allows the dynamic-import parenthesis).
const re = /(from|import)(\s*\(?\s*)(['"])((?:@typo3\/|\.\.?\/)[^'"]+?)(['"])/g;

let files = 0;
let edits = 0;
function walk(dir) {
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry);
    const st = statSync(p);
    if (st.isDirectory()) { walk(p); continue; }
    if (!p.endsWith('.js')) continue;
    const src = readFileSync(p, 'utf8');
    const out = src.replace(re, (m, kw, ws, q1, spec, q2) =>
      spec.endsWith('.js') ? m : `${kw}${ws}${q1}${spec}.js${q2}`);
    if (out !== src) { writeFileSync(p, out); edits++; }
    files++;
  }
}
walk(root);
console.log(`rewrote imports in ${edits}/${files} .js files under ${root}`);
