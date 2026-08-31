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
var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
import { html, LitElement, nothing } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import '@typo3/backend/element/icon-element.js';
import '@typo3/form/backend/form-editor/component/form-element-stage-item-toolbar.js';
import { stripTags } from '../utility/string-utility.js';
/**
 * Module: @typo3/form/backend/form-editor/component/form-element-stage-item
 *
 * Functionality for the form element stage item element
 *
 * @example
 * <typo3-form-form-element-stage-item
 *   element-type="Text"
 *   element-identifier="element-1"
 *   element-label="My Label"
 *   element-icon="form-text"
 *   is-required="false">
 * </typo3-form-form-element-stage-item>
 */
let FormElementStageItem = class FormElementStageItem extends LitElement {
    constructor() {
        super(...arguments);
        this.elementType = '';
        this.elementIdentifier = '';
        this.elementLabel = '';
        this.elementIconIdentifier = '';
        this.isRequired = false;
        this.isHidden = false;
        this.invalid = false;
        this.validators = [];
        this.options = [];
        /**
         * WapplerSystems fork: forwarded to the toolbar, which then offers no
         * insert or remove. Set by renderFormElementStageItem().
         */
        this.readOnly = false;
    }
    createRenderRoot() {
        // Avoid Shadow DOM so global styles apply to the element contents
        return this;
    }
    render() {
        return html `
      <typo3-form-form-element-stage-item-toolbar
        active
        icon-identifier="${this.elementIconIdentifier}"
        element-type="${this.elementType}"
        element-identifier="${this.elementIdentifier}"
        ?is-hidden="${this.isHidden}"
        ?is-invalid="${this.invalid}"
        ?read-only="${this.readOnly}">
      </typo3-form-form-element-stage-item-toolbar>
      <div class="formeditor-element-body">
        <div class="formeditor-element-info">
          <div class="formeditor-element-info-label">
            <span>${stripTags(this.elementLabel)}</span>
            ${this.isRequired ? html `<span>*</span>` : nothing}
          </div>
          ${this.renderInfoContent()}
        </div>
        ${this.renderValidators()}
      </div>
    `;
    }
    /**
     * Renders the info content section if content items are present
     */
    renderInfoContent() {
        const contentItems = this.renderContentItems();
        if (!contentItems.length) {
            return nothing;
        }
        return html `
      <div class="formeditor-element-info-content">
        ${contentItems}
      </div>
    `;
    }
    /**
     * Collects all content items to be rendered in the info section
     */
    renderContentItems() {
        const items = [];
        // Render text (for elements with text property)
        if (this.content) {
            items.push(html `
        <div class="formeditor-element-info-text">
          ${stripTags(this.content)}
        </div>
      `);
        }
        // Render options (for select elements)
        if (this.options?.length) {
            const multivalueItems = this.options.map(option => ({
                label: option.label,
                className: option.selected ? 'selected' : undefined,
            }));
            items.push(this.renderMultivalue(multivalueItems));
        }
        // Render allowed mime types (for file upload elements)
        if (this.allowedMimeTypes?.length) {
            const multivalueItems = this.allowedMimeTypes.map(mimeType => ({
                label: mimeType,
            }));
            items.push(this.renderMultivalue(multivalueItems));
        }
        return items;
    }
    /**
     * Renders a multivalue list with items
     */
    renderMultivalue(items) {
        return html `
      <div class="formeditor-element-info-multivalue">
        ${items.map(item => html `
          <div class="formeditor-element-info-multivalue-item${item.className ? ` ${item.className}` : ''}">
            ${item.label}
          </div>
        `)}
      </div>
    `;
    }
    /**
     * Renders the validator section if validators are present
     */
    renderValidators() {
        if (!this.validators?.length) {
            return nothing;
        }
        return html `
      <div class="formeditor-element-validator">
        <div class="formeditor-element-validator-icon">
          <typo3-backend-icon identifier="form-validator" size="small"></typo3-backend-icon>
        </div>
        <div class="formeditor-element-validator-list">
          ${this.validators.map(validator => html `
            <div class="formeditor-element-validator-list-item">
              ${validator.label}
            </div>
          `)}
        </div>
      </div>
    `;
    }
};
__decorate([
    property({ type: String, attribute: 'element-type' })
], FormElementStageItem.prototype, "elementType", void 0);
__decorate([
    property({ type: String, attribute: 'element-identifier' })
], FormElementStageItem.prototype, "elementIdentifier", void 0);
__decorate([
    property({ type: String, attribute: 'element-label' })
], FormElementStageItem.prototype, "elementLabel", void 0);
__decorate([
    property({ type: String, attribute: 'element-icon-identifier' })
], FormElementStageItem.prototype, "elementIconIdentifier", void 0);
__decorate([
    property({ type: Boolean, attribute: 'is-required' })
], FormElementStageItem.prototype, "isRequired", void 0);
__decorate([
    property({ type: Boolean, attribute: 'is-hidden' })
], FormElementStageItem.prototype, "isHidden", void 0);
__decorate([
    property({ type: Boolean, reflect: true })
], FormElementStageItem.prototype, "invalid", void 0);
__decorate([
    property({ type: Array })
], FormElementStageItem.prototype, "validators", void 0);
__decorate([
    property({ type: Array })
], FormElementStageItem.prototype, "options", void 0);
__decorate([
    property({ type: Array })
], FormElementStageItem.prototype, "allowedMimeTypes", void 0);
__decorate([
    property({ type: String })
], FormElementStageItem.prototype, "content", void 0);
__decorate([
    property({ type: Boolean, attribute: 'read-only' })
], FormElementStageItem.prototype, "readOnly", void 0);
FormElementStageItem = __decorate([
    customElement('typo3-form-form-element-stage-item')
], FormElementStageItem);
export { FormElementStageItem };
