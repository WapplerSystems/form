/*
 * release/v13 only: resolves `~labels/backend.wizards.general` on TYPO3 13.4.
 * See ../label-provider.js.
 */
import { loadLabels } from '@typo3/form/compat/label-provider.js';

export default await loadLabels('backend.wizards.general');
