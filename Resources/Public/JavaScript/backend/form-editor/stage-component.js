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
<<<<<<< HEAD
/**
 * Module: @typo3/form/backend/form-editor/stage-component
 */
import * as Helper from '@typo3/form/backend/form-editor/helper.js';
import { merge } from 'lodash-es';
import Icons from '@typo3/backend/icons.js';
import Sortable from 'sortablejs';
import '@typo3/form/backend/form-editor/component/form-element-stage-item.js';
import '@typo3/form/backend/form-editor/component/form-element-stage-item-toolbar.js';
import '@typo3/form/backend/form-editor/component/page-stage-item.js';
import labels from '~labels/form.form_editor_javascript';
const defaultConfiguration = {
    domElementClassNames: {
        formElementIsComposit: 'formeditor-element-composit',
        formElementIsTopLevel: 'formeditor-element-toplevel',
        noNesting: 'no-nesting',
        selected: 'selected',
        sortable: 'sortable',
        previewViewPreviewElement: 'formeditor-element-preview'
    },
    domElementDataAttributeNames: {
        abstractType: 'data-element-abstract-type',
        noSorting: 'data-no-sorting'
    },
    domElementDataAttributeValues: {
        abstractViewToolbarNewElement: 'stageElementToolbarNewElement',
        abstractViewToolbarNewElementSplitButton: 'stageElementToolbarNewElementSplitButton',
        abstractViewToolbarNewElementSplitButtonAfter: 'stageElementToolbarNewElementSplitButtonAfter',
        abstractViewToolbarNewElementSplitButtonInside: 'stageElementToolbarNewElementSplitButtonInside',
        abstractViewToolbarRemoveElement: 'stageElementToolbarRemoveElement',
        buttonHeaderRedo: 'redoButton',
        buttonHeaderUndo: 'undoButton',
        buttonPaginationPrevious: 'buttonPaginationPrevious',
        buttonPaginationNext: 'buttonPaginationNext',
        'FormElement-_ElementToolbar': 'FormElement-_ElementToolbar',
        'FormElement-_UnknownElement': 'FormElement-_UnknownElement',
        formElementIcon: 'elementIcon',
        iconValidator: 'form-validator',
        multiValueContainer: 'multiValueContainer',
        paginationTitle: 'paginationTitle',
        stageHeadline: 'formDefinitionLabel',
        stagePanel: 'stagePanel',
        validatorsContainer: 'validatorsContainer',
        validatorIcon: 'validatorIcon'
    },
    isSortable: true
};
let configuration = null;
let formEditorApp = null;
let stageDomElement = null;
function getFormEditorApp() {
    return formEditorApp;
}
function getHelper(_configuration) {
    if (getUtility().isUndefinedOrNull(_configuration)) {
        return Helper.setConfiguration(configuration);
    }
    return Helper.setConfiguration(_configuration);
}
function getUtility() {
    return getFormEditorApp().getUtility();
}
function getViewModel() {
    return getFormEditorApp().getViewModel();
}
function assert(test, message, messageCode) {
    return getFormEditorApp().assert(test, message, messageCode);
}
function getRootFormElement() {
    return getFormEditorApp().getRootFormElement();
}
function getCurrentlySelectedFormElement() {
    return getFormEditorApp().getCurrentlySelectedFormElement();
}
function getPublisherSubscriber() {
    return getFormEditorApp().getPublisherSubscriber();
}
function getFormElementDefinition(formElement, formElementDefinitionKey) {
    return getFormEditorApp().getFormElementDefinition(formElement, formElementDefinitionKey);
}
function setTemplateTextContent(domElement, content) {
    if (getUtility().isNonEmptyString(content)) {
        domElement.textContent = content;
    }
}
/**
 * @publish view/stage/abstract/render/template/perform
 */
function renderTemplateDispatcher(formElement, template) {
    getPublisherSubscriber().publish('view/stage/abstract/render/template/perform', [formElement, template]);
}
/**
 * Creates a "new element" placeholder <li> rendered at the first position
 * inside a Page or composite element.
 *
 * @param formElement - The parent form element (Page or composite)
 * @param position - always 'inside': inserts as first child of formElement
 */
function createNewElementPlaceholder(formElement, position) {
    const listItem = document.createElement('li');
    listItem.setAttribute('data-no-sorting', 'true');
    listItem.classList.add('formeditor-new-element-placeholder');
    const buttonLabel = labels.get('formEditor.stage.toolbar.new_element');
    const button = document.createElement('button');
    button.type = 'button';
    button.title = buttonLabel;
    button.classList.add('btn', 'btn-sm', 'btn-default');
    const icon = document.createElement('typo3-backend-icon');
    icon.setAttribute('identifier', 'actions-plus');
    icon.setAttribute('size', 'small');
    button.append(icon, document.createTextNode(' ' + buttonLabel));
    button.addEventListener('click', function (e) {
        e.stopPropagation();
        getFormEditorApp().setCurrentlySelectedFormElement(formElement);
        if (position === 'inside') {
            getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
                'view/insertElements/perform/inside',
                { disableElementTypes: [], onlyEnableElementTypes: [] }
            ]);
        }
        else {
            getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
                'view/insertElements/perform/after',
                { disableElementTypes: [], onlyEnableElementTypes: [] }
            ]);
        }
    });
    listItem.append(button);
    return listItem;
}
/**
 * @throws 1478987818
 */
function renderNestedSortableListItem(formElement) {
    let childList;
    const listItem = document.createElement('li');
    if (!getFormElementDefinition(formElement, '_isCompositeFormElement')) {
        listItem.classList.add(getHelper().getDomElementClassName('noNesting'));
    }
    if (getFormElementDefinition(formElement, '_isTopLevelFormElement')) {
        listItem.classList.add(getHelper().getDomElementClassName('formElementIsTopLevel'));
    }
    if (getFormElementDefinition(formElement, '_isCompositeFormElement')) {
        listItem.classList.add(getHelper().getDomElementClassName('formElementIsComposit'));
    }
    let rawTemplate;
    try {
        rawTemplate = getHelper().getTemplateElement('FormElement-' + formElement.get('type'));
    }
    catch {
        rawTemplate = null;
    }
    // When no custom template is registered, the web component renders the element directly.
    // The _UnknownElement template fallback is no longer required.
    const shouldRenderWebComponent = rawTemplate === null;
    const templateEl = document.createElement('div');
    templateEl.setAttribute(getHelper().getDomElementDataAttribute('elementIdentifier'), formElement.get('__identifierPath'));
    if (rawTemplate) {
        templateEl.append(document.importNode(rawTemplate.content, true));
    }
    const isCompositeFormElement = getFormElementDefinition(formElement, '_isCompositeFormElement');
    if (isCompositeFormElement) {
        templateEl.setAttribute(getHelper().getDomElementDataAttribute('abstractType'), 'isCompositeFormElement');
    }
    const isTopLevelFormElement = getFormElementDefinition(formElement, '_isTopLevelFormElement');
    if (isTopLevelFormElement) {
        templateEl.setAttribute(getHelper().getDomElementDataAttribute('abstractType'), 'isTopLevelFormElement');
    }
    else {
        templateEl.classList.add('formeditor-element');
        templateEl.setAttribute('tabindex', '0');
        templateEl.setAttribute('role', 'button');
        templateEl.setAttribute('aria-label', (formElement.get('label') || formElement.get('identifier')) + ' (' + getFormElementDefinition(formElement, 'label') + ')');
    }
    if (formElement.get('renderingOptions.enabled') === false) {
        templateEl.classList.add('formeditor-element-hidden');
    }
    // For non-top-level elements rendered via a custom Fluid template (legacy path),
    // automatically prepend the standalone toolbar web component.
    if (!isTopLevelFormElement && !shouldRenderWebComponent
        && !templateEl.querySelector('typo3-form-form-element-stage-item-toolbar')) {
        const toolbarEl = document.createElement('typo3-form-form-element-stage-item-toolbar');
        toolbarEl.iconIdentifier = getFormElementDefinition(formElement, 'iconIdentifier') || '';
        toolbarEl.elementType = getFormElementDefinition(formElement, 'label') || '';
        toolbarEl.elementIdentifier = formElement.get('identifier') || '';
        toolbarEl.isHidden = formElement.get('renderingOptions.enabled') === false;
        toolbarEl.active = true;
        templateEl.prepend(toolbarEl);
    }
    listItem.append(templateEl);
    if (isTopLevelFormElement && shouldRenderWebComponent) {
        renderTopLevelStageItem(formElement, templateEl);
    }
    else if (shouldRenderWebComponent) {
        renderFormElementStageItem(formElement, templateEl);
    }
    else {
        renderTemplateDispatcher(formElement, templateEl);
    }
    if (isTopLevelFormElement || isCompositeFormElement) {
        childList = document.createElement('ol');
        childList.classList.add(getHelper().getDomElementClassName('sortable'));
        childList.classList.add('formeditor-list');
        const childFormElements = formElement.get('renderables');
        const hasChildren = Array.isArray(childFormElements) && childFormElements.length > 0;
        // Show "Create new element" placeholder when the container (page or composite) is empty.
        if (!hasChildren) {
            childList.append(createNewElementPlaceholder(formElement, 'inside'));
        }
        if (hasChildren) {
            for (let i = 0, len = childFormElements.length; i < len; ++i) {
                childList.append(renderNestedSortableListItem(childFormElements[i]));
            }
        }
        listItem.append(childList);
    }
    return listItem;
}
/**
 * @publish view/stage/abstract/dnd/start
 * @publish view/stage/abstract/dnd/stop
 * @publish view/stage/abstract/dnd/change
 * @publish view/stage/abstract/dnd/update
 */
