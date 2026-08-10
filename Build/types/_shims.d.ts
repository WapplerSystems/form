/**
 * Build-time shims for the fork's form editor compilation.
 *
 * Goal: compile the fork's own `@typo3/form/*` TypeScript sources (which ARE
 * resolved to the copied sources via tsconfig `paths`), while treating the
 * cross-sysext dependencies as opaque external modules. The runtime resolves
 * all of these via the TYPO3 importmap; at build time we only need them to not
 * break type resolution.
 *
 * Trade-off: cross-sysext imports (@typo3/backend|core|rte-ckeditor) and
 * lodash-es become `any` here, so type errors that cross those boundaries are
 * not caught. Restoring full type fidelity (real .d.ts surface) is a later
 * hardening step — see plan V0 "Vollständiger Plan".
 */

// Build-virtual label modules (`~labels/...`); runtime ESM via importmap.
// `get()` takes an optional interpolation argument (e.g. { count }).
declare module '~labels/*' {
  const labels: {
    get(key: string, options?: unknown): string;
  };
  export default labels;
}

// No @types/lodash-es is installed in the reused toolchain.
declare module 'lodash-es';

// frontend/date-picker.ts uses the global jQuery `$` (provided at runtime by the
// page). @types/jquery only exposes `$` as a module export (`export = $`), not a
// global, so declare the ambient global here. The named jQuery types
// (JQueryStatic, JQueryEventObject) already resolve via the "jquery" entry in
// tsconfig `types`.
declare const $: JQueryStatic;
