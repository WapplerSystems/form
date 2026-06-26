# wapplersystems/form

A hard fork of `typo3/cms-form` (TYPO3 v14) that extends the form framework and its
backend editor with features the upstream extension does not provide. It is a drop-in
replacement for `typo3/cms-form` — existing forms keep working; every addition is
backwards compatible and degrades gracefully.

> Deep technical notes (branch layout, upstream sync, internals) live in
> [`FORK.md`](FORK.md). This README is the feature overview.

## New features

### Backend editor

- **Variant / condition editor** — Variants (conditions) are editable directly in the
  form editor for **every** renderable (elements, pages, the form) **and every
  finisher**, instead of being YAML-only. Per variant: condition expression,
  visibility (`renderingOptions.enabled`) and conditional *required* (`NotEmpty`).
  Round-trip-safe on save. Previously, opening + saving a form in the editor silently
  dropped hand-written variants.

- **Visual condition builder** — A *Build…* button next to any condition field opens a
  modal to click conditions together: rule rows (field + operator + value) with nested
  AND/OR groups, live expression preview, and parsing of existing expressions back into
  the tree (with a raw-text fallback for anything non-parseable).

- **Comfortable e-mail content editor** — Each e-mail finisher gets an *Edit email
  content* button opening a large modal with: a **template chooser**, a **rich-text
  HTML body** and a **separate plain-text body**, a **server-rendered preview** (the
  real Fluid e-mail layout, filled with type-appropriate sample values), and a
  **“Send test email”** action. HTML and plain text are now independent finisher
  options.

- **Field-marker inserter** — In the e-mail content editor (HTML and plain panes), an
  *Insert field marker…* dropdown drops `{fieldIdentifier}` / `{formValues}`
  placeholders at the cursor, so editors don’t type the placeholder syntax by hand.

### Localization (in-editor, per site language)

No XLF authoring required — translations are stored inside the form definition and work
for database-stored forms too.

- **Per-element translation** — A *Translate…* button per element edits **label,
  placeholder and option labels** for every configured site language, with a
  completeness badge.

- **Form-wide translation overview** — A *Translate whole form…* button on the form
  opens a matrix of every translatable string × every language in one place, including
  finisher options (see below), with an overall completeness indicator.

- **Translatable validation messages** — Custom validator error messages
  (`properties.validationErrorMessages`) are translatable per language. Built-in
  validator messages remain localized via TYPO3’s shipped XLF.

- **Translatable finisher options** — The text options of e-mail / confirmation
  finishers (`subject`, `message`, `plainMessage`) are translatable per language, both
  from a per-finisher *Translate…* button and from the form-wide overview.

### Frontend

- **Live conditions (same page)** — Variants/conditions that reference fields on the
  **same page** now react live in the browser (show/hide, toggle *required*) while the
  user types, without a server round-trip. The server stays authoritative on submit.

### Runtime

- **Variant-capable finishers** — Any finisher can carry a `variants` list and be
  enabled/disabled (or otherwise overridden) by a condition on the submitted values
  (e.g. “send a copy to the sender only when the checkbox is ticked”). The dedicated
  `CopyToSenderEmail` finisher was removed in favour of this general mechanism.

- **Additional PSR-14 events** — Extra extension points around the form lifecycle:
  form submitted / state initialized / rendered, before finishers invoked, after mail
  sent, after database record persisted, after file uploaded, after renderable
  validated. (Full list and signatures in [`FORK.md`](FORK.md).)

### Other additions carried by the fork

- Cross-field / form-level validators (e.g. an entropy-based spam filter).
- Extra form elements (`Time`, `CountrySelect`) and view helpers.
- Opt-in site-sender feature and opt-in validation-failure logging.
- Password-policy JSON endpoint.
- A self-contained build pipeline for the editor TypeScript sources under `Build/`
  (`npm run build` → compile → rewrite imports → deploy to `Resources/Public/JavaScript/`).

## Installation

Replaces `typo3/cms-form`; install via Composer in the usual way for this project. See
[`FORK.md`](FORK.md) for the branch layout and how the fork tracks upstream.

## Building the editor JavaScript

```bash
cd Build
npm install
npm run build
```

Note: the TYPO3 backend serves the editor as ES modules and caches them aggressively —
after a rebuild, reload the editor with the browser cache bypassed (hard reload).
