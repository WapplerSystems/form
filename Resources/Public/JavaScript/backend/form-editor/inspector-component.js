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
import * as Helper from '@typo3/form/backend/form-editor/helper.js';
import { merge } from 'lodash-es';
import Icons from '@typo3/backend/icons.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import { openConditionBuilderModal } from './condition-builder.js';
import { MessageUtility } from '@typo3/backend/utility/message-utility.js';
import Sortable from 'sortablejs';
import { PropertyGridEditorUpdateEvent } from '@typo3/form/backend/form-editor/component/property-grid-editor.js';
const ckeditor = await import('@typo3/rte-ckeditor/ckeditor5.js').catch(() => null);
import '@typo3/form/backend/form-editor/component/date-editor.js';
import { DateEditorChangeEvent } from '@typo3/form/backend/form-editor/component/date-editor.js';
import { FormElementSelectorSelectedEvent } from '@typo3/form/backend/form-editor/component/form-element-selector.js';
/**
 * WapplerSystems fork: resolve a localized editor label delivered server-side via
 * TYPO3.settings.FormEditor.labels (Database.xlf / de.Database.xlf). Falls back to
 * the English literal when the label bag or key is unavailable.
 */
function lll(key, fallback) {
    const labels = TYPO3?.settings?.FormEditor?.labels;
    const value = labels && labels[key];
    return typeof value === 'string' && value.length > 0 ? value : fallback;
}
/**
 * WapplerSystems fork: the non-default site languages, delivered once per page
 * load via TYPO3.settings.FormEditor.availableLanguages (FormEditorController::
 * collectNonDefaultSiteLanguages()) instead of per-editor through the YAML/PHP
 * editor configuration - this is what lets "translations" be declared as a
 * plain, static (and therefore YAML-overridable) editor entry like any other,
 * with the one genuinely dynamic piece (which languages exist) resolved here
 * in JS instead of baked into the config.
 */
function getAvailableLanguages() {
    const languages = TYPO3?.settings?.FormEditor?.availableLanguages;
    return Array.isArray(languages) ? languages : [];
}
const defaultConfiguration = {
    domElementClassNames: {
        buttonFormElementRemove: 'formeditor-inspector-element-remove-button',
        collectionElement: 'formeditor-inspector-collection-element',
        finisherEditorPrefix: 't3-form-inspector-finishers-editor-',
        inspectorEditor: 'formeditor-inspector-element',
        inspectorInputGroup: 'input-group',
        sortable: 'sortable',
        validatorEditorPrefix: 'formeditor-inspector-validators-editor-'
    },
    domElementDataAttributeNames: {
        contentElementSelectorTarget: 'data-insert-target',
        finisher: 'data-finisher-identifier',
        validator: 'data-validator-identifier',
        randomId: 'data-random-id',
        randomIdTarget: 'data-random-id-attribute',
        randomIdIndex: 'data-random-id-number',
        maximumFileSize: 'data-maximumFileSize'
    },
    domElementDataAttributeValues: {
        collapse: 'actions-view-table-expand',
        editorControlsInputGroup: 'inspectorEditorControlsGroup',
        editorWrapper: 'editorWrapper',
        editorControlsWrapper: 'inspectorEditorControlsWrapper',
        formElementHeaderEditor: 'inspectorFormElementHeaderEditor',
        formElementSelectorControlsWrapper: 'inspectorEditorFormElementSelectorControlsWrapper',
        formElementSelectorSplitButtonContainer: 'inspectorEditorFormElementSelectorSplitButtonContainer',
        formElementSelectorSplitButtonListContainer: 'inspectorEditorFormElementSelectorSplitButtonListContainer',
        iconNotAvailable: 'actions-close',
        inspector: 'inspector',
        'Inspector-CheckboxEditor': 'Inspector-CheckboxEditor',
        'Inspector-CollectionElementHeaderEditor': 'Inspector-CollectionElementHeaderEditor',
        'Inspector-FinishersEditor': 'Inspector-FinishersEditor',
        'Inspector-FormElementHeaderEditor': 'Inspector-FormElementHeaderEditor',
        'Inspector-PropertyGridEditor': 'Inspector-PropertyGridEditor',
        'Inspector-RemoveElementEditor': 'Inspector-RemoveElementEditor',
        'Inspector-RequiredValidatorEditor': 'Inspector-RequiredValidatorEditor',
        'Inspector-SingleSelectEditor': 'Inspector-SingleSelectEditor',
        'Inspector-MultiSelectEditor': 'Inspector-MultiSelectEditor',
        'Inspector-GridColumnViewPortConfigurationEditor': 'Inspector-GridColumnViewPortConfigurationEditor',
        'Inspector-TextareaEditor': 'Inspector-TextareaEditor',
        'Inspector-TextEditor': 'Inspector-TextEditor',
        'Inspector-Typo3WinBrowserEditor': 'Inspector-Typo3WinBrowserEditor',
        'Inspector-ValidatorsEditor': 'Inspector-ValidatorsEditor',
        'Inspector-ValidationErrorMessageEditor': 'Inspector-ValidationErrorMessageEditor',
        'Inspector-DateEditor': 'Inspector-DateEditor',
        'Inspector-VariantsEditor': 'Inspector-VariantsEditor',
        'Inspector-EmailContentEditor': 'Inspector-EmailContentEditor',
        'Inspector-TranslationEditor': 'Inspector-TranslationEditor',
        'Inspector-TranslationOverviewEditor': 'Inspector-TranslationOverviewEditor',
        inspectorFinishers: 'inspectorFinishers',
        inspectorValidators: 'inspectorValidators',
        viewportButton: 'viewportButton'
    },
    domElementIdNames: {
        finisherPrefix: 't3-form-inspector-finishers-',
        validatorPrefix: 't3-form-inspector-validators-'
    },
    isSortable: true
};
let configuration = null;
let formEditorApp = null;
function getFormEditorApp() {
    return formEditorApp;
}
function getViewModel() {
    return getFormEditorApp().getViewModel();
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
/**
 * @publish view/inspector/editor/insert/perform
 */
function renderEditorDispatcher(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    switch (editorConfiguration.templateName) {
        case 'Inspector-FormElementHeaderEditor':
            renderFormElementHeaderEditor(editorConfiguration, editorHtml);
            break;
        case 'Inspector-CollectionElementHeaderEditor':
            renderCollectionElementHeaderEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-MaximumFileSizeEditor':
            renderFileMaxSizeEditor(editorConfiguration, editorHtml);
            break;
        case 'Inspector-TextEditor':
            renderTextEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-FinishersEditor':
            renderCollectionElementSelectionEditor('finishers', editorConfiguration, editorHtml);
            break;
        case 'Inspector-ValidatorsEditor':
            renderCollectionElementSelectionEditor('validators', editorConfiguration, editorHtml);
            break;
        case 'Inspector-ValidationErrorMessageEditor':
            renderValidationErrorMessageEditor(editorConfiguration, editorHtml);
            break;
        case 'Inspector-RemoveElementEditor':
            renderRemoveElementEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-RequiredValidatorEditor':
            renderRequiredValidatorEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-CheckboxEditor':
            renderCheckboxEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-CountrySelectEditor':
            renderCountrySelectEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-SingleSelectEditor':
            renderSingleSelectEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-MultiSelectEditor':
            renderMultiSelectEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-GridColumnViewPortConfigurationEditor':
            renderGridColumnViewPortConfigurationEditor(editorConfiguration, editorHtml);
            break;
        case 'Inspector-ViewPortColumnEditor':
            renderViewPortColumnEditor(editorConfiguration, editorHtml);
            break;
        case 'Inspector-PropertyGridEditor':
            renderPropertyGridEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-TextareaEditor':
            renderTextareaEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-Typo3WinBrowserEditor':
            renderTypo3WinBrowserEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-DateEditor':
            renderDateEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-VariantsEditor':
            renderVariantsEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-EmailContentEditor':
            renderEmailContentEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-TranslationEditor':
            renderTranslationEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName);
            break;
        case 'Inspector-TranslationOverviewEditor':
            renderTranslationOverviewEditor(editorConfiguration, editorHtml);
            break;
        default:
            break;
    }
    getPublisherSubscriber().publish('view/inspector/editor/insert/perform', [
        editorConfiguration, editorHtml, collectionElementIdentifier, collectionName
    ]);
}
/**
 * opens a popup window with the element browser
 */
function openTypo3WinBrowser(mode, fieldReference, allowedTypes) {
    const queryParams = new URLSearchParams({
        mode: mode,
        fieldReference: fieldReference,
        allowedTypes: allowedTypes,
    });
    Modal.advanced({
        type: Modal.types.iframe,
        content: TYPO3.settings.FormEditor.typo3WinBrowserUrl + '&' + queryParams.toString(),
        size: Modal.sizes.large
    });
}
let elementBrowserListenerRegistered = false;
/**
 * Listens on messages sent by ElementBrowser – registers only once
 */
function listenOnElementBrowser() {
    if (elementBrowserListenerRegistered) {
        return;
    }
    elementBrowserListenerRegistered = true;
    window.addEventListener('message', function (e) {
        if (!MessageUtility.verifyOrigin(e.origin)) {
            throw 'Denied message sent by ' + e.origin;
        }
        if (e.data.actionName === 'typo3:elementBrowser:elementAdded') {
            if (typeof e.data.fieldName === 'undefined') {
                throw 'fieldName not defined in message';
            }
            if (typeof e.data.value === 'undefined') {
                throw 'value not defined in message';
            }
            const result = e.data.value.split('_');
            const targetEl = document.querySelector(getHelper().getDomElementDataAttribute('contentElementSelectorTarget', 'bracesWithKeyValue', [e.data.fieldName]));
            if (targetEl) {
                targetEl.value = result.pop() ?? '';
                targetEl.dispatchEvent(new Event('paste'));
            }
        }
    });
}
function getCollectionElementClass(collectionName, collectionElementIdentifier) {
    if (collectionName === 'finishers') {
        return getHelper()
            .getDomElementClassName('finisherEditorPrefix') + collectionElementIdentifier;
    }
    else {
        return getHelper()
            .getDomElementClassName('validatorEditorPrefix') + collectionElementIdentifier;
    }
}
function getCollectionElementId(collectionName, collectionElementIdentifier, asSelector) {
    if (collectionName === 'finishers') {
        return getHelper()
            .getDomElementIdName('finisherPrefix', asSelector) + collectionElementIdentifier;
    }
    else {
        return getHelper()
            .getDomElementIdName('validatorPrefix', asSelector) + collectionElementIdentifier;
    }
}
function addSortableCollectionElementsEvents(sortableDomElement, collectionName) {
    sortableDomElement.classList.add('sortable');
    new Sortable(sortableDomElement, {
        draggable: getHelper().getDomElementClassName('collectionElement', true),
        filter: 'input,textarea,select',
        preventOnFilter: false,
        animation: 200,
        fallbackTolerance: 200,
        swapThreshold: 0.6,
        dragClass: 'formeditor-sortable-drag',
        ghostClass: 'formeditor-sortable-ghost',
        onEnd: function (e) {
            let dataAttributeName;
            if (collectionName === 'finishers') {
                dataAttributeName = getHelper().getDomElementDataAttribute('finisher');
            }
            else {
                dataAttributeName = getHelper().getDomElementDataAttribute('validator');
            }
            const movedCollectionElementIdentifier = e.item.getAttribute(dataAttributeName);
            const previousCollectionElementIdentifier = e.item
                .previousElementSibling?.closest(getHelper().getDomElementClassName('collectionElement', true))
                ?.getAttribute(dataAttributeName);
            const nextCollectionElementIdentifier = e.item
                .nextElementSibling?.closest(getHelper().getDomElementClassName('collectionElement', true))
                ?.getAttribute(dataAttributeName);
            getPublisherSubscriber().publish('view/inspector/collectionElements/dnd/update', [
                movedCollectionElementIdentifier,
                previousCollectionElementIdentifier,
                nextCollectionElementIdentifier,
                collectionName
            ]);
        }
    });
}
function getEditorWrapperDomElement(editorDomElement) {
    return (editorDomElement).querySelector(getHelper().getDomElementDataIdentifierSelector('editorWrapper'));
}
function getEditorControlsWrapperDomElement(editorDomElement) {
    return (editorDomElement).querySelector(getHelper().getDomElementDataIdentifierSelector('editorControlsWrapper'));
}
function validateCollectionElement(propertyPath, editorHtml) {
    let hasError, propertyPrefix, validationResults;
    validationResults = getFormEditorApp().validateCurrentlySelectedFormElementProperty(propertyPath);
    const controlsWrapper = getEditorControlsWrapperDomElement(editorHtml);
    const collectionElement = controlsWrapper?.closest(getHelper().getDomElementClassName('collectionElement', true)) ?? null;
    const validationErrorsElement = getHelper().getTemplatePropertyElement('validationErrors', editorHtml);
    const inputElement = getEditorControlsWrapperDomElement(editorHtml)?.querySelector('input, textarea, select, button') ?? null;
    if (validationResults.length > 0) {
        // Generate a unique ID for the error message to link via aria-describedby
        let errorId = validationErrorsElement?.id ?? '';
        if (!errorId) {
            errorId = 'validation-error-' + Math.random().toString(36).substring(2, 9);
            if (validationErrorsElement) {
                validationErrorsElement.id = errorId;
            }
        }
        if (validationErrorsElement) {
            validationErrorsElement.innerHTML =
                '<span class="text-danger">' +
                    '<typo3-backend-icon identifier="actions-exclamation-circle" size="small"></typo3-backend-icon> ' +
                    validationResults[0] +
                    '</span>';
            validationErrorsElement.setAttribute('role', 'alert');
        }
        if (inputElement) {
            inputElement.setAttribute('aria-invalid', 'true');
            inputElement.setAttribute('aria-describedby', errorId);
        }
        getViewModel().setElementValidationErrorClass(getEditorControlsWrapperDomElement(editorHtml), 'hasError');
    }
    else {
        if (validationErrorsElement) {
            validationErrorsElement.innerHTML = '';
            validationErrorsElement.removeAttribute('role');
        }
        // Remove aria attributes from input
        if (inputElement) {
            inputElement.removeAttribute('aria-invalid');
            inputElement.removeAttribute('aria-describedby');
        }
        getViewModel().removeElementValidationErrorClass(getEditorControlsWrapperDomElement(editorHtml), 'hasError');
    }
    validationResults = getFormEditorApp().validateFormElement(getCurrentlySelectedFormElement());
    propertyPrefix = propertyPath.split('.');
    propertyPrefix = propertyPrefix[0] + '.' + propertyPrefix[1];
    hasError = false;
    for (let i = 0, len = validationResults.length; i < len; ++i) {
        if (validationResults[i].propertyPath.indexOf(propertyPrefix, 0) === 0
            && validationResults[i].validationResults
            && validationResults[i].validationResults.length > 0) {
            hasError = true;
            break;
        }
    }
    if (hasError) {
        getViewModel().setElementValidationErrorClass(collectionElement);
    }
    else {
        getViewModel().removeElementValidationErrorClass(collectionElement);
    }
}
/**
 * @throws 1489932939
 * @throws 1489932940
 */
