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
import { customElement, query } from 'lit/decorators.js';
import '@typo3/backend/tree/tree-toolbar.js';
import './form-editor-tree.js';
export const navigationComponentName = 'typo3-backend-navigation-component-formeditortree';
/**
 * Form Editor Tree Container - Navigation Component
 * Similar structure to FileStorageTreeNavigationComponent
 * Contains toolbar and tree component
 */
let FormEditorTreeContainer = class FormEditorTreeContainer extends LitElement {
    async setNodes(nodes) {
        await this.updateComplete;
        if (this.tree) {
            this.tree.setNodes(nodes);
        }
    }
    setSelectedNode(identifierPath) {
        if (this.tree) {
            this.tree.setSelectedNode(identifierPath);
        }
    }
    search(term) {
        if (this.tree) {
            this.tree.search(term);
        }
    }
    setNodeValidationError(identifierPath, hasError = true) {
        if (this.tree) {
            this.tree.setNodeValidationError(identifierPath, hasError);
        }
    }
    setNodeChildHasError(identifierPath, childHasError = true) {
        if (this.tree) {
            this.tree.setNodeChildHasError(identifierPath, childHasError);
        }
    }
    clearAllValidationErrors() {
        if (this.tree) {
            this.tree.clearAllValidationErrors();
        }
    }
    // Disable shadow DOM for compatibility with backend styles
    createRenderRoot() {
        return this;
    }
    render() {
        return html `
      <typo3-backend-tree-toolbar
        .tree="${this.tree}"
        .showRefresh="${false}"
        id="typo3-formeditortree-toolbar"
      ></typo3-backend-tree-toolbar>
      <typo3-backend-navigation-component-formeditor-tree
        id="typo3-formeditortree-tree"
      ></typo3-backend-navigation-component-formeditor-tree>
    `;
    }
    firstUpdated() {
        if (this.toolbar && this.tree) {
            this.toolbar.tree = this.tree;
        }
        // Dispatch ready event for components waiting for tree container
        this.dispatchEvent(new CustomEvent('typo3:tree-container:ready', {
            bubbles: true,
            composed: true
        }));
    }
};
__decorate([
    query('typo3-backend-navigation-component-formeditor-tree')
], FormEditorTreeContainer.prototype, "tree", void 0);
__decorate([
    query('typo3-backend-tree-toolbar')
], FormEditorTreeContainer.prototype, "toolbar", void 0);
FormEditorTreeContainer = __decorate([
    customElement('typo3-backend-navigation-component-formeditortree')
], FormEditorTreeContainer);
export { FormEditorTreeContainer };
