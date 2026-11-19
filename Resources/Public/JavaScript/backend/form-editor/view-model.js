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
 * WapplerSystems fork: watches the inspector for editors that render after
 * applyReadOnlyToInspector() has run. Installed once, never torn down: the
 * inspector section lives as long as the module does.
 */
let readOnlyInspectorObserver = null;
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
            // A literal attribute selector on purpose: these identifiers are written
            // straight into the markup by the fork's own editors and are not part of
            // the domElementDataAttributeValues map, so the helper would assert
            // (1478806884) — which aborted this whole pass before it hid anything.
            inspectorSection
                .querySelectorAll('[data-identifier="' + identifier + '"]')
                .forEach((button) => {
                button.classList.add('hidden');
            });
        }
    };
    disable();
    // Several editors are not in the DOM when this returns: the Lit-based ones
    // render their internals asynchronously, and the fork's own editors (email
    // content, translations, variants) build their controls after their data
    // arrives. A single pass therefore missed four of fifteen fields and all
    // three modal buttons in the contact-form example, so the pass repeats for
    // whatever appears later. Only childList is observed — setting `disabled` or
    // adding a class is an attribute change, so the observer cannot retrigger
    // itself.
    if (readOnlyInspectorObserver !== null) {
        return;
    }
    readOnlyInspectorObserver = new MutationObserver(() => {
        disable();
    });
    readOnlyInspectorObserver.observe(inspectorSection, { childList: true, subtree: true });
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
=======
import{cloneDeep as we}from"lodash-es";import*as z from"@typo3/form/backend/form-editor/tree-component-adapter.js";import*as K from"@typo3/form/backend/form-editor/modals-component.js";import*as V from"@typo3/form/backend/form-editor/inspector-component.js";import*as Ce from"@typo3/form/backend/form-editor/stage-component.js";import*as M from"@typo3/form/backend/form-editor/helper.js";import C from"@typo3/backend/icons.js";import A from"@typo3/backend/notification.js";import{loadModule as Ae}from"@typo3/core/java-script-item-processor.js";const Q={domElementClassNames:{formElementIsComposit:"formeditor-element-composit",formElementIsTopLevel:"formeditor-element-toplevel",hasError:"has-error",selectedCompositFormElement:"selected",selectedFormElement:"selected",selectedRootFormElement:"selected",selectedStagePanel:"t3-form-form-stage-selected",sortableHover:"sortable-hover",viewModeAbstract:"formeditor-module-viewmode-abstract",viewModePreview:"formeditor-module-viewmode-preview"},domElementDataAttributeNames:{abstractType:"data-element-abstract-type"},domElementDataAttributeValues:{buttonHeaderClose:"closeButton",buttonHeaderPaginationNext:"buttonPaginationNext",buttonHeaderPaginationPrevious:"buttonPaginationPrevious",buttonHeaderRedo:"redoButton",buttonHeaderSave:"saveButton",buttonHeaderUndo:"undoButton",buttonHeaderViewModeAbstract:"buttonViewModeAbstract",buttonHeaderViewModePreview:"buttonViewModePreview",buttonFormSettings:"formSettings",buttonToggleStructure:"formeditorStructureToggle",buttonCollapseInspector:"formeditorInspectorCollapse",buttonNewPage:"newPage",iconMailform:"content-form",iconSave:"actions-document-save",iconSaveSpinner:"spinner-circle",inspectorSection:"inspectorSection",inspectorTitle:"inspectorTitle",moduleLoadingIndicator:"moduleLoadingIndicator",moduleWrapper:"moduleWrapper",stageArea:"stageArea",stageContainer:"stageContainer",stageContainerInner:"stageContainerInner",stagePanelHeading:"panelHeading",stageSection:"stageSection",structure:"structure-element",structureSection:"structureSection",structureRootContainer:"treeRootContainer",structureRootElement:"treeRootElement"}};let $=!1,G=null,R=null,P=0,H=!1,T=null,J=null,X=null,Y=null,Z=null;function d(){return a().getRootFormElement()}function E(e,t,n){return a().assert(e,t,n)}function m(){return a().getUtility()}function v(){return a().getCurrentlySelectedFormElement()}function s(){return a().getPublisherSubscriber()}const Pe=/^([0-9]{4})-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])$/i;function q(e){return Pe.test(e)}function ee(e){const t=e.trim();return t.length>0&&!q(t)}function Fe(){a().addPropertyValidationValidator("NotEmpty",function(e,t){const n=e.get(t);if(!n||n===""||Array.isArray(n)&&!n.length)return a().getFormElementPropertyValidatorDefinition("NotEmpty").errorMessage||"invalid value"}),a().addPropertyValidationValidator("Integer",function(e,t){const n=e.get(t);if(n===""||n===null||isNaN(Number(n)))return a().getFormElementPropertyValidatorDefinition("Integer").errorMessage||"invalid value"}),a().addPropertyValidationValidator("IntegerOrEmpty",function(e,t){if(!m().isUndefinedOrNull(e.get(t))&&e.get(t).length>0&&isNaN(Number(e.get(t))))return a().getFormElementPropertyValidatorDefinition("Integer").errorMessage||"invalid value"}),a().addPropertyValidationValidator("NaiveEmail",function(e,t){if(!m().isUndefinedOrNull(e.get(t))&&!e.get(t).match(/\S+@\S+\.\S+/))return a().getFormElementPropertyValidatorDefinition("NaiveEmail").errorMessage||"invalid value"}),a().addPropertyValidationValidator("NaiveEmailOrEmpty",function(e,t){if(!m().isUndefinedOrNull(e.get(t))&&e.get(t).length>0&&!e.get(t).match(/\S+@\S+\.\S+/))return a().getFormElementPropertyValidatorDefinition("NaiveEmailOrEmpty").errorMessage||"invalid value"}),a().addPropertyValidationValidator("FormElementIdentifierWithinCurlyBracesInclusive",function(e,t){if(m().isUndefinedOrNull(e.get(t)))return;const r=/\{([a-z0-9-_]+)?\}/gi.exec(e.get(t));if(r&&(r[1]&&r[1]!=="__currentTimestamp"&&!a().isFormElementIdentifierUsed(r[1])||!r[1]))return a().getFormElementPropertyValidatorDefinition("FormElementIdentifierWithinCurlyBracesInclusive").errorMessage||"invalid value"}),a().addPropertyValidationValidator("FormElementIdentifierWithinCurlyBracesExclusive",function(e,t){if(m().isUndefinedOrNull(e.get(t)))return;const r=/^\{([a-z0-9-_]+)?\}$/i.exec(e.get(t));if(!r||r[1]&&r[1]!=="__currentTimestamp"&&!a().isFormElementIdentifierUsed(r[1])||!r[1])return a().getFormElementPropertyValidatorDefinition("FormElementIdentifierWithinCurlyBracesInclusive").errorMessage||"invalid value"}),a().addPropertyValidationValidator("FileSize",function(e,t){if(!m().isUndefinedOrNull(e.get(t))&&!e.get(t).match(/^(\d*\.?\d+)(B|K|M|G)$/i))return a().getFormElementPropertyValidatorDefinition("FileSize").errorMessage||"invalid value"}),a().addPropertyValidationValidator("RFC3339FullDate",function(e,t){if(m().isUndefinedOrNull(e.get(t)))return;const n=e.get(t);if(!q(n)&&!ee(n))return a().getFormElementPropertyValidatorDefinition("RFC3339FullDate").errorMessage||"invalid value"}),a().addPropertyValidationValidator("RFC3339FullDateOrEmpty",function(e,t){if(m().isUndefinedOrNull(e.get(t)))return;const n=e.get(t);if(n.length>0&&!q(n)&&!ee(n))return a().getFormElementPropertyValidatorDefinition("RFC3339FullDate").errorMessage||"invalid value"}),a().addPropertyValidationValidator("RegularExpressionPattern",function(e,t){const n=e.get(t);let r=!0;if(!m().isNonEmptyString(n))return a().getFormElementPropertyValidatorDefinition("RegularExpressionPattern").errorMessage||"invalid value";try{const i=n.match(/^\/(.*)\/[gmixsuUAJD]*$/);i!==null?new RegExp(i[1]):r=!1}catch{r=!1}if(!r)return a().getFormElementPropertyValidatorDefinition("RegularExpressionPattern").errorMessage||"invalid value"})}function Le(e){let t=[];if(typeof e=="object"&&!Array.isArray(e))for(const r of Object.keys(e))t.push(e[r]);else t=e;if(!Array.isArray(t)){s().publish("view/ready");return}const n=t.length;if(n>0){let r=0;for(let i=0;i<n;++i)Ae(t[i]).then(function(l){E(typeof l.bootstrap=="function",'The module "'+t[i].name+'" does not implement the method "bootstrap"',1475425785),l.bootstrap(a()),r++,n===r&&s().publish("view/ready")})}else s().publish("view/ready")}function Ne(){E(typeof z.bootstrap=="function",'The structure component does not implement the method "bootstrap"',1478268639),J=z.bootstrap(a(),document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("structure")])));const e=document.querySelector(o().getDomElementDataIdentifierSelector("structureRootContainer"))?.querySelector(o().getDomElementDataIdentifierSelector("iconMailform"));e&&e.setAttribute("title","identifier: "+d().get("identifier"))}function Ie(){E(typeof K.bootstrap=="function",'The modals component does not implement the method "bootstrap"',1478895106),X=K.bootstrap(a())}function Ve(){E(typeof V.bootstrap=="function",'The inspector component does not implement the method "bootstrap"',1478895106),Y=V.bootstrap(a())}function Me(){E(typeof V.bootstrap=="function",'The stage component does not implement the method "bootstrap"',1478986610),Z=Ce.bootstrap(a(),document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("stageArea")]))),c().getStagePanelDomElement()?.addEventListener("click",function(t){const n=o().getDomElementDataAttribute("identifier"),r=t.target;(r.getAttribute(n)===o().getDomElementDataAttributeValue("stagePanelHeading")||r.getAttribute(n)===o().getDomElementDataAttributeValue("stageSection")||r.getAttribute(n)===o().getDomElementDataAttributeValue("stageArea"))&&be(a().getCurrentlySelectedPageIndex()),s().publish("view/stage/panel/clicked",[])})}function Re(){const e=n=>document.querySelector(o().getDomElementDataIdentifierSelector(n));e("buttonHeaderSave")?.addEventListener("click",function(){s().publish("view/header/button/save/clicked",[])}),e("buttonToggleStructure")?.addEventListener("click",function(){e("structureSection")?.classList.toggle("formeditor-inspector-expanded")}),e("buttonCollapseInspector")?.addEventListener("click",function(){F()});const t=y();t?.addEventListener("cancel",function(n){n.preventDefault(),F()}),t?.addEventListener("click",function(n){n.target===t&&F()}),e("buttonFormSettings")?.addEventListener("click",function(){s().publish("view/header/formSettings/clicked",[])}),e("buttonNewPage")?.addEventListener("click",function(){s().publish("view/structure/button/newPage/clicked",["view/insertPages/perform"])}),e("buttonHeaderClose")?.addEventListener("click",function(n){a().getUnsavedContent()&&(n.preventDefault(),s().publish("view/header/button/close/clicked",[]))}),e("buttonHeaderUndo")?.addEventListener("click",function(){s().publish("view/undoButton/clicked",[])}),e("buttonHeaderRedo")?.addEventListener("click",function(){s().publish("view/redoButton/clicked",[])}),e("buttonHeaderViewModeAbstract")?.addEventListener("click",function(){s().publish("view/viewModeButton/abstract/clicked",[])}),e("buttonHeaderViewModePreview")?.addEventListener("click",function(){s().publish("view/viewModeButton/preview/clicked",[])}),e("structureRootContainer")?.addEventListener("click",function(){s().publish("view/structure/root/selected")}),e("buttonHeaderPaginationNext")?.addEventListener("click",function(){s().publish("view/paginationNext/clicked",[])}),e("buttonHeaderPaginationPrevious")?.addEventListener("click",function(){s().publish("view/paginationPrevious/clicked",[])})}function a(){return T}function o(e){return m().isUndefinedOrNull(e)?M.setConfiguration(Q):M.setConfiguration(e)}function g(e,t){return a().getFormElementDefinition(e,t)}function He(){return we(Q)}function Te(){return $}function qe(e){$=!!e}function u(){return J}function k(){u().renew(),s().publish("view/structure/renew/postProcess")}function ke(e){u().selectTreeNode(e)}function te(e){u().getTreeNode(e)?.classList.add(o().getDomElementClassName("selectedFormElement"))}function Ue(e){u().getTreeNode(e)?.classList.remove(o().getDomElementClassName("selectedFormElement"))}function ne(){const e=u().getTreeDomElement();e&&e.querySelectorAll(o().getDomElementClassName("selectedFormElement",!0)).forEach(t=>t.classList.remove(o().getDomElementClassName("selectedFormElement")))}function U(){return document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("structureRootContainer")]))}function oe(){return document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("structureRootElement")]))}function re(){U()?.classList.remove(o().getDomElementClassName("selectedRootFormElement"))}function ie(){U()?.classList.add(o().getDomElementClassName("selectedRootFormElement"))}function ae(e){if(m().isUndefinedOrNull(e)){const n=document.createElement("span");n.textContent=d().get("label")?d().get("label"):d().get("identifier"),e=n.textContent}const t=oe();t&&(t.textContent=e)}function Be(){u().clearAllValidationErrors();const e=a().validateFormElementRecursive(d());for(let t=0,n=e.length;t<n;++t){let r=!1;for(let i=0,l=e[t].validationResults.length;i<l;++i)if(e[t].validationResults[i].validationResults&&e[t].validationResults[i].validationResults.length>0){r=!0;break}if(r){const i=e[t].formElementIdentifierPath;u().setNodeValidationError(i,!0);const l=i.split("/");for(;l.pop();){const f=l.join("/");f&&u().setNodeChildHasError(f,!0)}}}}function D(){return X}function xe(e){m().isUndefinedOrNull(e)&&(e=v()),D().showRemoveFormElementModal(e)}function Oe(e,t,n){m().isUndefinedOrNull(n)&&(n=v()),D().showRemoveCollectionElementModal(e,t,n)}function We(){D().showCloseConfirmationModal()}function _e(e,t){D().showInsertElementsModal(e,t)}function je(e){D().showInsertPagesModal(e)}function ze(){const e=a().validateFormElementRecursive(d());D().showValidationErrorsModal(e)}function B(){return Y}function x(e){B().renderEditors(e)}function le(){const e=document.querySelector(o().getDomElementDataIdentifierSelector("inspectorSection"));e&&e.querySelector("input, select, textarea")?.focus()}function Ke(){const e=y();if(!(!e||!L())){if(e.open&&e.classList.contains("modal-closing")){P++,e.classList.remove("modal-closing");return}e.open||(e.classList.remove("modal-closing"),e.querySelector(".formeditor-inspector-inner")?.scrollTo({top:0}),e.showModal())}}function se(){if(!L()){le();return}document.querySelector(o().getDomElementDataIdentifierSelector("buttonCollapseInspector"))?.focus({preventScroll:!0})}function F(){const e=y();if(!e?.matches(":modal")||e.classList.contains("modal-closing"))return;const t=++P;e.classList.add("modal-closing"),new Promise(n=>{const r=i=>{i.target===e&&(e.removeEventListener("transitionend",r),n())};e.addEventListener("transitionend",r),window.setTimeout(()=>{e.removeEventListener("transitionend",r),n()},305)}).then(()=>{if(t!==P)return;e.close();const n=c().getAbstractViewFormElementDomElement();(n?.matches("[tabindex]")?n:n?.querySelector("button")??document.querySelector(o().getDomElementDataIdentifierSelector("buttonFormSettings")))?.focus({preventScroll:!0})})}function ce(){de(),ue().addEventListener("change",de),fe().addEventListener("change",me),Qe()}function Qe(){const e=y(),t=document.querySelector(o().getDomElementDataIdentifierSelector("inspectorTitle")),n=e?.querySelector(".formeditor-inspector-content");if(!t||!n)return;const r=()=>{const i=n.querySelector('[data-identifier="inspectorFormElementHeaderEditor"]');t.replaceChildren(...Array.from(i?.childNodes??[],l=>l.cloneNode(!0)))};new MutationObserver(r).observe(n,{childList:!0,subtree:!0}),r()}function de(){const e=y();if(!e)return;const t=!L(),n=e.matches(":modal")&&!e.classList.contains("modal-closing");e.classList.add("formeditor-inspector-instant"),P++,e.open&&e.close(),e.classList.remove("modal-closing"),e.classList.toggle("modal",!t),e.classList.toggle("modal-size-expand",!t),e.classList.toggle("modal-position-sheet",!t),e.closest(".formeditor")?.classList.toggle("formeditor-docked",t),me(),t?(H=n,e.setAttribute("open","")):H&&(H=!1,e.showModal(),se()),getComputedStyle(e).getPropertyValue("display"),e.classList.remove("formeditor-inspector-instant")}function y(){return document.querySelector(o().getDomElementDataIdentifierSelector("inspectorSection"))}function me(){y()?.classList.toggle("formeditor-inspector-sheet-end",L()&&fe().matches)}function ue(){return G??=window.matchMedia("(min-width: 950px)"),G}function fe(){if(R===null){let e=window;try{e=window.top??window,e.matchMedia("all")}catch{e=window}R=e.matchMedia("(min-width: 768px)")}return R}function L(){return!ue().matches}function $e(e,t){B().renderCollectionElementEditors(e,t)}function c(){return Z}function ge(e){c().setStageHeadline(e)}function pe(){c().getStagePanelDomElement()?.classList.add(o().getDomElementClassName("selectedStagePanel"))}function Ee(){c().getStagePanelDomElement()?.classList.remove(o().getDomElementClassName("selectedStagePanel"))}function O(){c().renderPagination()}function Ge(){c().renderUndoRedo()}function ve(){N(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModeAbstract"))),_(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModePreview"))),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.add(o().getDomElementClassName("viewModeAbstract")),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.remove(o().getDomElementClassName("viewModePreview"));const e=n=>{c().renderAbstractStageArea(void 0,n)},t=()=>{const n=g(v());c().getAllFormElementDomElements().forEach(function(r){r.addEventListener("mouseenter",function(){c().getAllFormElementDomElements().forEach(i=>{i.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))}),r.parentElement?.classList.contains(o().getDomElementClassName("formElementIsComposit"))&&!r.parentElement?.classList.contains(o().getDomElementClassName("formElementIsTopLevel"))&&r.parentElement?.classList.add(o().getDomElementClassName("sortableHover"))})}),n._isTopLevelFormElement&&!n._isCompositeFormElement&&a().isRootFormElementSelected(),W(),s().publish("view/stage/abstract/render/postProcess")};e(function(){s().publish("view/stage/abstract/render/preProcess"),t(),s().publish("view/stage/abstract/render/postProcess")})}function Je(e){N(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModePreview"))),_(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModeAbstract"))),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.add(o().getDomElementClassName("viewModePreview")),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.remove(o().getDomElementClassName("viewModeAbstract")),c().renderPreviewStageArea(e),s().publish("view/stage/preview/render/postProcess")}function Xe(){const e=a().validateFormElementRecursive(d());for(let t=0,n=e.length;t<n;++t){let r=!1;for(let i=0,l=e[t].validationResults.length;i<l;++i)if(e[t].validationResults[i].validationResults&&e[t].validationResults[i].validationResults.length>0){r=!0;break}if(r&&t>0){const i=c().getAbstractViewFormElementDomElement(e[t].formElementIdentifierPath),l=i?.querySelector("typo3-form-form-element-stage-item");l&&"invalid"in l&&(l.invalid=!0),he(i)}}}function Ye(e,t,n){const r=a().createAndAddFormElement(e,t);return n||s().publish("view/formElement/inserted",[r]),r}function h(e,t,n,r){const i=a().moveFormElement(e,t,n,!1);return r||s().publish("view/formElement/moved",[i]),i}function Ze(e,t){let n;return m().isUndefinedOrNull(e)&&(e=v()),g(e,"_isTopLevelFormElement")&&g(e,"_isCompositeFormElement")&&d().get("renderables").length===1?A.error(g(d(),"modalRemoveElementLastAvailablePageFlashMessageTitle"),g(d(),"modalRemoveElementLastAvailablePageFlashMessageMessage"),2):(n=a().removeFormElement(e,!1),t||s().publish("view/formElement/removed",[n])),n}function et(e,t,n,r,i,l){a().createAndAddPropertyCollectionElement(e,t,n,r,i),l||s().publish("view/collectionElement/new/added",[e,t,n,r,i])}function tt(e,t,n,r,i,l){m().isUndefinedOrNull(i)&&(i=v()),a().movePropertyCollectionElement(e,t,n,r,i,!1),l||s().publish("view/collectionElement/moved",[e,t,n,r,i])}function nt(e,t,n,r){let i,l;a().removePropertyCollectionElement(e,t,n);const f=a().getPropertyCollectionElementConfiguration(e,t);if(Array.isArray(f.editors)){for(let p=0,ye=f.editors.length;p<ye;++p)if(Array.isArray(f.editors[p].additionalElementPropertyPaths))for(let b=0,I=f.editors[p].additionalElementPropertyPaths.length;b<I;++b)v().unset(f.editors[p].additionalElementPropertyPaths[b],!0);else if(f.editors[p].identifier==="validationErrorMessage"&&(l=a().buildPropertyPath(f.editors[p].propertyPath),i=v().get(l),!m().isUndefinedOrNull(i))){for(let b=0,I=f.editors[p].errorCodes.length;b<I;++b)for(let w=0,j=i.length;w<j;++w)parseInt(f.editors[p].errorCodes[b],10)===parseInt(i[w].code,10)&&(i.splice(w,1),--j);v().set(l,i)}}r||s().publish("view/collectionElement/removed",[e,t,n])}function W(){const e=g(v());if(De(),ne(),!a().isRootFormElementSelected()){re(),te();const t=c().getAbstractViewFormElementDomElement();e._isTopLevelFormElement?pe():(t?.classList.add(o().getDomElementClassName("selectedFormElement")),c().createAndAddAbstractViewFormElementToolbar(t,void 0)),c().getAllFormElementDomElements().forEach(n=>{n.parentElement?.classList.remove(o().getDomElementClassName("selectedCompositFormElement"))}),!e._isTopLevelFormElement&&e._isCompositeFormElement&&t?.parentElement?.classList.add(o().getDomElementClassName("selectedCompositFormElement"))}}function be(e){E(typeof e=="number",'Invalid parameter "pageIndex"',1478651732),E(e>=0,'Invalid parameter "pageIndex"',1478651733),E(e<d().get("renderables").length,'Invalid parameter "pageIndex"',1478651734),a().setCurrentlySelectedFormElement(d().get("renderables")[e]),k(),O(),W(),x()}function De(){c().getAllFormElementDomElements().forEach(e=>e.classList.remove(o().getDomElementClassName("selectedFormElement"))),Ee(),c().getAllFormElementDomElements().forEach(e=>e.parentElement?.classList.remove(o().getDomElementClassName("sortableHover")))}function ot(){ge(),ae(),ve(),k(),ie(),x(),O(),Se(document.querySelector(o().getDomElementDataIdentifierSelector("moduleLoadingIndicator"))),S(document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))),S(document.querySelector(o().getDomElementDataIdentifierSelector("inspectorSection"))),ce(),S(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderSave"))),S(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderClose"))),S(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderUndo"))),S(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderRedo"))),N(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModeAbstract")))}function rt(e,t){t?.classList.remove(o().getDomElementClassName("sortableHover"))}function it(e,t,n){c().getAllFormElementDomElements().forEach(r=>{r.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))}),n&&c().getAbstractViewParentFormElementWithinDomElement(e)?.parentElement?.classList.add(o().getDomElementClassName("sortableHover"))}function at(e,t,n,r){let i,l;r?i=h(t,"before",r):n?i=h(t,"after",n):(l=c().getAbstractViewParentFormElementIdentifierPathWithinDomElement(e),l?i=h(t,"inside",l):E(!1,"Next element, previous or parent element need to be set.",1472502237)),c().getAbstractViewFormElementWithinDomElement(e)?.setAttribute(o().getDomElementDataAttribute("elementIdentifier"),i.get("__identifierPath"))}function lt(e,t,n){u().getAllTreeNodes().forEach(r=>r.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))),c().getAllFormElementDomElements().forEach(r=>r.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))),n&&(u().getParentTreeNodeWithinDomElement(e)?.parentElement?.classList.add(o().getDomElementClassName("sortableHover")),c().getAbstractViewFormElementDomElement(n)?.parentElement?.classList.add(o().getDomElementClassName("sortableHover")))}function st(e,t,n,r){let i,l;r?i=h(t,"before",r):n?i=h(t,"after",n):(l=u().getParentTreeNodeIdentifierPathWithinDomElement(e),l?i=h(t,"inside",l):a().assert(!1,"Next element, previous or parent element need to be set.",1479048646)),u().getTreeNodeWithinDomElement(e)?.setAttribute(o().getDomElementDataAttribute("elementIdentifier"),i.get("__identifierPath"))}function ct(){const e=document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderClose"));document.location.href=e?.href??""}function he(e,t){a().getUtility().isUndefinedOrNull(t)?e?.classList.replace("panel-default","panel-danger"):e?.classList.add(o().getDomElementClassName(t))}function dt(e,t){a().getUtility().isUndefinedOrNull(t)?e?.classList.replace("panel-danger","panel-default"):e?.classList.remove(o().getDomElementClassName(t))}function S(e){e?.classList.remove(o().getDomElementClassName("hidden")),e&&(e.style.display="")}function Se(e){e?.classList.add(o().getDomElementClassName("hidden")),e&&(e.style.display="none")}function mt(e){e&&(e.disabled=!1),e?.classList.remove(o().getDomElementClassName("disabled"))}function ut(e){e&&(e.disabled=!0),e?.classList.add(o().getDomElementClassName("disabled"))}function N(e){e?.classList.add(o().getDomElementClassName("active"))}function _(e){e?.classList.remove(o().getDomElementClassName("active"))}function ft(){C.getIcon(o().getDomElementDataAttributeValue("iconSaveSpinner"),C.sizes.small).then(function(e){const t=document.querySelector(o().getDomElementDataIdentifierSelector("iconSave"));if(t){const n=document.createElement("div");n.innerHTML=e,t.replaceWith(n.firstElementChild??n)}})}function gt(){C.getIcon(o().getDomElementDataAttributeValue("iconSave"),C.sizes.small).then(function(e){const t=document.querySelector(o().getDomElementDataIdentifierSelector("iconSaveSpinner"));if(t){const n=document.createElement("div");n.innerHTML=e,t.replaceWith(n.firstElementChild??n)}})}function pt(){A.success(g(d(),"saveSuccessFlashMessageTitle"),g(d(),"saveSuccessFlashMessageMessage"),2)}function Et(e){A.error(g(d(),"saveErrorFlashMessageTitle"),g(d(),"saveErrorFlashMessageMessage")+" "+e.message)}function vt(e,t){A.error(e,t,2)}function bt(e,t){T=e,M.bootstrap(T),Ne(),Ie(),Ve(),Me(),Re(),Fe(),Le(t)}export{Xe as addAbstractViewValidationResults,pe as addStagePanelSelection,ie as addStructureRootElementSelection,te as addStructureSelection,Be as addStructureValidationResults,bt as bootstrap,ct as closeEditor,Ye as createAndAddFormElement,et as createAndAddPropertyCollectionElement,ut as disableButton,mt as enableButton,le as focusFirstInspectorInput,se as focusInspectorSidebar,He as getConfiguration,a as getFormEditorApp,g as getFormElementDefinition,o as getHelper,B as getInspector,D as getModals,Te as getPreviewMode,c as getStage,u as getStructure,U as getStructureRootContainer,oe as getStructureRootElement,Se as hideComponent,F as hideInspectorSidebar,h as moveFormElement,tt as movePropertyCollectionElement,it as onAbstractViewDndChangeBatch,rt as onAbstractViewDndStartBatch,at as onAbstractViewDndUpdateBatch,lt as onStructureDndChangeBatch,st as onStructureDndUpdateBatch,ot as onViewReadyBatch,W as refreshSelectedElementItemsBatch,De as removeAllStageElementSelectionsBatch,ne as removeAllStructureSelections,_ as removeButtonActive,dt as removeElementValidationErrorClass,Ze as removeFormElement,nt as removePropertyCollectionElement,Ee as removeStagePanelSelection,re as removeStructureRootElementSelection,Ue as removeStructureSelection,ve as renderAbstractStageArea,$e as renderInspectorCollectionElementEditors,x as renderInspectorEditors,O as renderPagination,Je as renderPreviewStageArea,Ge as renderUndoRedo,k as renewStructure,be as selectPageBatch,ke as selectStructureNode,N as setButtonActive,he as setElementValidationErrorClass,qe as setPreviewMode,ge as setStageHeadline,ae as setStructureRootElementTitle,ce as setupInspectorLayout,We as showCloseConfirmationModal,S as showComponent,vt as showErrorFlashMessage,_e as showInsertElementsModal,je as showInsertPagesModal,Ke as showInspectorSidebar,Oe as showRemoveCollectionElementModal,xe as showRemoveFormElementModal,gt as showSaveButtonSaveIcon,ft as showSaveButtonSpinnerIcon,Et as showSaveErrorMessage,pt as showSaveSuccessMessage,ze as showValidationErrorsModal};
>>>>>>> ca975574 ([TASK] Show the form editor inspector as sheet on small screens)
