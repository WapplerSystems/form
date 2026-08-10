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
export class StorageStep {
    constructor(context) {
        this.context = context;
        this.key = 'storage';
        this.title = formManagerLabels.get('formManager.newFormWizard.step.storages.progressLabel');
        this.autoAdvance = true;
        this.hasDispatchedAutoAdvance = false;
        this.selectedStorage = null;
    }
    isComplete() {
        return this.getValue() !== null;
    }
    render() {
        const storageAdapters = this.context.formManager.getAccessibleStorageAdapters();
        let shouldAutoAdvance = false;
        // Auto-select first storage adapter if none selected
        if (this.getValue() == null && storageAdapters.length > 0) {
            this.setValue(storageAdapters[0]);
            // Only auto-advance if there's only one option
            if (storageAdapters.length === 1) {
                shouldAutoAdvance = true;
            }
        }
        // Dispatch auto-advance if needed (only once)
        if (shouldAutoAdvance && !this.hasDispatchedAutoAdvance) {
            this.hasDispatchedAutoAdvance = true;
            this.context.dispatchAutoAdvance();
            return this.context.wizard.renderLoader();
        }
        return html `
      <h2 class="h4">${formManagerLabels.get('formManager.newFormWizard.step.storages.title')}</h2>
      <p>${formManagerLabels.get('formManager.newFormWizard.step.storages.description')}</p>
      <div class="form-storage-selection">
        <div class="form-check-card-container">
          ${storageAdapters.map((storage) => html `
            <div class="form-check form-check-type-card">
              <input
                class="form-check-input"
                type="radio"
                name="${this.key}"
                id="mode-${storage.typeIdentifier}"
                value=${storage.typeIdentifier}
                .checked=${live(this.getValue()?.typeIdentifier === storage.typeIdentifier)}
                @change=${() => this.setValue(storage)}
              >
              <label class="form-check-label" for="mode-${storage.typeIdentifier}">
                <span class="form-check-label-header">
                  <typo3-backend-icon identifier="${storage.iconIdentifier}" size="medium"></typo3-backend-icon>
                  ${storage.label}
                </span>
                <span class="form-check-label-body">
                  ${unsafeHTML(storage.description)}
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
        return this.selectedStorage;
    }
    setValue(value) {
        this.selectedStorage = value;
        this.context.wizard.requestUpdate();
    }
    beforeAdvance() {
        this.context.setStoreData(this.key, this.getValue());
    }
    getSummaryData() {
        const selectedStorage = this.context.getStoreData(this.key);
        if (!selectedStorage) {
            return [];
        }
        return [{
                label: formManagerLabels.get('formManager.newFormWizard.step.storages.summary.title'),
                value: html `
        <typo3-backend-icon identifier="${selectedStorage.iconIdentifier}" size="small" class="me-1"></typo3-backend-icon>
        ${selectedStorage.label}
      `
            }];
    }
}
export default StorageStep;