function addSortableEvents() {
    const sortableLists = stageDomElement.querySelectorAll('ol.' + getHelper().getDomElementClassName('sortable'));
    const draggableSelector = 'li:not(' + getHelper().getDomElementDataAttribute('noSorting', 'bracesWithKey') + ')';
    const handleSelector = 'div' + getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKey');
    sortableLists.forEach(function (sortableList) {
        sortableList.querySelectorAll(handleSelector).forEach(function (draggable) {
            draggable.classList.add('formeditor-sortable-handle');
        });
        new Sortable(sortableList, {
            group: 'stage-nodes',
            handle: handleSelector,
            draggable: draggableSelector,
            animation: 200,
            swapThreshold: 0.6,
            dragClass: 'formeditor-sortable-drag',
            ghostClass: 'formeditor-sortable-ghost',
            onStart: function (e) {
                stageDomElement.classList.add('formeditor-is-dragging');
                getPublisherSubscriber().publish('view/stage/abstract/dnd/start', [e.item, e.item]);
            },
            onChange: function (e) {
                let enclosingCompositeFormElement;
                const parentFormElementIdentifierPath = getAbstractViewParentFormElementIdentifierPathWithinDomElement(e.item);
                if (parentFormElementIdentifierPath) {
                    enclosingCompositeFormElement = getFormEditorApp()
                        .findEnclosingCompositeFormElementWhichIsNotOnTopLevel(parentFormElementIdentifierPath);
                }
                getPublisherSubscriber().publish('view/stage/abstract/dnd/change', [
                    e.item,
                    parentFormElementIdentifierPath, enclosingCompositeFormElement
                ]);
            },
            onEnd: function (e) {
                const item = e.item;
                const movedFormElementIdentifierPath = getAbstractViewFormElementIdentifierPathWithinDomElement(item);
                const previousFormElementIdentifierPath = getAbstractViewSiblingFormElementIdentifierPathWithinDomElement(item, 'prev');
                const nextFormElementIdentifierPath = getAbstractViewSiblingFormElementIdentifierPathWithinDomElement(item, 'next');
                getPublisherSubscriber().publish('view/stage/abstract/dnd/update', [
                    item,
                    movedFormElementIdentifierPath,
                    previousFormElementIdentifierPath,
                    nextFormElementIdentifierPath
                ]);
                getPublisherSubscriber().publish('view/stage/abstract/dnd/stop', [
                    getAbstractViewFormElementIdentifierPathWithinDomElement(item)
                ]);
                stageDomElement.classList.remove('formeditor-is-dragging');
            },
        });
    });
}
export function getStageDomElement() {
    return stageDomElement;
}
/**
 * @throws 1479037151
 */