function getFirstAvailableValidationErrorMessage(errorCodes, propertyData) {
    assert(Array.isArray(errorCodes), 'Invalid configuration "errorCodes"', 1489932939);
    assert(Array.isArray(propertyData), 'Invalid configuration "propertyData"', 1489932940);
    for (let i = 0, len1 = errorCodes.length; i < len1; ++i) {
        for (let j = 0, len2 = propertyData.length; j < len2; ++j) {
            if (parseInt(errorCodes[i], 10) === parseInt(propertyData[j].code, 10)) {
                if (getUtility().isNonEmptyString(propertyData[j].message)) {
                    return propertyData[j].message;
                }
            }
        }
    }
    return null;
}
/**
 * @throws 1489932942
 */
function renewValidationErrorMessages(errorCodes, propertyData, value) {
    assert(Array.isArray(propertyData), 'Invalid configuration "propertyData"', 1489932942);
    if (!getUtility().isUndefinedOrNull(errorCodes)
        && Array.isArray(errorCodes)) {
        const errorCodeSubset = [];
        for (let i = 0, len1 = errorCodes.length; i < len1; ++i) {
            let errorCodeFound = false;
            for (let j = 0, len2 = propertyData.length; j < len2; ++j) {
                if (parseInt(errorCodes[i], 10) === parseInt(propertyData[j].code, 10)) {
                    errorCodeFound = true;
                    if (getUtility().isNonEmptyString(value)) {
                        // error code exists and should be updated because message is not empty
                        propertyData[j].message = value;
                    }
                    else {
                        // error code exists but should be removed because message is empty
                        propertyData.splice(j, 1);
                        --len2;
                    }
                }
            }
            if (!errorCodeFound) {
                // add new codes because message is not empty
                if (getUtility().isNonEmptyString(value)) {
                    errorCodeSubset.push({
                        code: errorCodes[i],
                        message: value
                    });
                }
            }
        }
        propertyData = propertyData.concat(errorCodeSubset);
    }
    return propertyData;
}
/**
 * @throws 1523904699
 */
