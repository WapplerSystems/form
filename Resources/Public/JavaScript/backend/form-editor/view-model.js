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
 * Module: @typo3/form/backend/form-editor/view-model
 */
import { cloneDeep } from 'lodash-es';
import * as TreeComponent from '@typo3/form/backend/form-editor/tree-component-adapter.js';
import * as ModalsComponent from '@typo3/form/backend/form-editor/modals-component.js';
import * as InspectorComponent from '@typo3/form/backend/form-editor/inspector-component.js';
import * as StageComponent from '@typo3/form/backend/form-editor/stage-component.js';
import * as Helper from '@typo3/form/backend/form-editor/helper.js';
import Icons from '@typo3/backend/icons.js';
import Notification from '@typo3/backend/notification.js';
import { loadModule } from '@typo3/core/java-script-item-processor.js';
const configuration = {
    domElementClassNames: {
        formElementIsComposit: 'formeditor-element-composit',
        formElementIsTopLevel: 'formeditor-element-toplevel',
        hasError: 'has-error',
        selectedCompositFormElement: 'selected',
        selectedFormElement: 'selected',
        selectedRootFormElement: 'selected',
        selectedStagePanel: 't3-form-form-stage-selected',
        sortableHover: 'sortable-hover',
        viewModeAbstract: 'formeditor-module-viewmode-abstract',
        viewModePreview: 'formeditor-module-viewmode-preview'
    },
    domElementDataAttributeNames: {
        abstractType: 'data-element-abstract-type'
    },
    domElementDataAttributeValues: {
        buttonHeaderClose: 'closeButton',
        buttonHeaderPaginationNext: 'buttonPaginationNext',
        buttonHeaderPaginationPrevious: 'buttonPaginationPrevious',
        buttonHeaderRedo: 'redoButton',
        buttonHeaderSave: 'saveButton',
        buttonHeaderUndo: 'undoButton',
        buttonHeaderViewModeAbstract: 'buttonViewModeAbstract',
        buttonHeaderViewModePreview: 'buttonViewModePreview',
        buttonFormSettings: 'formSettings',
        buttonToggleStructure: 'formeditorStructureToggle',
        buttonExpandInspector: 'formeditorInspectorExpand',
        buttonCollapseInspector: 'formeditorInspectorCollapse',
        buttonNewPage: 'newPage',
        iconMailform: 'content-form',
        iconSave: 'actions-document-save',
        iconSaveSpinner: 'spinner-circle',
        inspectorSection: 'inspectorSection',
        moduleLoadingIndicator: 'moduleLoadingIndicator',
        moduleWrapper: 'moduleWrapper',
        stageArea: 'stageArea',
        stageContainer: 'stageContainer',
        stageContainerInner: 'stageContainerInner',
        stagePanelHeading: 'panelHeading',
        stageSection: 'stageSection',
        structure: 'structure-element',
        structureSection: 'structureSection',
        structureRootContainer: 'treeRootContainer',
        structureRootElement: 'treeRootElement'
    }
};
let previewMode = false;
let formEditorApp = null;
let structureComponent = null;
let modalsComponent = null;
let inspectorsComponent = null;
let stageComponent = null;
function getRootFormElement() {
    return getFormEditorApp().getRootFormElement();
}
function assert(test, message, messageCode) {
    return getFormEditorApp().assert(test, message, messageCode);
}
function getUtility() {
    return getFormEditorApp().getUtility();
}
function getCurrentlySelectedFormElement() {
    return getFormEditorApp().getCurrentlySelectedFormElement();
}
function getPublisherSubscriber() {
    return getFormEditorApp().getPublisherSubscriber();
}
/**
 * RFC 3339 full-date format: YYYY-MM-DD
 */
const RFC3339_FULL_DATE_PATTERN = /^([0-9]{4})-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])$/i;
function isAbsoluteDate(value) {
    return RFC3339_FULL_DATE_PATTERN.test(value);
}
/**
 * A relative date expression is any non-empty string that is NOT an absolute
 * date (YYYY-MM-DD). Actual validation is performed server-side by PHP's
 * DateTime parser, which supports the full strtotime() grammar (e.g.
 * "last sunday", "first day of next month", "+1 month +3 days").
 */
