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
/**
 * Module: @typo3/form/backend/form-editor/modals-component
 */
import * as Helper from '@typo3/form/backend/form-editor/helper.js';
import { merge } from 'lodash-es';
import Modal, {} from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';
let configuration = null;
const defaultConfiguration = {
    domElementClassNames: {
        buttonDefault: 'btn-default',
        buttonInfo: 'btn-info',
        buttonWarning: 'btn-warning'
    },
    domElementDataAttributeNames: {
        elementType: 'element-type',
        fullElementType: 'data-element-type'
    },
    domElementDataAttributeValues: {
        rowItem: 'rowItem',
        rowLink: 'rowLink',
        rowsContainer: 'rowsContainer',
        templateInsertElements: 'Modal-InsertElements',
        templateInsertPages: 'Modal-InsertPages',
        templateValidationErrors: 'Modal-ValidationErrors'
    }
};
let formEditorApp = null;
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
function assert(test, message, messageCode) {
    return getFormEditorApp().assert(test, message, messageCode);
}
function getRootFormElement() {
    return getFormEditorApp().getRootFormElement();
}
function getPublisherSubscriber() {
    return getFormEditorApp().getPublisherSubscriber();
}
function getFormElementDefinition(formElement, formElementDefinitionKey) {
    return getFormEditorApp().getFormElementDefinition(formElement, formElementDefinitionKey);
}
/**
 * @throws 1478889044
 * @throws 1478889049
 */
function showRemoveElementModal(publisherTopicName, publisherTopicArguments) {
    const modalButtons = [];
    assert(getUtility().isNonEmptyString(publisherTopicName), 'Invalid parameter "publisherTopicName"', 1478889049);
    assert(Array.isArray(publisherTopicArguments), 'Invalid parameter "formElement"', 1478889044);
    modalButtons.push({
        text: getFormElementDefinition(getRootFormElement(), 'modalRemoveElementCancelButton'),
        active: true,
        btnClass: getHelper().getDomElementClassName('buttonDefault'),
        name: 'cancel',
        trigger: (e, modal) => {
            modal.hideModal();
        }
    });
    modalButtons.push({
        text: getFormElementDefinition(getRootFormElement(), 'modalRemoveElementConfirmButton'),
        active: true,
        btnClass: getHelper().getDomElementClassName('buttonWarning'),
        name: 'confirm',
        trigger: (e, modal) => {
            getPublisherSubscriber().publish(publisherTopicName, publisherTopicArguments);
            modal.hideModal();
        }
    });
    Modal.show(getFormElementDefinition(getRootFormElement(), 'modalRemoveElementDialogTitle'), getFormElementDefinition(getRootFormElement(), 'modalRemoveElementDialogMessage'), Severity.warning, modalButtons);
}
/**
 * @publish mixed
 * @throws 1478910954
 */
function insertElementsModalSetup(modalContent, publisherTopicName, configuration) {
    assert(getUtility().isNonEmptyString(publisherTopicName), 'Invalid parameter "publisherTopicName"', 1478910954);
    if (typeof configuration === 'object' && configuration !== null && !Array.isArray(configuration)) {
        for (const key of Object.keys(configuration)) {
            if (key === 'disableElementTypes'
                && Array.isArray(configuration[key])) {
                for (let i = 0, len = configuration[key].length; i < len; ++i) {
                    modalContent.querySelectorAll(getHelper().getDomElementDataAttribute('fullElementType', 'bracesWithKeyValue', [configuration[key][i]])).forEach((el) => el.classList.add(getHelper().getDomElementClassName('disabled')));
                }
            }
            if (key === 'onlyEnableElementTypes'
                && Array.isArray(configuration[key])) {
                modalContent.querySelectorAll(getHelper().getDomElementDataAttribute('fullElementType', 'bracesWithKey')).forEach((el) => {
                    const elementType = el.getAttribute(getHelper().getDomElementDataAttribute('elementType'));
                    const isEnabled = configuration[key].some((type) => type === elementType);
                    if (!isEnabled) {
                        el.classList.add(getHelper().getDomElementClassName('disabled'));
                    }
                });
            }
        }
    }
    [...modalContent.children].forEach(el => el.addEventListener('typo3:form:insert-element-click', function (e) {
        getPublisherSubscriber().publish(publisherTopicName, [e.detail.item.identifier]);
    }));
}
/**
 * @publish view/modal/validationErrors/element/clicked
 * @throws 1479161268
 */
