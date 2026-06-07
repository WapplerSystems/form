# WapplerSystems Fork of typo3/cms-form

This repository is a hard fork of the TYPO3 system extension `form` — the read-only
subtree split at <https://github.com/TYPO3-CMS/form>, which is itself derived from
`typo3/sysext/form/` in <https://github.com/TYPO3/typo3>.

It is installed via Composer as `wapplersystems/form` and **transparently replaces
`typo3/cms-form`** through Composer's `replace` mechanism. The Composer package
name and the TYPO3 extension key on disk are deliberately different (`wapplersystems/form`
vs. `form`) so that the extension remains a drop-in for `EXT:form` — everything that
references `\TYPO3\CMS\Form\…`, the YAML mixins, the form editor JavaScript, the
Fluid template paths and the FAL config continues to work without changes.

## Why fork?

The TYPO3 core `form` sysext is intentionally minimal in places where editor
workflows benefit from more depth:

1. **More events** — make finisher pipelines, variant evaluation and form rendering
   pluggable from outside via PSR-14 events instead of class extension.
2. **Backend editor parity** — features that exist today only via YAML hand-editing
   (variants/conditions, complex validators, finisher options) become editable in
   the form editor.
3. **Cross-field validators** — validators that need access to more than a single
   field's value (entropy/spam filtering across all submitted text, conditional
   `required`, sums of numeric fields, etc.).
4. **Variants/Conditions editor** — visual editor for the existing `variants` mechanism
   so integrators can express conditional behavior without writing YAML.
5. **Consolidate `wapplersystems/form_extended`** — the patches and additions that
   currently live in `form_extended` (multi-upload, sender-address config in site
   settings, country/date/time fields, custom finishers) migrate into this fork
   step by step; `form_extended` is then deprecated.

## Branch layout

| Branch        | Purpose                                                     |
| ------------- | ----------------------------------------------------------- |
| `release/v14` | Active dev branch tracking TYPO3 14.x. **Default branch.**  |
| `release/v15` | Will be created when TYPO3 v15 ships.                       |
| `main`        | Mirror of upstream `main` — never patched, sync-only.       |
| `14.3`, …     | Mirrors of upstream major branches — sync-only.             |

## Upstream sync workflow

Upstream is registered as the `upstream` remote
(`https://github.com/TYPO3-CMS/form.git`). The flow for picking up new upstream
work is:

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

**Never merge `upstream/14.3` directly into `release/v14`.** Use cherry-pick so
the fork history stays linear and grep-able; we want to see *our* changes
without upstream noise mixed in.

When a new TYPO3 minor (e.g. 14.4) lands upstream, we cherry-pick the relevant
commits up to that tag and adjust the `branch-alias` in `composer.json`.

## Local development inside the dev14 monorepo

The `dev14` Composer project loads this directory via the `packages/*/*` path
repository. To require it, replace the line

```json
"typo3/cms-form": "14.3.*@dev",
```

in the project's `composer.json` with

```json
"wapplersystems/form": "dev-release/v14 as 14.3",
```

The path repo provides `wapplersystems/form`; the `replace` clause in our
`composer.json` makes Composer treat `typo3/cms-form` as already satisfied so
no second copy is downloaded.

## Fork-added PSR-14 events

