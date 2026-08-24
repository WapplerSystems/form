# wapplersystems/form

A hard fork of the TYPO3 system extension `form` — the read-only subtree split at
<https://github.com/TYPO3-CMS/form>, itself derived from `typo3/sysext/form/` in
<https://github.com/TYPO3/typo3>.

It is installed via Composer as `wapplersystems/form` and **transparently replaces
`typo3/cms-form`** through Composer's `replace` mechanism. The Composer package name and
the TYPO3 extension key on disk are deliberately different (`wapplersystems/form` vs.
`form`) so the extension stays a drop-in for `EXT:form` — everything that references
`\TYPO3\CMS\Form\…`, the YAML mixins, the form editor JavaScript, the Fluid template
paths and the FAL config keeps working without changes.

Every addition is backwards compatible: existing forms keep working and features degrade
gracefully (e.g. without JavaScript, or for forms that don't use them).

## Why fork?

The TYPO3 core `form` sysext is intentionally minimal in places where editor workflows
benefit from more depth:

1. **More events** — make finisher pipelines, variant evaluation and form rendering
   pluggable from outside via PSR-14 events instead of class extension.
2. **Backend editor parity** — features that exist today only via YAML hand-editing
   (variants/conditions, complex validators, finisher options, translations) become
   editable in the form editor.
3. **Cross-field validators** — validators that need more than a single field's value
   (entropy/spam filtering across all submitted text, conditional `required`, sums of
   numeric fields, etc.).
4. **Visual variants/conditions editor** — so integrators express conditional behavior
   without writing YAML.
5. **Consolidate `wapplersystems/form_extended`** — the patches and additions that live
   in `form_extended` (multi-upload, sender-address config in site settings,
   country/date/time fields, custom finishers) migrate into this fork step by step;
   `form_extended` is then deprecated.

---

## New features

### Screenshots

The fork's backend editor, shown here with the German interface language (labels
are shipped in `Resources/Private/Language/de.Database.xlf`):

| | |
| --- | --- |
| ![Inspector: variants & conditions and in-editor translation](Documentation/Images/01-inspector-variants-translations-de.png) | ![Whole-form translation overview](Documentation/Images/02-whole-form-translation-de.png) |
| Variants & conditions plus per-element and whole-form translation, right in the inspector. | The whole-form translation matrix (every translatable string × every site language). |
| ![E-mail content editor – HTML body](Documentation/Images/03-email-content-html-de.png) | ![E-mail content editor – plain text](Documentation/Images/04-email-content-plaintext-de.png) |
| Rich-text **HTML** body of an e-mail finisher, with field-marker insertion. | Separate **plain-text** body (left empty it is derived from the HTML automatically). |
| ![E-mail content editor – preview](Documentation/Images/05-email-content-preview-de.png) | ![Visual condition builder](Documentation/Images/06-condition-builder-de.png) |
| Server-rendered **preview** (real Fluid e-mail layout, filled with sample values) and a test-send action. | The visual **condition builder** with a live expression preview. |

### Backend editor

- **Variant / condition editor** — Variants (conditions) are editable directly in the
  form editor for **every** renderable (elements, pages, the form) **and every
  finisher**, instead of being YAML-only. Per variant: condition expression, visibility
  (`renderingOptions.enabled`) and conditional *required* (`NotEmpty`). Round-trip-safe
  on save. Previously, opening + saving a form in the editor silently dropped
  hand-written variants.

- **Visual condition builder** — A *Build…* button next to any condition field opens a
  modal to click conditions together: rule rows (field + operator + value) with nested
  AND/OR groups, live expression preview, and parsing of existing expressions back into
  the tree (raw-text fallback for anything non-parseable).

- **Comfortable e-mail content editor** — Each e-mail finisher gets an *Edit email
  content* button opening a large modal with: a **template chooser**, a **rich-text HTML
  body** and a **separate plain-text body**, a **server-rendered preview** (the real
  Fluid e-mail layout, filled with type-appropriate sample values), and a **“Send test
  email”** action. HTML and plain text are now independent finisher options.