export function buildTitleByFormElement(formElement) {
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getRootFormElement();
    }
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1479037151);
    const span = document.createElement('span');
    span.textContent = formElement.get('label') ? formElement.get('label') : formElement.get('identifier');
    return span;
}
export function setStageHeadline(title) {
    if (getUtility().isUndefinedOrNull(title)) {
        title = buildTitleByFormElement().textContent;
    }
    const el = document.querySelector(getHelper().getDomElementDataIdentifierSelector('stageHeadline'));
    if (el) {
        el.textContent = title;
    }
}
export function getStagePanelDomElement() {
    return document.querySelector(getHelper().getDomElementDataIdentifierSelector('stagePanel'));
}
export function renderPagination() {
    const pageCount = getRootFormElement().get('renderables').length;
    const qs = (id) => document.querySelector(getHelper().getDomElementDataIdentifierSelector(id));
    getViewModel().enableButton(qs('buttonPaginationPrevious'));
    getViewModel().enableButton(qs('buttonPaginationNext'));
    if (getFormEditorApp().getCurrentlySelectedPageIndex() === 0) {
        getViewModel().disableButton(qs('buttonPaginationPrevious'));
    }
    if (pageCount === 1 || getFormEditorApp().getCurrentlySelectedPageIndex() === (pageCount - 1)) {
        getViewModel().disableButton(qs('buttonPaginationNext'));
    }
    const currentPage = getFormEditorApp().getCurrentlySelectedPageIndex() + 1;
    const paginationEl = qs('paginationTitle');
    if (paginationEl) {
        paginationEl.textContent = getFormElementDefinition(getRootFormElement(), 'paginationTitle')
            .replace('{0}', currentPage.toString())
            .replace('{1}', pageCount);
    }
}
export function renderUndoRedo() {
    const qs = (id) => document.querySelector(getHelper().getDomElementDataIdentifierSelector(id));
    getViewModel().enableButton(qs('buttonHeaderUndo'));
    getViewModel().enableButton(qs('buttonHeaderRedo'));
    if (getFormEditorApp().getCurrentApplicationStatePosition() + 1 >= getFormEditorApp().getCurrentApplicationStates()) {
        getViewModel().disableButton(qs('buttonHeaderUndo'));
    }
    if (getFormEditorApp().getCurrentApplicationStatePosition() === 0) {
        getViewModel().disableButton(qs('buttonHeaderRedo'));
    }
}
export function getAllFormElementDomElements() {
    return stageDomElement
        ? stageDomElement.querySelectorAll(getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKey'))
        : document.querySelectorAll('.formeditor-element-none');
}
/* *************************************************************
 * Abstract stage
 * ************************************************************/
/**
 * @throws 1478721208
 */
export function renderFormDefinitionPageAsSortableList(pageIndex) {
    assert(typeof pageIndex === 'number', 'Invalid parameter "pageIndex"', 1478721208);
    const ol = document.createElement('ol');
    ol.classList.add('formeditor-stage-list');
    ol.append(renderNestedSortableListItem(getRootFormElement().get('renderables')[pageIndex]));
    return ol;
}
export function getAbstractViewParentFormElementWithinDomElement(element) {
    return element
        .parentElement
        ?.closest('li')
        ?.querySelector(getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKey'))
        ?? null;
}
export function getAbstractViewParentFormElementIdentifierPathWithinDomElement(element) {
    return getAbstractViewParentFormElementWithinDomElement(element)
        ?.getAttribute(getHelper().getDomElementDataAttribute('elementIdentifier')) ?? '';
}
export function getAbstractViewFormElementWithinDomElement(element) {
    return element.querySelector(getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKey'));
}
export function getAbstractViewFormElementIdentifierPathWithinDomElement(element) {
    return getAbstractViewFormElementWithinDomElement(element)
        ?.getAttribute(getHelper().getDomElementDataAttribute('elementIdentifier')) ?? '';
}
export function getAbstractViewSiblingFormElementIdentifierPathWithinDomElement(element, position) {
    if (getUtility().isUndefinedOrNull(position)) {
        position = 'prev';
    }
    const formElementIdentifierPath = getAbstractViewFormElementIdentifierPathWithinDomElement(element);
    const sibling = position === 'prev'
        ? element.previousElementSibling
        : element.nextElementSibling;
    if (!sibling) {
        return '';
    }
    const attr = getHelper().getDomElementDataAttribute('elementIdentifier');
    const found = sibling.querySelector(getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKey') +
        ':not(' + getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKeyValue', [formElementIdentifierPath]) + ')');
    return found?.getAttribute(attr) ?? '';
}
export function getAbstractViewFormElementDomElement(formElement) {
    let formElementIdentifierPath;
    if (typeof formElement === 'string') {
        formElementIdentifierPath = formElement;
    }
    else {
        if (getUtility().isUndefinedOrNull(formElement)) {
            formElementIdentifierPath = getCurrentlySelectedFormElement().get('__identifierPath');
        }
        else {
            formElementIdentifierPath = formElement.get('__identifierPath');
        }
    }
    return stageDomElement
        ? stageDomElement.querySelector(getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKeyValue', [formElementIdentifierPath]))
        : null;
}
/**
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15.
 *   Only used by the legacy template-based stage rendering approach.
 *   Web component-based elements handle their toolbar via the
 *   `toolbarConfig` property of `<typo3-form-form-element-stage-item>`.
 *   See Deprecation #109306.
 * @publish view/insertElements/perform/after
 * @publish view/insertElements/perform/inside
 * @throws 1479035778
 */
export function createAbstractViewFormElementToolbar(formElement) {
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1479035778);
    const formElementTypeDefinition = getFormElementDefinition(formElement, undefined);
    if (formElementTypeDefinition._isTopLevelFormElement) {
        return null;
    }
    const rawTemplate = getHelper().getTemplateElement('FormElement-_ElementToolbar');
    if (!rawTemplate) {
        return null;
    }
    const template = document.importNode(rawTemplate.content, true).firstElementChild ?? document.createElement('div');
    getHelper().getTemplatePropertyElement('_type', template)?.append(document.createTextNode(getFormElementDefinition(formElement, 'label')));
    getHelper().getTemplatePropertyElement('_identifier', template)?.append(document.createTextNode(formElement.get('identifier')));
    wireAbstractViewFormElementToolbarEventListeners(template, formElement);
    return template;
}
/**
 * Wires toolbar button event listeners onto an already-cloned toolbar HTMLElement.
 * Only used by the deprecated {@link createAbstractViewFormElementToolbar} function
 * (global _ElementToolbar template path).  New code uses
 * `<typo3-form-form-element-stage-item-toolbar>` which dispatches its own events.
 *
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15 together with
 *   {@link createAbstractViewFormElementToolbar}.
 */
function wireAbstractViewFormElementToolbarEventListeners(toolbar, formElement) {
    const formElementTypeDefinition = getFormElementDefinition(formElement, undefined);
    const qs = (id) => toolbar.querySelector(getHelper().getDomElementDataIdentifierSelector(id));
    if (formElementTypeDefinition._isCompositeFormElement) {
        getViewModel().hideComponent(qs('abstractViewToolbarNewElement'));
        qs('abstractViewToolbarNewElementSplitButtonAfter')?.addEventListener('click', function () {
            getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
                'view/insertElements/perform/after',
                { disableElementTypes: [], onlyEnableElementTypes: [] }
            ]);
        });
        qs('abstractViewToolbarNewElementSplitButtonInside')?.addEventListener('click', function () {
            getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
                'view/insertElements/perform/inside',
                { disableElementTypes: [], onlyEnableElementTypes: [] }
            ]);
        });
    }
    else {
        getViewModel().hideComponent(qs('abstractViewToolbarNewElementSplitButton'));
        qs('abstractViewToolbarNewElement')?.addEventListener('click', function () {
            getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
                'view/insertElements/perform/after',
                { disableElementTypes: [] }
            ]);
        });
    }
    qs('abstractViewToolbarRemoveElement')?.addEventListener('click', function () {
        getViewModel().showRemoveFormElementModal(formElement);
    });
}
export function createAndAddAbstractViewFormElementToolbar(selectedFormElementDomElement, formElement) {
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getCurrentlySelectedFormElement();
    }
    const stageItem = selectedFormElementDomElement.querySelector('typo3-form-form-element-stage-item');
    if (stageItem) {
        return;
    }
    const formElementTypeDefinition = getFormElementDefinition(formElement, undefined);
    if (formElementTypeDefinition._isTopLevelFormElement) {
        return;
    }
    let toolbar = selectedFormElementDomElement.querySelector('typo3-form-form-element-stage-item-toolbar');
    if (!toolbar) {
        const toolbarEl = document.createElement('typo3-form-form-element-stage-item-toolbar');
        selectedFormElementDomElement.prepend(toolbarEl);
        toolbar = toolbarEl;
    }
    // Wire events exactly once per toolbar instance.
    if (!toolbar.dataset.eventsWired) {
        toolbar.dataset.eventsWired = 'true';
        toolbar.addEventListener('toolbar-new-element-before', () => {
            getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
                'view/insertElements/perform/before',
                { disableElementTypes: [] }
            ]);
        });
        toolbar.addEventListener('toolbar-new-element-after', () => {
            getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
                'view/insertElements/perform/after',
                { disableElementTypes: [] }
            ]);
        });
        toolbar.addEventListener('toolbar-remove-element', () => {
            getViewModel().showRemoveFormElementModal(formElement);
        });
    }
    toolbar.active = true;
}
/**
 * @publish view/stage/dnd/stop
 * @publish view/stage/element/clicked
 * @throws 1478169511
 */
export function renderAbstractStageArea(pageIndex, callback) {
    if (getUtility().isUndefinedOrNull(pageIndex)) {
        pageIndex = getFormEditorApp().getCurrentlySelectedPageIndex();
    }
    stageDomElement.replaceChildren(renderFormDefinitionPageAsSortableList(pageIndex));
    stageDomElement.addEventListener('click', function (e) {
        const formElementIdentifierPath = e.target
            .closest(getHelper().getDomElementDataAttribute('elementIdentifier', 'bracesWithKey'))
            ?.getAttribute(getHelper().getDomElementDataAttribute('elementIdentifier'));
        if (getUtility().isUndefinedOrNull(formElementIdentifierPath)
            || !getUtility().isNonEmptyString(formElementIdentifierPath)) {
            return;
        }
        getPublisherSubscriber().publish('view/stage/element/clicked', [formElementIdentifierPath]);
    });
    stageDomElement.addEventListener('keydown', function (e) {
        const target = e.target.closest('.formeditor-element[tabindex]');
        if (!target) {
            return;
        }
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        }
    });
    if (configuration.isSortable) {
        addSortableEvents();
    }
    if (typeof callback === 'function') {
        callback();
    }
}
/* *************************************************************
 * Preview stage
 * ************************************************************/
/**
 * @throws 1475424409
 */