function setRandomIds(html) {
    assert(typeof html === 'object' && html !== null && !Array.isArray(html), 'Invalid input "html"', 1523904699);
    const idReplacements = {};
    html.querySelectorAll(getHelper().getDomElementDataAttribute('randomId', 'bracesWithKey')).forEach(function (element) {
        const targetAttribute = element.getAttribute(getHelper().getDomElementDataAttribute('randomIdTarget'));
        const randomIdIndex = element.getAttribute(getHelper().getDomElementDataAttribute('randomIdIndex'));
        if (element.hasAttribute(targetAttribute)) {
            return;
        }
        if (!(randomIdIndex in idReplacements)) {
            idReplacements[randomIdIndex] = 'fe' + Math.floor(Math.random() * 42) + Date.now();
        }
        element.setAttribute(targetAttribute, idReplacements[randomIdIndex]);
    });
}
export function getInspectorDomElement() {
    return document.querySelector(getHelper().getDomElementDataIdentifierSelector('inspector'));
}
export function getFinishersContainerDomElement() {
    return getInspectorDomElement()?.querySelector(getHelper().getDomElementDataIdentifierSelector('inspectorFinishers')) ?? null;
}
export function getValidatorsContainerDomElement() {
    return getInspectorDomElement()?.querySelector(getHelper().getDomElementDataIdentifierSelector('inspectorValidators')) ?? null;
}
export function getCollectionElementDomElement(collectionName, collectionElementIdentifier) {
    if (collectionName === 'finishers') {
        return getFinishersContainerDomElement()?.querySelector(getHelper().getDomElementDataAttribute('finisher', 'bracesWithKeyValue', [collectionElementIdentifier])) ?? null;
    }
    else {
        return getValidatorsContainerDomElement()?.querySelector(getHelper().getDomElementDataAttribute('validator', 'bracesWithKeyValue', [collectionElementIdentifier])) ?? null;
    }
}
export function renderEditors(formElement, callback) {
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getCurrentlySelectedFormElement();
    }
    const inspectorEl = getInspectorDomElement();
    if (inspectorEl) {
        inspectorEl.replaceChildren();
    }
    const formElementTypeDefinition = getFormElementDefinition(formElement, undefined);
    if (!Array.isArray(formElementTypeDefinition.editors)) {
        return;
    }
    for (let i = 0, len = formElementTypeDefinition.editors.length; i < len; ++i) {
        const rawTemplate = getHelper().getTemplateElement(formElementTypeDefinition.editors[i].templateName);
        if (!rawTemplate) {
            continue;
        }
        const wrapper = document.createElement('div');
        wrapper.innerHTML = rawTemplate.innerHTML;
        const children = Array.from(wrapper.children);
        for (const child of children) {
            child.classList.add(getHelper().getDomElementClassName('inspectorEditor'));
            inspectorEl?.append(child);
        }
        const html = children[0];
        if (!html) {
            continue;
        }
        for (const child of children) {
            setRandomIds(child);
        }
        renderEditorDispatcher(formElementTypeDefinition.editors[i], html);
    }
    if (typeof callback === 'function') {
        callback();
    }
}
export function renderCollectionElementEditors(collectionName, collectionElementIdentifier) {
    let collapseWrapper, collapsePanel, collectionContainer;
    assert(getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1478354853);
    assert(getUtility().isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1478354854);
    const collectionElementConfiguration = getFormEditorApp().getPropertyCollectionElementConfiguration(collectionElementIdentifier, collectionName);
    if (!collectionElementConfiguration || !Array.isArray(collectionElementConfiguration.editors)) {
        return;
    }
    const collectionContainerElementWrapper = document.createElement('div');
    collectionContainerElementWrapper.classList.add(getHelper().getDomElementClassName('collectionElement'), 'panel', 'panel-default');
    if (collectionName === 'finishers') {
        collectionContainer = getFinishersContainerDomElement();
        collectionContainerElementWrapper.setAttribute(getHelper().getDomElementDataAttribute('finisher'), collectionElementIdentifier);
    }
    else {
        collectionContainer = getValidatorsContainerDomElement();
        collectionContainerElementWrapper.setAttribute(getHelper().getDomElementDataAttribute('validator'), collectionElementIdentifier);
    }
    collectionContainer?.append(collectionContainerElementWrapper);
    const collectionElementEditorsLength = collectionElementConfiguration.editors.length;
    if (collectionElementEditorsLength > 0
        && collectionElementConfiguration.editors[0].identifier === 'header') {
        collapsePanel = document.createElement('div');
        collapsePanel.classList.add('panel-body');
        collapseWrapper = document.createElement('div');
        collapseWrapper.classList.add('panel-collapse', 'collapse');
        collapseWrapper.id = getCollectionElementId(collectionName, collectionElementIdentifier);
        collapseWrapper.appendChild(collapsePanel);
    }
    for (let i = 0; i < collectionElementEditorsLength; ++i) {
        const rawTemplate = getHelper().getTemplateElement(collectionElementConfiguration.editors[i].templateName);
        if (!rawTemplate) {
            continue;
        }
        const wrapper = document.createElement('div');
        wrapper.innerHTML = rawTemplate.innerHTML;
        const html = wrapper.firstElementChild ?? wrapper;
        html.classList.add(getCollectionElementClass(collectionName, collectionElementConfiguration.editors[i].identifier), getHelper().getDomElementClassName('inspectorEditor'));
        if (i === 0 && collapseWrapper) {
            collectionContainerElementWrapper.append(html);
            collectionContainerElementWrapper.append(collapseWrapper);
        }
        else if (i === (collectionElementEditorsLength - 1)
            && collapseWrapper
            && collectionElementConfiguration.editors[i].identifier === 'removeButton') {
            collapsePanel.append(html);
        }
        else if (i > 0 && collapseWrapper) {
            collapsePanel.append(html);
        }
        else {
            collectionContainerElementWrapper.append(html);
        }
        setRandomIds(html);
        renderEditorDispatcher(collectionElementConfiguration.editors[i], html, collectionElementIdentifier, collectionName);
    }
    if ((collectionElementEditorsLength === 2
        && collectionElementConfiguration.editors[0].identifier === 'header'
        && collectionElementConfiguration.editors[1].identifier === 'removeButton') || (collectionElementEditorsLength === 1
        && collectionElementConfiguration.editors[0].identifier === 'header')) {
        collectionContainerElementWrapper.querySelector(getHelper().getDomElementDataIdentifierSelector('collapse'))?.remove();
    }
    if (configuration.isSortable && collectionContainer) {
        addSortableCollectionElementsEvents(collectionContainer, collectionName);
    }
}
export function renderCollectionElementSelectionEditor(collectionName, editorConfiguration, editorHtml) {
    let alreadySelectedCollectionElements, collectionContainer, removeSelectElement;
    assert(getUtility().isNonEmptyString(collectionName), 'Invalid configuration "collectionName"', 1478362968);
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475423098);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475423099);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475423100);
    assert(Array.isArray(editorConfiguration.selectOptions), 'Invalid configuration "selectOptions"', 1475423101);
    if (collectionName === 'finishers') {
        collectionContainer = getFinishersContainerDomElement();
        alreadySelectedCollectionElements = getRootFormElement().get(collectionName);
    }
    else {
        collectionContainer = getValidatorsContainerDomElement();
        alreadySelectedCollectionElements = getCurrentlySelectedFormElement().get(collectionName);
    }
    collectionContainer?.replaceChildren();
    const labelEl = getHelper().getTemplatePropertyElement('label', editorHtml);
    if (labelEl) {
        labelEl.append(document.createTextNode(editorConfiguration.label));
    }
    const selectElement = getHelper().getTemplatePropertyElement('selectOptions', editorHtml);
    const hasAlreadySelectedCollectionElements = (!getUtility().isUndefinedOrNull(alreadySelectedCollectionElements) &&
        alreadySelectedCollectionElements.length > 0);
    if (hasAlreadySelectedCollectionElements) {
        for (let i = 0, len = alreadySelectedCollectionElements.length; i < len; ++i) {
            getPublisherSubscriber().publish('view/inspector/collectionElement/existing/selected', [
                alreadySelectedCollectionElements[i].identifier,
                collectionName
            ]);
        }
    }
    removeSelectElement = true;
    for (let i = 0, len1 = editorConfiguration.selectOptions.length; i < len1; ++i) {
        let appendOption = true;
        if (!getUtility().isUndefinedOrNull(alreadySelectedCollectionElements)) {
            for (let j = 0, len2 = alreadySelectedCollectionElements.length; j < len2; ++j) {
                if (alreadySelectedCollectionElements[j].identifier === editorConfiguration.selectOptions[i].value) {
                    appendOption = false;
                    break;
                }
            }
        }
        if (appendOption) {
            selectElement?.append(new Option(editorConfiguration.selectOptions[i].label, editorConfiguration.selectOptions[i].value));
            if (editorConfiguration.selectOptions[i].value !== '') {
                removeSelectElement = false;
            }
        }
    }
    if (removeSelectElement) {
        const selectGroup = getHelper().getTemplatePropertyElement('select-group', editorHtml);
        selectGroup?.replaceChildren();
        selectGroup?.remove();
        const labelNoSelect = getHelper().getTemplatePropertyElement('label-no-select', editorHtml);
        if (hasAlreadySelectedCollectionElements) {
            if (labelNoSelect) {
                labelNoSelect.textContent = editorConfiguration.label;
            }
        }
        else {
            labelNoSelect?.remove();
        }
        return;
    }
    getHelper().getTemplatePropertyElement('label-no-select', editorHtml)?.remove();
    selectElement?.addEventListener('change', function () {
        const value = this.value;
        if (value !== '') {
            this.querySelector(`option[value="${value}"]`)?.remove();
            getFormEditorApp().getPublisherSubscriber().publish('view/inspector/collectionElement/new/selected', [value, collectionName]);
        }
    });
}
export function renderFormElementHeaderEditor(editorConfiguration, editorHtml) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475421525);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475421526);
    Icons.getIcon(getFormElementDefinition(getCurrentlySelectedFormElement(), 'iconIdentifier'), Icons.sizes.small, null, Icons.states.default).then(function (icon) {
        const headerLabel = getHelper().getTemplatePropertyElement('header-label', editorHtml);
        if (headerLabel) {
            const tmp = document.createElement('div');
            tmp.innerHTML = icon;
            const iconEl = tmp.firstElementChild;
            if (iconEl) {
                iconEl.classList.add(getHelper().getDomElementClassName('icon'));
                headerLabel.append(iconEl);
            }
            headerLabel.append(buildTitleByFormElement());
            const code = document.createElement('code');
            code.textContent = getCurrentlySelectedFormElement().get('identifier');
            headerLabel.append(code);
        }
    });
}
export function renderCollectionElementHeaderEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475421258);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475421257);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475421259);
    const setData = function (icon) {
        const iconPlaceholder = getHelper().getTemplatePropertyElement('panel-icon', editorHtml);
        if (icon) {
            const tmp = document.createElement('div');
            tmp.innerHTML = icon;
            iconPlaceholder?.replaceWith(tmp.firstElementChild ?? tmp);
        }
        else {
            iconPlaceholder?.remove();
        }
        const collectionConfig = getFormEditorApp().getPropertyCollectionElementConfiguration(collectionElementIdentifier, collectionName);
        const editors = collectionConfig?.editors;
        if (editors && !((editors.length === 2 && editors[0].identifier === 'header' && editors[1].identifier === 'removeButton') ||
            (editors.length === 1 && editors[0].identifier === 'header'))) {
            const button = document.createElement('button');
            button.classList.add('panel-button', 'collapsed');
            button.setAttribute('type', 'button');
            button.setAttribute('data-bs-toggle', 'collapse');
            button.setAttribute('data-bs-target', getCollectionElementId(collectionName, collectionElementIdentifier, true));
            button.setAttribute('aria-expaned', 'false');
            button.setAttribute('aria-controls', getCollectionElementId(collectionName, collectionElementIdentifier));
            const caret = document.createElement('span');
            caret.classList.add('caret');
            const panelHeadingRow = getHelper().getTemplatePropertyElement('panel-heading-row', editorHtml);
            panelHeadingRow?.querySelector('.panel-title')?.before(caret);
            // wrapInner equivalent: wrap all children of panelHeadingRow in button
            if (panelHeadingRow) {
                while (panelHeadingRow.firstChild) {
                    button.appendChild(panelHeadingRow.firstChild);
                }
                panelHeadingRow.appendChild(button);
            }
        }
        // Move delete button – search within editorHtml's parent (collectionContainerElementWrapper)
        const collectionContainerEl = editorHtml.closest(getHelper().getDomElementClassName('collectionElement', true));
        const removeButtonElement = collectionContainerEl?.querySelector('.formeditor-inspector-element-remove-button');
        if (removeButtonElement) {
            const removeButton = removeButtonElement.querySelector('button');
            if (removeButton) {
                removeButton.classList.add('btn-sm');
                removeButton.querySelector('.btn-label')?.classList.add('visually-hidden');
                const panelActions = document.createElement('div');
                panelActions.classList.add('panel-actions');
                panelActions.append(removeButton);
                getHelper().getTemplatePropertyElement('panel-heading-row', editorHtml)?.append(panelActions);
            }
        }
        removeButtonElement?.remove();
    };
    const collectionElementConfiguration = getFormEditorApp().getFormEditorDefinition(collectionName, collectionElementIdentifier);
    if ('iconIdentifier' in collectionElementConfiguration) {
        Icons.getIcon(collectionElementConfiguration.iconIdentifier, Icons.sizes.small, null, Icons.states.default).then(function (icon) {
            setData(icon);
        });
    }
    else {
        setData();
    }
    if (editorConfiguration.label) {
        const panelTitle = getHelper().getTemplatePropertyElement('panel-title', editorHtml);
        if (panelTitle) {
            panelTitle.removeAttribute('data-template-property');
            panelTitle.append(document.createTextNode(editorConfiguration.label));
        }
    }
}
export function renderFileMaxSizeEditor(editorConfiguration, editorHtml) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475421258);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475421257);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475421259);
    if (editorConfiguration.label) {
        const element = getHelper().getTemplatePropertyElement('label', editorHtml);
        const maximumFileSize = element?.getAttribute(getHelper().getDomElementDataAttribute('maximumFileSize'));
        element?.append(document.createTextNode(editorConfiguration.label.replace('{0}', maximumFileSize ?? '')));
    }
}
export function renderTextEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475421053);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475421054);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475421055);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1475421056);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    if (getUtility().isNonEmptyString(editorConfiguration.placeholder)) {
        getHelper().getTemplatePropertyElement('propertyPath', editorHtml)
            ?.setAttribute('placeholder', editorConfiguration.placeholder);
    }
    const propertyPath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    const propertyData = getCurrentlySelectedFormElement().get(propertyPath);
    validateCollectionElement(propertyPath, editorHtml);
    const inputEl = getHelper().getTemplatePropertyElement('propertyPath', editorHtml);
    if (inputEl) {
        inputEl.value = propertyData ?? '';
    }
    if (!getUtility().isUndefinedOrNull(editorConfiguration.additionalElementPropertyPaths)
        && Array.isArray(editorConfiguration.additionalElementPropertyPaths)) {
        for (let i = 0, len = editorConfiguration.additionalElementPropertyPaths.length; i < len; ++i) {
            getCurrentlySelectedFormElement().set(editorConfiguration.additionalElementPropertyPaths[i], propertyData);
        }
    }
    renderFormElementSelectorEditorAddition(editorConfiguration, editorHtml, propertyPath);
    inputEl?.addEventListener('keyup', handleTextInput);
    inputEl?.addEventListener('paste', handleTextInput);
    function handleTextInput() {
        if (!!editorConfiguration.doNotSetIfPropertyValueIsEmpty
            && !getUtility().isNonEmptyString(this.value)) {
            getCurrentlySelectedFormElement().unset(propertyPath);
        }
        else {
            getCurrentlySelectedFormElement().set(propertyPath, this.value);
        }
        validateCollectionElement(propertyPath, editorHtml);
        if (!getUtility().isUndefinedOrNull(editorConfiguration.additionalElementPropertyPaths)
            && Array.isArray(editorConfiguration.additionalElementPropertyPaths)) {
            for (let i = 0, len = editorConfiguration.additionalElementPropertyPaths.length; i < len; ++i) {
                if (!!editorConfiguration.doNotSetIfPropertyValueIsEmpty
                    && !getUtility().isNonEmptyString(this.value)) {
                    getCurrentlySelectedFormElement().unset(editorConfiguration.additionalElementPropertyPaths[i]);
                }
                else {
                    getCurrentlySelectedFormElement().set(editorConfiguration.additionalElementPropertyPaths[i], this.value);
                }
            }
        }
    }
}
export function renderValidationErrorMessageEditor(editorConfiguration, editorHtml) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1489874121);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1489874122);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1489874123);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1489874124);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    const propertyPath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath);
    let propertyData = getCurrentlySelectedFormElement().get(propertyPath);
    if (!getUtility().isUndefinedOrNull(propertyData) && Array.isArray(propertyData)) {
        const validationErrorMessage = getFirstAvailableValidationErrorMessage(editorConfiguration.errorCodes, propertyData);
        const inputEl = getHelper().getTemplatePropertyElement('propertyPath', editorHtml);
        if (!getUtility().isUndefinedOrNull(validationErrorMessage) && inputEl) {
            inputEl.value = validationErrorMessage;
        }
    }
    const inputEl = getHelper().getTemplatePropertyElement('propertyPath', editorHtml);
    inputEl?.addEventListener('keyup', handleInput);
    inputEl?.addEventListener('paste', handleInput);
    function handleInput() {
        propertyData = getCurrentlySelectedFormElement().get(propertyPath);
        if (getUtility().isUndefinedOrNull(propertyData)) {
            propertyData = [];
        }
        getCurrentlySelectedFormElement().set(propertyPath, renewValidationErrorMessages(editorConfiguration.errorCodes, propertyData, this.value));
    }
}
export function renderCountrySelectEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1674826430);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1674826431);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1674826432);
    const propertyPath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    const selectElement = getHelper().getTemplatePropertyElement('selectOptions', editorHtml);
    const propertyData = getCurrentlySelectedFormElement().get(propertyPath) || {};
    validateCollectionElement(propertyPath, editorHtml);
    const options = Array.from(selectElement?.querySelectorAll('option') ?? []);
    selectElement?.replaceChildren();
    for (let i = 0, len = options.length; i < len; ++i) {
        let selected = false;
        for (const propertyDataKey of Object.keys(propertyData)) {
            if (options[i].value === propertyData[propertyDataKey]) {
                selected = true;
                break;
            }
        }
        const option = new Option(options[i].text, i.toString(), false, selected);
        option._dataValue = options[i].value;
        selectElement?.append(option);
    }
    selectElement?.addEventListener('change', function () {
        const selectValues = [];
        this.querySelectorAll('option:checked').forEach(function (opt) {
            selectValues.push(opt._dataValue);
        });
        getCurrentlySelectedFormElement().set(propertyPath, selectValues);
        validateCollectionElement(propertyPath, editorHtml);
    });
}
export function renderSingleSelectEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475421048);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475421049);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475421050);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1475421051);
    assert(Array.isArray(editorConfiguration.selectOptions), 'Invalid configuration "selectOptions"', 1475421052);
    const propertyPath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    const selectElement = getHelper().getTemplatePropertyElement('selectOptions', editorHtml);
    const propertyData = getCurrentlySelectedFormElement().get(propertyPath);
    validateCollectionElement(propertyPath, editorHtml);
    for (let i = 0, len = editorConfiguration.selectOptions.length; i < len; ++i) {
        const selected = editorConfiguration.selectOptions[i].value === propertyData;
        const option = new Option(editorConfiguration.selectOptions[i].label, i.toString(), false, selected);
        option._dataValue = editorConfiguration.selectOptions[i].value;
        selectElement?.append(option);
    }
    selectElement?.addEventListener('change', function () {
        const selectedOpt = this.querySelector('option:checked');
        getCurrentlySelectedFormElement().set(propertyPath, selectedOpt?._dataValue);
        validateCollectionElement(propertyPath, editorHtml);
    });
}
export function renderMultiSelectEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1485712399);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1485712400);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1485712401);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1485712402);
    assert(Array.isArray(editorConfiguration.selectOptions), 'Invalid configuration "selectOptions"', 1485712403);
    const propertyPath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    const selectElement = getHelper().getTemplatePropertyElement('selectOptions', editorHtml);
    const propertyData = getCurrentlySelectedFormElement().get(propertyPath) || {};
    validateCollectionElement(propertyPath, editorHtml);
    for (let i = 0, len1 = editorConfiguration.selectOptions.length; i < len1; ++i) {
        let selected = false;
        for (const propertyDataKey of Object.keys(propertyData)) {
            if (editorConfiguration.selectOptions[i].value === propertyData[propertyDataKey]) {
                selected = true;
                break;
            }
        }
        const option = new Option(editorConfiguration.selectOptions[i].label, i.toString(), false, selected);
        option._dataValue = editorConfiguration.selectOptions[i].value;
        selectElement?.append(option);
    }
    selectElement?.addEventListener('change', function () {
        const selectValues = [];
        this.querySelectorAll('option:checked').forEach(function (opt) {
            selectValues.push(opt._dataValue);
        });
        getCurrentlySelectedFormElement().set(propertyPath, selectValues);
        validateCollectionElement(propertyPath, editorHtml);
    });
}
export function renderGridColumnViewPortConfigurationEditor(editorConfiguration, editorHtml) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1489528242);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1489528243);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1489528244);
    assert(Array.isArray(editorConfiguration.configurationOptions.viewPorts), 'Invalid configurationOptions "viewPorts"', 1489528245);
    assert(!getUtility().isUndefinedOrNull(editorConfiguration.configurationOptions.numbersOfColumnsToUse.label), 'Invalid configurationOptions "numbersOfColumnsToUse"', 1489528246);
    assert(!getUtility().isUndefinedOrNull(editorConfiguration.configurationOptions.numbersOfColumnsToUse.propertyPath), 'Invalid configuration "selectOptions"', 1489528247);
    if (!getFormElementDefinition(getCurrentlySelectedFormElement().get('__parentRenderable'), '_isGridRowFormElement')) {
        editorHtml.remove();
        return;
    }
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    const viewportButtonSel = getHelper().getDomElementDataIdentifierSelector('viewportButton');
    const viewportButtonTemplate = editorHtml.querySelector(viewportButtonSel)?.cloneNode(true);
    editorHtml.querySelectorAll(viewportButtonSel).forEach(el => el.remove());
    const numbersOfColumnsTemplate = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.cloneNode(true);
    getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.remove();
    const editorControlsWrapper = getEditorControlsWrapperDomElement(editorHtml);
    const initNumbersOfColumnsField = function (element) {
        getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.replaceChildren();
        getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.remove();
        const numbersOfColumnsTemplateClone = numbersOfColumnsTemplate?.cloneNode(true);
        getEditorWrapperDomElement(editorHtml)?.after(numbersOfColumnsTemplateClone);
        numbersOfColumnsTemplateClone?.querySelector('input')?.focus();
        const labelEl = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse-label', numbersOfColumnsTemplateClone);
        if (labelEl) {
            labelEl.append(document.createTextNode(editorConfiguration.configurationOptions.numbersOfColumnsToUse.label
                .replace('{@viewPortLabel}', element.dataset.viewPortLabel ?? '')));
        }
        const descEl = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse-description', numbersOfColumnsTemplateClone);
        if (descEl) {
            descEl.append(document.createTextNode(editorConfiguration.configurationOptions.numbersOfColumnsToUse.description));
        }
        const propertyPath = editorConfiguration.configurationOptions.numbersOfColumnsToUse.propertyPath
            .replace('{@viewPortIdentifier}', element.dataset.viewPortIdentifier ?? '');
        const inputEl = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse-propertyPath', numbersOfColumnsTemplateClone);
        if (inputEl) {
            inputEl.value = getCurrentlySelectedFormElement().get(propertyPath) ?? '';
            inputEl.addEventListener('keyup', handleInput);
            inputEl.addEventListener('paste', handleInput);
            inputEl.addEventListener('change', handleInput);
            function handleInput() {
                if (this.value === '' || isNaN(Number(this.value))) {
                    this.value = '';
                }
                getCurrentlySelectedFormElement().set(propertyPath, this.value);
            }
        }
    };
    for (let i = 0, len = editorConfiguration.configurationOptions.viewPorts.length; i < len; ++i) {
        const viewPortIdentifier = editorConfiguration.configurationOptions.viewPorts[i].viewPortIdentifier;
        const viewPortLabel = editorConfiguration.configurationOptions.viewPorts[i].label;
        const viewportButtonTemplateClone = viewportButtonTemplate?.cloneNode(true);
        if (!viewportButtonTemplateClone) {
            continue;
        }
        viewportButtonTemplateClone.textContent = viewPortIdentifier;
        viewportButtonTemplateClone.dataset.viewPortIdentifier = viewPortIdentifier;
        viewportButtonTemplateClone.dataset.viewPortLabel = viewPortLabel;
        viewportButtonTemplateClone.setAttribute('title', viewPortLabel);
        editorControlsWrapper?.append(viewportButtonTemplateClone);
        if (i === (len - 1)) {
            initNumbersOfColumnsField(viewportButtonTemplateClone);
            viewportButtonTemplateClone.classList.add(getHelper().getDomElementClassName('active'));
        }
    }
    editorControlsWrapper?.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', function () {
            editorControlsWrapper.querySelectorAll('button').forEach(b => b.classList.remove(getHelper().getDomElementClassName('active')));
            this.classList.add(getHelper().getDomElementClassName('active'));
            initNumbersOfColumnsField(this);
        });
    });
}
/**
 * WapplerSystems fork: same viewport-button + number-input UI as
 * renderGridColumnViewPortConfigurationEditor() above (identical markup,
 * Inspector/GridColumnViewPortConfigurationEditor.fluid.html is reused for
 * both templateNames), but without that function's "only show inside a
 * GridRow" guard - which crashes with "Cannot read properties of null
 * (reading 'get')" for any element without a parent (e.g. the Form root),
 * since getCurrentlySelectedFormElement().get('__parentRenderable') is null
 * there and findFormElement(null) treats null as an object (typeof null ===
 * 'object' in JS).
 *
 * Use this (Inspector-ViewPortColumnEditor) for any per-breakpoint numeric
 * setting that is NOT about GridRow sibling column auto-division - e.g. the
 * Form root's "layoutLabelColumns" editor.
 */