function isRelativeDateExpression(value) {
    const trimmed = value.trim();
    return trimmed.length > 0 && !isAbsoluteDate(trimmed);
}
function addPropertyValidators() {
    getFormEditorApp().addPropertyValidationValidator('NotEmpty', function (formElement, propertyPath) {
        const value = formElement.get(propertyPath);
        if (!value || value === '' || Array.isArray(value) && !value.length) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('NotEmpty').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('Integer', function (formElement, propertyPath) {
        const value = formElement.get(propertyPath);
        if (value === '' || value === null || isNaN(Number(value))) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('Integer').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('IntegerOrEmpty', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        if (formElement.get(propertyPath).length > 0 && isNaN(Number(formElement.get(propertyPath)))) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('Integer').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('NaiveEmail', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        if (!formElement.get(propertyPath).match(/\S+@\S+\.\S+/)) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('NaiveEmail').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('NaiveEmailOrEmpty', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        if (formElement.get(propertyPath).length > 0 && !formElement.get(propertyPath).match(/\S+@\S+\.\S+/)) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('NaiveEmailOrEmpty').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('FormElementIdentifierWithinCurlyBracesInclusive', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        const regex = /\{([a-z0-9-_]+)?\}/gi;
        const match = regex.exec(formElement.get(propertyPath));
        if (match && ((match[1] && match[1] !== '__currentTimestamp' && !getFormEditorApp().isFormElementIdentifierUsed(match[1])) || !match[1])) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('FormElementIdentifierWithinCurlyBracesInclusive').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('FormElementIdentifierWithinCurlyBracesExclusive', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        const regex = /^\{([a-z0-9-_]+)?\}$/i;
        const match = regex.exec(formElement.get(propertyPath));
        if (!match || ((match[1] && match[1] !== '__currentTimestamp' && !getFormEditorApp().isFormElementIdentifierUsed(match[1])) || !match[1])) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('FormElementIdentifierWithinCurlyBracesInclusive').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('FileSize', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        if (!formElement.get(propertyPath).match(/^(\d*\.?\d+)(B|K|M|G)$/i)) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('FileSize').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('RFC3339FullDate', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        const value = formElement.get(propertyPath);
        if (!isAbsoluteDate(value) && !isRelativeDateExpression(value)) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('RFC3339FullDate').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('RFC3339FullDateOrEmpty', function (formElement, propertyPath) {
        if (getUtility().isUndefinedOrNull(formElement.get(propertyPath))) {
            return undefined;
        }
        const value = formElement.get(propertyPath);
        if (value.length > 0 && !isAbsoluteDate(value) && !isRelativeDateExpression(value)) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('RFC3339FullDate').errorMessage || 'invalid value';
        }
        return undefined;
    });
    getFormEditorApp().addPropertyValidationValidator('RegularExpressionPattern', function (formElement, propertyPath) {
        const value = formElement.get(propertyPath);
        let isValid = true;
        if (!getUtility().isNonEmptyString(value)) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('RegularExpressionPattern').errorMessage || 'invalid value';
        }
        try {
            const matches = value.match(/^\/(.*)\/[gmixsuUAJD]*$/);
            if (null !== matches) {
                new RegExp(matches[1]);
            }
            else {
                isValid = false;
            }
        }
        catch {
            isValid = false;
        }
        if (!isValid) {
            return getFormEditorApp().getFormElementPropertyValidatorDefinition('RegularExpressionPattern').errorMessage || 'invalid value';
        }
        return undefined;
    });
}
/**
 * @publish view/ready
 * @throws 1475425785
 */
function loadAdditionalModules(_additionalViewModelModules) {
    let additionalViewModelModules = [];
    if (typeof _additionalViewModelModules === 'object' && !Array.isArray(_additionalViewModelModules)) {
        for (const key of Object.keys(_additionalViewModelModules)) {
            additionalViewModelModules.push(_additionalViewModelModules[key]);
        }
    }
    else {
        additionalViewModelModules = _additionalViewModelModules;
    }
    if (!Array.isArray(additionalViewModelModules)) {
        getPublisherSubscriber().publish('view/ready');
        return;
    }
    const additionalViewModelModulesLength = additionalViewModelModules.length;
    if (additionalViewModelModulesLength > 0) {
        let loadedAdditionalViewModelModules = 0;
        for (let i = 0; i < additionalViewModelModulesLength; ++i) {
            loadModule(additionalViewModelModules[i]).then(function (additionalViewModelModule) {
                assert(typeof additionalViewModelModule.bootstrap === 'function', 'The module "' + additionalViewModelModules[i].name + '" does not implement the method "bootstrap"', 1475425785);
                additionalViewModelModule.bootstrap(getFormEditorApp());
                loadedAdditionalViewModelModules++;
                if (additionalViewModelModulesLength === loadedAdditionalViewModelModules) {
                    getPublisherSubscriber().publish('view/ready');
                }
            });
        }
    }
    else {
        getPublisherSubscriber().publish('view/ready');
    }
}
/**
 * @throws 1478268639
 */
function structureComponentSetup() {
    assert(typeof TreeComponent.bootstrap === 'function', 'The structure component does not implement the method "bootstrap"', 1478268639);
    structureComponent = TreeComponent.bootstrap(getFormEditorApp(), document.querySelector(getHelper().getDomElementDataAttribute('identifier', 'bracesWithKeyValue', [
        getHelper().getDomElementDataAttributeValue('structure')
    ])));
    const iconMailformEl = document.querySelector(getHelper().getDomElementDataIdentifierSelector('structureRootContainer'))?.querySelector(getHelper().getDomElementDataIdentifierSelector('iconMailform'));
    if (iconMailformEl) {
        iconMailformEl.setAttribute('title', 'identifier: ' + getRootFormElement().get('identifier'));
    }
}
/**
 * @throws 1478895106
 */
function modalsComponentSetup() {
    assert(typeof ModalsComponent.bootstrap === 'function', 'The modals component does not implement the method "bootstrap"', 1478895106);
    modalsComponent = ModalsComponent.bootstrap(getFormEditorApp());
}
/**
 * @throws 1478895106
 */
function inspectorsComponentSetup() {
    assert(typeof InspectorComponent.bootstrap === 'function', 'The inspector component does not implement the method "bootstrap"', 1478895106);
    inspectorsComponent = InspectorComponent.bootstrap(getFormEditorApp());
}
/**
 * @throws 1478986610
 */
function stageComponentSetup() {
    assert(typeof InspectorComponent.bootstrap === 'function', 'The stage component does not implement the method "bootstrap"', 1478986610);
    stageComponent = StageComponent.bootstrap(getFormEditorApp(), document.querySelector(getHelper().getDomElementDataAttribute('identifier', 'bracesWithKeyValue', [
        getHelper().getDomElementDataAttributeValue('stageArea')
    ])));
    const stagePanelEl = getStage().getStagePanelDomElement();
    stagePanelEl?.addEventListener('click', function (e) {
        const identifierAttr = getHelper().getDomElementDataAttribute('identifier');
        const target = e.target;
        if (target.getAttribute(identifierAttr) === getHelper().getDomElementDataAttributeValue('stagePanelHeading')
            || target.getAttribute(identifierAttr) === getHelper().getDomElementDataAttributeValue('stageSection')
            || target.getAttribute(identifierAttr) === getHelper().getDomElementDataAttributeValue('stageArea')) {
            selectPageBatch(getFormEditorApp().getCurrentlySelectedPageIndex());
        }
        getPublisherSubscriber().publish('view/stage/panel/clicked', []);
    });
}
/**
 * @publish view/header/button/save/clicked
 * @publish view/stage/abstract/button/newElement/clicked
 * @publish view/header/button/newPage/clicked
 * @publish view/structure/button/newPage/clicked
 * @publish view/header/button/close/clicked
 */
function buttonsSetup() {
    const qs = (id) => document.querySelector(getHelper().getDomElementDataIdentifierSelector(id));
    qs('buttonHeaderSave')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/header/button/save/clicked', []);
    });
    qs('buttonToggleStructure')?.addEventListener('click', function () {
        qs('structureSection')?.classList.toggle('formeditor-inspector-expanded');
    });
    qs('buttonExpandInspector')?.addEventListener('click', function () {
        qs('inspectorSection')?.classList.add('formeditor-inspector-expanded');
    });
    qs('buttonCollapseInspector')?.addEventListener('click', function () {
        qs('inspectorSection')?.classList.remove('formeditor-inspector-expanded');
    });
    qs('buttonFormSettings')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/header/formSettings/clicked', []);
    });
    qs('buttonNewPage')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/structure/button/newPage/clicked', ['view/insertPages/perform']);
    });
    qs('buttonHeaderClose')?.addEventListener('click', function (e) {
        if (!getFormEditorApp().getUnsavedContent()) {
            return;
        }
        e.preventDefault();
        getPublisherSubscriber().publish('view/header/button/close/clicked', []);
    });
    qs('buttonHeaderUndo')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/undoButton/clicked', []);
    });
    qs('buttonHeaderRedo')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/redoButton/clicked', []);
    });
    qs('buttonHeaderViewModeAbstract')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/viewModeButton/abstract/clicked', []);
    });
    qs('buttonHeaderViewModePreview')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/viewModeButton/preview/clicked', []);
    });
    qs('structureRootContainer')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/structure/root/selected');
    });
    qs('buttonHeaderPaginationNext')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/paginationNext/clicked', []);
    });
    qs('buttonHeaderPaginationPrevious')?.addEventListener('click', function () {
        getPublisherSubscriber().publish('view/paginationPrevious/clicked', []);
    });
}
/* *************************************************************
 * Public Methods
 * ************************************************************/
