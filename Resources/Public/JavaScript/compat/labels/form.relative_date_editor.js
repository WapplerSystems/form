/*
 * release/v13 only: resolves `~labels/form.relative_date_editor` on TYPO3 13.4.
 * See ../label-provider.js.
 */
import { loadLabels } from '@typo3/form/compat/label-provider.js';

export default await loadLabels('form.relative_date_editor');