export function renderViewPortColumnEditor(editorConfiguration, editorHtml) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1755400001);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1755400002);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1755400003);
    assert(Array.isArray(editorConfiguration.configurationOptions.viewPorts), 'Invalid configurationOptions "viewPorts"', 1755400004);
    assert(!getUtility().isUndefinedOrNull(editorConfiguration.configurationOptions.numbersOfColumnsToUse.label), 'Invalid configurationOptions "numbersOfColumnsToUse"', 1755400005);
    assert(!getUtility().isUndefinedOrNull(editorConfiguration.configurationOptions.numbersOfColumnsToUse.propertyPath), 'Invalid configuration "selectOptions"', 1755400006);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    const viewportButtonSel = getHelper().getDomElementDataIdentifierSelector('viewportButton');
    const viewportButtonTemplate = editorHtml.querySelector(viewportButtonSel)?.cloneNode(true);
    editorHtml.querySelectorAll(viewportButtonSel).forEach(el => el.remove());
    const numbersOfColumnsTemplate = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.cloneNode(true);
    getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.remove();
    const editorControlsWrapper = getEditorControlsWrapperDomElement(editorHtml);
    const initNumbersOfColumnsField = function (element) {
        getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.replaceChildren();
        getHelper().getTemplatePropertyElement('numbersOfColumnsToUse', editorHtml)?.remove();
        const numbersOfColumnsTemplateClone = numbersOfColumnsTemplate?.cloneNode(true);
        getEditorWrapperDomElement(editorHtml)?.after(numbersOfColumnsTemplateClone);
        numbersOfColumnsTemplateClone?.querySelector('input')?.focus();
        const labelEl = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse-label', numbersOfColumnsTemplateClone);
        if (labelEl) {
            labelEl.append(document.createTextNode(editorConfiguration.configurationOptions.numbersOfColumnsToUse.label
                .replace('{@viewPortLabel}', element.dataset.viewPortLabel ?? '')));
        }
        const descEl = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse-description', numbersOfColumnsTemplateClone);
        if (descEl) {
            descEl.append(document.createTextNode(editorConfiguration.configurationOptions.numbersOfColumnsToUse.description));
        }
        const propertyPath = editorConfiguration.configurationOptions.numbersOfColumnsToUse.propertyPath
            .replace('{@viewPortIdentifier}', element.dataset.viewPortIdentifier ?? '');
        const inputEl = getHelper().getTemplatePropertyElement('numbersOfColumnsToUse-propertyPath', numbersOfColumnsTemplateClone);
        if (inputEl) {
            inputEl.value = getCurrentlySelectedFormElement().get(propertyPath) ?? '';
            inputEl.addEventListener('keyup', handleInput);
            inputEl.addEventListener('paste', handleInput);
            inputEl.addEventListener('change', handleInput);
            function handleInput() {
                if (this.value === '' || isNaN(Number(this.value))) {
                    this.value = '';
                }
                getCurrentlySelectedFormElement().set(propertyPath, this.value);
            }
        }
    };
    for (let i = 0, len = editorConfiguration.configurationOptions.viewPorts.length; i < len; ++i) {
        const viewPortIdentifier = editorConfiguration.configurationOptions.viewPorts[i].viewPortIdentifier;
        const viewPortLabel = editorConfiguration.configurationOptions.viewPorts[i].label;
        const viewportButtonTemplateClone = viewportButtonTemplate?.cloneNode(true);
        if (!viewportButtonTemplateClone) {
            continue;
        }
        viewportButtonTemplateClone.textContent = viewPortIdentifier;
        viewportButtonTemplateClone.dataset.viewPortIdentifier = viewPortIdentifier;
        viewportButtonTemplateClone.dataset.viewPortLabel = viewPortLabel;
        viewportButtonTemplateClone.setAttribute('title', viewPortLabel);
        editorControlsWrapper?.append(viewportButtonTemplateClone);
        if (i === (len - 1)) {
            initNumbersOfColumnsField(viewportButtonTemplateClone);
            viewportButtonTemplateClone.classList.add(getHelper().getDomElementClassName('active'));
        }
    }
    editorControlsWrapper?.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', function () {
            editorControlsWrapper.querySelectorAll('button').forEach(b => b.classList.remove(getHelper().getDomElementClassName('active')));
            this.classList.add(getHelper().getDomElementClassName('active'));
            initNumbersOfColumnsField(this);
        });
    });
}
export function renderPropertyGridEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475419226);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475419227);
    assert(typeof editorConfiguration.enableAddRow === 'boolean', 'Invalid configuration "enableAddRow"', 1475419228);
    assert(typeof editorConfiguration.enableDeleteRow === 'boolean', 'Invalid configuration "enableDeleteRow"', 1475419230);
    assert(typeof editorConfiguration.isSortable === 'boolean', 'Invalid configuration "isSortable"', 1475419229);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1475419231);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475419232);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    const propertyPathPrefix = (() => {
        const path = getFormEditorApp().buildPropertyPath(undefined, collectionElementIdentifier, collectionName, undefined, true);
        return getUtility().isNonEmptyString(path) ? path + '.' : path;
    })();
    const multiSelection = getUtility().isUndefinedOrNull(editorConfiguration.multiSelection)
        ? false
        : !!editorConfiguration.multiSelection;
    const enableSelection = getUtility().isNonEmptyArray(editorConfiguration.gridColumns)
        ? editorConfiguration.gridColumns.some(item => item.name === 'selected')
        : true;
    const defaultValue = (() => {
        const val = getCurrentlySelectedFormElement().get(propertyPathPrefix + 'defaultValue');
        return !getUtility().isUndefinedOrNull(val)
            ? multiSelection ? val : { '0': val }
            : {};
    })();
    const propertyData = (() => {
        const formElement = getCurrentlySelectedFormElement();
        const fullPropertyPath = propertyPathPrefix + editorConfiguration.propertyPath;
        const rawData = formElement.get(fullPropertyPath) || {};
        let propertyEntries;
        if (Array.isArray(rawData)) {
            // Handle array of objects: [{_label, _value}] or raw values
            propertyEntries = rawData.map((item, index) => ({
                id: 'fe' + Math.floor(Math.random() * 42) + Date.now(),
                label: getUtility().isUndefinedOrNull(item._label) ? item : item._label,
                value: getUtility().isUndefinedOrNull(item._label) ? index : item._value,
                selected: false,
            }));
        }
        else if (typeof rawData === 'object') {
            // Handle object case: { value: label }
            propertyEntries = Object.entries(rawData).map(([value, label]) => ({
                id: 'fe' + Math.floor(Math.random() * 42) + Date.now(),
                label,
                value,
                selected: false,
            }));
        }
        return propertyEntries.map(entry => {
            for (const defaultValueKey of Object.keys(defaultValue)) {
                if (defaultValue[defaultValueKey] === entry.value) {
                    entry.selected = true;
                    break;
                }
            }
            return entry;
        });
    })();
    const useLabelAsFallbackValue = getUtility().isUndefinedOrNull(editorConfiguration.useLabelAsFallbackValue)
        ? true
        : editorConfiguration.useLabelAsFallbackValue;
    const propertyGridEditor = editorHtml.querySelector('typo3-form-property-grid-editor');
    propertyGridEditor.enableAddRow = editorConfiguration.enableAddRow;
    propertyGridEditor.enableSelection = enableSelection;
    propertyGridEditor.enableMultiSelection = multiSelection;
    propertyGridEditor.enableSorting = editorConfiguration.isSortable ?? false;
    propertyGridEditor.enableDeleteRow = editorConfiguration.enableDeleteRow ?? false;
    propertyGridEditor.enableLabelAsFallbackValue = useLabelAsFallbackValue;
    propertyGridEditor.entries = propertyData;
    if (getUtility().isNonEmptyArray(editorConfiguration.gridColumns)) {
        editorConfiguration.gridColumns.forEach(gridColumnConfig => {
            if (gridColumnConfig.name === 'label') {
                propertyGridEditor.labelLabel = gridColumnConfig.title;
                propertyGridEditor.enableLabelFormElementSelectionButton = gridColumnConfig.enableFormelementSelectionButton;
            }
            if (gridColumnConfig.name === 'value') {
                propertyGridEditor.labelValue = gridColumnConfig.title;
                propertyGridEditor.enableValueFormElementSelectionButton = gridColumnConfig.enableFormelementSelectionButton;
            }
            if (gridColumnConfig.name === 'selected') {
                propertyGridEditor.labelSelected = gridColumnConfig.title;
            }
        });
    }
    if (propertyGridEditor.enableLabelFormElementSelectionButton || propertyGridEditor.enableValueFormElementSelectionButton) {
        propertyGridEditor.formElements = getFormElementSelectorEntries();
    }
    propertyGridEditor.addEventListener(PropertyGridEditorUpdateEvent.eventName, (event) => {
        const entries = event.data;
        const defaultValues = [];
        const newData = [];
        for (const entry of entries) {
            const entryLabel = entry.label;
            const entryValue = entry.value === ''
                ? entry.label
                : getUtility().canBeInterpretedAsInteger(entry.value)
                    ? parseInt(entry.value, 10)
                    : entry.value;
            if (entry.selected) {
                defaultValues.push(entryValue);
            }
            newData.push({
                _label: entryLabel,
                _value: entryValue
            });
        }
        if (multiSelection) {
            getCurrentlySelectedFormElement().set(propertyPathPrefix + 'defaultValue', defaultValues);
        }
        else {
            getCurrentlySelectedFormElement().set(propertyPathPrefix + 'defaultValue', defaultValues[0] ?? '', true);
        }
        getCurrentlySelectedFormElement().set(propertyPathPrefix + editorConfiguration.propertyPath, newData);
        validateCollectionElement(propertyPathPrefix + editorConfiguration.propertyPath, editorHtml);
    });
    validateCollectionElement(propertyPathPrefix + editorConfiguration.propertyPath, editorHtml);
}
/**
 * @publish view/inspector/collectionElement/new/selected
 * @publish view/inspector/removeCollectionElement/perform
 * @throws 1475417093
 * @throws 1475417094
 * @throws 1475417095
 * @throws 1475417096
 */
export function renderRequiredValidatorEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475417093);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475417094);
    assert(getUtility().isNonEmptyString(editorConfiguration.validatorIdentifier), 'Invalid configuration "validatorIdentifier"', 1475417095);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475417096);
    const validatorIdentifier = editorConfiguration.validatorIdentifier;
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    let propertyValue;
    let propertyPath;
    let propertyData;
    if (getUtility().isNonEmptyString(editorConfiguration.propertyPath)) {
        propertyPath = getFormEditorApp()
            .buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    }
    if (getUtility().isNonEmptyString(editorConfiguration.propertyValue)) {
        propertyValue = editorConfiguration.propertyValue;
    }
    else {
        propertyValue = '';
    }
    const validationErrorMessagePropertyPath = getFormEditorApp()
        .buildPropertyPath(editorConfiguration.configurationOptions.validationErrorMessage.propertyPath);
    const rawValidationErrorMessageTemplate = getHelper().getTemplatePropertyElement('validationErrorMessage', editorHtml);
    const validationErrorMessageTemplate = rawValidationErrorMessageTemplate?.cloneNode(true);
    rawValidationErrorMessageTemplate?.remove();
    const showValidationErrorMessage = function () {
        const validationErrorMessageTemplateClone = validationErrorMessageTemplate?.cloneNode(true);
        getEditorWrapperDomElement(editorHtml)?.after(validationErrorMessageTemplateClone);
        getHelper().getTemplatePropertyElement('validationErrorMessage-label', validationErrorMessageTemplateClone)
            ?.append(document.createTextNode(editorConfiguration.configurationOptions.validationErrorMessage.label));
        getHelper().getTemplatePropertyElement('validationErrorMessage-description', validationErrorMessageTemplateClone)
            ?.append(document.createTextNode(editorConfiguration.configurationOptions.validationErrorMessage.description));
        propertyData = getCurrentlySelectedFormElement().get(validationErrorMessagePropertyPath);
        if (getUtility().isUndefinedOrNull(propertyData)) {
            propertyData = [];
        }
        const validationErrorMessage = getFirstAvailableValidationErrorMessage(editorConfiguration.configurationOptions.validationErrorMessage.errorCodes, propertyData);
        const valInputEl = getHelper().getTemplatePropertyElement('validationErrorMessage-propertyPath', validationErrorMessageTemplateClone);
        if (!getUtility().isUndefinedOrNull(validationErrorMessage) && valInputEl) {
            valInputEl.value = validationErrorMessage;
        }
        valInputEl?.addEventListener('keyup', handleValInput);
        valInputEl?.addEventListener('paste', handleValInput);
        function handleValInput() {
            let propertyData = getCurrentlySelectedFormElement().get(validationErrorMessagePropertyPath);
            if (getUtility().isUndefinedOrNull(propertyData)) {
                propertyData = [];
            }
            getCurrentlySelectedFormElement().set(validationErrorMessagePropertyPath, renewValidationErrorMessages(editorConfiguration.configurationOptions.validationErrorMessage.errorCodes, propertyData, this.value));
        }
    };
    const checkboxEl = editorHtml.querySelector('input[type="checkbox"]');
    if (-1 !== getFormEditorApp().getIndexFromPropertyCollectionElement(validatorIdentifier, 'validators')) {
        if (checkboxEl) {
            checkboxEl.checked = true;
        }
        showValidationErrorMessage();
    }
    checkboxEl?.addEventListener('change', function () {
        getHelper().getTemplatePropertyElement('validationErrorMessage', editorHtml)?.replaceChildren();
        getHelper().getTemplatePropertyElement('validationErrorMessage', editorHtml)?.remove();
        if (this.checked) {
            showValidationErrorMessage();
            getPublisherSubscriber().publish('view/inspector/collectionElement/new/selected', [validatorIdentifier, 'validators']);
            if (getUtility().isNonEmptyString(propertyPath)) {
                getCurrentlySelectedFormElement().set(propertyPath, propertyValue);
            }
        }
        else {
            if (getUtility().isNonEmptyString(propertyPath)) {
                getCurrentlySelectedFormElement().unset(propertyPath);
            }
            getPublisherSubscriber().publish('view/inspector/removeCollectionElement/perform', [validatorIdentifier, 'validators']);
            propertyData = getCurrentlySelectedFormElement().get(validationErrorMessagePropertyPath);
            if (getUtility().isUndefinedOrNull(propertyData)) {
                propertyData = [];
            }
            getCurrentlySelectedFormElement().set(validationErrorMessagePropertyPath, renewValidationErrorMessages(editorConfiguration.configurationOptions.validationErrorMessage.errorCodes, propertyData, ''));
        }
    });
}
export function renderCheckboxEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1476218671);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1476218672);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1476218673);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1476218674);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    const propertyPath = getFormEditorApp()
        .buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    const propertyData = getCurrentlySelectedFormElement().get(propertyPath);
    // For renderingOptions.enabled, undefined means "use default" which is true
    const useDefaultEnabled = editorConfiguration.propertyPath === 'renderingOptions.enabled'
        && getUtility().isUndefinedOrNull(propertyData);
    const checkboxEl = editorHtml.querySelector('input[type="checkbox"]');
    if (useDefaultEnabled
        || (typeof propertyData === 'boolean' && propertyData)
        || propertyData === 'true'
        || propertyData === 1
        || propertyData === '1') {
        if (checkboxEl) {
            checkboxEl.checked = true;
        }
    }
    checkboxEl?.addEventListener('change', function () {
        getCurrentlySelectedFormElement().set(propertyPath, this.checked);
    });
}
/**
 * @throws 1475412567
 * @throws 1475412568
 * @throws 1475416098
 * @throws 1475416099
 */
