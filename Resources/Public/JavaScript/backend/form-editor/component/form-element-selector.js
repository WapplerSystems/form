var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
import { customElement, property } from 'lit/decorators.js';
import { html, LitElement, nothing } from 'lit';
export class FormElementSelectorSelectedEvent extends Event {
    static { this.eventName = 'typo3:backend:form-editor:component:form-element-selector:selected'; }
    constructor(value) {
        super(FormElementSelectorSelectedEvent.eventName);
        this.value = value;
    }
}
/**
 * Module: @typo3/form/backend/form-editor/component/form-element-selector
 */
let FormElementSelector = class FormElementSelector extends LitElement {
    constructor() {
        super(...arguments);
        this.elements = [];
        this.size = '';
    }
    createRenderRoot() {
        return this;
    }
    render() {
        if (!this.elements?.length) {
            return html `${nothing}`;
        }
        return html `
      <span class="input-group-btn" role="group" data-identifier="inspectorEditorFormElementSelectorControlsWrapper">
        <span class="btn-group" data-identifier="inspectorEditorFormElementSelectorSplitButtonContainer">
          <button type="button" class="btn btn-default dropdown-toggle${this.size === 'small' ? ' btn-sm' : ''}" data-bs-toggle="dropdown" aria-expanded="false" title="{f:translate(key: 'LLL:EXT:form/Resources/Private/Language/Database.xlf:formEditor.inspector.editor.formelement_selector.title')}">
            <typo3-backend-icon identifier="actions-variable-select"></typo3-backend-icon>
            <span class="visually-hidden">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-right" data-identifier="inspectorEditorFormElementSelectorSplitButtonListContainer">
            ${this.elements.map(element => this.renderEntry(element))}
          </ul>
        </span>
      </span>
    `;
    }
    renderEntry(element) {
        return html `
      <li>
        <a @click=${() => this.onSelect(element.value)} href="#" class="dropdown-item" data-formelement-identifier="${element.value}">
          <span class="dropdown-item-columns">
            <span class="dropdown-item-column dropdown-item-column-icon">
               <typo3-backend-icon identifier=${element.icon} size="small"></typo3-backend-icon>
            </span>
            <span class="dropdown-item-column dropdown-item-column-text">${element.label}</span>
          </span>
        </a>
      </li>
    `;
    }
    onSelect(value) {
        this.dispatchEvent(new FormElementSelectorSelectedEvent(value));
    }
};
__decorate([
    property({ type: Array, attribute: 'elements' })
], FormElementSelector.prototype, "elements", void 0);
__decorate([
    property({ type: String })
], FormElementSelector.prototype, "size", void 0);
FormElementSelector = __decorate([
    customElement('typo3-form-element-selector')
], FormElementSelector);
export { FormElementSelector };
