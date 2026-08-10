/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import { html } from 'lit';
import { live } from 'lit/directives/live.js';
import { unsafeHTML } from 'lit/directives/unsafe-html.js';
import formManagerLabels from '~labels/form.form_manager_javascript';
export var MODE;
(function (MODE) {
    MODE["Blank"] = "blank";
    MODE["Predefined"] = "predefined";
})(MODE || (MODE = {}));
export class ModeStep {
    constructor(context) {
        this.context = context;
        this.key = 'mode';
        this.title = formManagerLabels.get('formManager.newFormWizard.step1.progressLabel');
        this.autoAdvance = true;
        this.selectedMode = null;
        this.modes = [
            {
                key: MODE.Blank,
                label: formManagerLabels.get('formManager.blankForm.label'),
                description: formManagerLabels.get('formManager.blankForm.description'),
                iconIdentifier: 'apps-pagetree-page-default',
            },
            {
                key: MODE.Predefined,
                label: formManagerLabels.get('formManager.predefinedForm.label'),
                description: formManagerLabels.get('formManager.predefinedForm.description'),
                iconIdentifier: 'form-page',
            }
        ];
    }
    isComplete() {
        return this.getValue() !== null;
    }
    render() {
        // Auto-select first mode if none selected
        if (this.getValue() == null && this.modes.length > 0) {
            this.setValue(this.modes[0].key);
        }
        return html `
      <div class="form-mode-selection">
        <div class="form-check-card-container">
          ${this.modes.map((mode) => html `
            <div class="form-check form-check-type-card">
              <input
                class="form-check-input"
                type="radio"
                name="${this.key}"
                id="mode-${mode.key}"
                value=${mode.key}
                .checked=${live(this.getValue() === mode.key)}
                @change=${() => this.setValue(mode.key)}
              >
              <label class="form-check-label" for="mode-${mode.key}">
                <span class="form-check-label-header">
                  <typo3-backend-icon identifier="${mode.iconIdentifier}" size="medium"></typo3-backend-icon>
                  ${mode.label}
                </span>
                <span class="form-check-label-body">
                  ${unsafeHTML(mode.description)}
                </span>
              </label>
            </div>
          `)}
        </div>
      </div>
    `;
    }
    reset() {
        this.setValue(null);
        this.context.clearStoreData(this.key);
    }
    getValue() {
        return this.selectedMode;
    }
    setValue(value) {
        this.selectedMode = value;
        this.context.wizard.requestUpdate();
    }
    beforeAdvance() {
        this.context.setStoreData(this.key, this.getValue());
    }
    getSummaryData() {
        const selectedMode = this.context.getStoreData(this.key);
        if (!selectedMode) {
            return [];
        }
        const mode = this.modes.find((m) => m.key === selectedMode);
        if (!mode) {
            return [];
        }
        return [{
                label: formManagerLabels.get('formManager.newFormWizard.step.modes.summary.title'),
                value: html `
        <typo3-backend-icon identifier="${mode.iconIdentifier}" size="small" class="me-1"></typo3-backend-icon>
        ${mode.label}
      `
            }];
    }
}
export default ModeStep;
