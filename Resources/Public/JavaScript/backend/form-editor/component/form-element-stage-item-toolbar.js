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
import labels from '~labels/form.form_editor_javascript';
import '@typo3/backend/element/icon-element.js';
/**
 * Module: @typo3/form/backend/form-editor/component/form-element-stage-item-toolbar
 *
 * Standalone toolbar web component form element stage templates.
 *
 * Dispatches (bubbling):
 *   - toolbar-new-element-before
 *   - toolbar-new-element-after
 *   - toolbar-remove-element
 */
let FormElementStageItemToolbar = class FormElementStageItemToolbar extends LitElement {
    constructor() {
        super(...arguments);
        this.active = false;
        this.iconIdentifier = '';
        this.elementType = '';
        this.elementIdentifier = '';
        this.isHidden = false;
        this.isInvalid = false;
    }
    createRenderRoot() {
        return this;
    }
    render() {
        if (!this.active) {
            return nothing;
        }
        return html `
      <div class="formeditor-element-toolbar">
        <div class="formeditor-element-toolbar-left">
          <typo3-backend-icon
            identifier="${this.iconIdentifier}"
            size="small"
            overlay="${this.isHidden ? 'overlay-hidden' : (this.isInvalid ? 'overlay-missing' : '')}">
          </typo3-backend-icon>
        </div>
        <div class="formeditor-element-toolbar-title">
          ${this.elementType}
        </div>
        <div class="formeditor-element-toolbar-right">
          <div class="btn-toolbar">
            <div class="btn-group btn-group-sm" role="group">
              <a
                  class="btn btn-default btn-borderless"
                  href="#"
                  title="${labels.get('formEditor.stage.toolbar.new_element.before')}"
                  @click="${this.handleNewElementBefore}">
                <typo3-backend-icon identifier="actions-form-insert-before" size="small"></typo3-backend-icon>
              </a>
              <a
                  class="btn btn-default btn-borderless"
                  href="#"
                  title="${labels.get('formEditor.stage.toolbar.new_element.after')}"
                  @click="${this.handleNewElementAfter}">
                <typo3-backend-icon identifier="actions-form-insert-after" size="small"></typo3-backend-icon>
              </a>
              <a
                class="btn btn-default btn-borderless"
                href="#"
                title="${labels.get('formEditor.stage.toolbar.remove')}"
                @click="${this.handleRemoveElement}">
                <typo3-backend-icon identifier="actions-edit-delete" size="small"></typo3-backend-icon>
              </a>
            </div>
          </div>
        </div>
      </div>
    `;
    }
    handleNewElementBefore(event) {
        event.preventDefault();
        this.dispatchEvent(new CustomEvent('toolbar-new-element-before', { bubbles: true, composed: true }));
    }
    handleNewElementAfter(event) {
        event.preventDefault();
        this.dispatchEvent(new CustomEvent('toolbar-new-element-after', { bubbles: true, composed: true }));
    }
    handleRemoveElement(event) {
        event.preventDefault();
        this.dispatchEvent(new CustomEvent('toolbar-remove-element', { bubbles: true, composed: true }));
    }
};
__decorate([
    property({ type: Boolean, reflect: true })
], FormElementStageItemToolbar.prototype, "active", void 0);
__decorate([
    property({ type: String, attribute: 'icon-identifier' })
], FormElementStageItemToolbar.prototype, "iconIdentifier", void 0);
__decorate([
    property({ type: String, attribute: 'element-type' })
], FormElementStageItemToolbar.prototype, "elementType", void 0);
__decorate([
    property({ type: String, attribute: 'element-identifier' })
], FormElementStageItemToolbar.prototype, "elementIdentifier", void 0);
__decorate([
    property({ type: Boolean, attribute: 'is-hidden' })
], FormElementStageItemToolbar.prototype, "isHidden", void 0);
__decorate([
    property({ type: Boolean, attribute: 'is-invalid' })
], FormElementStageItemToolbar.prototype, "isInvalid", void 0);
FormElementStageItemToolbar = __decorate([
    customElement('typo3-form-form-element-stage-item-toolbar')
], FormElementStageItemToolbar);
export { FormElementStageItemToolbar };
