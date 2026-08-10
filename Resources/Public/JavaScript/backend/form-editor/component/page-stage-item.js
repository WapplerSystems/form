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
import { html, LitElement } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import labels from '~labels/form.form_editor_javascript';
/**
 * Module: @typo3/form/backend/form-editor/component/page-stage-item
 *
 * Functionality for the page stage item element (top-level form elements)
 *
 * @example
 * <typo3-form-page-stage-item
 *   page-title="Step 1">
 * </typo3-form-page-stage-item>
 */
let PageStageItem = class PageStageItem extends LitElement {
    constructor() {
        super(...arguments);
        this.pageTitle = '';
    }
    createRenderRoot() {
        // Avoid Shadow DOM so global styles apply to the element contents
        return this;
    }
    render() {
        const displayTitle = this.pageTitle || labels.get('formEditor.step.name.empty');
        return html `
      <h2 class="formeditor-page-title">
        ${displayTitle}
      </h2>
    `;
    }
};
__decorate([
    property({ type: String, attribute: 'page-title' })
], PageStageItem.prototype, "pageTitle", void 0);
PageStageItem = __decorate([
    customElement('typo3-form-page-stage-item')
], PageStageItem);
export { PageStageItem };