export function renderPreviewStageArea(html) {
    assert(getUtility().isNonEmptyString(html), 'Invalid parameter "html"', 1475424409);
    stageDomElement.replaceChildren();
    stageDomElement.innerHTML = html;
    stageDomElement.querySelectorAll('input, select, textarea, button').forEach((el) => {
        el.disabled = true;
        ['click', 'dblclick', 'select', 'focus', 'keydown', 'keypress', 'keyup', 'mousedown', 'mouseup'].forEach((evt) => {
            el.addEventListener(evt, (e) => e.preventDefault());
        });
    });
    stageDomElement.querySelector('form')?.addEventListener('submit', (e) => e.preventDefault());
    getAllFormElementDomElements().forEach(function (el) {
        const formElement = getFormEditorApp()
            .getFormElementByIdentifierPath(el.dataset.elementIdentifierPath);
        if (!getFormElementDefinition(formElement, '_isTopLevelFormElement')) {
            el.setAttribute('title', 'identifier: ' + formElement.get('identifier') + ' (type: ' + formElement.get('type') + ')');
        }
        if (getFormElementDefinition(formElement, '_isTopLevelFormElement')) {
            el.classList.add(getHelper().getDomElementClassName('formElementIsTopLevel'));
        }
        if (getFormElementDefinition(formElement, '_isCompositeFormElement')) {
            el.classList.add(getHelper().getDomElementClassName('formElementIsComposit'));
        }
    });
}
/* *************************************************************
 * Template rendering
 * ************************************************************/
/**
 * Renders a top-level form element (page) using the PageStageItem web component
 *
 * @throws 1768924251
 */
export function renderTopLevelStageItem(formElement, template) {
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1768924251);
    const stageItem = document.createElement('typo3-form-page-stage-item');
    stageItem.pageTitle = formElement.get('label') || '';
    template.replaceChildren(stageItem);
}
/**
 * @throws 1768924252
 */
export function renderFormElementStageItem(formElement, template) {
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1768924252);
    const stageItem = document.createElement('typo3-form-form-element-stage-item');
    stageItem.elementType = getFormElementDefinition(formElement, 'label');
    stageItem.elementIdentifier = formElement.get('identifier');
    stageItem.elementLabel = formElement.get('label') || formElement.get('identifier');
    stageItem.elementIconIdentifier = getFormElementDefinition(formElement, 'iconIdentifier');
    stageItem.isHidden = formElement.get('renderingOptions.enabled') === false;
    const validators = formElement.get('validators');
    const validatorList = [];
    let hasNotEmptyValidator = false;
    if (Array.isArray(validators) && validators.length > 0) {
        for (let i = 0, len = validators.length; i < len; ++i) {
            if ('NotEmpty' === validators[i].identifier) {
                hasNotEmptyValidator = true;
                continue;
            }
            const collectionElementConfiguration = getFormEditorApp()
                .getFormEditorDefinition('validators', validators[i].identifier);
            validatorList.push({
                identifier: validators[i].identifier,
                label: collectionElementConfiguration.label
            });
        }
    }
    stageItem.validators = validatorList;
    stageItem.isRequired = hasNotEmptyValidator;
    const textValue = formElement.get('properties.text');
    if (textValue && getUtility().isNonEmptyString(textValue)) {
        stageItem.content = textValue;
    }
    const contentElementUid = formElement.get('properties.contentElementUid');
    if (contentElementUid && getUtility().isNonEmptyString(contentElementUid)) {
        stageItem.content = contentElementUid;
    }
    // Process options (for select elements like SingleSelect, MultiSelect, RadioButton, etc.)
    const propertyPath = 'properties.options';
    const propertyValue = formElement.get(propertyPath);
    const optionsList = [];
    if (propertyValue) {
        let defaultValue = formElement.get('defaultValue');
        if (getFormEditorApp().getUtility().isUndefinedOrNull(defaultValue)) {
            defaultValue = {};
        }
        else if (typeof defaultValue === 'string') {
            defaultValue = { 0: defaultValue };
        }
        if (typeof propertyValue === 'object' && propertyValue !== null && !Array.isArray(propertyValue)) {
            for (const propertyValueKey of Object.keys(propertyValue)) {
                let isSelected = false;
                for (const defaultValueKey of Object.keys(defaultValue)) {
                    if (defaultValue[defaultValueKey] === propertyValueKey) {
                        isSelected = true;
                        break;
                    }
                }
                optionsList.push({
                    label: propertyValue[propertyValueKey],
                    value: propertyValueKey,
                    selected: isSelected
                });
            }
        }
        else if (Array.isArray(propertyValue)) {
            const entries = propertyValue;
            for (const propertyValueKey of Object.keys(entries)) {
                let label;
                let value;
                if (getUtility().isUndefinedOrNull(entries[propertyValueKey]._label)) {
                    label = entries[propertyValueKey];
                    value = propertyValueKey;
                }
                else {
                    label = entries[propertyValueKey]._label;
                    value = entries[propertyValueKey]._value;
                }
                let isSelected = false;
                for (const defaultValueKey of Object.keys(defaultValue)) {
                    if (defaultValue[defaultValueKey] === value) {
                        isSelected = true;
                        break;
                    }
                }
                optionsList.push({
                    label: label,
                    value: value,
                    selected: isSelected
                });
            }
        }
    }
    stageItem.options = optionsList;
    // Process allowed mime types (for FileUpload and ImageUpload elements)
    const allowedMimeTypesPath = 'properties.allowedMimeTypes';
    const allowedMimeTypesValue = formElement.get(allowedMimeTypesPath);
    const mimeTypesList = [];
    if (allowedMimeTypesValue) {
        if (typeof allowedMimeTypesValue === 'object' && allowedMimeTypesValue !== null && !Array.isArray(allowedMimeTypesValue)) {
            for (const key of Object.keys(allowedMimeTypesValue)) {
                if (!isNaN(Number(key))) {
                    mimeTypesList.push(allowedMimeTypesValue[key]);
                }
            }
        }
        else if (Array.isArray(allowedMimeTypesValue)) {
            for (let i = 0, len = allowedMimeTypesValue.length; i < len; ++i) {
                mimeTypesList.push(allowedMimeTypesValue[i]);
            }
        }
    }
    if (mimeTypesList.length > 0) {
        stageItem.allowedMimeTypes = mimeTypesList;
    }
    if (stageItem.isHidden) {
        stageItem.classList.add('formeditor-element-hidden');
    }
    // Check if form element has validation errors
    const validationResults = getFormEditorApp().validateFormElement(formElement);
    let hasValidationError = false;
    for (let i = 0, len = validationResults.length; i < len; ++i) {
        if (validationResults[i].validationResults
            && validationResults[i].validationResults.length > 0) {
            hasValidationError = true;
            break;
        }
    }
    stageItem.invalid = hasValidationError;
    stageItem.addEventListener('toolbar-new-element-before', () => {
        getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
            'view/insertElements/perform/before',
            { disableElementTypes: [] }
        ]);
    });
    stageItem.addEventListener('toolbar-new-element-after', () => {
        getPublisherSubscriber().publish('view/stage/abstract/elementToolbar/button/newElement/clicked', [
            'view/insertElements/perform/after',
            { disableElementTypes: [] }
        ]);
    });
    stageItem.addEventListener('toolbar-remove-element', () => {
        getViewModel().showRemoveFormElementModal(formElement);
    });
    template.replaceChildren(stageItem);
}
/**
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15.
 *   See also Feature #107058.
 */
export function eachTemplateProperty(formElement, template, callback) {
    template.querySelectorAll(getHelper().getDomElementDataAttribute('templateProperty', 'bracesWithKey')).forEach(function (element) {
        const propertyPath = element.getAttribute(getHelper().getDomElementDataAttribute('templateProperty'));
        const propertyValue = formElement.get(propertyPath);
        if (typeof callback === 'function') {
            callback(propertyPath, propertyValue, element);
        }
    });
}
/**
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15.
 *   Implement a custom rendering instead.
 *   See also Feature #107058.
 */
export function renderCheckboxTemplate(formElement, template) {
    renderSimpleTemplateWithValidators(formElement, template);
    eachTemplateProperty(formElement, template, function (propertyPath, propertyValue, domElement) {
        if ((typeof propertyValue === 'boolean' && propertyValue)
            || propertyValue === 'true'
            || propertyValue === 1
            || propertyValue === '1') {
            domElement.classList.add(getHelper().getDomElementClassName('noNesting'));
        }
    });
}
/**
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15.
 *   Implement a custom rendering instead.
 *   See also Feature #107058.
 *
 * @throws 1479035696
 */