function _validationErrorsModalSetup(modalContent, validationResults) {
    let formElement, newRowItem;
    assert(Array.isArray(validationResults), 'Invalid parameter "validationResults"', 1479161268);
    const rowItemSelector = getHelper().getDomElementDataIdentifierSelector('rowItem');
    const rowItemTemplate = modalContent.querySelector(rowItemSelector)?.cloneNode(true);
    modalContent.querySelectorAll(rowItemSelector).forEach((el) => el.remove());
    for (let i = 0, len = validationResults.length; i < len; ++i) {
        let hasError = false;
        for (let j = 0, len2 = validationResults[i].validationResults.length; j < len2; ++j) {
            if (validationResults[i].validationResults[j].validationResults
                && validationResults[i].validationResults[j].validationResults.length > 0) {
                hasError = true;
                break;
            }
        }
        if (hasError) {
            formElement = getFormEditorApp()
                .getFormElementByIdentifierPath(validationResults[i].formElementIdentifierPath);
            newRowItem = rowItemTemplate?.cloneNode(true);
            const rowLink = newRowItem?.querySelector(getHelper().getDomElementDataIdentifierSelector('rowLink'));
            if (rowLink) {
                rowLink.setAttribute(getHelper().getDomElementDataAttribute('elementIdentifier'), validationResults[i].formElementIdentifierPath);
                rowLink.replaceChildren(_buildTitleByFormElement(formElement));
            }
            const rowsContainer = modalContent.querySelector(getHelper().getDomElementDataIdentifierSelector('rowsContainer'));
            if (rowsContainer && newRowItem) {
                rowsContainer.append(newRowItem);
            }
        }
    }
    modalContent.querySelectorAll('a').forEach((a) => {
        a.addEventListener('click', function () {
            getPublisherSubscriber().publish('view/modal/validationErrors/element/clicked', [
                a.getAttribute(getHelper().getDomElementDataAttribute('elementIdentifier'))
            ]);
            modalContent.querySelectorAll('a').forEach((link) => link.replaceWith(link.cloneNode(true)));
            Modal.currentModal.hideModal();
        });
    });
}
/**
 * @throws 1479162557
 */
function _buildTitleByFormElement(formElement) {
    assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1479162557);
    const span = document.createElement('span');
    span.textContent = formElement.get('label') ? formElement.get('label') : formElement.get('identifier');
    return span;
}
/* *************************************************************
 * Public Methods
 * ************************************************************/
/**
 * @publish view/modal/removeFormElement/perform
 */
export function showRemoveFormElementModal(formElement) {
    showRemoveElementModal('view/modal/removeFormElement/perform', [formElement]);
}
/**
 * @publish view/modal/removeCollectionElement/perform
 * @throws 1478894420
 * @throws 1478894421
 */
export function showRemoveCollectionElementModal(collectionElementIdentifier, collectionName, formElement) {
    assert(getUtility().isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1478894420);
    assert(getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1478894421);
    showRemoveElementModal('view/modal/removeCollectionElement/perform', [collectionElementIdentifier, collectionName, formElement]);
}
/**
 * @publish view/modal/close/perform
 */
export function showCloseConfirmationModal() {
    const modalButtons = [];
    modalButtons.push({
        text: getFormElementDefinition(getRootFormElement(), 'modalCloseCancelButton'),
        active: true,
        btnClass: getHelper().getDomElementClassName('buttonDefault'),
        name: 'cancel',
        trigger: (e, modal) => {
            modal.hideModal();
        }
    });
    modalButtons.push({
        text: getFormElementDefinition(getRootFormElement(), 'modalCloseConfirmButton'),
        active: true,
        btnClass: getHelper().getDomElementClassName('buttonWarning'),
        name: 'confirm',
        trigger: (e, modal) => {
            getPublisherSubscriber().publish('view/modal/close/perform', []);
            modal.hideModal();
        }
    });
    Modal.show(getFormElementDefinition(getRootFormElement(), 'modalCloseDialogTitle'), getFormElementDefinition(getRootFormElement(), 'modalCloseDialogMessage'), Severity.warning, modalButtons);
}
export function showInsertElementsModal(publisherTopicName, configuration) {
    const template = getHelper().getTemplateElement('templateInsertElements');
    if (template) {
        const content = document.importNode(template.content, true);
        insertElementsModalSetup(content, publisherTopicName, configuration);
        Modal.advanced({
            title: getFormElementDefinition(getRootFormElement(), 'modalInsertElementsDialogTitle'),
            size: Modal.sizes.large,
            content,
        });
    }
}
export function showInsertPagesModal(publisherTopicName) {
    const template = getHelper().getTemplateElement('templateInsertPages');
    if (template) {
        const content = document.importNode(template.content, true);
        insertElementsModalSetup(content, publisherTopicName);
        Modal.advanced({
            title: getFormElementDefinition(getRootFormElement(), 'modalInsertPagesDialogTitle'),
            size: Modal.sizes.small,
            content,
        });
    }
}
export function showValidationErrorsModal(validationResults) {
    const modalButtons = [];
    modalButtons.push({
        text: getFormElementDefinition(getRootFormElement(), 'modalValidationErrorsConfirmButton'),
        active: true,
        btnClass: getHelper().getDomElementClassName('buttonDefault'),
        name: 'confirm',
        trigger: function (e, modal) {
            modal.hideModal();
        }
    });
    const template = getHelper().getTemplateElement('templateValidationErrors');
    if (template) {
        const content = document.importNode(template.content, true);
        _validationErrorsModalSetup(content, validationResults);
        Modal.show(getFormElementDefinition(getRootFormElement(), 'modalValidationErrorsDialogTitle'), content, Severity.error, modalButtons);
    }
}
export function bootstrap(_formEditorApp, customConfiguration) {
    formEditorApp = _formEditorApp;
    configuration = merge({}, defaultConfiguration, customConfiguration ?? {});
    Helper.bootstrap(formEditorApp);
    return this;
}
