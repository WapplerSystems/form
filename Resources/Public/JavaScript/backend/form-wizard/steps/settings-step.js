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
import { html, nothing } from 'lit';
import { MODE } from '@typo3/form/backend/form-wizard/steps/mode-step.js';
import formManagerLabels from '~labels/form.form_manager_javascript';
export class SettingsStep {
    constructor(context) {
        this.context = context;
        this.key = 'settings';
        this.title = formManagerLabels.get('formManager.newFormWizard.step2.title');
        this.autoAdvance = true;
        this.data = {
            formName: '',
            storageLocation: '',
            prototype: '',
            template: '',
        };
        this.reset();
    }
    isComplete() {
        return this.getValue()?.formName !== '';
    }
    render() {
        return html `
      ${this.renderPredefinedFormFields()}
      ${this.renderSavePath()}
      ${this.renderFormNameInput()}
    `;
    }
    reset() {
        this.setValue({
            formName: '',
            storageLocation: '',
        });
        this.setPrototype(this.context.formManager.getPrototypes()[0]?.value ?? '');
        this.context.clearStoreData(this.key);
    }
    getValue() {
        return this.data;
    }
    setValue(value) {
        this.data = {
            ...this.data,
            ...value
        };
        this.context.wizard.requestUpdate();
    }
    beforeAdvance() {
        this.context.setStoreData(this.key, this.getValue());
    }
    getSummaryData() {
        const config = this.context.getStoreData(this.key);
        const prototypeLabel = this.context.formManager.getPrototypes()
            .find(p => p.value === config.prototype)?.label;
        const templateLabel = this.context.formManager.getTemplatesForPrototype(config.prototype)
            .find(t => t.value === config.template)
            ?.label;
        const isPredefined = this.context.getStoreData('mode') === MODE.Predefined;
        // Resolve storage location value to its human-readable label
        const storageAdapter = this.context.getStoreData('storage');
        const storageLocations = storageAdapter
            ? this.context.formManager.getAccessibleStorageLocationsForAdapter(storageAdapter)
            : [];
        const storageLocationLabel = storageLocations.find(l => l.value === config.storageLocation)?.label
            ?? config.storageLocation;
        return [
            ...(isPredefined
                ? [
                    {
                        value: prototypeLabel,
                        label: formManagerLabels.get('formManager.form_prototype')
                    },
                    {
                        value: templateLabel,
                        label: formManagerLabels.get('formManager.form_template')
                    }
                ]
                : []),
            {
                value: config.formName,
                label: formManagerLabels.get('formManager.form_name')
            },
            {
                value: storageLocationLabel,
                label: formManagerLabels.get('formManager.form_storageLocation')
            }
        ];
    }
    renderSavePath() {
        const storageLocations = this.context.formManager.getAccessibleStorageLocationsForAdapter(this.context.getStoreData('storage')) ?? [];
        if (storageLocations.length <= 1) {
            this.setValue({ storageLocation: storageLocations[0]?.value ?? '' });
            return nothing;
        }
        // Auto-select first location if none selected yet
        if (!this.data.storageLocation && storageLocations.length > 0) {
            this.setValue({ storageLocation: storageLocations[0].value });
        }
        return html `
      <div class="form-group">
        <label class="form-label" for="new-form-save-path">${formManagerLabels.get('formManager.form_storageLocation')}</label>
        <div class="form-description">${formManagerLabels.get('formManager.form_storageLocation_description')}</div>
        <select class="new-form-save-path form-select" id="new-form-save-path" data-identifier="newFormSavePath"
          @change=${(e) => this.setValue({ storageLocation: e.target.value })}
        >
          ${storageLocations.map(option => html `
            <option
              value=${option.value}
              ?selected=${option.value === this.data.storageLocation}
            >
              ${option.label}
            </option>
        `)}
        </select>
      </div>`;
    }
    renderFormNameInput() {
        this.focusInput('#new-form-name');
        return html `
      <div class="form-group">
        <label class="form-label" for="new-form-name">${formManagerLabels.get('formManager.form_name')}</label>
        <div class="form-description">${formManagerLabels.get('formManager.form_name_description')}</div>
        <input class="form-control ${!this.isComplete() ? 'has-error' : ''}"
               id="new-form-name"
               data-identifier="newFormName"
               name="newFormName"
               .value=${this.data.formName}
               @input=${(e) => this.setValue({ formName: e.target.value })}
        />
      </div>`;
    }
    renderPredefinedFormFields() {
        const prototypes = this.context.formManager.getPrototypes() ?? [];
        if (this.context.getStoreData('mode') !== MODE.Predefined || prototypes.length < 1) {
            return nothing;
        }
        const currentPrototype = this.data.prototype;
        const templates = this.context.formManager.getTemplatesForPrototype(currentPrototype);
        let templatesFormGroup = nothing;
        if (templates.length > 0) {
            templatesFormGroup = html `
        <div class="form-group">
          <label class="form-label" for="new-form-template">${formManagerLabels.get('formManager.form_template')}</label>
          <select class="new-form-template form-select"
                  id="new-form-template"
                  data-identifier="newFormTemplate"
                  @change=${(e) => this.setValue({ template: e.target.value })}
          >
            ${templates.map(option => html `
              <option
                ?selected=${option.value === this.data.template}
                value=${option.value}
              >
                ${option.label}
              </option>
            `)}
          </select>
        </div>`;
        }
        return html `
      <div class="form-group">
        <label class="form-label" for="new-form-prototype-name">${formManagerLabels.get('formManager.form_prototype')}</label>
        <select class="new-form-prototype-name form-select"
                id="new-form-prototype-name"
                data-identifier="newFormPrototype"
                @change=${(e) => this.setPrototype(e.currentTarget.value)}
        >
          ${prototypes.map(option => html `
            <option
              value=${option.value}
              ?selected=${option.value === this.data.prototype}
            >
              ${option.label}
            </option>
          `)}
        </select>
      </div>
      ${templatesFormGroup}`;
    }
    setPrototype(currentPrototype) {
        const currentTemplates = this.context.formManager.getTemplatesForPrototype(currentPrototype);
        this.setValue({
            prototype: currentPrototype,
            template: (currentTemplates[0]?.value ?? '')
        });
    }
    focusInput(selector) {
        this.context.wizard.updateComplete.then(() => {
            const input = this.context.wizard.renderRoot.querySelector(selector);
            if (input) {
                input.focus();
            }
        });
    }
}
export default SettingsStep;