export function renderSimpleTemplate(formElement, template) {
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1479035696);
    eachTemplateProperty(formElement, template, (propertyPath, propertyValue, domElement) => {
        setTemplateTextContent(domElement, propertyValue);
    });
    const overlayIdentifier = formElement.get('renderingOptions.enabled') === false ? 'overlay-hidden' : null;
    Icons.getIcon(getFormElementDefinition(formElement, 'iconIdentifier'), Icons.sizes.small, overlayIdentifier, Icons.states.default, Icons.markupIdentifiers.inline).then(function (icon) {
        const iconContainer = template.querySelector(getHelper().getDomElementDataIdentifierSelector('formElementIcon'));
        if (iconContainer) {
            const tmp = document.createElement('div');
            tmp.innerHTML = icon;
            const iconEl = tmp.firstElementChild;
            if (iconEl) {
                iconEl.classList.add(getHelper().getDomElementClassName('icon'));
                iconContainer.append(iconEl);
            }
        }
    });
    getHelper().getTemplatePropertyElement('_type', template)
        ?.append(document.createTextNode(getFormElementDefinition(formElement, 'label')));
    getHelper().getTemplatePropertyElement('_identifier', template)
        ?.append(document.createTextNode(formElement.get('identifier')));
}
/**
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15.
 *   Implement a custom rendering instead.
 *   See also Feature #107058.
 *
 * @throws 1479035674
 */
export function renderSimpleTemplateWithValidators(formElement, template) {
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1479035674);
    renderSimpleTemplate(formElement, template);
    const validatorsContainerSel = getHelper().getDomElementDataIdentifierSelector('validatorsContainer');
    const validatorsContainerEl = template.querySelector(validatorsContainerSel);
    const validatorsTemplateContent = validatorsContainerEl?.cloneNode(true);
    validatorsContainerEl?.replaceChildren();
    const validators = formElement.get('validators');
    if (Array.isArray(validators)) {
        let validatorsCountWithoutRequired = 0;
        if (validators.length > 0) {
            for (let i = 0, len = validators.length; i < len; ++i) {
                if ('NotEmpty' === validators[i].identifier) {
                    getHelper().getTemplatePropertyElement('_required', template)?.append(document.createTextNode('*'));
                    continue;
                }
                validatorsCountWithoutRequired++;
                const collectionElementConfiguration = getFormEditorApp()
                    .getFormEditorDefinition('validators', validators[i].identifier);
                const rowTemplate = validatorsTemplateContent?.cloneNode(true);
                if (!rowTemplate) {
                    continue;
                }
                getHelper().getTemplatePropertyElement('_label', rowTemplate)
                    ?.append(document.createTextNode(collectionElementConfiguration.label));
                const refreshedContainer = template.querySelector(validatorsContainerSel);
                refreshedContainer?.insertAdjacentHTML('beforeend', rowTemplate.outerHTML);
            }
            if (validatorsCountWithoutRequired > 0) {
                Icons.getIcon(getHelper().getDomElementDataAttributeValue('iconValidator'), Icons.sizes.small, null, Icons.states.default, Icons.markupIdentifiers.inline).then(function (icon) {
                    const iconContainer = template.querySelector(getHelper().getDomElementDataIdentifierSelector('validatorIcon'));
                    if (iconContainer) {
                        const tmp = document.createElement('div');
                        tmp.innerHTML = icon;
                        const iconEl = tmp.firstElementChild;
                        if (iconEl) {
                            iconEl.classList.add(getHelper().getDomElementClassName('icon'));
                            iconContainer.append(iconEl);
                        }
                    }
                });
            }
        }
    }
}
/**
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15.
 *   Implement a custom rendering instead.
 *   See also Feature #107058.
 */
export function renderSelectTemplates(formElement, template) {
    const multiValueContainerSel = getHelper().getDomElementDataIdentifierSelector('multiValueContainer');
    const multiValueContainerEl = template.querySelector(multiValueContainerSel);
    const multiValueTemplateContent = multiValueContainerEl?.cloneNode(true);
    multiValueContainerEl?.replaceChildren();
    renderSimpleTemplateWithValidators(formElement, template);
    const propertyPath = template.querySelector(multiValueContainerSel)
        ?.getAttribute(getHelper().getDomElementDataAttribute('templateProperty'));
    const propertyValue = formElement.get(propertyPath);
    const appendMultiValue = (label, value, defaultValue) => {
        let isPreselected = false;
        const rowTemplate = multiValueTemplateContent?.cloneNode(true);
        if (!rowTemplate) {
            return;
        }
        for (const defaultValueKey of Object.keys(defaultValue)) {
            if (defaultValue[defaultValueKey] === value) {
                isPreselected = true;
                break;
            }
        }
        getHelper().getTemplatePropertyElement('_label', rowTemplate)
            ?.append(document.createTextNode(label));
        if (isPreselected) {
            getHelper().getTemplatePropertyElement('_label', rowTemplate)
                ?.classList.add(getHelper().getDomElementClassName('selected'));
        }
        template.querySelector(multiValueContainerSel)
            ?.insertAdjacentHTML('beforeend', rowTemplate.outerHTML);
    };
    let defaultValue = formElement.get('defaultValue');
    if (getFormEditorApp().getUtility().isUndefinedOrNull(defaultValue)) {
        defaultValue = {};
    }
    else if (typeof defaultValue === 'string') {
        defaultValue = { 0: defaultValue };
    }
    if (typeof propertyValue === 'object' && propertyValue !== null && !Array.isArray(propertyValue)) {
        for (const propertyValueKey of Object.keys(propertyValue)) {
            appendMultiValue(propertyValue[propertyValueKey], propertyValueKey, defaultValue);
        }
    }
    else if (Array.isArray(propertyValue)) {
        const entries = propertyValue;
        for (const propertyValueKey of Object.keys(entries)) {
            if (getUtility().isUndefinedOrNull(entries[propertyValueKey]._label)) {
                appendMultiValue(entries[propertyValueKey], propertyValueKey, defaultValue);
            }
            else {
                appendMultiValue(entries[propertyValueKey]._label, entries[propertyValueKey]._value, defaultValue);
            }
        }
    }
}
/**
 * @deprecated since TYPO3 v14.2, will be removed in TYPO3 v15.
 *   Implement a custom rendering instead.
 *   See also Feature #107058.
 */
export function renderFileUploadTemplates(formElement, template) {
    const multiValueContainerSel = getHelper().getDomElementDataIdentifierSelector('multiValueContainer');
    const multiValueContainerEl = template.querySelector(multiValueContainerSel);
    const multiValueTemplateContent = multiValueContainerEl?.cloneNode(true);
    multiValueContainerEl?.replaceChildren();
    renderSimpleTemplateWithValidators(formElement, template);
    const propertyPath = template.querySelector(multiValueContainerSel)
        ?.getAttribute(getHelper().getDomElementDataAttribute('templateProperty'));
    const propertyValue = formElement.get(propertyPath);
    const appendMultiValue = function (value) {
        const rowTemplate = multiValueTemplateContent?.cloneNode(true);
        if (!rowTemplate) {
            return;
        }
        getHelper().getTemplatePropertyElement('_value', rowTemplate)?.append(document.createTextNode(value));
        template.querySelector(multiValueContainerSel)
            ?.insertAdjacentHTML('beforeend', rowTemplate.outerHTML);
    };
    if (typeof propertyValue === 'object' && propertyValue !== null && !Array.isArray(propertyValue)) {
        for (const propertyValueKey of Object.keys(propertyValue)) {
            appendMultiValue(propertyValue[propertyValueKey]);
        }
    }
    else if (Array.isArray(propertyValue)) {
        for (let i = 0, len = propertyValue.length; i < len; ++i) {
            appendMultiValue(propertyValue[i]);
        }
    }
}
/**
 * @throws 1478992119
 */