export function renderTextareaEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475412567);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475412568);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1475416098);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1475416099);
    const propertyPath = getFormEditorApp()
        .buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    renderDescription(editorConfiguration, editorHtml);
    const propertyData = getCurrentlySelectedFormElement().get(propertyPath);
    const textarea = editorHtml.querySelector('textarea');
    if (!textarea) {
        throw new Error('Textarea element not found in editor HTML');
    }
    textarea.value = propertyData;
    const rteOptions = editorConfiguration.rteOptions || {};
    if (editorConfiguration.enableRichtext === true && rteOptions && typeof rteOptions === 'object' && Object.keys(rteOptions).length !== 0) {
        const wrapper = textarea.parentElement;
        if (!wrapper) {
            throw new Error('Textarea wrapper element not found');
        }
        if (ckeditor) {
            const textareaId = textarea.id;
            const rteId = textareaId ? textareaId + 'ckeditor5' : '';
            const rteElement = document.createElement('typo3-rte-ckeditor-ckeditor5');
            if (rteId) {
                rteElement.id = rteId;
            }
            const optionsJson = JSON.stringify(rteOptions);
            rteElement.setAttribute('options', optionsJson);
            textarea.setAttribute('slot', 'textarea');
            rteElement.appendChild(textarea);
            wrapper.innerHTML = '';
            wrapper.appendChild(rteElement);
            rteElement.options = rteOptions;
        }
    }
    validateCollectionElement(propertyPath, editorHtml);
    const eventNames = editorConfiguration.enableRichtext === true ? ['change'] : ['keyup', 'paste'];
    const handleTextareaChange = (event) => {
        const target = event.target;
        getCurrentlySelectedFormElement().set(propertyPath, target.value);
        validateCollectionElement(propertyPath, editorHtml);
    };
    eventNames.forEach(eventName => {
        textarea.addEventListener(eventName, handleTextareaChange);
    });
}
/**
 * @throws 1477300587
 * @throws 1477300588
 * @throws 1477300589
 * @throws 1477300590
 * @throws 1477318981
 * @throws 1477319859
 */
export function renderTypo3WinBrowserEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1477300587);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1477300588);
    assert(getUtility().isNonEmptyString(editorConfiguration.label), 'Invalid configuration "label"', 1477300589);
    assert(getUtility().isNonEmptyString(editorConfiguration.buttonLabel), 'Invalid configuration "buttonLabel"', 1477318981);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1477300590);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label));
    getHelper().getTemplatePropertyElement('buttonLabel', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.buttonLabel));
    renderDescription(editorConfiguration, editorHtml);
    const formEl = editorHtml.querySelector('form');
    if (formEl) {
        formEl.name = editorConfiguration.propertyPath;
    }
    Icons.getIcon(editorConfiguration.iconIdentifier, Icons.sizes.small).then(function (icon) {
        const imageEl = getHelper().getTemplatePropertyElement('image', editorHtml);
        if (imageEl) {
            const tmp = document.createElement('div');
            tmp.innerHTML = icon;
            imageEl.append(tmp.firstElementChild ?? tmp);
        }
    });
    getHelper().getTemplatePropertyElement('onclick', editorHtml)?.addEventListener('click', function () {
        const randomIdentifier = Math.floor((Math.random() * 100000) + 1);
        const insertTarget = this
            .closest(getHelper().getDomElementDataIdentifierSelector('editorControlsWrapper'))
            ?.querySelector(getHelper().getDomElementDataAttribute('contentElementSelectorTarget', 'bracesWithKey'));
        if (insertTarget) {
            insertTarget.setAttribute(getHelper().getDomElementDataAttribute('contentElementSelectorTarget'), String(randomIdentifier));
        }
        openTypo3WinBrowser('db', String(randomIdentifier), editorConfiguration.browsableType);
    });
    listenOnElementBrowser();
    const propertyPath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    const propertyData = getCurrentlySelectedFormElement().get(propertyPath);
    validateCollectionElement(propertyPath, editorHtml);
    const inputEl = getHelper().getTemplatePropertyElement('propertyPath', editorHtml);
    if (inputEl) {
        inputEl.value = propertyData ?? '';
    }
    inputEl?.addEventListener('keyup', handleInput);
    inputEl?.addEventListener('paste', handleInput);
    function handleInput() {
        getCurrentlySelectedFormElement().set(propertyPath, this.value);
        validateCollectionElement(propertyPath, editorHtml);
    }
}
export function renderRemoveElementEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1475412563);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1475412564);
    const button = editorHtml.querySelector('button');
    if (getUtility().isUndefinedOrNull(collectionElementIdentifier)) {
        button?.classList.add(getHelper().getDomElementClassName('buttonFormElementRemove'), getHelper().getDomElementClassName('buttonFormEditor'));
    }
    else {
        button?.classList.add(getHelper().getDomElementClassName('buttonCollectionElementRemove'));
    }
    button?.addEventListener('click', function () {
        if (getUtility().isUndefinedOrNull(collectionElementIdentifier)) {
            getViewModel().showRemoveFormElementModal();
        }
        else {
            getViewModel().showRemoveCollectionElementModal(collectionElementIdentifier, collectionName);
        }
    });
}
export function renderFormElementSelectorEditorAddition(editorConfiguration, editorHtml, propertyPath) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null && !Array.isArray(editorConfiguration), 'Invalid parameter "editorConfiguration"', 1484574704);
    assert(typeof editorHtml === 'object' && editorHtml !== null && !Array.isArray(editorHtml), 'Invalid parameter "editorHtml"', 1484574705);
    assert(getUtility().isNonEmptyString(propertyPath), 'Invalid parameter "propertyPath"', 1484574706);
    const formElementSelector = editorHtml.querySelector('typo3-form-element-selector');
    if (!formElementSelector) {
        return;
    }
    if (editorConfiguration.enableFormelementSelectionButton === true) {
        formElementSelector.elements = getFormElementSelectorEntries();
        formElementSelector.addEventListener(FormElementSelectorSelectedEvent.eventName, (event) => {
            let propertyData;
            propertyData = getCurrentlySelectedFormElement().get(propertyPath) || '';
            if (propertyData.length === 0) {
                propertyData = `{${event.value}}`;
            }
            else {
                propertyData = `${propertyData} {${event.value}}`;
            }
            getCurrentlySelectedFormElement().set(propertyPath, propertyData);
            const inputEl = getHelper().getTemplatePropertyElement('propertyPath', editorHtml);
            if (inputEl) {
                inputEl.value = propertyData;
            }
            validateCollectionElement(propertyPath, editorHtml);
        });
    }
    else {
        formElementSelector.remove();
        const controlsGroup = editorHtml.querySelector('[data-identifier="inspectorEditorControlsGroup"]');
        if (controlsGroup) {
            controlsGroup.classList.remove('input-group');
        }
    }
}
function getFormElementSelectorEntries() {
    return (() => {
        const nonCompositeNonToplevelFormElements = getFormEditorApp().getNonCompositeNonToplevelFormElements();
        return nonCompositeNonToplevelFormElements.map((nonCompositeNonToplevelFormElement) => ({
            icon: getFormElementDefinition(nonCompositeNonToplevelFormElement, 'iconIdentifier'),
            label: nonCompositeNonToplevelFormElement.get('label'),
            value: nonCompositeNonToplevelFormElement.get('identifier'),
        }));
    })();
}
/**
 * @throws 1478967319
 */
export function buildTitleByFormElement(formElement) {
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getCurrentlySelectedFormElement();
    }
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1478967319);
    let label;
    if (formElement.get('type') === 'Form') {
        label = formElement.get('type');
    }
    else {
        label = getFormElementDefinition(formElement, 'label')
            ? getFormElementDefinition(formElement, 'label')
            : formElement.get('identifier');
    }
    const span = document.createElement('span');
    span.textContent = label;
    return span;
}
/**
 * Inspector editor for date constraints using the <typo3-form-date-editor> web component.
 *
 * Delegates UI rendering and state management to the web component.
 * Handles syncing the composed value to the form element model and additionalElementPropertyPaths.
 * The regex patterns from DateRangeValidatorPatterns (PHP) are passed via TYPO3.settings
 * (injected by FormEditorController) to the web component as attributes, so JS and PHP
 * always share the same validation logic.
 */
