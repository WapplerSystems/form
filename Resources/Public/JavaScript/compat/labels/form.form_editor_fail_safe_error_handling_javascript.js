/*
 * release/v13 only: resolves `~labels/form.form_editor_fail_safe_error_handling_javascript` on TYPO3 13.4.
 * See ../label-provider.js.
 */
import { loadLabels } from '@typo3/form/compat/label-provider.js';

export default await loadLabels('form.form_editor_fail_safe_error_handling_javascript');