- **Field-marker inserter** — In the e-mail content editor (HTML and plain panes), an
  *Insert field marker…* dropdown drops `{fieldIdentifier}` / `{formValues}` placeholders
  at the cursor, so editors don't type the placeholder syntax by hand.

### Localization (in-editor, per site language)

No XLF authoring required — translations are stored inside the form definition and work
for database-stored forms too.

- **Per-element translation** — A *Translate…* button per element edits **label,
  placeholder and option labels** for every configured site language, with a completeness
  badge.

- **Form-wide translation overview** — A *Translate whole form…* button on the form opens
  a matrix of every translatable string × every language in one place (including finisher
  options), with an overall completeness indicator.

- **Translatable validation messages** — Custom validator error messages
  (`properties.validationErrorMessages`) are translatable per language. Built-in validator
  messages remain localized via TYPO3's shipped XLF.

- **Translatable finisher options** — The text options of e-mail / confirmation finishers
  (`subject`, `message`, `plainMessage`) are translatable per language, both from a
  per-finisher *Translate…* button and from the form-wide overview.

### Frontend

- **Live conditions (same page)** — Variants/conditions that reference fields on the
  **same page** react live in the browser (show/hide, toggle *required*) while the user
  types, without a server round-trip. The server stays authoritative on submit.

### Runtime

- **Variant-capable finishers** — Any finisher can carry a `variants` list and be
  enabled/disabled (or otherwise overridden) by a condition on the submitted values
  (e.g. “send a copy to the sender only when the checkbox is ticked”). The dedicated
  `CopyToSenderEmail` finisher was removed in favour of this general mechanism.

- **Additional PSR-14 events** — Extra extension points around the whole form lifecycle
  (see the reference table below).

### Other additions carried by the fork

- Cross-field / form-level validators (e.g. an entropy-based spam filter).
- Extra form elements (`Time`) and finishers (`RedirectToUri`, `FeUser`,
  `AttachUploadsToObject`) and view helpers.
- Opt-in site-sender feature and opt-in validation-failure logging.
- Password-policy JSON endpoint.
- A self-contained build pipeline for the editor TypeScript sources under `Build/`.

---

## Installation

Replaces `typo3/cms-form`; install via Composer. The `replace` clause in this package's
`composer.json` makes Composer treat `typo3/cms-form` as already satisfied, so no second
copy is downloaded.

### Local development inside the dev14 monorepo

The `dev14` Composer project loads this directory via the `packages/*/*` path repository.
To require it, replace the line

```json
"typo3/cms-form": "14.3.*@dev",
```

in the project's `composer.json` with

```json
"wapplersystems/form": "dev-release/v14 as 14.3",
```

### Building the editor JavaScript

The editor's TypeScript sources live under `Build/`. The pipeline compiles, rewrites
import specifiers, and deploys to `Resources/Public/JavaScript/`:

```bash
cd Build
npm install
npm run build      # tsc → rewrite-imports → deploy
```

> Note: the TYPO3 backend serves the editor as ES modules and caches them aggressively —
> after a rebuild, reload the editor with the browser cache bypassed (hard reload), or the
> stale module keeps running.

---

## Reference

### Fork-added PSR-14 events

