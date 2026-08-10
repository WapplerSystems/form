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

import { html, LitElement, nothing, type TemplateResult } from 'lit';
import { customElement, property } from 'lit/decorators.js';
import '@typo3/backend/element/icon-element';
import '@typo3/form/backend/form-editor/component/form-element-stage-item-toolbar';
import { stripTags } from '../utility/string-utility';

export interface Validator {
  identifier: string;
  label: string;
}

export interface SelectOption {
  label: string;
  value: string;
  selected?: boolean;
}

interface MultivalueItem {
  label: string;
  className?: string;
}

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
@customElement('typo3-form-form-element-stage-item')
export class FormElementStageItem extends LitElement {
  @property({ type: String, attribute: 'element-type' }) elementType: string = '';
  @property({ type: String, attribute: 'element-identifier' }) elementIdentifier: string = '';
  @property({ type: String, attribute: 'element-label' }) elementLabel: string = '';
  @property({ type: String, attribute: 'element-icon-identifier' }) elementIconIdentifier: string = '';
  @property({ type: Boolean, attribute: 'is-required' }) isRequired: boolean = false;
  @property({ type: Boolean, attribute: 'is-hidden' }) isHidden: boolean = false;
  @property({ type: Boolean, reflect: true }) invalid: boolean = false;
  @property({ type: Array }) validators: Validator[] = [];
  @property({ type: Array }) options: SelectOption[] = [];
  @property({ type: Array }) allowedMimeTypes?: string[];
  @property({ type: String }) content?: string;

  protected override createRenderRoot(): HTMLElement | ShadowRoot {
    // Avoid Shadow DOM so global styles apply to the element contents
    return this;
  }

  protected override render(): TemplateResult {
    return html`
      <typo3-form-form-element-stage-item-toolbar
        active
        icon-identifier="${this.elementIconIdentifier}"
        element-type="${this.elementType}"
        element-identifier="${this.elementIdentifier}"
        ?is-hidden="${this.isHidden}"
        ?is-invalid="${this.invalid}">
      </typo3-form-form-element-stage-item-toolbar>
      <div class="formeditor-element-body">
        <div class="formeditor-element-info">
          <div class="formeditor-element-info-label">
            <span>${stripTags(this.elementLabel)}</span>
            ${this.isRequired ? html`<span>*</span>` : nothing}
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
  private renderInfoContent(): TemplateResult | typeof nothing {
    const contentItems = this.renderContentItems();

    if (!contentItems.length) {
      return nothing;
    }

    return html`
      <div class="formeditor-element-info-content">
        ${contentItems}
      </div>
    `;
  }

  /**
   * Collects all content items to be rendered in the info section
   */
  private renderContentItems(): TemplateResult[] {
    const items: TemplateResult[] = [];

    // Render text (for elements with text property)
    if (this.content) {
      items.push(html`
        <div class="formeditor-element-info-text">
          ${stripTags(this.content)}
        </div>
      `);
    }

    // Render options (for select elements)
    if (this.options?.length) {
      const multivalueItems: MultivalueItem[] = this.options.map(option => ({
        label: option.label,
        className: option.selected ? 'selected' : undefined,
      }));
      items.push(this.renderMultivalue(multivalueItems));
    }

    // Render allowed mime types (for file upload elements)
    if (this.allowedMimeTypes?.length) {
      const multivalueItems: MultivalueItem[] = this.allowedMimeTypes.map(mimeType => ({
        label: mimeType,
      }));
      items.push(this.renderMultivalue(multivalueItems));
    }

    return items;
  }

  /**
   * Renders a multivalue list with items
   */
  private renderMultivalue(items: MultivalueItem[]): TemplateResult {
    return html`
      <div class="formeditor-element-info-multivalue">
        ${items.map(item => html`
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
  private renderValidators(): TemplateResult | typeof nothing {
    if (!this.validators?.length) {
      return nothing;
    }

    return html`
      <div class="formeditor-element-validator">
        <div class="formeditor-element-validator-icon">
          <typo3-backend-icon identifier="form-validator" size="small"></typo3-backend-icon>
        </div>
        <div class="formeditor-element-validator-list">
          ${this.validators.map(validator => html`
            <div class="formeditor-element-validator-list-item">
              ${validator.label}
            </div>
          `)}
        </div>
      </div>
    `;
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'typo3-form-form-element-stage-item': FormElementStageItem;
  }
}