export function getFormEditorApp() {
    return formEditorApp;
}
export function getHelper(_configuration) {
    if (getUtility().isUndefinedOrNull(_configuration)) {
        return Helper.setConfiguration(configuration);
    }
    return Helper.setConfiguration(_configuration);
}
export function getFormElementDefinition(formElement, formElementDefinitionKey) {
    return getFormEditorApp().getFormElementDefinition(formElement, formElementDefinitionKey);
}
export function getConfiguration() {
    return cloneDeep(configuration);
}
export function getPreviewMode() {
    return previewMode;
}
export function setPreviewMode(newPreviewMode) {
    previewMode = !!newPreviewMode;
}
/* *************************************************************
 * Structure
 * ************************************************************/
export function getStructure() {
    return structureComponent;
}
/**
 * @publish view/structure/renew/postProcess
 */
export function renewStructure() {
    getStructure().renew();
    getPublisherSubscriber().publish('view/structure/renew/postProcess');
}
export function selectStructureNode(formElement) {
    getStructure().selectTreeNode(formElement);
}
export function addStructureSelection(formElement) {
    getStructure().getTreeNode(formElement)?.classList.add(getHelper().getDomElementClassName('selectedFormElement'));
}
/**
 * @todo deprecate, method is unused
 */
export function removeStructureSelection(formElement) {
    getStructure().getTreeNode(formElement)?.classList.remove(getHelper().getDomElementClassName('selectedFormElement'));
}
export function removeAllStructureSelections() {
    const treeDom = getStructure().getTreeDomElement();
    if (treeDom) {
        treeDom.querySelectorAll(getHelper().getDomElementClassName('selectedFormElement', true))
            .forEach((el) => el.classList.remove(getHelper().getDomElementClassName('selectedFormElement')));
    }
}
export function getStructureRootContainer() {
    return document.querySelector(getHelper().getDomElementDataAttribute('identifier', 'bracesWithKeyValue', [
        getHelper().getDomElementDataAttributeValue('structureRootContainer')
    ]));
}
export function getStructureRootElement() {
    return document.querySelector(getHelper().getDomElementDataAttribute('identifier', 'bracesWithKeyValue', [
        getHelper().getDomElementDataAttributeValue('structureRootElement')
    ]));
}
export function removeStructureRootElementSelection() {
    getStructureRootContainer()?.classList.remove(getHelper().getDomElementClassName('selectedRootFormElement'));
}
export function addStructureRootElementSelection() {
    getStructureRootContainer()?.classList.add(getHelper().getDomElementClassName('selectedRootFormElement'));
}
export function setStructureRootElementTitle(title) {
    if (getUtility().isUndefinedOrNull(title)) {
        const span = document.createElement('span');
        span.textContent = getRootFormElement().get('label') ? getRootFormElement().get('label') : getRootFormElement().get('identifier');
        title = span.textContent;
    }
    const el = getStructureRootElement();
    if (el) {
        el.textContent = title;
    }
}
export function addStructureValidationResults() {
    getStructure().clearAllValidationErrors();
    const validationResults = getFormEditorApp().validateFormElementRecursive(getRootFormElement());
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
            const identifierPath = validationResults[i].formElementIdentifierPath;
            // Set validation error on the tree node (adds CSS class + overlay icon)
            getStructure().setNodeValidationError(identifierPath, true);
            // Mark all parent nodes as having a child with error
            const pathParts = identifierPath.split('/');
            while (pathParts.pop()) {
                const parentPath = pathParts.join('/');
                if (parentPath) {
                    getStructure().setNodeChildHasError(parentPath, true);
                }
            }
        }
    }
}
/* *************************************************************
 * Modals
 * ************************************************************/