export function renderDateEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    assert(typeof editorConfiguration === 'object' && editorConfiguration !== null, 'Invalid parameter "editorConfiguration"', 1740000001);
    assert(typeof editorHtml === 'object' && editorHtml !== null, 'Invalid parameter "editorHtml"', 1740000002);
    assert(getUtility().isNonEmptyString(editorConfiguration.propertyPath), 'Invalid configuration "propertyPath"', 1740000003);
    const propertyPath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath, collectionElementIdentifier, collectionName);
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label || ''));
    renderDescription(editorConfiguration, editorHtml);
    const editorElement = editorHtml.querySelector('typo3-form-date-editor');
    const dateEditorSettings = TYPO3.settings.FormEditor.dateEditor;
    assert(getUtility().isNonEmptyString(dateEditorSettings.absolutePattern), 'Missing required TYPO3.settings.FormEditor.dateEditor.absolutePattern', 1740000004);
    editorElement.setAttribute('absolute-pattern', dateEditorSettings.absolutePattern);
    editorElement.value = getCurrentlySelectedFormElement().get(propertyPath) || '';
    validateCollectionElement(propertyPath, editorHtml);
    editorElement.addEventListener(DateEditorChangeEvent.eventName, (event) => {
        const value = event.value;
        getCurrentlySelectedFormElement().set(propertyPath, value);
        if (!getUtility().isUndefinedOrNull(editorConfiguration.additionalElementPropertyPaths)
            && Array.isArray(editorConfiguration.additionalElementPropertyPaths)) {
            for (let i = 0, len = editorConfiguration.additionalElementPropertyPaths.length; i < len; ++i) {
                if (value === '') {
                    getCurrentlySelectedFormElement().unset(editorConfiguration.additionalElementPropertyPaths[i]);
                }
                else {
                    getCurrentlySelectedFormElement().set(editorConfiguration.additionalElementPropertyPaths[i], value);
                }
            }
        }
        validateCollectionElement(propertyPath, editorHtml);
    });
}
export function renderDescription(editorConfiguration, editorHtml) {
    const descEl = getHelper().getTemplatePropertyElement('description', editorHtml);
    if (getUtility().isNonEmptyString(editorConfiguration.description)) {
        if (descEl) {
            descEl.textContent = editorConfiguration.description;
        }
    }
    else {
        descEl?.remove();
    }
}
export function bootstrap(_formEditorApp, customConfiguration) {
    formEditorApp = _formEditorApp;
    configuration = merge({}, defaultConfiguration, customConfiguration ?? {});
    Helper.bootstrap(formEditorApp);
    return this;
}
export function renderVariantsEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label ?? 'Variants'));
    renderDescription(editorConfiguration, editorHtml);
    const formElement = getCurrentlySelectedFormElement();
    // Base property path for the variants array. For form elements this is just
    // "variants"; for a finisher (collection element) it resolves to
    // "finishers.<index>.options.variants" via the editor's propertyPath.
    const basePath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath ?? 'variants', collectionElementIdentifier, collectionName);
    const container = editorHtml.querySelector('[data-identifier="variantsContainer"]');
    const addButton = editorHtml.querySelector('[data-identifier="variantsAddButton"]');
    const addButtonLabelEl = editorHtml.querySelector('[data-template-property="addButtonLabel"]');
    if (addButtonLabelEl) {
        addButtonLabelEl.append(document.createTextNode(lll('variants.add', 'Add variant')));
    }
    if (!container) {
        return;
    }
    const getVariants = () => {
        const value = formElement.get(basePath);
        return Array.isArray(value) ? value : [];
    };
    const buildFormGroup = (labelText, control) => {
        const group = document.createElement('div');
        group.className = 'form-group';
        const label = document.createElement('label');
        label.className = 'form-label';
        label.append(document.createTextNode(labelText));
        group.append(label, control);
        return group;
    };
    const buildVariantRow = (variant, index) => {
        const base = basePath + '.' + index;
        const panel = document.createElement('div');
        panel.className = 'panel panel-default';
        const heading = document.createElement('div');
        heading.className = 'panel-heading';
        heading.style.display = 'flex';
        heading.style.justifyContent = 'space-between';
        heading.style.alignItems = 'center';
        const title = document.createElement('strong');
        title.append(document.createTextNode(lll('variants.variantPrefix', 'Variant') + ' ' + (index + 1)));
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-link btn-sm';
        removeButton.append(document.createTextNode('✕'));
        removeButton.addEventListener('click', () => {
            const variants = getVariants();
            variants.splice(index, 1);
            formElement.set(basePath, variants);
            renderRows();
        });
        heading.append(title, removeButton);
        const body = document.createElement('div');
        body.className = 'panel-body';
        // Condition
        const conditionInput = document.createElement('input');
        conditionInput.type = 'text';
        conditionInput.className = 'form-control';
        conditionInput.value = variant.condition ?? '';
        conditionInput.placeholder = lll('variants.conditionPlaceholder', 'e.g. traverse(formValues, "fieldId") == 1');
        const writeCondition = function () {
            if (this.value === '') {
                formElement.unset(base + '.condition');
            }
            else {
                formElement.set(base + '.condition', this.value);
            }
        };
        conditionInput.addEventListener('keyup', writeCondition);
        conditionInput.addEventListener('paste', writeCondition);
        // WapplerSystems fork (Feature 5): visual condition builder next to the raw input.
        const conditionGroup = document.createElement('div');
        conditionGroup.className = 'input-group';
        const buildButton = document.createElement('button');
        buildButton.type = 'button';
        buildButton.className = 'btn btn-default';
        buildButton.append(document.createTextNode(lll('variants.build', 'Build…')));
        buildButton.addEventListener('click', () => {
            openConditionBuilderModal({
                initialExpression: conditionInput.value,
                fields: collectFormFieldOptions(),
                onApply: (expression) => {
                    conditionInput.value = expression;
                    writeCondition.call(conditionInput);
                },
            });
        });
        conditionGroup.append(conditionInput, buildButton);
        body.append(buildFormGroup('Condition (expression)', conditionGroup));
        // Visibility -> renderingOptions.enabled
        const enabled = variant.renderingOptions?.enabled;
        const visibilitySelect = document.createElement('select');
        visibilitySelect.className = 'form-select';
        const visibilityOptions = [
            ['inherit', 'Inherit (no change)'],
            ['shown', 'Shown'],
            ['hidden', 'Hidden'],
        ];
        const currentVisibility = enabled === true ? 'shown' : (enabled === false ? 'hidden' : 'inherit');
        for (const [value, text] of visibilityOptions) {
            const option = new Option(text, value, false, value === currentVisibility);
            visibilitySelect.append(option);
        }
        visibilitySelect.addEventListener('change', function () {
            if (this.value === 'inherit') {
                formElement.unset(base + '.renderingOptions.enabled');
            }
            else {
                formElement.set(base + '.renderingOptions.enabled', this.value === 'shown');
            }
        });
        body.append(buildFormGroup('Visibility when condition matches', visibilitySelect));
        // Required -> NotEmpty validator inside the variant
        const hasRequired = Array.isArray(variant.validators)
            && variant.validators.some((validator) => !!validator && validator.identifier === 'NotEmpty');
        const requiredWrapper = document.createElement('div');
        requiredWrapper.className = 'form-check form-switch';
        const requiredInput = document.createElement('input');
        requiredInput.type = 'checkbox';
        requiredInput.className = 'form-check-input';
        requiredInput.checked = hasRequired;
        const requiredLabel = document.createElement('label');
        requiredLabel.className = 'form-check-label';
        requiredLabel.append(document.createTextNode(lll('variants.requiredWhen', 'Required when condition matches')));
        requiredInput.addEventListener('change', function () {
            const variants = getVariants();
            const current = variants[index] ?? {};
            let validators = Array.isArray(current.validators) ? current.validators : [];
            if (this.checked) {
                if (!validators.some((validator) => !!validator && validator.identifier === 'NotEmpty')) {
                    validators.push({ identifier: 'NotEmpty' });
                }
            }
            else {
                validators = validators.filter((validator) => !(!!validator && validator.identifier === 'NotEmpty'));
            }
            if (validators.length > 0) {
                formElement.set(base + '.validators', validators);
            }
            else {
                formElement.unset(base + '.validators');
            }
        });
        requiredWrapper.append(requiredInput, requiredLabel);
        body.append(requiredWrapper);
        panel.append(heading, body);
        return panel;
    };
    function renderRows() {
        container.replaceChildren();
        getVariants().forEach((variant, index) => {
            container.append(buildVariantRow(variant, index));
        });
    }
    addButton?.addEventListener('click', () => {
        const variants = getVariants();
        variants.push({ identifier: 'variant-' + Math.random().toString(36).slice(2, 10) });
        formElement.set(basePath, variants);
        renderRows();
    });
    renderRows();
}
/**
 * WapplerSystems fork (Feature 5): collect the form's input fields (identifier +
 * label + selectable options) for the visual condition builder's field/value
 * dropdowns. Leaf input elements only — pages, sections and presentational
 * elements are skipped.
 */
function extractFieldOptions(element) {
    let raw;
    try {
        raw = element.get('properties.options');
    }
    catch {
        return [];
    }
    const out = [];
    if (Array.isArray(raw)) {
        raw.forEach((option) => {
            if (option && typeof option === 'object' && '_value' in option) {
                const opt = option;
                out.push({ value: String(opt._value), label: String(opt._label ?? opt._value) });
            }
        });
    }
    else if (raw && typeof raw === 'object') {
        Object.entries(raw).forEach(([value, label]) => {
            out.push({ value, label: String(label) });
        });
    }
    return out;
}
function collectFormFieldOptions() {
    const skipTypes = ['Form', 'Page', 'SummaryPage', 'Fieldset', 'GridRow', 'GridContainer', 'StaticText', 'ContentElement', 'Honeypot', 'Hidden'];
    const result = [];
    const visit = (element) => {
        let children = [];
        try {
            children = element.get('renderables');
        }
        catch {
            children = [];
        }
        if (Array.isArray(children) && children.length > 0) {
            children.forEach((child) => visit(child));
            return;
        }
        let type = '';
        let identifier = '';
        let label = '';
        try {
            type = element.get('type') || '';
            identifier = element.get('identifier') || '';
            label = element.get('label') || '';
        }
        catch {
            // ignore unreadable element
        }
        if (identifier !== '' && !skipTypes.includes(type)) {
            const field = { identifier, label: label || identifier };
            const options = extractFieldOptions(element);
            if (options.length > 0) {
                field.options = options;
            }
            result.push(field);
        }
    };
    try {
        visit(getFormEditorApp().getRootFormElement());
    }
    catch {
        // root not traversable
    }
    return result;
}
/**
 * WapplerSystems fork (Feature 3): renders the "Email content" inspector editor
 * for email finishers. Shows a summary + an "Edit" button that opens a large modal
 * with a template chooser, a rich-text HTML body, a separate plain-text body and a
 * client-side preview (HTML + plain). All edits are written live into the form
 * editor model (consistent with the other inspector editors); the form-level
 * "Save" button persists them. Round-trip of options.plainMessage /
 * options.templateName is guaranteed via additionalElementPropertyPaths in the
 * editor YAML.
 */
export function renderEmailContentEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label ?? 'Email content'));
    renderDescription(editorConfiguration, editorHtml);
    const editButtonLabelEl = editorHtml.querySelector('[data-template-property="editButtonLabel"]');
    if (editButtonLabelEl) {
        editButtonLabelEl.append(document.createTextNode(editorConfiguration.editButtonLabel ?? lll('email.editButton', 'Edit email content')));
    }
    const formElement = getCurrentlySelectedFormElement();
    const app = getFormEditorApp();
    const messagePath = app.buildPropertyPath(editorConfiguration.propertyPath ?? 'options.message', collectionElementIdentifier, collectionName);
    const plainPath = app.buildPropertyPath(editorConfiguration.plainMessagePropertyPath ?? 'options.plainMessage', collectionElementIdentifier, collectionName);
    const templatePath = app.buildPropertyPath(editorConfiguration.templateNamePropertyPath ?? 'options.templateName', collectionElementIdentifier, collectionName);
    const availableTemplates = editorConfiguration.availableTemplates ?? { Default: 'Default' };
    const rteOptions = editorConfiguration.rteOptions || {};
    const enableRichtext = editorConfiguration.enableRichtext === true
        && rteOptions && typeof rteOptions === 'object' && Object.keys(rteOptions).length !== 0;
    const summaryEl = editorHtml.querySelector('[data-identifier="emailContentSummary"]');
    const editButton = editorHtml.querySelector('[data-identifier="emailContentEditButton"]');
    const updateSummary = () => {
        if (!summaryEl) {
            return;
        }
        const templateName = formElement.get(templatePath) || Object.keys(availableTemplates)[0] || 'Default';
        const hasPlain = typeof formElement.get(plainPath) === 'string' && formElement.get(plainPath) !== '';
        summaryEl.textContent = lll('email.templatePrefix', 'Template') + ': ' + templateName + ' · ' + lll('email.plainLabel', 'plain text') + ': ' + (hasPlain ? lll('email.plainCustom', 'custom') : lll('email.plainAuto', 'auto (from HTML)'));
    };
    updateSummary();
    const labels = {
        modalTitle: editorConfiguration.modalTitle ?? 'Email content',
        templateLabel: editorConfiguration.templateLabel ?? 'Template',
        htmlTab: editorConfiguration.htmlTabLabel ?? 'HTML',
        plainTab: editorConfiguration.plainTabLabel ?? 'Plain text',
        previewTab: editorConfiguration.previewTabLabel ?? 'Preview',
        refreshPreview: editorConfiguration.refreshPreviewLabel ?? 'Refresh preview',
        close: editorConfiguration.closeLabel ?? 'Close',
        plainHint: editorConfiguration.plainHintLabel
            ?? 'Leave empty to derive the plain-text part from the HTML body automatically. Use {formValues} to position the submitted values.',
        insertMarker: editorConfiguration.insertMarkerLabel ?? 'Insert field marker…',
        allValuesMarker: editorConfiguration.allValuesMarkerLabel ?? 'All form values (table)',
    };
    editButton?.addEventListener('click', () => {
        openEmailContentModal({
            formElement,
            messagePath,
            plainPath,
            templatePath,
            availableTemplates,
            rteOptions,
            enableRichtext,
            labels,
            finisherIdentifier: collectionElementIdentifier ?? '',
            onClose: updateSummary,
        });
    });
}
const FORM_VALUES_PLACEHOLDER = '{formValues}';
function escapeEmailHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
function stripEmailTags(value) {
    return value.replace(/<[^>]*>/g, '');
}
function splitOnFormValues(text) {
    const index = text.indexOf(FORM_VALUES_PLACEHOLDER);
    if (index === -1) {
        return { before: text, after: '', hasValues: false };
    }
    return {
        before: text.slice(0, index),
        after: text.slice(index + FORM_VALUES_PLACEHOLDER.length),
        hasValues: true,
    };
}
/**
 * Collect a representative list of element labels from the current form (for the
 * preview's form-values table). Leaf input elements only — pages, sections and
 * presentational elements are skipped.
 */
function collectEmailPreviewLabels() {
    const skipTypes = ['Form', 'Page', 'SummaryPage', 'Fieldset', 'GridRow', 'GridContainer', 'StaticText', 'ContentElement', 'Honeypot', 'Hidden'];
    const labels = [];
    const visit = (element) => {
        let children = [];
        try {
            children = element.get('renderables');
        }
        catch {
            children = [];
        }
        if (Array.isArray(children) && children.length > 0) {
            children.forEach((child) => visit(child));
            return;
        }
        let type = '';
        let label = '';
        try {
            type = element.get('type') || '';
            label = element.get('label') || '';
        }
        catch {
            // ignore unreadable element
        }
        if (label !== '' && !skipTypes.includes(type)) {
            labels.push(label);
        }
    };
    try {
        visit(getFormEditorApp().getRootFormElement());
    }
    catch {
        // root not traversable - preview falls back to placeholder
    }
    return labels;
}
/**
 * The form editor (and therefore CKEditor) runs inside the list-frame iframe, so
 * CKEditor5 injects its (~100) lark-theme `<style>` tags into the iframe document.
 * TYPO3 modals are portaled to the top document, where those styles are absent —
 * leaving the RTE toolbar unstyled (oversized icons). Clone the CKEditor-related
 * styles into the top document once so the in-modal RTE renders correctly.
 */