All fork-added events live in `TYPO3\CMS\Form\Event\`, alongside the events shipped by
upstream EXT:form, and are dispatched from patched upstream call-sites. Their class names
are distinct from the upstream ones, so the two sets never collide.

| Event | Fired from | Carries | Use-case |
| --- | --- | --- | --- |
| `BeforeFormPageProcessedEvent` | `FormRuntime::processSubmittedFormValues()` | `Page`, `FormRuntime`, `RequestInterface` | Preprocess submitted request args, snapshot for analytics, early-exit hooks |
| `BeforeFormIsValidatedEvent` | `FormRuntime::mapAndValidatePage()` (start) | `Page`, `FormRuntime`, `RequestInterface` | Setup before cross-field validators run (precompute shared values) |
| `AfterFormIsValidatedEvent` | `FormRuntime::mapAndValidatePage()` (end) | `Page`, `FormRuntime`, `RequestInterface`, **mutable** `Result` | Cross-field validators add errors via `$event->result->forProperty(...)->addError(...)`. Also the hook for validation-failure logging. |
| `AfterVariantAppliedEvent` | `FormRuntime::processVariants()` | `VariableRenderableInterface`, `RenderableVariantInterface`, `FormRuntime` | React to dynamic form structure changes (cache invalidation, condition-match analytics) |
| `BeforeFinisherExecutedEvent` | `AbstractFinisher::execute()` | `FinisherInterface`, `FinisherContext` | Inject runtime values, log finisher invocations, call `$context->cancel()` to skip the rest of the chain |
| `AfterFinisherExecutedEvent` | `AbstractFinisher::execute()` | `FinisherInterface`, `FinisherContext`, `mixed` (executeInternal result) | Post-finisher logging, output transformation, follow-up actions. Does **not** fire on FinisherException. |
| `AfterYamlConfigurationLoadedEvent` | `ConfigurationManager::getYamlConfiguration()` | mutable `array $yamlConfiguration` | Inject runtime-computed values into the form-editor configuration (site languages, file mounts, dynamic option lists). Fires on every load, not cache-gated — listeners must be cheap. |
| `MailBeforeSendingEvent` | `EmailFinisher::executeInternal()` | `FluidEmail` (mutable), `FinisherContext`, `EmailFinisher` | Mutate the email immediately before transport — extra recipients, custom headers, conditional attachments, audit logging. Does **not** fire if EmailFinisher throws before reaching the transport step. |
| `AfterMailSentEvent` | `EmailFinisher::executeInternal()` | `FluidEmail`, `FinisherContext`, `EmailFinisher` | Fires **only after a successful** `MailerInterface::send()` — the reliable "delivered" hook for audit logging / post-delivery follow-ups (unlike `MailBeforeSendingEvent`, which can't tell success from a later transport failure). |
| `AfterFormStateInitializedEvent` | `FormRuntime::triggerAfterFormStateInitialized()` | `FormRuntime` | PSR-14 replacement for the legacy `SC_OPTIONS['ext/form']['afterFormStateInitialized']` hook (still fired alongside). Canonical point to **prefill** form values from fe_user / GET-POST / session via the FormRuntime ArrayAccess API (`$event->formRuntime['email'] = …`). Fires every request, so guard first-display-only prefills. |
| `AfterFormSubmittedEvent` | `FormRuntime::invokeFinishers()` (after the chain) | `FormRuntime`, `array $formValues`, `string $renderedOutput`, `bool $wasCancelled` | Fires **exactly once** per submission after the whole finisher chain (Before/AfterFinisherExecuted fire per finisher). The hook for "submission complete" actions: conversion / analytics tracking, CRM sync, a single follow-up. `wasCancelled` reflects a finisher having called `FinisherContext::cancel()`. |
| `BeforeFinishersInvokedEvent` | `FormRuntime::invokeFinishers()` (before the chain) | `FormRuntime`, **mutable** `FinisherInterface[] $finishers`, `FinisherContext` | Fires once before the chain. Listeners may reorder / filter / inject finishers (FormRuntime iterates the modified `$finishers`), cancel the whole chain via `$finisherContext->cancel()`, or seed the shared FinisherVariableProvider. Counterpart to `AfterFormSubmittedEvent`. |
| `AfterDatabaseRecordPersistedEvent` | `SaveToDatabaseFinisher::saveToDatabase()` (also covers `FeUserFinisher`) | `string $table`, `int $uid`, `array $data`, `'insert'\|'update' $mode`, `FinisherInterface`, `FinisherContext` | Fires after a row was inserted/updated by a form. Hook for "record persisted" follow-ups (workflow on new fe_user, CRM push) with the inserted `uid` in hand. For `update` there is no single uid → `$uid` is `0`; use `$mode`/`$data`. |
| `AfterFileUploadedEvent` | `UploadedFileReferenceConverter::importUploadedResource()` | `File $file`, `array $uploadInfo` | Fires right after an uploaded file is stored in FAL (before the FileReference is built). Use for virus scanning, EXIF/metadata stripping, content policy. A listener that **throws** aborts property mapping and thereby rejects the upload. |
| `AfterRenderableIsValidatedEvent` | `FormRuntime::mapAndValidatePage()` (per field) | `RenderableInterface`, `mixed $value`, `FormRuntime`, `RequestInterface`, `Result $validationResult` | Per-renderable companion to upstream `BeforeRenderableIsValidatedEvent`; fires after a field's processing rule ran. Inspect or add field-scoped errors via `$event->validationResult->addError(...)`. Fires only for renderables that have a processing rule. For the page aggregate use `AfterFormIsValidatedEvent`. |
| `AfterFormRenderedEvent` | `FormRuntime::render()` | `FormRuntime`, **mutable** `string $renderedContent` | Fires after the renderer produced the form markup (page-render path only, not finisher output). Listeners may rewrite/wrap the markup — tracking pixel, JSON island for client-side logic, CSP nonces. FormRuntime returns the modified `$renderedContent`. |

The Before/After finisher events fire generically for every finisher inheriting
`AbstractFinisher` — Email, Redirect, Confirmation, SaveToDatabase, FlashMessage,
DeleteUploads, Closure and any custom finishers. No need to subclass per finisher type.

### Frontend live-conditions (same-page variants)

Variants whose condition references a field on the **same page** are applied live in the
browser (show/hide via `renderingOptions.enabled`, required via a `NotEmpty` validator) —
not just on the server at page/step transitions. Pieces:

- `InjectFrontendConditions` (listener on `AfterFormRenderedEvent`) emits a JSON island
  `<script type="application/json" data-wsform-conditions>` inside the `<form>` carrying
  each element's `{condition, enabled?, required?}` rules, and loads
  `Resources/Public/JavaScript/frontend/form-conditions.js` via `AssetCollector`. Forms
  without such variants get neither.
- `FormRuntime::render()` re-enables (`reEnableClientConditionElements()`) elements that a
  *client-evaluable* variant disabled, so they are present in the DOM for the client to
  toggle. A condition is client-evaluable when it uses `traverse(formValues, …)` and no
  server-only context (`stepType`, `finisherIdentifier`, …). The server re-evaluates
  authoritatively on submit; without JS the fields stay visible and validation still holds.
- `frontend/form-conditions.js` evaluates a subset of the ExpressionLanguage
  (`traverse(formValues,"id")`, `== != < <= > >= in "not in" && || ()`) against the live
  form values and toggles the field container (`[data-form-element]`), `disabled` (so
  hidden fields are not submitted) and `required`. Unparseable conditions are skipped.
- `RenderableVariant::getCondition()` / `getOptions()` expose the raw condition + override
  options for this.

### In-editor localization (per-site-language translations)

Each element has a **“Translate…”** button opening a modal with one section per
non-default site language and inputs for the element's **label, placeholder, options** and
any **custom validation messages**. The form (root) element additionally offers a
**“Translate whole form…”** matrix covering every element and every finisher's text options
(`subject`, `message`, `plainMessage`).

Translations are stored in the form definition under
`renderingOptions.translation.overrides.<languageCode>` for elements and
`options.translation.overrides.<languageCode>` for finishers (round-trip via the
`MultiValuePropertiesExtractor`s, so no XLF files are required — works for DB-stored forms
too). They are applied at render time before the XLF chain:
`TranslationService::translateFormElementValue()` (label / placeholder / options),
`translateFormElementError()` (validation messages, keyed `c<code>` to keep the path
segment non-numeric) and `translateFinisherOption()` (finisher options).
`InjectTranslationEditorIntoFormElements` injects the editor(s) + the available site
languages (`SiteFinder`, `languageId !== 0`); the inspector shows per-language completeness
badges.

### Visual condition builder (form editor)

The variants editor's condition field has a **“Build…”** button
(`Build/Sources/TypeScript/form/backend/form-editor/condition-builder.ts`) opening a modal
to click together rules (field / operator / value) with AND/OR groups and nesting. It
serializes the rule tree to an ExpressionLanguage condition and parses existing ones back
(raw-textarea fallback when unparseable). Pure editor JS.

### Form elements added on top of upstream

| Element | Class | Notes |
| --- | --- | --- |
| `Time` | `TYPO3\CMS\Form\Domain\Model\FormElements\Time` | HTML5 `<input type="time">`. Backed by `\DateTimeImmutable` parsed with format `H:i`; the date portion is "today" — only the time portion is meaningful. Fills a real gap (core ships `Date` but no `Time`). |

### Finishers added on top of upstream

| Identifier | Class | Purpose |
| --- | --- | --- |
| `RedirectToUri` | `TYPO3\CMS\Form\Domain\Finishers\RedirectToUriFinisher` | Redirect to **any** URI (external too). Core's `Redirect` only handles TYPO3 pages via t3-page IDs. Options: `uri`, `statusCode` (default 303). |
| `FeUser` | `TYPO3\CMS\Form\Domain\Finishers\FeUserFinisher` | Insert/update `fe_users` rows from form values. Built on core's `SaveToDatabase`. Per-element `hashPassword: true` runs the value through `PasswordHashFactory::getDefaultHashInstance('FE')`. Requires `pid` option for the storage page. |
| `AttachUploadsToObject` | `TYPO3\CMS\Form\Domain\Finishers\AttachUploadsToObjectFinisher` | Attaches uploaded files to an arbitrary DB record via new `sys_file_reference` rows. Pair with `SaveToDatabase` and reference the inserted UID via `{SaveToDatabase.insertedUids.<index>}`. Rebuild of the legacy form_extended finisher: direct ConnectionPool inserts, no fake backend user, no `bypassAccessCheck` hack, supports multiple files per element. |

> **Conditional finishers via variants** (replaces the removed `CopyToSenderEmail`): any
> finisher can carry a `variants` list inside its `options`, each entry being
> `{ condition: <ExpressionLanguage>, ...overrides }`. Before a finisher runs,
> `FormRuntime::processFinisherVariants()` merges every matching variant into the finisher
> options (formValues / stepType / finisherIdentifier are in scope). A "send me a copy"
> email is just a second `EmailToSender` with `renderingOptions.enabled: false` and a
> variant `{ condition: 'traverse(formValues, "sendCopy") == 1', renderingOptions: { enabled: true } }`.

### View helpers added on top of upstream

| Helper | Class | Use case |
| --- | --- | --- |
| `<formvh:remoteAddress />` | `TYPO3\CMS\Form\ViewHelpers\RemoteAddressViewHelper` | Renders client IP via `GeneralUtility::getIndpEnv('REMOTE_ADDR')` (respects trusted-proxy config). Useful for audit-trailing email finishers / confirmation pages. |
| `<formvh:translate />` | `TYPO3\CMS\Form\ViewHelpers\TranslateViewHelper` | Form-aware translation wrapper that hits `TYPO3\CMS\Form\Service\TranslationService` (with its form-element overlay logic) instead of `LocalizationUtility`. Use inside form-rendering templates; outside use Fluid's `f:translate`. |

### Cross-field (form-level) validators

Validators that need access to more than a single field's value implement
`TYPO3\CMS\Form\Validation\FormAwareValidatorInterface` (or extend
`AbstractFormAwareValidator`). They are declared on the form root, not on an individual
element:

```yaml
type: Form
identifier: contact
renderingOptions:
  formLevelValidators:
    -
      identifier: EntropySpam
      options:
        minimumEntropy: 2.0
        maximumEntropy: 5.5
        textFieldIdentifiers: ['message', 'subject']
        minimumLength: 30