export function getModals() {
    return modalsComponent;
}
export function showRemoveFormElementModal(formElement) {
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getCurrentlySelectedFormElement();
    }
    getModals().showRemoveFormElementModal(formElement);
}
export function showRemoveCollectionElementModal(collectionElementIdentifier, collectionName, formElement) {
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getCurrentlySelectedFormElement();
    }
    getModals().showRemoveCollectionElementModal(collectionElementIdentifier, collectionName, formElement);
}
export function showCloseConfirmationModal() {
    getModals().showCloseConfirmationModal();
}
export function showInsertElementsModal(targetEvent, configuration) {
    getModals().showInsertElementsModal(targetEvent, configuration);
}
export function showInsertPagesModal(targetEvent) {
    getModals().showInsertPagesModal(targetEvent);
}
export function showValidationErrorsModal() {
    const validationResults = getFormEditorApp().validateFormElementRecursive(getRootFormElement());
    getModals().showValidationErrorsModal(validationResults);
}
/* *************************************************************
 * Inspector
 * ************************************************************/
export function getInspector() {
    return inspectorsComponent;
}
/**
 * WapplerSystems fork: the editors the inspector just rendered, made
 * non-editable for a form that is open for viewing only.
 *
 * Disabling the form controls covers more than it looks like: the inspector
 * adds and removes validators and finishers through a `<select>` and a
 * checkbox, not through buttons, so a disabled control blocks those too. What
 * is left are the buttons that open an editing modal of their own — variants,
 * translations, email content — whose fields live outside this section and
 * would not be caught here. Their read-only summaries stay visible.
 *
 * Runs again on the next frame because the Lit-based editors (property grid,
 * date editor) render their internals asynchronously and are not in the DOM
 * yet when this returns.
 */
function applyReadOnlyToInspector() {
    if (!getFormEditorApp().isReadOnly()) {
        return;
    }
    const inspectorSection = document.querySelector(getHelper().getDomElementDataIdentifierSelector('inspectorSection'));
    if (!inspectorSection) {
        return;
    }
    const mutatingButtonIdentifiers = [
        'variantsAddButton',
        'translationEditButton',
        'translationOverviewButton',
        'emailContentEditButton',
    ];
    const disable = () => {
        inspectorSection
            .querySelectorAll('input, select, textarea')
            .forEach((field) => {
            field.disabled = true;
        });
        for (const identifier of mutatingButtonIdentifiers) {
            inspectorSection
                .querySelectorAll(getHelper().getDomElementDataIdentifierSelector(identifier))
                .forEach((button) => {
                button.classList.add('hidden');
            });
        }
    };
    disable();
    requestAnimationFrame(disable);
}
export function renderInspectorEditors(formElement) {
    getInspector().renderEditors(formElement);
    applyReadOnlyToInspector();
}
export function focusFirstInspectorInput() {
    const inspectorSection = document.querySelector(getHelper().getDomElementDataIdentifierSelector('inspectorSection'));
    if (inspectorSection) {
        const firstInput = inspectorSection.querySelector('input, select, textarea');
        firstInput?.focus();
    }
}
export function showInspectorSidebar() {
    document.querySelector(getHelper().getDomElementDataIdentifierSelector('inspectorSection'))?.classList.add('formeditor-inspector-expanded');
}
export function renderInspectorCollectionElementEditors(collectionName, collectionElementIdentifier) {
    getInspector().renderCollectionElementEditors(collectionName, collectionElementIdentifier);
    applyReadOnlyToInspector();
}
/* *************************************************************
 * Stage
 * ************************************************************/