function ensureModalEditorStyles() {
    try {
        const topDoc = window.top?.document;
        if (!topDoc || topDoc === document) {
            return;
        }
        if (topDoc.querySelector('style[data-form-email-editor-styles]')) {
            return;
        }
        const marker = topDoc.createElement('style');
        marker.setAttribute('data-form-email-editor-styles', '');
        topDoc.head.appendChild(marker);
        const isCkStyle = (css) => css.includes('.ck-') || css.includes('.ck ') || css.includes('.ck{');
        document.head.querySelectorAll('style').forEach((styleEl) => {
            const css = styleEl.textContent || '';
            if (css !== '' && isCkStyle(css)) {
                const clone = topDoc.createElement('style');
                clone.textContent = css;
                topDoc.head.appendChild(clone);
            }
        });
        const adopted = document.adoptedStyleSheets || [];
        adopted.forEach((sheet) => {
            try {
                const cssText = Array.from(sheet.cssRules).map((rule) => rule.cssText).join('\n');
                if (isCkStyle(cssText)) {
                    const st = topDoc.createElement('style');
                    st.textContent = cssText;
                    topDoc.head.appendChild(st);
                }
            }
            catch {
                // cross-origin / inaccessible sheet — skip
            }
        });
    }
    catch {
        // never let style cloning break the modal
    }
}
function openEmailContentModal(options) {
    const { formElement, messagePath, plainPath, templatePath, availableTemplates, rteOptions, enableRichtext, labels, finisherIdentifier, onClose } = options;
    if (enableRichtext) {
        ensureModalEditorStyles();
    }
    const content = document.createElement('div');
    content.className = 'form-editor-email-content-modal';
    // --- Template chooser ---------------------------------------------------
    const templateGroup = document.createElement('div');
    templateGroup.className = 'form-group';
    const templateLabel = document.createElement('label');
    templateLabel.className = 'form-label';
    templateLabel.append(document.createTextNode(labels.templateLabel));
    const templateSelect = document.createElement('select');
    templateSelect.className = 'form-select';
    const currentTemplate = formElement.get(templatePath) || Object.keys(availableTemplates)[0] || 'Default';
    Object.keys(availableTemplates).forEach((templateName) => {
        const optionEl = new Option(availableTemplates[templateName] || templateName, templateName, false, templateName === currentTemplate);
        templateSelect.append(optionEl);
    });
    templateSelect.addEventListener('change', function () {
        formElement.set(templatePath, this.value);
        onClose();
    });
    templateGroup.append(templateLabel, templateSelect);
    content.append(templateGroup);
    // --- Tab bar ------------------------------------------------------------
    const tabNames = [['html', labels.htmlTab], ['plain', labels.plainTab], ['preview', labels.previewTab]];
    const tabBar = document.createElement('div');
    tabBar.className = 'btn-group';
    tabBar.setAttribute('role', 'group');
    const panes = {};
    const tabButtons = {};
    const activateTab = (name) => {
        Object.keys(panes).forEach((key) => {
            panes[key].style.display = key === name ? '' : 'none';
            tabButtons[key].classList.toggle('active', key === name);
            tabButtons[key].classList.toggle('btn-primary', key === name);
            tabButtons[key].classList.toggle('btn-default', key !== name);
        });
        if (name === 'preview') {
            refreshPreview();
        }
    };
    tabNames.forEach(([name, labelText]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-default';
        button.append(document.createTextNode(labelText));
        button.addEventListener('click', () => activateTab(name));
        tabBar.append(button);
        tabButtons[name] = button;
    });
    content.append(tabBar);
    // --- Field-marker inserter ---------------------------------------------
    // Lets the editor drop {fieldIdentifier} / {formValues} placeholders into the
    // HTML or plain body at the caret without typing the syntax by hand. The marker
    // list is derived from the form's own fields (collectFormFieldOptions) plus the
    // special {formValues} table marker that the EmailFinisher understands.
    const markerDefs = [
        { value: '{formValues}', label: labels.allValuesMarker },
        ...collectFormFieldOptions().map((field) => ({
            value: '{' + field.identifier + '}',
            label: field.label ? field.label + ' ({' + field.identifier + '})' : '{' + field.identifier + '}',
        })),
    ];
    const insertIntoTextarea = (textarea, marker, write) => {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        textarea.value = textarea.value.slice(0, start) + marker + textarea.value.slice(end);
        const caret = start + marker.length;
        textarea.focus();
        textarea.setSelectionRange(caret, caret);
        write();
    };
    const buildMarkerBar = (insert) => {
        const bar = document.createElement('div');
        bar.style.marginTop = '1em';
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.style.maxWidth = '340px';
        const placeholder = new Option(labels.insertMarker, '', true, true);
        placeholder.disabled = true;
        select.append(placeholder);
        markerDefs.forEach((marker) => select.append(new Option(marker.label, marker.value)));
        select.addEventListener('change', function () {
            if (this.value !== '') {
                insert(this.value);
            }
            this.selectedIndex = 0;
        });
        bar.append(select);
        return bar;
    };
    // --- HTML body pane -----------------------------------------------------
    const htmlPane = document.createElement('div');
    htmlPane.style.marginTop = '1em';
    const htmlTextarea = document.createElement('textarea');
    htmlTextarea.className = 'form-control';
    htmlTextarea.id = 'emailContentHtml-' + Math.random().toString(36).slice(2, 10);
    htmlTextarea.rows = 14;
    htmlTextarea.value = formElement.get(messagePath) || '';
    const writeHtml = function () {
        formElement.set(messagePath, htmlTextarea.value);
    };
    htmlTextarea.addEventListener('change', writeHtml);
    htmlTextarea.addEventListener('keyup', writeHtml);
    const insertHtmlMarker = (marker) => {
        if (enableRichtext && ckeditor) {
            // CKEditor5 exposes its editor instance on the editable DOM element via the
            // documented `.ckeditorInstance` property. Insert at the current model selection;
            // the editor's change:data handler syncs the value back to the slotted textarea
            // (which fires writeHtml), so we don't need to touch the textarea ourselves.
            const editable = htmlPane.querySelector('.ck-editor__editable');
            const editor = editable?.ckeditorInstance ?? null;
            if (editor && editor.model) {
                editor.model.change((writer) => {
                    editor.model.insertContent(writer.createText(marker));
                });
                editor.editing?.view?.focus?.();
                return;
            }
        }
        insertIntoTextarea(htmlTextarea, marker, writeHtml);
    };
    if (enableRichtext && ckeditor) {
        const rteElement = document.createElement('typo3-rte-ckeditor-ckeditor5');
        rteElement.id = htmlTextarea.id + 'ckeditor5';
        rteElement.setAttribute('options', JSON.stringify(rteOptions));
        htmlTextarea.setAttribute('slot', 'textarea');
        rteElement.appendChild(htmlTextarea);
        htmlPane.appendChild(rteElement);
    }
    else {
        htmlPane.appendChild(htmlTextarea);
    }
    htmlPane.appendChild(buildMarkerBar(insertHtmlMarker));
    panes.html = htmlPane;
    content.append(htmlPane);
    // --- Plain body pane ----------------------------------------------------
    const plainPane = document.createElement('div');
    plainPane.style.marginTop = '1em';
    const plainHint = document.createElement('div');
    plainHint.className = 'form-text text-body-secondary';
    plainHint.append(document.createTextNode(labels.plainHint));
    const plainTextarea = document.createElement('textarea');
    plainTextarea.className = 'form-control';
    plainTextarea.rows = 14;
    plainTextarea.value = formElement.get(plainPath) || '';
    const writePlain = function () {
        if (plainTextarea.value === '') {
            formElement.unset(plainPath);
        }
        else {
            formElement.set(plainPath, plainTextarea.value);
        }
        onClose();
    };
    plainTextarea.addEventListener('change', writePlain);
    plainTextarea.addEventListener('keyup', writePlain);
    plainPane.append(plainHint, plainTextarea, buildMarkerBar((marker) => insertIntoTextarea(plainTextarea, marker, writePlain)));
    panes.plain = plainPane;
    content.append(plainPane);
    // --- Preview pane -------------------------------------------------------
    const previewPane = document.createElement('div');
    previewPane.style.marginTop = '1em';
    const refreshButton = document.createElement('button');
    refreshButton.type = 'button';
    refreshButton.className = 'btn btn-default btn-sm';
    refreshButton.append(document.createTextNode(labels.refreshPreview));
    const previewHtmlHeading = document.createElement('h5');
    previewHtmlHeading.append(document.createTextNode(lll('email.html', 'HTML')));
    const previewFrame = document.createElement('iframe');
    previewFrame.style.width = '100%';
    previewFrame.style.minHeight = '260px';
    previewFrame.style.border = '1px solid var(--typo3-component-border-color, #ccc)';
    previewFrame.setAttribute('sandbox', '');
    const previewPlainHeading = document.createElement('h5');
    previewPlainHeading.append(document.createTextNode(lll('email.plainText', 'Plain text')));
    const previewPlain = document.createElement('pre');
    previewPlain.style.whiteSpace = 'pre-wrap';
    previewPlain.style.padding = '0.5em';
    previewPlain.style.border = '1px solid var(--typo3-component-border-color, #ccc)';
    // Test-send row: send the rendered sample email to a real address.
    const formEditorSettings = window.TYPO3?.settings?.FormEditor;
    const testRow = document.createElement('div');
    testRow.style.display = 'flex';
    testRow.style.gap = '0.5em';
    testRow.style.alignItems = 'center';
    testRow.style.marginBottom = '0.75em';
    const testInput = document.createElement('input');
    testInput.type = 'email';
    testInput.className = 'form-control form-control-sm';
    testInput.style.flex = '0 0 280px';
    testInput.placeholder = 'recipient@example.com';
    testInput.value = formEditorSettings?.testEmailRecipient ?? '';
    const testButton = document.createElement('button');
    testButton.type = 'button';
    testButton.className = 'btn btn-default btn-sm';
    testButton.append(document.createTextNode(lll('email.sendTest', 'Send test email')));
    testButton.addEventListener('click', () => sendTestEmail(testInput.value, testButton));
    testRow.append(testInput, testButton);
    previewPane.append(refreshButton, testRow, previewHtmlHeading, previewFrame, previewPlainHeading, previewPlain);
    panes.preview = previewPane;
    content.append(previewPane);
    // Client-side fallback preview (body + sample value table, no SystemEmail layout).
    function clientPreviewFallback() {
        const htmlSource = htmlTextarea.value || '';
        const plainSourceRaw = plainTextarea.value || '';
        const plainSource = plainSourceRaw !== '' ? plainSourceRaw : stripEmailTags(htmlSource);
        const fieldLabels = collectEmailPreviewLabels();
        const htmlTable = fieldLabels.length > 0
            ? '<table style="border-collapse:collapse" border="1" cellpadding="6">'
                + fieldLabels.map((label) => '<tr><td><strong>' + escapeEmailHtml(label) + '</strong></td><td>&mdash;</td></tr>').join('')
                + '</table>'
            : '<em>[submitted form values]</em>';
        const plainTable = fieldLabels.length > 0
            ? fieldLabels.map((label) => label + ': -').join('\n')
            : '[submitted form values]';
        const htmlParts = splitOnFormValues(htmlSource);
        previewFrame.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:sans-serif;font-size:14px;color:#000;margin:1em}</style></head><body>'
            + (htmlParts.hasValues ? htmlParts.before + htmlTable + htmlParts.after : htmlParts.before)
            + '</body></html>';
        const plainParts = splitOnFormValues(plainSource);
        previewPlain.textContent = plainParts.hasValues
            ? plainParts.before + plainTable + plainParts.after
            : plainParts.before;
    }
    const getEndpoints = () => window.TYPO3?.settings?.FormEditor ?? {};
    // Shared payload (current form definition + the modal's live content) for both the
    // server-side preview and the test-send. Returns null if the definition can't be read.
    function buildEmailPayload() {
        try {
            const root = getFormEditorApp().getRootFormElement();
            return {
                formDefinition: JSON.stringify(getUtility().convertToSimpleObject(root)),
                prototypeName: root.get('prototypeName') || 'standard',
                finisherIdentifier,
                message: htmlTextarea.value || '',
                plainMessage: plainTextarea.value || '',
                emailTemplateName: templateSelect.value || 'Default',
            };
        }
        catch {
            return null;
        }
    }
    // Server-side preview: renders the REAL Fluid email template (SystemEmail layout +
    // form-values table) so the preview matches the actual email. Falls back to the
    // client-side approximation if the endpoint is unavailable or fails.
    function refreshPreview() {
        const url = getEndpoints().emailPreviewUrl;
        const payload = url ? buildEmailPayload() : null;
        if (!url || !payload) {
            clientPreviewFallback();
            return;
        }
        previewPlain.textContent = lll('email.loadingPreview', 'Loading preview…');
        new AjaxRequest(url).post(payload).then(async (response) => {
            const data = await response.resolve();
            previewFrame.srcdoc = data.html ?? '';
            previewPlain.textContent = data.plain ?? '';
        }).catch(() => {
            clientPreviewFallback();
        });
    }
    // Test-send: render + actually send the sample email to the given recipient.
    function sendTestEmail(recipient, button) {
        const url = getEndpoints().sendTestEmailUrl;
        const payload = url ? buildEmailPayload() : null;
        if (!url || !payload) {
            Notification.error(lll('email.testTitle', 'Test email'), lll('email.testEndpointUnavailable', 'The test-send endpoint is not available.'));
            return;
        }
        payload.recipient = recipient;
        button.disabled = true;
        new AjaxRequest(url).post(payload).then(async (response) => {
            const data = await response.resolve();
            if (data.status === 'success') {
                Notification.success(lll('email.testTitle', 'Test email'), data.message ?? lll('email.testSent', 'Test email sent.'));
            }
            else {
                Notification.error(lll('email.testTitle', 'Test email'), data.message ?? lll('email.testFailed', 'Could not send the test email.'));
            }
        }).catch(() => {
            Notification.error(lll('email.testTitle', 'Test email'), lll('email.testFailed', 'Could not send the test email.'));
        }).finally(() => {
            button.disabled = false;
        });
    }
    refreshButton.addEventListener('click', refreshPreview);
    activateTab('html');
    Modal.advanced({
        type: Modal.types.default,
        title: labels.modalTitle,
        content: content,
        size: Modal.sizes.large,
        buttons: [
            {
                text: labels.close,
                btnClass: 'btn-primary',
                trigger: (_event, modal) => {
                    modal.hideModal();
                },
            },
        ],
        callback: () => {
            onClose();
        },
    });
}
// WapplerSystems fork (Feature 7): the finisher option keys that carry user-facing text
// worth translating. Other options (templateName, recipients, technical flags) are left
// untouched. subPath === the option key; storage is options.translation.overrides.<lang>.<key>.
const TRANSLATABLE_FINISHER_OPTIONS = {
    subject: 'Subject',
    message: 'Message (HTML)',
    plainMessage: 'Message (plain text)',
};
function buildFinisherTranslatableItems(options) {
    const items = [];
    if (!options || typeof options !== 'object') {
        return items;
    }
    Object.keys(TRANSLATABLE_FINISHER_OPTIONS).forEach((key) => {
        const value = options[key];
        if (typeof value === 'string' && value !== '') {
            items.push({ kind: 'finisherOption', subPath: key, source: value });
        }
    });
    return items;
}
function buildTranslatableItems(formElement) {
    const items = [];
    const label = formElement.get('label') || '';
    if (label !== '') {
        items.push({ kind: 'label', subPath: 'label', source: label });
    }
    const placeholder = formElement.get('properties.fluidAdditionalAttributes.placeholder') || '';
    if (placeholder !== '') {
        items.push({ kind: 'placeholder', subPath: 'placeholder', source: placeholder });
    }
    extractFieldOptions(formElement).forEach((option) => {
        items.push({ kind: 'option', subPath: 'options.' + option.value, source: option.label });
    });
    // WapplerSystems fork (Feature 7): custom validation error messages. Only messages the
    // editor actually configured (properties.validationErrorMessages) are translatable here —
    // the built-in validator messages are already localised via TYPO3's shipped XLF. The
    // override key is "c<code>" to keep the form-editor model path segment non-numeric.
    const validationMessages = formElement.get('properties.validationErrorMessages');
    if (Array.isArray(validationMessages)) {
        validationMessages.forEach((entry) => {
            if (entry && typeof entry === 'object') {
                const code = String(entry.code ?? '');
                const message = String(entry.message ?? '');
                if (code !== '' && message !== '') {
                    items.push({ kind: 'validationErrorMessage', subPath: 'validationErrorMessages.c' + code, source: message, code });
                }
            }
        });
    }
    return items;
}
function readOverrideValue(languageOverride, item) {
    // Generic dotted-path resolve so flat (label/placeholder) and nested
    // (options.<value>, validationErrorMessages.c<code>) overrides all work.
    let current = languageOverride;
    for (const part of item.subPath.split('.')) {
        if (current && typeof current === 'object') {
            current = current[part];
        }
        else {
            return '';
        }
    }
    return typeof current === 'string' ? current : '';
}
export function renderTranslationEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
    const languages = editorConfiguration.availableLanguages ?? getAvailableLanguages();
    if (languages.length === 0) {
        // No additional site languages configured - keep the sidebar uncluttered,
        // same as when this editor previously wasn't injected at all in that case.
        editorHtml.style.display = 'none';
        return;
    }
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label ?? 'Translations'));
    renderDescription(editorConfiguration, editorHtml);
    editorHtml.querySelector('[data-template-property="editButtonLabel"]')
        ?.append(document.createTextNode(editorConfiguration.editButtonLabel ?? lll('translation.edit', 'Translate…')));
    const formElement = getCurrentlySelectedFormElement();
    // For finishers (collection editors) the property path is resolved against the
    // collection, e.g. finishers.<idx>.options.translation.overrides, and the translatable
    // items come from the finisher's own options rather than the (form) element model.
    const isCollection = !!collectionElementIdentifier && !!collectionName;
    const basePath = getFormEditorApp().buildPropertyPath(editorConfiguration.propertyPath ?? 'renderingOptions.translation.overrides', collectionElementIdentifier, collectionName);
    const collectOptionsPath = isCollection
        ? getFormEditorApp().buildPropertyPath('options', collectionElementIdentifier, collectionName)
        : '';
    const collectItems = () => isCollection
        ? buildFinisherTranslatableItems(formElement.get(collectOptionsPath))
        : buildTranslatableItems(formElement);
    const summaryEl = editorHtml.querySelector('[data-identifier="translationSummary"]');
    const editButton = editorHtml.querySelector('[data-identifier="translationEditButton"]');
    const updateSummary = () => {
        if (!summaryEl) {
            return;
        }
        const items = collectItems();
        if (items.length === 0 || languages.length === 0) {
            summaryEl.textContent = languages.length === 0 ? lll('translation.noLanguages', 'No additional site languages configured.') : lll('translation.nothingHere', 'Nothing translatable here.');
            return;
        }
        const overrides = formElement.get(basePath) || {};
        const parts = languages.map((language) => {
            const languageOverride = overrides[language.code] || {};
            const filled = items.filter((item) => readOverrideValue(languageOverride, item) !== '').length;
            return language.code.toUpperCase() + ' ' + filled + '/' + items.length;
        });
        summaryEl.textContent = lll('translation.translationsLabel', 'Translations') + ': ' + parts.join(' · ');
    };
    updateSummary();
    editButton?.addEventListener('click', () => {
        openTranslationModal({
            formElement,
            basePath,
            languages,
            items: collectItems(),
            onClose: updateSummary,
        });
    });
}
function openTranslationModal(options) {
    const { formElement, basePath, languages, items, onClose } = options;
    const content = document.createElement('div');
    content.className = 'form-editor-translation-modal';
    if (items.length === 0) {
        const info = document.createElement('div');
        info.className = 'alert alert-info';
        info.append(document.createTextNode(lll('translation.elementNothing', 'This element has no label, placeholder, options or custom validation messages to translate.')));
        content.append(info);
    }
    const itemLabel = (item) => {
        if (item.kind === 'label') {
            return 'Label';
        }
        if (item.kind === 'placeholder') {
            return 'Placeholder';
        }
        if (item.kind === 'validationErrorMessage') {
            return 'Error message (' + (item.code ?? '') + ')';
        }
        if (item.kind === 'finisherOption') {
            return TRANSLATABLE_FINISHER_OPTIONS[item.subPath] ?? item.subPath;
        }
        return 'Option: ' + item.subPath.slice('options.'.length);
    };
    languages.forEach((language) => {
        const section = document.createElement('div');
        section.className = 'panel panel-default';
        section.style.border = '1px solid var(--typo3-component-border-color, #ccc)';
        section.style.borderRadius = '4px';
        section.style.padding = '0.75em';
        section.style.marginBottom = '0.75em';
        const heading = document.createElement('strong');
        heading.append(document.createTextNode(language.title + ' (' + language.code + ')'));
        section.append(heading);
        items.forEach((item) => {
            const group = document.createElement('div');
            group.className = 'form-group';
            group.style.marginTop = '0.5em';
            const label = document.createElement('label');
            label.className = 'form-label';
            label.style.fontWeight = 'normal';
            label.append(document.createTextNode(itemLabel(item)));
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = item.source;
            const path = basePath + '.' + language.code + '.' + item.subPath;
            input.value = formElement.get(path) || '';
            const write = function () {
                if (this.value === '') {
                    formElement.unset(path);
                }
                else {
                    formElement.set(path, this.value);
                }
                onClose();
            };
            input.addEventListener('keyup', write);
            input.addEventListener('change', write);
            group.append(label, input);
            section.append(group);
        });
        content.append(section);
    });
    Modal.advanced({
        type: Modal.types.default,
        title: lll('translation.modalTitle', 'Translations'),
        content: content,
        size: Modal.sizes.large,
        buttons: [
            {
                text: lll('common.close', 'Close'),
                btnClass: 'btn-primary',
                trigger: (_event, modal) => {
                    modal.hideModal();
                },
            },
        ],
        callback: () => {
            onClose();
        },
    });
}
function collectTranslatableElements() {
    const entries = [];
    const visit = (element) => {
        let identifier = '';
        let label = '';
        try {
            identifier = element.get('identifier') || '';
            label = element.get('label') || '';
        }
        catch {
            // ignore
        }
        if (identifier !== '' && identifier !== 'formValues') {
            const items = buildTranslatableItems(element);
            if (items.length > 0) {
                entries.push({
                    element: element,
                    basePath: 'renderingOptions.translation.overrides',
                    identifier,
                    label: label || identifier,
                    items,
                });
            }
        }
        let children = [];
        try {
            children = element.get('renderables');
        }
        catch {
            children = [];
        }
        if (Array.isArray(children)) {
            children.forEach((child) => visit(child));
        }
    };
    let root = null;
    try {
        root = getFormEditorApp().getRootFormElement();
        visit(root);
    }
    catch {
        // root not traversable
    }
    // Finishers: translatable string options (subject/message/plainMessage) of every finisher.
    // Written to the root form model under finishers.<idx>.options.translation.overrides.
    if (root) {
        let finishers = [];
        try {
            finishers = root.get('finishers');
        }
        catch {
            finishers = [];
        }
        if (Array.isArray(finishers)) {
            finishers.forEach((finisher, index) => {
                // Finishers are stored as plain nested objects under the root form model
                // (the finisher editors mutate them via dotted paths), so read their fields
                // directly — with a .get() fallback in case a build wraps them as models.
                const plain = finisher;
                let identifier = '';
                let options = null;
                try {
                    identifier = (typeof plain.get === 'function' ? plain.get('identifier') : plain.identifier) || '';
                    options = (typeof plain.get === 'function' ? plain.get('options') : plain.options) || null;
                }
                catch {
                    // ignore
                }
                const items = buildFinisherTranslatableItems(options);
                if (items.length > 0) {
                    entries.push({
                        element: root,
                        basePath: 'finishers.' + index + '.options.translation.overrides',
                        identifier: 'finisher:' + (identifier || index),
                        label: 'Finisher: ' + (identifier || index),
                        items,
                    });
                }
            });
        }
    }
    return entries;
}
export function renderTranslationOverviewEditor(editorConfiguration, editorHtml) {
    const languages = editorConfiguration.availableLanguages ?? getAvailableLanguages();
    if (languages.length === 0) {
        editorHtml.style.display = 'none';
        return;
    }
    getHelper().getTemplatePropertyElement('label', editorHtml)
        ?.append(document.createTextNode(editorConfiguration.label ?? 'Form translations'));
    renderDescription(editorConfiguration, editorHtml);
    editorHtml.querySelector('[data-template-property="editButtonLabel"]')
        ?.append(document.createTextNode(editorConfiguration.editButtonLabel ?? lll('translation.editWholeForm', 'Translate whole form…')));
    const summaryEl = editorHtml.querySelector('[data-identifier="translationOverviewSummary"]');
    const button = editorHtml.querySelector('[data-identifier="translationOverviewButton"]');
    const updateSummary = () => {
        if (!summaryEl) {
            return;
        }
        if (languages.length === 0) {
            summaryEl.textContent = lll('translation.noLanguages', 'No additional site languages configured.');
            return;
        }
        const entries = collectTranslatableElements();
        const perLanguage = {};
        languages.forEach((language) => { perLanguage[language.code] = { filled: 0, total: 0 }; });
        entries.forEach((entry) => {
            const overrides = entry.element.get(entry.basePath) || {};
            languages.forEach((language) => {
                const languageOverride = overrides[language.code] || {};
                entry.items.forEach((item) => {
                    perLanguage[language.code].total += 1;
                    if (readOverrideValue(languageOverride, item) !== '') {
                        perLanguage[language.code].filled += 1;
                    }
                });
            });
        });
        const parts = languages.map((language) => {
            const stat = perLanguage[language.code];
            return language.code.toUpperCase() + ' ' + stat.filled + '/' + stat.total;
        });
        summaryEl.textContent = lll('translation.completenessLabel', 'Completeness') + ': ' + parts.join(' · ');
    };
    updateSummary();
    button?.addEventListener('click', () => openTranslationOverviewModal(languages, updateSummary));
}
function openTranslationOverviewModal(languages, onClose) {
    const content = document.createElement('div');
    content.className = 'form-editor-translation-overview-modal';
    const entries = collectTranslatableElements();
    if (entries.length === 0 || languages.length === 0) {
        const info = document.createElement('div');
        info.className = 'alert alert-info';
        info.append(document.createTextNode(languages.length === 0
            ? lll('translation.noLanguagesForSite', 'No additional site languages are configured for this site.')
            : 'This form has nothing translatable yet.'));
        content.append(info);
    }
    const itemLabel = (item) => {
        if (item.kind === 'label') {
            return 'Label';
        }
        if (item.kind === 'placeholder') {
            return 'Placeholder';
        }
        if (item.kind === 'validationErrorMessage') {
            return 'Error message (' + (item.code ?? '') + ')';
        }
        if (item.kind === 'finisherOption') {
            return TRANSLATABLE_FINISHER_OPTIONS[item.subPath] ?? item.subPath;
        }
        return 'Option: ' + item.subPath.slice('options.'.length);
    };
    entries.forEach((entry) => {
        const panel = document.createElement('div');
        panel.className = 'panel panel-default';
        panel.style.border = '1px solid var(--typo3-component-border-color, #ccc)';
        panel.style.borderRadius = '4px';
        panel.style.padding = '0.5em 0.75em';
        panel.style.marginBottom = '0.75em';
        const heading = document.createElement('div');
        heading.style.fontWeight = 'bold';
        heading.style.marginBottom = '0.35em';
        heading.append(document.createTextNode(entry.label + ' (' + entry.identifier + ')'));
        panel.append(heading);
        const table = document.createElement('table');
        table.className = 'table table-sm';
        table.style.width = '100%';
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        const thItem = document.createElement('th');
        thItem.style.width = '20%';
        thItem.append(document.createTextNode(lll('translation.field', 'Field')));
        headRow.append(thItem);
        languages.forEach((language) => {
            const th = document.createElement('th');
            th.append(document.createTextNode(language.title + ' (' + language.code + ')'));
            headRow.append(th);
        });
        thead.append(headRow);
        table.append(thead);
        const tbody = document.createElement('tbody');
        entry.items.forEach((item) => {
            const row = document.createElement('tr');
            const tdLabel = document.createElement('td');
            tdLabel.append(document.createTextNode(itemLabel(item)));
            const hint = document.createElement('div');
            hint.className = 'text-body-secondary';
            hint.style.fontSize = '0.85em';
            hint.append(document.createTextNode(item.source));
            tdLabel.append(hint);
            row.append(tdLabel);
            languages.forEach((language) => {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.placeholder = item.source;
                const path = entry.basePath + '.' + language.code + '.' + item.subPath;
                input.value = entry.element.get(path) || '';
                const write = function () {
                    if (this.value === '') {
                        entry.element.unset(path);
                    }
                    else {
                        entry.element.set(path, this.value);
                    }
                    onClose();
                };
                input.addEventListener('keyup', write);
                input.addEventListener('change', write);
                td.append(input);
                row.append(td);
            });
            tbody.append(row);
        });
        table.append(tbody);
        panel.append(table);
        content.append(panel);
    });
    Modal.advanced({
        type: Modal.types.default,
        title: lll('translation.formModalTitle', 'Form translations'),
        content: content,
        size: Modal.sizes.full,
        buttons: [
            {
                text: lll('common.close', 'Close'),
                btnClass: 'btn-primary',
                trigger: (_event, modal) => {
                    modal.hideModal();
                },
            },
        ],
        callback: () => {
            onClose();
        },
    });
}