```

The validator identifier must be registered in the prototype's `validatorsDefinition` (the
standard prototype already registers `EntropySpam`). The internal listener
`RunFormLevelValidators` consumes `AfterFormIsValidatedEvent` and invokes each declared
validator after per-element validation has finished; errors merge into the form's aggregate
`Result`.

`EntropySpamValidator` ships as the first concrete cross-field validator and uses a
Shannon-entropy band to reject submissions that look either repetitive (`aaaaaaa`,
`hahaha`) or uniform-random (bot brute-force). Human-written text in most languages falls
between roughly 3.5 and 5.0 bits/character; the default band 1.8-5.8 is intentionally wide
to avoid false positives.

### Password policy JSON endpoint

A frontend middleware at `/_form/password-policy/` exposes TYPO3's configured FE password
policy (`$GLOBALS['TYPO3_CONF_VARS']['FE']['passwordPolicy']`) as a structured JSON
document. Client-side JavaScript can fetch it once and render a live "is your password
strong enough yet?" indicator next to a form's password field, in lockstep with the same
`CorePasswordValidator` that will validate the submission server-side.

Response shape:

```json
{
  "policy": "default",
  "rules": [
    {"id": "minimumLength",            "label": "…", "value": 8},
    {"id": "upperCaseCharacterRequired","label": "…"},
    {"id": "lowerCaseCharacterRequired","label": "…"},
    {"id": "digitCharacterRequired",    "label": "…"},
    {"id": "specialCharacterRequired",  "label": "…"}
  ]
}
```

Only rules the configured `CorePasswordValidator` actually enforces are emitted; a policy
that disables `specialCharacterRequired` simply won't return that rule, so the UI stays
consistent with the validator. Labels are translated against the active site default
language. The middleware is registered in `Configuration/RequestMiddlewares.php` after
`cms-frontend/site` (so the site context is available) and before
`cms-frontend/page-resolver` (so the JSON URL never enters page-not-found lookup).

### Site-sender feature (opt-in via extension flag)

Lets site administrators maintain a list of email sender addresses in the BE Site
Configuration module; the form plugin's FlexForm then offers a dropdown to pick one per
content element. The actual sender on outgoing emails is resolved at runtime from the
selection.

Enable in `Admin Tools → Settings → Extension Configuration → form`:

```
form.featureSiteEmail = 1
```

After flushing caches and updating the schema, a new "Form senders" group appears in each
site's BE configuration with `email` and `name` fields per entry.

Architecture (classes follow the standard `TYPO3\CMS\Form\…` layout):

- `Configuration/SiteConfiguration/site_sender.php` — TCA for the sub-site-entity with
  `email` and `name` columns.
- `Configuration/SiteConfiguration/Overrides/site.php` — adds an inline `senders`
  collection to the `site` configuration. Only active when `featureSiteEmail` is on.
- `Form/FormDataProvider/SiteTcaInline` + `SiteDatabaseEditRow` — Symfony-DI decorators of
  the core providers; they add `site_sender` to the allowed inline tables. Registered in
  `Configuration/Services.yaml`.
- `EventListener/InjectSenderDropdownIntoFormPluginFlexForm` — inserts a `settings.sender`
  Select into the form-plugin FlexForm whose items are populated by
  `Hooks/SiteSenderItemsProcFunc`.
- `EventListener/HideStaticSenderFieldsInFormPluginFlexForm` — hides the EmailToReceiver
  finisher's `senderAddress` / `senderName` fields in the form-plugin FlexForm so editors
  aren't asked to fill in values that the site-sender will override anyway.
- `EventListener/ApplySiteSenderToEmailFinisher` — consumes the upstream
  `BeforeEmailFinisherInitializedEvent`, reads the selected sender from the plugin's
  FlexForm, and rewrites the finisher's `senderAddress` / `senderName` options.

When the feature flag is OFF, all listeners early-return and the decorators behave like the
plain core data providers — zero runtime cost.

### Validation-failure logging (opt-in per form)

Enable per form to track which fields fail validation most often — useful for drop-off
analysis without storing any user-submitted values:

```yaml
type: Form
identifier: contact
renderingOptions:
  recordValidationFailures: true