export function bootstrap(_formEditorApp, appendToDomElement, customConfiguration) {
    formEditorApp = _formEditorApp;
    assert(typeof appendToDomElement === 'object' && appendToDomElement !== null && !Array.isArray(appendToDomElement), 'Invalid parameter "appendToDomElement"', 1478992119);
    stageDomElement = appendToDomElement;
    configuration = merge({}, defaultConfiguration, customConfiguration ?? {});
    Helper.bootstrap(formEditorApp);
    return this;
}
=======
import*as F from"@typo3/form/backend/form-editor/helper.js";import{merge as Y}from"lodash-es";import D from"@typo3/backend/icons.js";import Z from"sortablejs";import"@typo3/form/backend/form-editor/component/form-element-stage-item.js";import"@typo3/form/backend/form-editor/component/form-element-stage-item-toolbar.js";import"@typo3/form/backend/form-editor/component/page-stage-item.js";import $ from"~labels/form.form_editor_javascript";const ee={domElementClassNames:{formElementIsComposit:"formeditor-element-composit",formElementIsTopLevel:"formeditor-element-toplevel",noNesting:"no-nesting",selected:"selected",sortable:"sortable",previewViewPreviewElement:"formeditor-element-preview"},domElementDataAttributeNames:{abstractType:"data-element-abstract-type",noSorting:"data-no-sorting"},domElementDataAttributeValues:{abstractViewToolbarNewElement:"stageElementToolbarNewElement",abstractViewToolbarNewElementSplitButton:"stageElementToolbarNewElementSplitButton",abstractViewToolbarNewElementSplitButtonAfter:"stageElementToolbarNewElementSplitButtonAfter",abstractViewToolbarNewElementSplitButtonInside:"stageElementToolbarNewElementSplitButtonInside",abstractViewToolbarRemoveElement:"stageElementToolbarRemoveElement",buttonHeaderRedo:"redoButton",buttonHeaderUndo:"undoButton",buttonPaginationPrevious:"buttonPaginationPrevious",buttonPaginationNext:"buttonPaginationNext","FormElement-_ElementToolbar":"FormElement-_ElementToolbar","FormElement-_UnknownElement":"FormElement-_UnknownElement",formElementIcon:"elementIcon",iconValidator:"form-validator",multiValueContainer:"multiValueContainer",paginationTitle:"paginationTitle",stageHeadline:"formDefinitionLabel",stagePanel:"stagePanel",validatorsContainer:"validatorsContainer",validatorIcon:"validatorIcon"},isSortable:!0};let k=null,_=null,b=null;function f(){return _}function o(e){return y().isUndefinedOrNull(e)?F.setConfiguration(k):F.setConfiguration(e)}function y(){return f().getUtility()}function v(){return f().getViewModel()}function w(e,t,n){return f().assert(e,t,n)}function P(){return f().getRootFormElement()}function j(){return f().getCurrentlySelectedFormElement()}function E(){return f().getPublisherSubscriber()}function p(e,t){return f().getFormElementDefinition(e,t)}function te(e,t){y().isNonEmptyString(t)&&(e.textContent=t)}function ne(e,t){E().publish("view/stage/abstract/render/template/perform",[e,t])}function ie(e,t){const n=document.createElement("li");n.setAttribute("data-no-sorting","true"),n.classList.add("formeditor-new-element-placeholder");const i=$.get("formEditor.stage.toolbar.new_element"),r=document.createElement("button");r.type="button",r.title=i,r.classList.add("btn","btn-sm","btn-default");const l=document.createElement("typo3-backend-icon");return l.setAttribute("identifier","actions-plus"),l.setAttribute("size","small"),r.append(l,document.createTextNode(" "+i)),r.addEventListener("click",function(a){a.stopPropagation(),f().setCurrentlySelectedFormElement(e),t==="inside"?E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/inside",{disableElementTypes:[],onlyEnableElementTypes:[]}]):E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/after",{disableElementTypes:[],onlyEnableElementTypes:[]}])}),n.append(r),n}function H(e){let t;const n=document.createElement("li");p(e,"_isCompositeFormElement")||n.classList.add(o().getDomElementClassName("noNesting")),p(e,"_isTopLevelFormElement")&&n.classList.add(o().getDomElementClassName("formElementIsTopLevel")),p(e,"_isCompositeFormElement")&&n.classList.add(o().getDomElementClassName("formElementIsComposit"));let i;try{i=o().getTemplateElement("FormElement-"+e.get("type"))}catch{i=null}const r=i===null,l=document.createElement("div");l.setAttribute(o().getDomElementDataAttribute("elementIdentifier"),e.get("__identifierPath")),i&&l.append(document.importNode(i.content,!0));const a=p(e,"_isCompositeFormElement");a&&l.setAttribute(o().getDomElementDataAttribute("abstractType"),"isCompositeFormElement");const m=p(e,"_isTopLevelFormElement");if(m?l.setAttribute(o().getDomElementDataAttribute("abstractType"),"isTopLevelFormElement"):(l.classList.add("formeditor-element"),l.setAttribute("tabindex","0"),l.setAttribute("role","button"),l.setAttribute("aria-label",(e.get("label")||e.get("identifier"))+" ("+p(e,"label")+")")),e.get("renderingOptions.enabled")===!1&&l.classList.add("formeditor-element-hidden"),!m&&!r&&!l.querySelector("typo3-form-form-element-stage-item-toolbar")){const s=document.createElement("typo3-form-form-element-stage-item-toolbar");s.iconIdentifier=p(e,"iconIdentifier")||"",s.elementType=p(e,"label")||"",s.elementIdentifier=e.get("identifier")||"",s.isHidden=e.get("renderingOptions.enabled")===!1,s.active=!0,l.prepend(s)}if(n.append(l),m&&r?G(e,l):r?J(e,l):ne(e,l),m||a){t=document.createElement("ol"),t.classList.add(o().getDomElementClassName("sortable")),t.classList.add("formeditor-list");const s=e.get("renderables"),d=Array.isArray(s)&&s.length>0;if(!d&&a&&t.append(ie(e,"inside")),d)for(let u=0,S=s.length;u<S;++u)t.append(H(s[u]));n.append(t)}return n}function re(){const e=b.querySelectorAll("ol."+o().getDomElementClassName("sortable")),t="li:not("+o().getDomElementDataAttribute("noSorting","bracesWithKey")+")",n="div"+o().getDomElementDataAttribute("elementIdentifier","bracesWithKey");e.forEach(function(i){i.querySelectorAll(n).forEach(function(r){r.classList.add("formeditor-sortable-handle")}),new Z(i,{group:"stage-nodes",handle:n,draggable:t,animation:200,swapThreshold:.6,dragClass:"formeditor-sortable-drag",ghostClass:"formeditor-sortable-ghost",onStart:function(r){b.classList.add("formeditor-is-dragging"),E().publish("view/stage/abstract/dnd/start",[r.item,r.item])},onChange:function(r){let l;const a=R(r.item);a&&(l=f().findEnclosingCompositeFormElementWhichIsNotOnTopLevel(a)),E().publish("view/stage/abstract/dnd/change",[r.item,a,l])},onEnd:function(r){const l=r.item,a=L(l),m=q(l,"prev"),s=q(l,"next");E().publish("view/stage/abstract/dnd/update",[l,a,m,s]),E().publish("view/stage/abstract/dnd/stop",[L(l)]),b.classList.remove("formeditor-is-dragging")}})})}function oe(){return b}function M(e){y().isUndefinedOrNull(e)&&(e=P()),w(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1479037151);const t=document.createElement("span");return t.textContent=e.get("label")?e.get("label"):e.get("identifier"),t}function le(e){y().isUndefinedOrNull(e)&&(e=M().textContent);const t=document.querySelector(o().getDomElementDataIdentifierSelector("stageHeadline"));t&&(t.textContent=e)}function ae(){return document.querySelector(o().getDomElementDataIdentifierSelector("stagePanel"))}function se(){const e=P().get("renderables").length,t=r=>document.querySelector(o().getDomElementDataIdentifierSelector(r));v().enableButton(t("buttonPaginationPrevious")),v().enableButton(t("buttonPaginationNext")),f().getCurrentlySelectedPageIndex()===0&&v().disableButton(t("buttonPaginationPrevious")),(e===1||f().getCurrentlySelectedPageIndex()===e-1)&&v().disableButton(t("buttonPaginationNext"));const n=f().getCurrentlySelectedPageIndex()+1,i=t("paginationTitle");i&&(i.textContent=p(P(),"paginationTitle").replace("{0}",n.toString()).replace("{1}",e))}function de(){const e=t=>document.querySelector(o().getDomElementDataIdentifierSelector(t));v().enableButton(e("buttonHeaderUndo")),v().enableButton(e("buttonHeaderRedo")),f().getCurrentApplicationStatePosition()+1>=f().getCurrentApplicationStates()&&v().disableButton(e("buttonHeaderUndo")),f().getCurrentApplicationStatePosition()===0&&v().disableButton(e("buttonHeaderRedo"))}function B(){return b?b.querySelectorAll(o().getDomElementDataAttribute("elementIdentifier","bracesWithKey")):document.querySelectorAll(".formeditor-element-none")}function W(e){w(typeof e=="number",'Invalid parameter "pageIndex"',1478721208);const t=document.createElement("ol");return t.classList.add("formeditor-stage-list"),t.append(H(P().get("renderables")[e])),t}function K(e){return e.parentElement?.closest("li")?.querySelector(o().getDomElementDataAttribute("elementIdentifier","bracesWithKey"))??null}function R(e){return K(e)?.getAttribute(o().getDomElementDataAttribute("elementIdentifier"))??""}function z(e){return e.querySelector(o().getDomElementDataAttribute("elementIdentifier","bracesWithKey"))}function L(e){return z(e)?.getAttribute(o().getDomElementDataAttribute("elementIdentifier"))??""}function q(e,t){y().isUndefinedOrNull(t)&&(t="prev");const n=L(e),i=t==="prev"?e.previousElementSibling:e.nextElementSibling;if(!i)return"";const r=o().getDomElementDataAttribute("elementIdentifier");return i.querySelector(o().getDomElementDataAttribute("elementIdentifier","bracesWithKey")+":not("+o().getDomElementDataAttribute("elementIdentifier","bracesWithKeyValue",[n])+")")?.getAttribute(r)??""}function ce(e){let t;return typeof e=="string"?t=e:y().isUndefinedOrNull(e)?t=j().get("__identifierPath"):t=e.get("__identifierPath"),b?b.querySelector(o().getDomElementDataAttribute("elementIdentifier","bracesWithKeyValue",[t])):null}function me(e){if(w(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1479035778),p(e,void 0)._isTopLevelFormElement)return null;const n=o().getTemplateElement("FormElement-_ElementToolbar");if(!n)return null;const i=document.importNode(n.content,!0).firstElementChild??document.createElement("div");return o().getTemplatePropertyElement("_type",i)?.append(document.createTextNode(p(e,"label"))),o().getTemplatePropertyElement("_identifier",i)?.append(document.createTextNode(e.get("identifier"))),ue(i,e),i}function ue(e,t){const n=p(t,void 0),i=r=>e.querySelector(o().getDomElementDataIdentifierSelector(r));n._isCompositeFormElement?(v().hideComponent(i("abstractViewToolbarNewElement")),i("abstractViewToolbarNewElementSplitButtonAfter")?.addEventListener("click",function(){E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/after",{disableElementTypes:[],onlyEnableElementTypes:[]}])}),i("abstractViewToolbarNewElementSplitButtonInside")?.addEventListener("click",function(){E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/inside",{disableElementTypes:[],onlyEnableElementTypes:[]}])})):(v().hideComponent(i("abstractViewToolbarNewElementSplitButton")),i("abstractViewToolbarNewElement")?.addEventListener("click",function(){E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/after",{disableElementTypes:[]}])})),i("abstractViewToolbarRemoveElement")?.addEventListener("click",function(){v().showRemoveFormElementModal(t)})}function fe(e,t){if(y().isUndefinedOrNull(t)&&(t=j()),e.querySelector("typo3-form-form-element-stage-item")||p(t,void 0)._isTopLevelFormElement)return;let r=e.querySelector("typo3-form-form-element-stage-item-toolbar");if(!r){const l=document.createElement("typo3-form-form-element-stage-item-toolbar");e.prepend(l),r=l}r.dataset.eventsWired||(r.dataset.eventsWired="true",r.addEventListener("toolbar-new-element-before",()=>{E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/before",{disableElementTypes:[]}])}),r.addEventListener("toolbar-new-element-after",()=>{E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/after",{disableElementTypes:[]}])}),r.addEventListener("toolbar-remove-element",()=>{v().showRemoveFormElementModal(t)})),r.active=!0}function pe(e,t){y().isUndefinedOrNull(e)&&(e=f().getCurrentlySelectedPageIndex()),b.replaceChildren(W(e)),b.addEventListener("click",function(n){const i=n.target.closest(o().getDomElementDataAttribute("elementIdentifier","bracesWithKey"))?.getAttribute(o().getDomElementDataAttribute("elementIdentifier"));y().isUndefinedOrNull(i)||!y().isNonEmptyString(i)||E().publish("view/stage/element/clicked",[i])}),b.addEventListener("keydown",function(n){const i=n.target.closest(".formeditor-element[tabindex]");i&&(n.key==="Enter"||n.key===" ")&&(n.preventDefault(),i.dispatchEvent(new MouseEvent("click",{bubbles:!0,cancelable:!0})))}),k.isSortable&&re(),typeof t=="function"&&t()}function be(e){w(y().isNonEmptyString(e),'Invalid parameter "html"',1475424409),b.replaceChildren(),b.innerHTML=e,b.querySelectorAll("input, select, textarea, button").forEach(t=>{t.disabled=!0,["click","dblclick","select","focus","keydown","keypress","keyup","mousedown","mouseup"].forEach(n=>{t.addEventListener(n,i=>i.preventDefault())})}),b.querySelector("form")?.addEventListener("submit",t=>t.preventDefault()),B().forEach(function(t){const n=f().getFormElementByIdentifierPath(t.dataset.elementIdentifierPath);p(n,"_isTopLevelFormElement")||t.setAttribute("title","identifier: "+n.get("identifier")+" (type: "+n.get("type")+")"),p(n,"_isTopLevelFormElement")&&t.classList.add(o().getDomElementClassName("formElementIsTopLevel")),p(n,"_isCompositeFormElement")&&t.classList.add(o().getDomElementClassName("formElementIsComposit"))})}function G(e,t){w(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1768924251);const n=document.createElement("typo3-form-page-stage-item");n.pageTitle=e.get("label")||"",t.replaceChildren(n)}function J(e,t){w(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1768924252);const n=document.createElement("typo3-form-form-element-stage-item");n.elementType=p(e,"label"),n.elementIdentifier=e.get("identifier"),n.elementLabel=e.get("label")||e.get("identifier"),n.elementIconIdentifier=p(e,"iconIdentifier"),n.isHidden=e.get("renderingOptions.enabled")===!1;const i=e.get("validators"),r=[];let l=!1;if(Array.isArray(i)&&i.length>0)for(let c=0,g=i.length;c<g;++c){if(i[c].identifier==="NotEmpty"){l=!0;continue}const T=f().getFormEditorDefinition("validators",i[c].identifier);r.push({identifier:i[c].identifier,label:T.label})}n.validators=r,n.isRequired=l;const a=e.get("properties.text");a&&y().isNonEmptyString(a)&&(n.content=a);const m=e.get("properties.contentElementUid");m&&y().isNonEmptyString(m)&&(n.content=m);const d=e.get("properties.options"),u=[];if(d){let c=e.get("defaultValue");if(f().getUtility().isUndefinedOrNull(c)?c={}:typeof c=="string"&&(c={0:c}),typeof d=="object"&&d!==null&&!Array.isArray(d))for(const g of Object.keys(d)){let T=!1;for(const I of Object.keys(c))if(c[I]===g){T=!0;break}u.push({label:d[g],value:g,selected:T})}else if(Array.isArray(d)){const g=d;for(const T of Object.keys(g)){let I,N;y().isUndefinedOrNull(g[T]._label)?(I=g[T],N=T):(I=g[T]._label,N=g[T]._value);let U=!1;for(const X of Object.keys(c))if(c[X]===N){U=!0;break}u.push({label:I,value:N,selected:U})}}}n.options=u;const h=e.get("properties.allowedMimeTypes"),A=[];if(h){if(typeof h=="object"&&h!==null&&!Array.isArray(h))for(const c of Object.keys(h))isNaN(Number(c))||A.push(h[c]);else if(Array.isArray(h))for(let c=0,g=h.length;c<g;++c)A.push(h[c])}A.length>0&&(n.allowedMimeTypes=A),n.isHidden&&n.classList.add("formeditor-element-hidden");const C=f().validateFormElement(e);let O=!1;for(let c=0,g=C.length;c<g;++c)if(C[c].validationResults&&C[c].validationResults.length>0){O=!0;break}n.invalid=O,n.addEventListener("toolbar-new-element-before",()=>{E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/before",{disableElementTypes:[]}])}),n.addEventListener("toolbar-new-element-after",()=>{E().publish("view/stage/abstract/elementToolbar/button/newElement/clicked",["view/insertElements/perform/after",{disableElementTypes:[]}])}),n.addEventListener("toolbar-remove-element",()=>{v().showRemoveFormElementModal(e)}),t.replaceChildren(n)}function x(e,t,n){t.querySelectorAll(o().getDomElementDataAttribute("templateProperty","bracesWithKey")).forEach(function(i){const r=i.getAttribute(o().getDomElementDataAttribute("templateProperty")),l=e.get(r);typeof n=="function"&&n(r,l,i)})}function ge(e,t){V(e,t),x(e,t,function(n,i,r){(typeof i=="boolean"&&i||i==="true"||i===1||i==="1")&&r.classList.add(o().getDomElementClassName("noNesting"))})}function Q(e,t){w(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1479035696),x(e,t,(i,r,l)=>{te(l,r)});const n=e.get("renderingOptions.enabled")===!1?"overlay-hidden":null;D.getIcon(p(e,"iconIdentifier"),D.sizes.small,n,D.states.default,D.markupIdentifiers.inline).then(function(i){const r=t.querySelector(o().getDomElementDataIdentifierSelector("formElementIcon"));if(r){const l=document.createElement("div");l.innerHTML=i;const a=l.firstElementChild;a&&(a.classList.add(o().getDomElementClassName("icon")),r.append(a))}}),o().getTemplatePropertyElement("_type",t)?.append(document.createTextNode(p(e,"label"))),o().getTemplatePropertyElement("_identifier",t)?.append(document.createTextNode(e.get("identifier")))}function V(e,t){w(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1479035674),Q(e,t);const n=o().getDomElementDataIdentifierSelector("validatorsContainer"),i=t.querySelector(n),r=i?.cloneNode(!0);i?.replaceChildren();const l=e.get("validators");if(Array.isArray(l)){let a=0;if(l.length>0){for(let m=0,s=l.length;m<s;++m){if(l[m].identifier==="NotEmpty"){o().getTemplatePropertyElement("_required",t)?.append(document.createTextNode("*"));continue}a++;const d=f().getFormEditorDefinition("validators",l[m].identifier),u=r?.cloneNode(!0);if(!u)continue;o().getTemplatePropertyElement("_label",u)?.append(document.createTextNode(d.label)),t.querySelector(n)?.insertAdjacentHTML("beforeend",u.outerHTML)}a>0&&D.getIcon(o().getDomElementDataAttributeValue("iconValidator"),D.sizes.small,null,D.states.default,D.markupIdentifiers.inline).then(function(m){const s=t.querySelector(o().getDomElementDataIdentifierSelector("validatorIcon"));if(s){const d=document.createElement("div");d.innerHTML=m;const u=d.firstElementChild;u&&(u.classList.add(o().getDomElementClassName("icon")),s.append(u))}})}}}function ye(e,t){const n=o().getDomElementDataIdentifierSelector("multiValueContainer"),i=t.querySelector(n),r=i?.cloneNode(!0);i?.replaceChildren(),V(e,t);const l=t.querySelector(n)?.getAttribute(o().getDomElementDataAttribute("templateProperty")),a=e.get(l),m=(d,u,S)=>{let h=!1;const A=r?.cloneNode(!0);if(A){for(const C of Object.keys(S))if(S[C]===u){h=!0;break}o().getTemplatePropertyElement("_label",A)?.append(document.createTextNode(d)),h&&o().getTemplatePropertyElement("_label",A)?.classList.add(o().getDomElementClassName("selected")),t.querySelector(n)?.insertAdjacentHTML("beforeend",A.outerHTML)}};let s=e.get("defaultValue");if(f().getUtility().isUndefinedOrNull(s)?s={}:typeof s=="string"&&(s={0:s}),typeof a=="object"&&a!==null&&!Array.isArray(a))for(const d of Object.keys(a))m(a[d],d,s);else if(Array.isArray(a)){const d=a;for(const u of Object.keys(d))y().isUndefinedOrNull(d[u]._label)?m(d[u],u,s):m(d[u]._label,d[u]._value,s)}}function Ee(e,t){const n=o().getDomElementDataIdentifierSelector("multiValueContainer"),i=t.querySelector(n),r=i?.cloneNode(!0);i?.replaceChildren(),V(e,t);const l=t.querySelector(n)?.getAttribute(o().getDomElementDataAttribute("templateProperty")),a=e.get(l),m=function(s){const d=r?.cloneNode(!0);d&&(o().getTemplatePropertyElement("_value",d)?.append(document.createTextNode(s)),t.querySelector(n)?.insertAdjacentHTML("beforeend",d.outerHTML))};if(typeof a=="object"&&a!==null&&!Array.isArray(a))for(const s of Object.keys(a))m(a[s]);else if(Array.isArray(a))for(let s=0,d=a.length;s<d;++s)m(a[s])}function ve(e,t,n){return _=e,w(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "appendToDomElement"',1478992119),b=t,k=Y({},ee,n??{}),F.bootstrap(_),this}export{ve as bootstrap,M as buildTitleByFormElement,me as createAbstractViewFormElementToolbar,fe as createAndAddAbstractViewFormElementToolbar,x as eachTemplateProperty,ce as getAbstractViewFormElementDomElement,L as getAbstractViewFormElementIdentifierPathWithinDomElement,z as getAbstractViewFormElementWithinDomElement,R as getAbstractViewParentFormElementIdentifierPathWithinDomElement,K as getAbstractViewParentFormElementWithinDomElement,q as getAbstractViewSiblingFormElementIdentifierPathWithinDomElement,B as getAllFormElementDomElements,oe as getStageDomElement,ae as getStagePanelDomElement,pe as renderAbstractStageArea,ge as renderCheckboxTemplate,Ee as renderFileUploadTemplates,W as renderFormDefinitionPageAsSortableList,J as renderFormElementStageItem,se as renderPagination,be as renderPreviewStageArea,ye as renderSelectTemplates,Q as renderSimpleTemplate,V as renderSimpleTemplateWithValidators,G as renderTopLevelStageItem,de as renderUndoRedo,le as setStageHeadline};
>>>>>>> 0e41451d ([BUGFIX] Do not render "new element" button on SummaryPage in form editor)
