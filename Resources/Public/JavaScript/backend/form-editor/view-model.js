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
import{cloneDeep as de}from"lodash-es";import*as x from"@typo3/form/backend/form-editor/tree-component-adapter.js";import*as B from"@typo3/form/backend/form-editor/modals-component.js";import*as F from"@typo3/form/backend/form-editor/inspector-component.js";import*as me from"@typo3/form/backend/form-editor/stage-component.js";import*as N from"@typo3/form/backend/form-editor/helper.js";import y from"@typo3/backend/icons.js";import w from"@typo3/backend/notification.js";import{loadModule as ce}from"@typo3/core/java-script-item-processor.js";const W={domElementClassNames:{formElementIsComposit:"formeditor-element-composit",formElementIsTopLevel:"formeditor-element-toplevel",hasError:"has-error",selectedCompositFormElement:"selected",selectedFormElement:"selected",selectedRootFormElement:"selected",selectedStagePanel:"t3-form-form-stage-selected",sortableHover:"sortable-hover",viewModeAbstract:"formeditor-module-viewmode-abstract",viewModePreview:"formeditor-module-viewmode-preview"},domElementDataAttributeNames:{abstractType:"data-element-abstract-type"},domElementDataAttributeValues:{buttonHeaderClose:"closeButton",buttonHeaderPaginationNext:"buttonPaginationNext",buttonHeaderPaginationPrevious:"buttonPaginationPrevious",buttonHeaderRedo:"redoButton",buttonHeaderSave:"saveButton",buttonHeaderUndo:"undoButton",buttonHeaderViewModeAbstract:"buttonViewModeAbstract",buttonHeaderViewModePreview:"buttonViewModePreview",buttonFormSettings:"formSettings",buttonToggleStructure:"formeditorStructureToggle",buttonExpandInspector:"formeditorInspectorExpand",buttonCollapseInspector:"formeditorInspectorCollapse",buttonNewPage:"newPage",iconMailform:"content-form",iconSave:"actions-document-save",iconSaveSpinner:"spinner-circle",inspectorSection:"inspectorSection",moduleLoadingIndicator:"moduleLoadingIndicator",moduleWrapper:"moduleWrapper",stageArea:"stageArea",stageContainer:"stageContainer",stageContainerInner:"stageContainerInner",stagePanelHeading:"panelHeading",stageSection:"stageSection",structure:"structure-element",structureSection:"structureSection",structureRootContainer:"treeRootContainer",structureRootElement:"treeRootElement"}};let O=!1,V=null,_=null,j=null,z=null,K=null;function m(){return i().getRootFormElement()}function p(e,t,n){return i().assert(e,t,n)}function c(){return i().getUtility()}function v(){return i().getCurrentlySelectedFormElement()}function s(){return i().getPublisherSubscriber()}const ue=/^([0-9]{4})-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])$/i;function I(e){return ue.test(e)}function $(e){const t=e.trim();return t.length>0&&!I(t)}function fe(){i().addPropertyValidationValidator("NotEmpty",function(e,t){const n=e.get(t);if(!n||n===""||Array.isArray(n)&&!n.length)return i().getFormElementPropertyValidatorDefinition("NotEmpty").errorMessage||"invalid value"}),i().addPropertyValidationValidator("Integer",function(e,t){const n=e.get(t);if(n===""||n===null||isNaN(Number(n)))return i().getFormElementPropertyValidatorDefinition("Integer").errorMessage||"invalid value"}),i().addPropertyValidationValidator("IntegerOrEmpty",function(e,t){if(!c().isUndefinedOrNull(e.get(t))&&e.get(t).length>0&&isNaN(Number(e.get(t))))return i().getFormElementPropertyValidatorDefinition("Integer").errorMessage||"invalid value"}),i().addPropertyValidationValidator("NaiveEmail",function(e,t){if(!c().isUndefinedOrNull(e.get(t))&&!e.get(t).match(/\S+@\S+\.\S+/))return i().getFormElementPropertyValidatorDefinition("NaiveEmail").errorMessage||"invalid value"}),i().addPropertyValidationValidator("NaiveEmailOrEmpty",function(e,t){if(!c().isUndefinedOrNull(e.get(t))&&e.get(t).length>0&&!e.get(t).match(/\S+@\S+\.\S+/))return i().getFormElementPropertyValidatorDefinition("NaiveEmailOrEmpty").errorMessage||"invalid value"}),i().addPropertyValidationValidator("FormElementIdentifierWithinCurlyBracesInclusive",function(e,t){if(c().isUndefinedOrNull(e.get(t)))return;const r=/\{([a-z0-9-_]+)?\}/gi.exec(e.get(t));if(r&&(r[1]&&r[1]!=="__currentTimestamp"&&!i().isFormElementIdentifierUsed(r[1])||!r[1]))return i().getFormElementPropertyValidatorDefinition("FormElementIdentifierWithinCurlyBracesInclusive").errorMessage||"invalid value"}),i().addPropertyValidationValidator("FormElementIdentifierWithinCurlyBracesExclusive",function(e,t){if(c().isUndefinedOrNull(e.get(t)))return;const r=/^\{([a-z0-9-_]+)?\}$/i.exec(e.get(t));if(!r||r[1]&&r[1]!=="__currentTimestamp"&&!i().isFormElementIdentifierUsed(r[1])||!r[1])return i().getFormElementPropertyValidatorDefinition("FormElementIdentifierWithinCurlyBracesInclusive").errorMessage||"invalid value"}),i().addPropertyValidationValidator("FileSize",function(e,t){if(!c().isUndefinedOrNull(e.get(t))&&!e.get(t).match(/^(\d*\.?\d+)(B|K|M|G)$/i))return i().getFormElementPropertyValidatorDefinition("FileSize").errorMessage||"invalid value"}),i().addPropertyValidationValidator("RFC3339FullDate",function(e,t){if(c().isUndefinedOrNull(e.get(t)))return;const n=e.get(t);if(!I(n)&&!$(n))return i().getFormElementPropertyValidatorDefinition("RFC3339FullDate").errorMessage||"invalid value"}),i().addPropertyValidationValidator("RFC3339FullDateOrEmpty",function(e,t){if(c().isUndefinedOrNull(e.get(t)))return;const n=e.get(t);if(n.length>0&&!I(n)&&!$(n))return i().getFormElementPropertyValidatorDefinition("RFC3339FullDate").errorMessage||"invalid value"}),i().addPropertyValidationValidator("RegularExpressionPattern",function(e,t){const n=e.get(t);let r=!0;if(!c().isNonEmptyString(n))return i().getFormElementPropertyValidatorDefinition("RegularExpressionPattern").errorMessage||"invalid value";try{const a=n.match(/^\/(.*)\/[gmixsuUAJD]*$/);a!==null?new RegExp(a[1]):r=!1}catch{r=!1}if(!r)return i().getFormElementPropertyValidatorDefinition("RegularExpressionPattern").errorMessage||"invalid value"})}function ge(e){let t=[];if(typeof e=="object"&&!Array.isArray(e))for(const r of Object.keys(e))t.push(e[r]);else t=e;if(!Array.isArray(t)){s().publish("view/ready");return}const n=t.length;if(n>0){let r=0;for(let a=0;a<n;++a)ce(t[a]).then(function(l){p(typeof l.bootstrap=="function",'The module "'+t[a].name+'" does not implement the method "bootstrap"',1475425785),l.bootstrap(i()),r++,n===r&&s().publish("view/ready")})}else s().publish("view/ready")}function Ee(){p(typeof x.bootstrap=="function",'The structure component does not implement the method "bootstrap"',1478268639),_=x.bootstrap(i(),document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("structure")])));const e=document.querySelector(o().getDomElementDataIdentifierSelector("structureRootContainer"))?.querySelector(o().getDomElementDataIdentifierSelector("iconMailform"));e&&e.setAttribute("title","identifier: "+m().get("identifier"))}function pe(){p(typeof B.bootstrap=="function",'The modals component does not implement the method "bootstrap"',1478895106),j=B.bootstrap(i())}function ve(){p(typeof F.bootstrap=="function",'The inspector component does not implement the method "bootstrap"',1478895106),z=F.bootstrap(i())}function be(){p(typeof F.bootstrap=="function",'The stage component does not implement the method "bootstrap"',1478986610),K=me.bootstrap(i(),document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("stageArea")]))),d().getStagePanelDomElement()?.addEventListener("click",function(t){const n=o().getDomElementDataAttribute("identifier"),r=t.target;(r.getAttribute(n)===o().getDomElementDataAttributeValue("stagePanelHeading")||r.getAttribute(n)===o().getDomElementDataAttributeValue("stageSection")||r.getAttribute(n)===o().getDomElementDataAttributeValue("stageArea"))&&re(i().getCurrentlySelectedPageIndex()),s().publish("view/stage/panel/clicked",[])})}function De(){const e=t=>document.querySelector(o().getDomElementDataIdentifierSelector(t));e("buttonHeaderSave")?.addEventListener("click",function(){s().publish("view/header/button/save/clicked",[])}),e("buttonToggleStructure")?.addEventListener("click",function(){e("structureSection")?.classList.toggle("formeditor-inspector-expanded")}),e("buttonExpandInspector")?.addEventListener("click",function(){e("inspectorSection")?.classList.add("formeditor-inspector-expanded")}),e("buttonCollapseInspector")?.addEventListener("click",function(){e("inspectorSection")?.classList.remove("formeditor-inspector-expanded")}),e("buttonFormSettings")?.addEventListener("click",function(){s().publish("view/header/formSettings/clicked",[])}),e("buttonNewPage")?.addEventListener("click",function(){s().publish("view/structure/button/newPage/clicked",["view/insertPages/perform"])}),e("buttonHeaderClose")?.addEventListener("click",function(t){i().getUnsavedContent()&&(t.preventDefault(),s().publish("view/header/button/close/clicked",[]))}),e("buttonHeaderUndo")?.addEventListener("click",function(){s().publish("view/undoButton/clicked",[])}),e("buttonHeaderRedo")?.addEventListener("click",function(){s().publish("view/redoButton/clicked",[])}),e("buttonHeaderViewModeAbstract")?.addEventListener("click",function(){s().publish("view/viewModeButton/abstract/clicked",[])}),e("buttonHeaderViewModePreview")?.addEventListener("click",function(){s().publish("view/viewModeButton/preview/clicked",[])}),e("structureRootContainer")?.addEventListener("click",function(){s().publish("view/structure/root/selected")}),e("buttonHeaderPaginationNext")?.addEventListener("click",function(){s().publish("view/paginationNext/clicked",[])}),e("buttonHeaderPaginationPrevious")?.addEventListener("click",function(){s().publish("view/paginationPrevious/clicked",[])})}function i(){return V}function o(e){return c().isUndefinedOrNull(e)?N.setConfiguration(W):N.setConfiguration(e)}function g(e,t){return i().getFormElementDefinition(e,t)}function Se(){return de(W)}function he(){return O}function Ce(e){O=!!e}function u(){return _}function L(){u().renew(),s().publish("view/structure/renew/postProcess")}function ye(e){u().selectTreeNode(e)}function G(e){u().getTreeNode(e)?.classList.add(o().getDomElementClassName("selectedFormElement"))}function we(e){u().getTreeNode(e)?.classList.remove(o().getDomElementClassName("selectedFormElement"))}function J(){const e=u().getTreeDomElement();e&&e.querySelectorAll(o().getDomElementClassName("selectedFormElement",!0)).forEach(t=>t.classList.remove(o().getDomElementClassName("selectedFormElement")))}function M(){return document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("structureRootContainer")]))}function Q(){return document.querySelector(o().getDomElementDataAttribute("identifier","bracesWithKeyValue",[o().getDomElementDataAttributeValue("structureRootElement")]))}function X(){M()?.classList.remove(o().getDomElementClassName("selectedRootFormElement"))}function Y(){M()?.classList.add(o().getDomElementClassName("selectedRootFormElement"))}function Z(e){if(c().isUndefinedOrNull(e)){const n=document.createElement("span");n.textContent=m().get("label")?m().get("label"):m().get("identifier"),e=n.textContent}const t=Q();t&&(t.textContent=e)}function Pe(){u().clearAllValidationErrors();const e=i().validateFormElementRecursive(m());for(let t=0,n=e.length;t<n;++t){let r=!1;for(let a=0,l=e[t].validationResults.length;a<l;++a)if(e[t].validationResults[a].validationResults&&e[t].validationResults[a].validationResults.length>0){r=!0;break}if(r){const a=e[t].formElementIdentifierPath;u().setNodeValidationError(a,!0);const l=a.split("/");for(;l.pop();){const f=l.join("/");f&&u().setNodeChildHasError(f,!0)}}}}function D(){return j}function Ae(e){c().isUndefinedOrNull(e)&&(e=v()),D().showRemoveFormElementModal(e)}function Fe(e,t,n){c().isUndefinedOrNull(n)&&(n=v()),D().showRemoveCollectionElementModal(e,t,n)}function Ne(){D().showCloseConfirmationModal()}function Ve(e,t){D().showInsertElementsModal(e,t)}function Ie(e){D().showInsertPagesModal(e)}function Le(){const e=i().validateFormElementRecursive(m());D().showValidationErrorsModal(e)}function R(){return z}function H(e){R().renderEditors(e)}function Me(){const e=document.querySelector(o().getDomElementDataIdentifierSelector("inspectorSection"));e&&e.querySelector("input, select, textarea")?.focus()}function Re(){document.querySelector(o().getDomElementDataIdentifierSelector("inspectorSection"))?.classList.add("formeditor-inspector-expanded")}function He(e,t){R().renderCollectionElementEditors(e,t)}function d(){return K}function ee(e){d().setStageHeadline(e)}function te(){d().getStagePanelDomElement()?.classList.add(o().getDomElementClassName("selectedStagePanel"))}function ne(){d().getStagePanelDomElement()?.classList.remove(o().getDomElementClassName("selectedStagePanel"))}function T(){d().renderPagination()}function Te(){d().renderUndoRedo()}function oe(){P(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModeAbstract"))),q(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModePreview"))),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.add(o().getDomElementClassName("viewModeAbstract")),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.remove(o().getDomElementClassName("viewModePreview"));const e=n=>{d().renderAbstractStageArea(void 0,n)},t=()=>{const n=g(v());d().getAllFormElementDomElements().forEach(function(r){r.addEventListener("mouseenter",function(){d().getAllFormElementDomElements().forEach(a=>{a.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))}),r.parentElement?.classList.contains(o().getDomElementClassName("formElementIsComposit"))&&!r.parentElement?.classList.contains(o().getDomElementClassName("formElementIsTopLevel"))&&r.parentElement?.classList.add(o().getDomElementClassName("sortableHover"))})}),n._isTopLevelFormElement&&!n._isCompositeFormElement&&i().isRootFormElementSelected(),U(),s().publish("view/stage/abstract/render/postProcess")};e(function(){s().publish("view/stage/abstract/render/preProcess"),t(),s().publish("view/stage/abstract/render/postProcess")})}function Ue(e){P(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModePreview"))),q(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModeAbstract"))),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.add(o().getDomElementClassName("viewModePreview")),document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))?.classList.remove(o().getDomElementClassName("viewModeAbstract")),d().renderPreviewStageArea(e),s().publish("view/stage/preview/render/postProcess")}function qe(){const e=i().validateFormElementRecursive(m());for(let t=0,n=e.length;t<n;++t){let r=!1;for(let a=0,l=e[t].validationResults.length;a<l;++a)if(e[t].validationResults[a].validationResults&&e[t].validationResults[a].validationResults.length>0){r=!0;break}if(r&&t>0){const a=d().getAbstractViewFormElementDomElement(e[t].formElementIdentifierPath),l=a?.querySelector("typo3-form-form-element-stage-item");l&&"invalid"in l&&(l.invalid=!0),ae(a)}}}function ke(e,t,n){const r=i().createAndAddFormElement(e,t);return n||s().publish("view/formElement/inserted",[r]),r}function S(e,t,n,r){const a=i().moveFormElement(e,t,n,!1);return r||s().publish("view/formElement/moved",[a]),a}function xe(e,t){let n;return c().isUndefinedOrNull(e)&&(e=v()),g(e,"_isTopLevelFormElement")&&g(e,"_isCompositeFormElement")&&m().get("renderables").length===1?w.error(g(m(),"modalRemoveElementLastAvailablePageFlashMessageTitle"),g(m(),"modalRemoveElementLastAvailablePageFlashMessageMessage"),2):(n=i().removeFormElement(e,!1),t||s().publish("view/formElement/removed",[n])),n}function Be(e,t,n,r,a,l){i().createAndAddPropertyCollectionElement(e,t,n,r,a),l||s().publish("view/collectionElement/new/added",[e,t,n,r,a])}function We(e,t,n,r,a,l){c().isUndefinedOrNull(a)&&(a=v()),i().movePropertyCollectionElement(e,t,n,r,a,!1),l||s().publish("view/collectionElement/moved",[e,t,n,r,a])}function Oe(e,t,n,r){let a,l;i().removePropertyCollectionElement(e,t,n);const f=i().getPropertyCollectionElementConfiguration(e,t);if(Array.isArray(f.editors)){for(let E=0,se=f.editors.length;E<se;++E)if(Array.isArray(f.editors[E].additionalElementPropertyPaths))for(let b=0,A=f.editors[E].additionalElementPropertyPaths.length;b<A;++b)v().unset(f.editors[E].additionalElementPropertyPaths[b],!0);else if(f.editors[E].identifier==="validationErrorMessage"&&(l=i().buildPropertyPath(f.editors[E].propertyPath),a=v().get(l),!c().isUndefinedOrNull(a))){for(let b=0,A=f.editors[E].errorCodes.length;b<A;++b)for(let C=0,k=a.length;C<k;++C)parseInt(f.editors[E].errorCodes[b],10)===parseInt(a[C].code,10)&&(a.splice(C,1),--k);v().set(l,a)}}r||s().publish("view/collectionElement/removed",[e,t,n])}function U(){const e=g(v());if(ie(),J(),!i().isRootFormElementSelected()){X(),G();const t=d().getAbstractViewFormElementDomElement();e._isTopLevelFormElement?te():(t?.classList.add(o().getDomElementClassName("selectedFormElement")),d().createAndAddAbstractViewFormElementToolbar(t,void 0)),d().getAllFormElementDomElements().forEach(n=>{n.parentElement?.classList.remove(o().getDomElementClassName("selectedCompositFormElement"))}),!e._isTopLevelFormElement&&e._isCompositeFormElement&&t?.parentElement?.classList.add(o().getDomElementClassName("selectedCompositFormElement"))}}function re(e){p(typeof e=="number",'Invalid parameter "pageIndex"',1478651732),p(e>=0,'Invalid parameter "pageIndex"',1478651733),p(e<m().get("renderables").length,'Invalid parameter "pageIndex"',1478651734),i().setCurrentlySelectedFormElement(m().get("renderables")[e]),L(),T(),U(),H()}function ie(){d().getAllFormElementDomElements().forEach(e=>e.classList.remove(o().getDomElementClassName("selectedFormElement"))),ne(),d().getAllFormElementDomElements().forEach(e=>e.parentElement?.classList.remove(o().getDomElementClassName("sortableHover")))}function _e(){ee(),Z(),oe(),L(),Y(),H(),T(),le(document.querySelector(o().getDomElementDataIdentifierSelector("moduleLoadingIndicator"))),h(document.querySelector(o().getDomElementDataIdentifierSelector("moduleWrapper"))),h(document.querySelector(o().getDomElementDataIdentifierSelector("inspectorSection"))),h(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderSave"))),h(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderClose"))),h(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderUndo"))),h(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderRedo"))),P(document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderViewModeAbstract")))}function je(e,t){t?.classList.remove(o().getDomElementClassName("sortableHover"))}function ze(e,t,n){d().getAllFormElementDomElements().forEach(r=>{r.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))}),n&&d().getAbstractViewParentFormElementWithinDomElement(e)?.parentElement?.classList.add(o().getDomElementClassName("sortableHover"))}function Ke(e,t,n,r){let a,l;r?a=S(t,"before",r):n?a=S(t,"after",n):(l=d().getAbstractViewParentFormElementIdentifierPathWithinDomElement(e),l?a=S(t,"inside",l):p(!1,"Next element, previous or parent element need to be set.",1472502237)),d().getAbstractViewFormElementWithinDomElement(e)?.setAttribute(o().getDomElementDataAttribute("elementIdentifier"),a.get("__identifierPath"))}function $e(e,t,n){u().getAllTreeNodes().forEach(r=>r.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))),d().getAllFormElementDomElements().forEach(r=>r.parentElement?.classList.remove(o().getDomElementClassName("sortableHover"))),n&&(u().getParentTreeNodeWithinDomElement(e)?.parentElement?.classList.add(o().getDomElementClassName("sortableHover")),d().getAbstractViewFormElementDomElement(n)?.parentElement?.classList.add(o().getDomElementClassName("sortableHover")))}function Ge(e,t,n,r){let a,l;r?a=S(t,"before",r):n?a=S(t,"after",n):(l=u().getParentTreeNodeIdentifierPathWithinDomElement(e),l?a=S(t,"inside",l):i().assert(!1,"Next element, previous or parent element need to be set.",1479048646)),u().getTreeNodeWithinDomElement(e)?.setAttribute(o().getDomElementDataAttribute("elementIdentifier"),a.get("__identifierPath"))}function Je(){const e=document.querySelector(o().getDomElementDataIdentifierSelector("buttonHeaderClose"));document.location.href=e?.href??""}function ae(e,t){i().getUtility().isUndefinedOrNull(t)?e?.classList.replace("panel-default","panel-danger"):e?.classList.add(o().getDomElementClassName(t))}function Qe(e,t){i().getUtility().isUndefinedOrNull(t)?e?.classList.replace("panel-danger","panel-default"):e?.classList.remove(o().getDomElementClassName(t))}function h(e){e?.classList.remove(o().getDomElementClassName("hidden")),e&&(e.style.display="")}function le(e){e?.classList.add(o().getDomElementClassName("hidden")),e&&(e.style.display="none")}function Xe(e){e&&(e.disabled=!1),e?.classList.remove(o().getDomElementClassName("disabled"))}function Ye(e){e&&(e.disabled=!0),e?.classList.add(o().getDomElementClassName("disabled"))}function P(e){e?.classList.add(o().getDomElementClassName("active"))}function q(e){e?.classList.remove(o().getDomElementClassName("active"))}function Ze(){y.getIcon(o().getDomElementDataAttributeValue("iconSaveSpinner"),y.sizes.small).then(function(e){const t=document.querySelector(o().getDomElementDataIdentifierSelector("iconSave"));if(t){const n=document.createElement("div");n.innerHTML=e,t.replaceWith(n.firstElementChild??n)}})}function et(){y.getIcon(o().getDomElementDataAttributeValue("iconSave"),y.sizes.small).then(function(e){const t=document.querySelector(o().getDomElementDataIdentifierSelector("iconSaveSpinner"));if(t){const n=document.createElement("div");n.innerHTML=e,t.replaceWith(n.firstElementChild??n)}})}function tt(){w.success(g(m(),"saveSuccessFlashMessageTitle"),g(m(),"saveSuccessFlashMessageMessage"),2)}function nt(e){w.error(g(m(),"saveErrorFlashMessageTitle"),g(m(),"saveErrorFlashMessageMessage")+" "+e.message)}function ot(e,t){w.error(e,t,2)}function rt(e,t){V=e,N.bootstrap(V),Ee(),pe(),ve(),be(),De(),fe(),ge(t)}export{qe as addAbstractViewValidationResults,te as addStagePanelSelection,Y as addStructureRootElementSelection,G as addStructureSelection,Pe as addStructureValidationResults,rt as bootstrap,Je as closeEditor,ke as createAndAddFormElement,Be as createAndAddPropertyCollectionElement,Ye as disableButton,Xe as enableButton,Me as focusFirstInspectorInput,Se as getConfiguration,i as getFormEditorApp,g as getFormElementDefinition,o as getHelper,R as getInspector,D as getModals,he as getPreviewMode,d as getStage,u as getStructure,M as getStructureRootContainer,Q as getStructureRootElement,le as hideComponent,S as moveFormElement,We as movePropertyCollectionElement,ze as onAbstractViewDndChangeBatch,je as onAbstractViewDndStartBatch,Ke as onAbstractViewDndUpdateBatch,$e as onStructureDndChangeBatch,Ge as onStructureDndUpdateBatch,_e as onViewReadyBatch,U as refreshSelectedElementItemsBatch,ie as removeAllStageElementSelectionsBatch,J as removeAllStructureSelections,q as removeButtonActive,Qe as removeElementValidationErrorClass,xe as removeFormElement,Oe as removePropertyCollectionElement,ne as removeStagePanelSelection,X as removeStructureRootElementSelection,we as removeStructureSelection,oe as renderAbstractStageArea,He as renderInspectorCollectionElementEditors,H as renderInspectorEditors,T as renderPagination,Ue as renderPreviewStageArea,Te as renderUndoRedo,L as renewStructure,re as selectPageBatch,ye as selectStructureNode,P as setButtonActive,ae as setElementValidationErrorClass,Ce as setPreviewMode,ee as setStageHeadline,Z as setStructureRootElementTitle,Ne as showCloseConfirmationModal,h as showComponent,ot as showErrorFlashMessage,Ve as showInsertElementsModal,Ie as showInsertPagesModal,Re as showInspectorSidebar,Fe as showRemoveCollectionElementModal,Ae as showRemoveFormElementModal,et as showSaveButtonSaveIcon,Ze as showSaveButtonSpinnerIcon,nt as showSaveErrorMessage,tt as showSaveSuccessMessage,Le as showValidationErrorsModal};
>>>>>>> 637d4a4e ([TASK] Make the form editor null-safe)