All fork-added events live in `TYPO3\CMS\Form\WapplerSystems\Event\` and are dispatched from patched upstream call-sites. They do not overlap with the events shipped by upstream EXT:form under `TYPO3\CMS\Form\Event\`.

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

The Before/After finisher events fire generically for every finisher inheriting `AbstractFinisher` — Email, Redirect, Confirmation, SaveToDatabase, FlashMessage, DeleteUploads, Closure and any custom finishers. No need to subclass per finisher type.

## Form elements added on top of upstream

| Element | Class | Notes |
| --- | --- | --- |
| `Time` | `TYPO3\CMS\Form\WapplerSystems\Domain\Model\FormElements\Time` | HTML5 `<input type="time">`. Backed by `\DateTimeImmutable` parsed with format `H:i`; the date portion is "today" — only the time portion is meaningful. Fills a real gap (core ships `Date` but no `Time`). |

## Finishers added on top of upstream

| Identifier | Class | Purpose |
| --- | --- | --- |
| `RedirectToUri` | `TYPO3\CMS\Form\WapplerSystems\Domain\Finishers\RedirectToUriFinisher` | Redirect to **any** URI (external too). Core's `Redirect` only handles TYPO3 pages via t3-page IDs. Options: `uri`, `statusCode` (default 303). |
| `FeUser` | `TYPO3\CMS\Form\WapplerSystems\Domain\Finishers\FeUserFinisher` | Insert/update `fe_users` rows from form values. Built on core's `SaveToDatabase`. Per-element `hashPassword: true` runs the value through `PasswordHashFactory::getDefaultHashInstance('FE')`. Requires `pid` option for the storage page. |
| `CopyToSenderEmail` | `TYPO3\CMS\Form\WapplerSystems\Domain\Finishers\CopyToSenderEmailFinisher` | Conditional `EmailFinisher` — only fires when the form field named in `conditionFieldName` is truthy. Use case: "send me a copy" checkbox. |
| `AttachUploadsToObject` | `TYPO3\CMS\Form\WapplerSystems\Domain\Finishers\AttachUploadsToObjectFinisher` | Attaches uploaded files to an arbitrary DB record via new `sys_file_reference` rows. Pair with `SaveToDatabase` and reference the inserted UID via `{SaveToDatabase.insertedUids.<index>}`. Rebuild of the legacy form_extended finisher: direct ConnectionPool inserts, no fake backend user, no `bypassAccessCheck` hack, supports multiple files per element. |

## View helpers added on top of upstream

| Helper | Class | Use case |
| --- | --- | --- |
| `<formvh:remoteAddress />` | `TYPO3\CMS\Form\WapplerSystems\ViewHelpers\RemoteAddressViewHelper` | Renders client IP via `GeneralUtility::getIndpEnv('REMOTE_ADDR')` (respects trusted-proxy config). Useful for audit-trailing email finishers / confirmation pages. |
| `<formvh:translate />` | `TYPO3\CMS\Form\WapplerSystems\ViewHelpers\TranslateViewHelper` | Form-aware translation wrapper that hits `TYPO3\CMS\Form\Service\TranslationService` (with its form-element overlay logic) instead of `LocalizationUtility`. Use inside form-rendering templates; outside use Fluid's `f:translate`. |

## Cross-field (form-level) validators

Validators that need access to more than a single field's value implement
`TYPO3\CMS\Form\WapplerSystems\Validation\FormAwareValidatorInterface` (or extend
`AbstractFormAwareValidator`). They are declared on the form root, not on an
individual element:

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

The validator identifier must be registered in the prototype's
`validatorsDefinition` (the standard prototype already registers `EntropySpam`).
The internal listener `RunFormLevelValidators` consumes `AfterFormIsValidatedEvent`
and invokes each declared validator after per-element validation has finished;
errors merge into the form's aggregate `Result`.

`EntropySpamValidator` ships as the first concrete cross-field validator and
uses a Shannon-entropy band to reject submissions that look either repetitive
(`aaaaaaa`, `hahaha`) or uniform-random (bot brute-force). Human-written text
in most languages falls between roughly 3.5 and 5.0 bits/character; the default
band 1.8-5.8 is intentionally wide to avoid false positives.

## Site-sender feature (opt-in via extension flag)

Lets site administrators maintain a list of email sender addresses
in the BE Site Configuration module; the form plugin's FlexForm then
offers a dropdown to pick one per content element. The actual
sender on outgoing emails is resolved at runtime from the selection.

Enable in `Admin Tools → Settings → Extension Configuration → form`:

```
form.featureSiteEmail = 1
```

After flushing caches and updating the schema, a new "Form senders"
group appears in each site's BE configuration with `email` and
`name` fields per entry.

Architecture (all classes under `TYPO3\CMS\Form\WapplerSystems\…`):

- `Configuration/SiteConfiguration/site_sender.php` — TCA for the
  sub-site-entity with `email` and `name` columns.
- `Configuration/SiteConfiguration/Overrides/site.php` — adds an
  inline `senders` collection to the `site` configuration. Only
  active when `featureSiteEmail` is on.
- `Form/FormDataProvider/SiteTcaInline` + `SiteDatabaseEditRow` —
  Symfony-DI decorators of the core providers; they add `site_sender`
  to the allowed inline tables. Registered in `Configuration/Services.yaml`.
- `EventListener/InjectSenderDropdownIntoFormPluginFlexForm` —
  inserts a `settings.sender` Select into the form-plugin FlexForm
  whose items are populated by `Hooks/SiteSenderItemsProcFunc`.
- `EventListener/HideStaticSenderFieldsInFormPluginFlexForm` —
  hides the EmailToReceiver finisher's `senderAddress` / `senderName`
  fields in the form-plugin FlexForm so editors aren't asked to fill
  in values that the site-sender will override anyway.
- `EventListener/ApplySiteSenderToEmailFinisher` — consumes the
  upstream `BeforeEmailFinisherInitializedEvent`, reads the selected
  sender from the plugin's FlexForm, and rewrites the finisher's
  `senderAddress` / `senderName` options. Cleaner than form_extended's
  EmailFinisher subclass-override.

When the feature flag is OFF, all listeners early-return and the
decorators behave like the plain core data providers — zero runtime
cost.

## Validation-failure logging (opt-in per form)

Enable per form to track which fields fail validation most often — useful
for drop-off analysis without storing any user-submitted values:

```yaml
type: Form
identifier: contact
renderingOptions:
  recordValidationFailures: true