```

The `RecordValidationFailures` listener (consumes `AfterFormIsValidatedEvent`) writes one
row to `tx_form_validation_log` per validation error with:

- `form_identifier`, `element_identifier`, `property_path`
- `error_code`, `error_message` (already translated, safe to store)
- `page_uid`, `language_uid`, `page_index`
- `session_hash` — SHA-256 of the `FormSession` identifier so multi-attempt patterns from
  one visitor can be aggregated without identifying them
- `crdate`

What is **NOT** stored: submitted field values, raw inputs, IPs, user agents. The table is
engineered to be GDPR-defensible by default.

Sample analytics query:

```sql
SELECT element_identifier, error_code, COUNT(*) AS hits
FROM tx_form_validation_log
WHERE form_identifier = 'contact' AND crdate > UNIX_TIMESTAMP() - 86400*30
GROUP BY element_identifier, error_code
ORDER BY hits DESC;
```

**Periodic cleanup.** A native TYPO3 v14 scheduler task ships with the fork:
`TYPO3\CMS\Form\Task\CleanupValidationLogTask`. Configure it in
`Administration → Scheduler → Create task` and select **Form: clean up validation log**.
The `tx_form_retention_days` TCA field controls how old rows must be before deletion
(default 90 days, range 1–3650). Schedule it daily for production sites with active
validation logging — without it the table grows indefinitely. Manual run from CLI:
`ddev typo3 scheduler:execute --task=<uid>` after the task instance is created.

---

## Fork maintenance

### Branch layout

| Branch        | Purpose                                                     |
| ------------- | ----------------------------------------------------------- |
| `release/v14` | Active dev branch tracking TYPO3 14.x. **Default branch.**  |
| `release/v15` | Will be created when TYPO3 v15 ships.                       |
| `main`        | Mirror of upstream `main` — never patched, sync-only.       |
| `14.3`, …     | Mirrors of upstream major branches — sync-only.             |

### Upstream sync workflow

Upstream is registered as the `upstream` remote
(`https://github.com/TYPO3-CMS/form.git`). The flow for picking up new upstream work is:

```bash
# Update upstream branches
git fetch upstream --prune --tags

# Mirror upstream/14.3 onto our read-only mirror branch
git push origin "refs/remotes/upstream/14.3:refs/heads/14.3" --force-with-lease
git push origin "refs/remotes/upstream/main:refs/heads/main"   --force-with-lease
git push origin --tags

# Cherry-pick relevant commits onto release/v14
git checkout release/v14
git log --oneline release/v14..upstream/14.3      # what's new upstream
git cherry-pick <sha>                              # pick what we want
```

**Never merge `upstream/14.3` directly into `release/v14`.** Use cherry-pick so the fork
history stays linear and grep-able; we want to see *our* changes without upstream noise
mixed in. When a new TYPO3 minor (e.g. 14.4) lands upstream, cherry-pick the relevant
commits up to that tag and adjust the `branch-alias` in `composer.json`.

### Conventions for additions

- Code added on top of upstream lives in the **standard `\TYPO3\CMS\Form\…` layout**,
  mirroring upstream's own directory structure (`Event/`, `Validation/`, `Domain/Finishers/`, …).
  Fork-added classes use **distinct class names** so they never collide with upstream files,
  and an eventual switch to an official package stays painless. The trade-off vs. a separate
  subnamespace: upstream cherry-picks can land in the same directories, so watch for conflicts
  when syncing.
- Every public API surface we add gets a PSR-14 event so downstream extensions (including
  `wapplersystems/form_extended` during the migration period) can consume it.
- New editor UI is implemented through the form editor's existing extension points
  (TypeScript under `Build/Sources/TypeScript/form/`, partials registered via
  `formEditorPartials`) rather than by patching upstream templates.
- Commits that touch upstream files must explain *why* the upstream file needed to change —
  almost always preferable to add a hook/event upstream separately and contribute it back
  instead.