export function getStage() {
    return stageComponent;
}
export function setStageHeadline(title) {
    getStage().setStageHeadline(title);
}
export function addStagePanelSelection() {
    getStage().getStagePanelDomElement()?.classList.add(getHelper().getDomElementClassName('selectedStagePanel'));
}
export function removeStagePanelSelection() {
    getStage().getStagePanelDomElement()?.classList.remove(getHelper().getDomElementClassName('selectedStagePanel'));
}
export function renderPagination() {
    getStage().renderPagination();
}
export function renderUndoRedo() {
    getStage().renderUndoRedo();
}
/**
 * @publish view/stage/abstract/render/postProcess
 * @publish view/stage/abstract/render/preProcess
 */
export function renderAbstractStageArea() {
    setButtonActive(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderViewModeAbstract')));
    removeButtonActive(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderViewModePreview')));
    document.querySelector(getHelper().getDomElementDataIdentifierSelector('moduleWrapper'))
        ?.classList.add(getHelper().getDomElementClassName('viewModeAbstract'));
    document.querySelector(getHelper().getDomElementDataIdentifierSelector('moduleWrapper'))
        ?.classList.remove(getHelper().getDomElementClassName('viewModePreview'));
    const render = (callback) => {
        getStage().renderAbstractStageArea(undefined, callback);
    };
    const renderPostProcess = () => {
        const formElementTypeDefinition = getFormElementDefinition(getCurrentlySelectedFormElement(), undefined);
        getStage().getAllFormElementDomElements().forEach(function (el) {
            el.addEventListener('mouseenter', function () {
                getStage().getAllFormElementDomElements().forEach((other) => {
                    other.parentElement?.classList.remove(getHelper().getDomElementClassName('sortableHover'));
                });
                if (el.parentElement?.classList.contains(getHelper().getDomElementClassName('formElementIsComposit'))
                    && !el.parentElement?.classList.contains(getHelper().getDomElementClassName('formElementIsTopLevel'))) {
                    el.parentElement?.classList.add(getHelper().getDomElementClassName('sortableHover'));
                }
            });
        });
        if (formElementTypeDefinition._isTopLevelFormElement
            && !formElementTypeDefinition._isCompositeFormElement
            && !getFormEditorApp().isRootFormElementSelected()) {
            // Non-composite top-level elements don't allow adding children
        }
        refreshSelectedElementItemsBatch();
        getPublisherSubscriber().publish('view/stage/abstract/render/postProcess');
    };
    render(function () {
        getPublisherSubscriber().publish('view/stage/abstract/render/preProcess');
        renderPostProcess();
        getPublisherSubscriber().publish('view/stage/abstract/render/postProcess');
    });
}
/**
 * @publish view/stage/preview/render/postProcess
 */
export function renderPreviewStageArea(html) {
    setButtonActive(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderViewModePreview')));
    removeButtonActive(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderViewModeAbstract')));
    document.querySelector(getHelper().getDomElementDataIdentifierSelector('moduleWrapper'))
        ?.classList.add(getHelper().getDomElementClassName('viewModePreview'));
    document.querySelector(getHelper().getDomElementDataIdentifierSelector('moduleWrapper'))
        ?.classList.remove(getHelper().getDomElementClassName('viewModeAbstract'));
    getStage().renderPreviewStageArea(html);
    getPublisherSubscriber().publish('view/stage/preview/render/postProcess');
}
export function addAbstractViewValidationResults() {
    const validationResults = getFormEditorApp().validateFormElementRecursive(getRootFormElement());
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
            if (i > 0) {
                const validationElement = getStage().getAbstractViewFormElementDomElement(validationResults[i].formElementIdentifierPath);
                // Set invalid property on Lit web component (FormElementStageItem)
                const stageItem = validationElement?.querySelector('typo3-form-form-element-stage-item');
                if (stageItem && 'invalid' in stageItem) {
                    stageItem.invalid = true;
                }
                // Also set legacy CSS class for backward compatibility (legacy templates)
                setElementValidationErrorClass(validationElement);
            }
        }
    }
}
/* *************************************************************
 * Form element methods
 * ************************************************************/
/**
 * @publish view/formElement/inserted
 */
export function createAndAddFormElement(formElementType, referenceFormElement, disablePublishersOnSet) {
    const newFormElement = getFormEditorApp().createAndAddFormElement(formElementType, referenceFormElement);
    if (!disablePublishersOnSet) {
        getPublisherSubscriber().publish('view/formElement/inserted', [newFormElement]);
    }
    return newFormElement;
}
/**
 * @publish view/formElement/moved
 */
export function moveFormElement(formElementToMove, position, referenceFormElement, disablePublishersOnSet) {
    const movedFormElement = getFormEditorApp().moveFormElement(formElementToMove, position, referenceFormElement, false);
    if (!disablePublishersOnSet) {
        getPublisherSubscriber().publish('view/formElement/moved', [movedFormElement]);
    }
    return movedFormElement;
}
/**
 * @publish view/formElement/removed
 */
export function removeFormElement(formElement, disablePublishersOnSet) {
    let parentFormElement;
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getCurrentlySelectedFormElement();
    }
    if (getFormElementDefinition(formElement, '_isTopLevelFormElement')
        && getFormElementDefinition(formElement, '_isCompositeFormElement')
        && getRootFormElement().get('renderables').length === 1) {
        Notification.error(getFormElementDefinition(getRootFormElement(), 'modalRemoveElementLastAvailablePageFlashMessageTitle'), getFormElementDefinition(getRootFormElement(), 'modalRemoveElementLastAvailablePageFlashMessageMessage'), 2);
    }
    else {
        parentFormElement = getFormEditorApp().removeFormElement(formElement, false);
        if (!disablePublishersOnSet) {
            getPublisherSubscriber().publish('view/formElement/removed', [parentFormElement]);
        }
    }
    return parentFormElement;
}
/**
 * @publish view/collectionElement/new/added
 */
export function createAndAddPropertyCollectionElement(collectionElementIdentifier, collectionName, formElement, collectionElementConfiguration, referenceCollectionElementIdentifier, disablePublishersOnSet) {
    getFormEditorApp().createAndAddPropertyCollectionElement(collectionElementIdentifier, collectionName, formElement, collectionElementConfiguration, referenceCollectionElementIdentifier);
    if (!disablePublishersOnSet) {
        getPublisherSubscriber().publish('view/collectionElement/new/added', [
            collectionElementIdentifier,
            collectionName,
            formElement,
            collectionElementConfiguration,
            referenceCollectionElementIdentifier
        ]);
    }
}
export function movePropertyCollectionElement(collectionElementToMove, position, referenceCollectionElement, collectionName, formElement, disablePublishersOnSet) {
    if (getUtility().isUndefinedOrNull(formElement)) {
        formElement = getCurrentlySelectedFormElement();
    }
    getFormEditorApp().movePropertyCollectionElement(collectionElementToMove, position, referenceCollectionElement, collectionName, formElement, false);
    if (!disablePublishersOnSet) {
        getPublisherSubscriber().publish('view/collectionElement/moved', [
            collectionElementToMove,
            position,
            referenceCollectionElement,
            collectionName,
            formElement
        ]);
    }
}
/**
 * @publish view/collectionElement/removed
 */
export function removePropertyCollectionElement(collectionElementIdentifier, collectionName, formElement, disablePublishersOnSet) {
    let propertyData, propertyPath;
    getFormEditorApp().removePropertyCollectionElement(collectionElementIdentifier, collectionName, formElement);
    const collectionElementConfiguration = getFormEditorApp().getPropertyCollectionElementConfiguration(collectionElementIdentifier, collectionName);
    if (Array.isArray(collectionElementConfiguration.editors)) {
        for (let i = 0, len1 = collectionElementConfiguration.editors.length; i < len1; ++i) {
            if (Array.isArray(collectionElementConfiguration.editors[i].additionalElementPropertyPaths)) {
                for (let j = 0, len2 = collectionElementConfiguration.editors[i].additionalElementPropertyPaths.length; j < len2; ++j) {
                    getCurrentlySelectedFormElement().unset(collectionElementConfiguration.editors[i].additionalElementPropertyPaths[j], true);
                }
            }
            else if (collectionElementConfiguration.editors[i].identifier === 'validationErrorMessage') {
                propertyPath = getFormEditorApp().buildPropertyPath(collectionElementConfiguration.editors[i].propertyPath);
                propertyData = getCurrentlySelectedFormElement().get(propertyPath);
                if (!getUtility().isUndefinedOrNull(propertyData)) {
                    for (let j = 0, len2 = collectionElementConfiguration.editors[i].errorCodes.length; j < len2; ++j) {
                        for (let k = 0, len3 = propertyData.length; k < len3; ++k) {
                            if (parseInt(collectionElementConfiguration.editors[i].errorCodes[j], 10) === parseInt(propertyData[k].code, 10)) {
                                propertyData.splice(k, 1);
                                --len3;
                            }
                        }
                    }
                    getCurrentlySelectedFormElement().set(propertyPath, propertyData);
                }
            }
        }
    }
    if (!disablePublishersOnSet) {
        getPublisherSubscriber().publish('view/collectionElement/removed', [
            collectionElementIdentifier,
            collectionName,
            formElement
        ]);
    }
}
/* *************************************************************
 * Batch methods
 * ************************************************************/
export function refreshSelectedElementItemsBatch() {
    const formElementTypeDefinition = getFormElementDefinition(getCurrentlySelectedFormElement(), undefined);
    removeAllStageElementSelectionsBatch();
    removeAllStructureSelections();
    if (!getFormEditorApp().isRootFormElementSelected()) {
        removeStructureRootElementSelection();
        addStructureSelection();
        const selectedElement = getStage().getAbstractViewFormElementDomElement();
        if (formElementTypeDefinition._isTopLevelFormElement) {
            addStagePanelSelection();
        }
        else {
            selectedElement?.classList.add(getHelper().getDomElementClassName('selectedFormElement'));
            getStage().createAndAddAbstractViewFormElementToolbar(selectedElement, undefined);
        }
        getStage().getAllFormElementDomElements().forEach((el) => {
            el.parentElement?.classList.remove(getHelper().getDomElementClassName('selectedCompositFormElement'));
        });
        if (!formElementTypeDefinition._isTopLevelFormElement && formElementTypeDefinition._isCompositeFormElement) {
            selectedElement?.parentElement?.classList.add(getHelper().getDomElementClassName('selectedCompositFormElement'));
        }
    }
}
/**
 * @throws 1478651732
 * @throws 1478651733
 * @throws 1478651734
 */
export function selectPageBatch(pageIndex) {
    assert(typeof pageIndex === 'number', 'Invalid parameter "pageIndex"', 1478651732);
    assert(pageIndex >= 0, 'Invalid parameter "pageIndex"', 1478651733);
    assert(pageIndex < getRootFormElement().get('renderables').length, 'Invalid parameter "pageIndex"', 1478651734);
    getFormEditorApp().setCurrentlySelectedFormElement(getRootFormElement().get('renderables')[pageIndex]);
    renewStructure();
    renderPagination();
    refreshSelectedElementItemsBatch();
    renderInspectorEditors();
}
export function removeAllStageElementSelectionsBatch() {
    getStage().getAllFormElementDomElements().forEach((el) => el.classList.remove(getHelper().getDomElementClassName('selectedFormElement')));
    removeStagePanelSelection();
    getStage().getAllFormElementDomElements().forEach((el) => el.parentElement?.classList.remove(getHelper().getDomElementClassName('sortableHover')));
}
export function onViewReadyBatch() {
    setStageHeadline();
    setStructureRootElementTitle();
    renderAbstractStageArea();
    renewStructure();
    addStructureRootElementSelection();
    renderInspectorEditors();
    renderPagination();
    hideComponent(document.querySelector(getHelper().getDomElementDataIdentifierSelector('moduleLoadingIndicator')));
    showComponent(document.querySelector(getHelper().getDomElementDataIdentifierSelector('moduleWrapper')));
    showComponent(document.querySelector(getHelper().getDomElementDataIdentifierSelector('inspectorSection')));
    showComponent(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderSave')));
    showComponent(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderClose')));
    showComponent(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderUndo')));
    showComponent(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderRedo')));
    setButtonActive(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderViewModeAbstract')));
}
export function onAbstractViewDndStartBatch(draggedFormElementDomElement, draggedFormPlaceholderDomElement) {
    draggedFormPlaceholderDomElement?.classList.remove(getHelper().getDomElementClassName('sortableHover'));
}
export function onAbstractViewDndChangeBatch(placeholderDomElement, parentFormElementIdentifierPath, enclosingCompositeFormElement) {
    getStage().getAllFormElementDomElements().forEach((el) => {
        el.parentElement?.classList.remove(getHelper().getDomElementClassName('sortableHover'));
    });
    if (enclosingCompositeFormElement) {
        getStage()
            .getAbstractViewParentFormElementWithinDomElement(placeholderDomElement)
            ?.parentElement?.classList.add(getHelper().getDomElementClassName('sortableHover'));
    }
}
/**
 * @throws 1472502237
 */
export function onAbstractViewDndUpdateBatch(movedDomElement, movedFormElementIdentifierPath, previousFormElementIdentifierPath, nextFormElementIdentifierPath) {
    let movedFormElement, parentFormElementIdentifierPath;
    if (nextFormElementIdentifierPath) {
        movedFormElement = moveFormElement(movedFormElementIdentifierPath, 'before', nextFormElementIdentifierPath);
    }
    else if (previousFormElementIdentifierPath) {
        movedFormElement = moveFormElement(movedFormElementIdentifierPath, 'after', previousFormElementIdentifierPath);
    }
    else {
        parentFormElementIdentifierPath = getStage().getAbstractViewParentFormElementIdentifierPathWithinDomElement(movedDomElement);
        if (parentFormElementIdentifierPath) {
            movedFormElement = moveFormElement(movedFormElementIdentifierPath, 'inside', parentFormElementIdentifierPath);
        }
        else {
            assert(false, 'Next element, previous or parent element need to be set.', 1472502237);
        }
    }
    getStage()
        .getAbstractViewFormElementWithinDomElement(movedDomElement)
        ?.setAttribute(getHelper().getDomElementDataAttribute('elementIdentifier'), movedFormElement.get('__identifierPath'));
}
export function onStructureDndChangeBatch(placeholderDomElement, parentFormElementIdentifierPath, enclosingCompositeFormElement) {
    getStructure()
        .getAllTreeNodes()
        .forEach((node) => node.parentElement?.classList.remove(getHelper().getDomElementClassName('sortableHover')));
    getStage()
        .getAllFormElementDomElements()
        .forEach((el) => el.parentElement?.classList.remove(getHelper().getDomElementClassName('sortableHover')));
    if (enclosingCompositeFormElement) {
        getStructure()
            .getParentTreeNodeWithinDomElement(placeholderDomElement)
            ?.parentElement?.classList.add(getHelper().getDomElementClassName('sortableHover'));
        getStage()
            .getAbstractViewFormElementDomElement(enclosingCompositeFormElement)
            ?.parentElement?.classList.add(getHelper().getDomElementClassName('sortableHover'));
    }
}
/**
 * @throws 1479048646
 */
export function onStructureDndUpdateBatch(movedDomElement, movedFormElementIdentifierPath, previousFormElementIdentifierPath, nextFormElementIdentifierPath) {
    let movedFormElement, parentFormElementIdentifierPath;
    if (nextFormElementIdentifierPath) {
        movedFormElement = moveFormElement(movedFormElementIdentifierPath, 'before', nextFormElementIdentifierPath);
    }
    else if (previousFormElementIdentifierPath) {
        movedFormElement = moveFormElement(movedFormElementIdentifierPath, 'after', previousFormElementIdentifierPath);
    }
    else {
        parentFormElementIdentifierPath = getStructure().getParentTreeNodeIdentifierPathWithinDomElement(movedDomElement);
        if (parentFormElementIdentifierPath) {
            movedFormElement = moveFormElement(movedFormElementIdentifierPath, 'inside', parentFormElementIdentifierPath);
        }
        else {
            getFormEditorApp().assert(false, 'Next element, previous or parent element need to be set.', 1479048646);
        }
    }
    getStructure()
        .getTreeNodeWithinDomElement(movedDomElement)
        ?.setAttribute(getHelper().getDomElementDataAttribute('elementIdentifier'), movedFormElement.get('__identifierPath'));
}
/* *************************************************************
 * Misc
 * ************************************************************/
export function closeEditor() {
    const el = document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderClose'));
    document.location.href = el?.href ?? '';
}
export function setElementValidationErrorClass(element, classIdentifier) {
    if (getFormEditorApp().getUtility().isUndefinedOrNull(classIdentifier)) {
        element?.classList.replace('panel-default', 'panel-danger');
    }
    else {
        element?.classList.add(getHelper().getDomElementClassName(classIdentifier));
    }
}
export function removeElementValidationErrorClass(element, classIdentifier) {
    if (getFormEditorApp().getUtility().isUndefinedOrNull(classIdentifier)) {
        element?.classList.replace('panel-danger', 'panel-default');
    }
    else {
        element?.classList.remove(getHelper().getDomElementClassName(classIdentifier));
    }
}
export function showComponent(element) {
    element?.classList.remove(getHelper().getDomElementClassName('hidden'));
    if (element) {
        element.style.display = '';
    }
}
export function hideComponent(element) {
    element?.classList.add(getHelper().getDomElementClassName('hidden'));
    if (element) {
        element.style.display = 'none';
    }
}
export function enableButton(buttonElement) {
    if (buttonElement) {
        buttonElement.disabled = false;
    }
    buttonElement?.classList.remove(getHelper().getDomElementClassName('disabled'));
}
export function disableButton(buttonElement) {
    if (buttonElement) {
        buttonElement.disabled = true;
    }
    buttonElement?.classList.add(getHelper().getDomElementClassName('disabled'));
}
export function setButtonActive(buttonElement) {
    buttonElement?.classList.add(getHelper().getDomElementClassName('active'));
}
export function removeButtonActive(buttonElement) {
    buttonElement?.classList.remove(getHelper().getDomElementClassName('active'));
}
export function showSaveButtonSpinnerIcon() {
    Icons.getIcon(getHelper().getDomElementDataAttributeValue('iconSaveSpinner'), Icons.sizes.small).then(function (markup) {
        const target = document.querySelector(getHelper().getDomElementDataIdentifierSelector('iconSave'));
        if (target) {
            const tmp = document.createElement('div');
            tmp.innerHTML = markup;
            target.replaceWith(tmp.firstElementChild ?? tmp);
        }
    });
}
export function showSaveButtonSaveIcon() {
    Icons.getIcon(getHelper().getDomElementDataAttributeValue('iconSave'), Icons.sizes.small).then(function (markup) {
        const target = document.querySelector(getHelper().getDomElementDataIdentifierSelector('iconSaveSpinner'));
        if (target) {
            const tmp = document.createElement('div');
            tmp.innerHTML = markup;
            target.replaceWith(tmp.firstElementChild ?? tmp);
        }
    });
}
export function showSaveSuccessMessage() {
    Notification.success(getFormElementDefinition(getRootFormElement(), 'saveSuccessFlashMessageTitle'), getFormElementDefinition(getRootFormElement(), 'saveSuccessFlashMessageMessage'), 2);
}
export function showSaveErrorMessage(response) {
    Notification.error(getFormElementDefinition(getRootFormElement(), 'saveErrorFlashMessageTitle'), getFormElementDefinition(getRootFormElement(), 'saveErrorFlashMessageMessage') +
        ' ' +
        response.message);
}
export function showErrorFlashMessage(title, message) {
    Notification.error(title, message, 2);
}
export function bootstrap(_formEditorApp, additionalViewModelModules) {
    formEditorApp = _formEditorApp;
    Helper.bootstrap(formEditorApp);
    structureComponentSetup();
    modalsComponentSetup();
    inspectorsComponentSetup();
    stageComponentSetup();
    buttonsSetup();
    addPropertyValidators();
    loadAdditionalModules(additionalViewModelModules);
}