```

The `RecordValidationFailures` listener (consumes `AfterFormIsValidatedEvent`)
writes one row to `tx_form_validation_log` per validation error with:

- `form_identifier`, `element_identifier`, `property_path`
- `error_code`, `error_message` (already translated, safe to store)
- `page_uid`, `language_uid`, `page_index`
- `session_hash` — SHA-256 of the `FormSession` identifier so multi-attempt
  patterns from one visitor can be aggregated without identifying them
- `crdate`

What is **NOT** stored: submitted field values, raw inputs, IPs, user
agents. The table is engineered to be GDPR-defensible by default. Operators
should set up a Scheduler task that prunes rows older than the local
retention window; a built-in cleanup command may ship in a later phase.

Sample analytics query:

```sql
SELECT element_identifier, error_code, COUNT(*) AS hits
FROM tx_form_validation_log
WHERE form_identifier = 'contact' AND crdate > UNIX_TIMESTAMP() - 86400*30
GROUP BY element_identifier, error_code
ORDER BY hits DESC;
```

### Periodic cleanup

A native TYPO3 v14 scheduler task ships with the fork:
`TYPO3\CMS\Form\WapplerSystems\Task\CleanupValidationLogTask`. Configure
it in `Administration → Scheduler → Create task` and select
**Form: clean up validation log**. The `tx_form_retention_days` TCA field
controls how old rows must be before they are deleted (default 90 days,
range 1–3650). Schedule it daily for production sites with active
validation logging — without it the table grows indefinitely.

Manual run from CLI: `ddev typo3 scheduler:execute --task=<uid>` after
the task instance is created.

## Conventions for additions

* Code added on top of upstream **must live in a separate subnamespace** —
  `\TYPO3\CMS\Form\WapplerSystems\…` — so that future upstream cherry-picks
  never touch our files. The PSR-4 prefix stays `\TYPO3\CMS\Form\` because we
  *are* the `form` extension; the WapplerSystems-only subdir is just an
  organizational convention.
* Every public API surface we add gets a PSR-14 event so downstream extensions
  (including `wapplersystems/form_extended` during the migration period) can
  consume it.
* New editor UI is implemented as TYPO3 Lit web components under
  `Resources/Public/JavaScript/Backend/FormEditor/WapplerSystems/`, registered
  through the editor's existing extension points rather than by patching
  upstream templates.
* Commits that touch upstream files must explain *why* the upstream file
  needed to change — almost always preferable to add a hook/event upstream
  separately and contribute it back instead.