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
 * Module: @typo3/form/backend/form-editor/mediator
 */
import * as Helper from '@typo3/form/backend/form-editor/helper.js';
let formEditorApp = null;
let viewModel = null;
function getFormEditorApp() {
    return formEditorApp;
}
function getViewModel() {
    return viewModel;
}
function getUtility() {
    return getFormEditorApp().getUtility();
}
function assert(test, message, messageCode) {
    return getFormEditorApp().assert(test, message, messageCode);
}
function getHelper(_configuration) {
    if (getUtility().isUndefinedOrNull(_configuration)) {
        return Helper.setConfiguration(getViewModel().getConfiguration());
    }
    return Helper.setConfiguration(_configuration);
}
function getCurrentlySelectedFormElement() {
    return getFormEditorApp().getCurrentlySelectedFormElement();
}
function getPublisherSubscriber() {
    return getFormEditorApp().getPublisherSubscriber();
}
function getRootFormElement() {
    return getFormEditorApp().getRootFormElement();
}
/**
 * WapplerSystems fork: subscribe unless the form is open for viewing only.
 *
 * Every topic that changes the form definition goes through this instead of
 * subscribing directly, so read-only mode is enforced in one place rather than
 * relying on the view to withhold each affordance. A click that slips past a
 * hidden button, a drag the tree still bridges, or an additional view model
 * module publishing on its own then finds no subscriber — and the definition
 * stays as loaded. The server refuses such a save anyway
 * (FormEditorController::saveFormAction()); this keeps the editor from showing
 * the editor a change it is going to lose.
 */
function subscribeUnlessReadOnly(topic, func) {
    if (getFormEditorApp().isReadOnly()) {
        return;
    }
    getPublisherSubscriber().subscribe(topic, func);
}
function subscribeEvents() {
    /* *********************************************************
     * Misc
     * ********************************************************/
    window.onbeforeunload = function (e) {
        if (!getFormEditorApp().getUnsavedContent()) {
            return undefined;
        }
        e = e || window.event;
        if (e) {
            e.returnValue = getFormEditorApp().getFormElementDefinition(getRootFormElement(), 'modalCloseDialogMessage');
        }
        return getFormEditorApp().getFormElementDefinition(getRootFormElement(), 'modalCloseDialogTitle');
    };
    /**
     * @subscribe view/ready
     */
    getPublisherSubscriber().subscribe('view/ready', () => {
        getViewModel().onViewReadyBatch();
    });
    /**
     * @subscribe core/applicationState/add
     */
    getPublisherSubscriber().subscribe('core/applicationState/add', (topic, [applicationState, // eslint-disable-line @typescript-eslint/no-unused-vars
    currentStackPointer, currentStackSize]) => {
        getViewModel().disableButton(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderRedo')));
        if (currentStackSize > 1 && currentStackPointer <= currentStackSize) {
            getViewModel().enableButton(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderUndo')));
        }
        else {
            getViewModel().disableButton(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderUndo')));
        }
    });
    /* *********************************************************
     * Ajax
     * ********************************************************/
    /**
     * @subscribe core/ajax/saveFormDefinition/success
     */
    getPublisherSubscriber().subscribe('core/ajax/saveFormDefinition/success', (topic, [data]) => {
        getFormEditorApp().setUnsavedContent(false);
        getViewModel().setPreviewMode(false);
        getViewModel().showSaveSuccessMessage();
        getViewModel().showSaveButtonSaveIcon();
        getFormEditorApp().setFormDefinition(data.formDefinition);
        getViewModel().addStructureRootElementSelection();
        getFormEditorApp().setCurrentlySelectedFormElement(getRootFormElement());
        getViewModel().setStructureRootElementTitle();
        getViewModel().setStageHeadline();
        getViewModel().renderAbstractStageArea();
        getViewModel().renewStructure();
        getViewModel().renderPagination();
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe core/ajax/saveFormDefinition/error
     */
    getPublisherSubscriber().subscribe('core/ajax/saveFormDefinition/error', (topic, [data]) => {
        getViewModel().showSaveButtonSaveIcon();
        getViewModel().showSaveErrorMessage({ message: data.message });
    });
    /**
     * @subscribe core/ajax/renderFormDefinitionPage/success
     */
    getPublisherSubscriber().subscribe('core/ajax/renderFormDefinitionPage/success', (topic, [htmldata, pageIndex] // eslint-disable-line @typescript-eslint/no-unused-vars
    ) => {
        getViewModel().renderPreviewStageArea(htmldata);
    });
    /**
     * @subscribe core/ajax/saveFormDefinition/error
     */
    getPublisherSubscriber().subscribe('core/ajax/error', (topic, [statusText, responseBody]) => {
        getViewModel().showErrorFlashMessage(statusText, responseBody);
        getViewModel().renderPreviewStageArea(responseBody);
    });
    /* *********************************************************
     * Header
     * ********************************************************/
    /**
     * @subscribe view/header/button/save/clicked
     */
    subscribeUnlessReadOnly('view/header/button/save/clicked', () => {
        if (getFormEditorApp().validationResultsHasErrors(getFormEditorApp().validateFormElementRecursive(getRootFormElement(), true))) {
            getViewModel().showValidationErrorsModal();
        }
        else {
            getViewModel().showSaveButtonSpinnerIcon();
            getFormEditorApp().saveFormDefinition();
        }
    });
    /**
     * @subscribe view/header/formSettings/clicked
     */
    getPublisherSubscriber().subscribe('view/header/formSettings/clicked', () => {
        getViewModel().setPreviewMode(false);
        getViewModel().addStructureRootElementSelection();
        getFormEditorApp().setCurrentlySelectedFormElement(getRootFormElement());
        getViewModel().renderAbstractStageArea();
        getViewModel().renewStructure();
        getViewModel().renderPagination();
        getViewModel().showInspectorSidebar();
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/header/button/newPage/clicked
     */
    subscribeUnlessReadOnly('view/header/button/newPage/clicked', (topic, [targetEvent]) => {
        if (getFormEditorApp().isRootFormElementSelected()) {
            getViewModel().selectPageBatch(0);
        }
        getViewModel().showInsertPagesModal(targetEvent);
    });
    /**
     * @subscribe view/header/button/close/clicked
     */
    getPublisherSubscriber().subscribe('view/header/button/close/clicked', () => {
        getViewModel().showCloseConfirmationModal();
    });
    /**
     * @subscribe view/undoButton/clicked
     */
    subscribeUnlessReadOnly('view/undoButton/clicked', () => {
        getViewModel().disableButton(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderUndo')));
        getViewModel().disableButton(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderRedo')));
        getFormEditorApp().undoApplicationState();
        if (getViewModel().getPreviewMode()) {
            getFormEditorApp().renderCurrentFormPage();
        }
        else {
            getViewModel().renderAbstractStageArea();
        }
        getFormEditorApp().setUnsavedContent(true);
        getViewModel().renewStructure();
        getViewModel().renderPagination();
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/redoButton/clicked
     */
    subscribeUnlessReadOnly('view/redoButton/clicked', () => {
        getViewModel().disableButton(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderUndo')));
        getViewModel().disableButton(document.querySelector(getHelper().getDomElementDataIdentifierSelector('buttonHeaderRedo')));
        getFormEditorApp().redoApplicationState();
        if (getViewModel().getPreviewMode()) {
            getFormEditorApp().renderCurrentFormPage();
        }
        else {
            getViewModel().renderAbstractStageArea();
        }
        getFormEditorApp().setUnsavedContent(true);
        getViewModel().renewStructure();
        getViewModel().renderPagination();
        getViewModel().renderInspectorEditors();
    });
    /* *********************************************************
     * Stage
     * ********************************************************/
    /**
     * @subscribe view/stage/element/clicked
     */
    getPublisherSubscriber().subscribe('view/stage/element/clicked', (topic, [formElementIdentifierPath]) => {
        if (getViewModel().getPreviewMode()) {
            return;
        }
        if (getCurrentlySelectedFormElement().get('__identifierPath') !== formElementIdentifierPath) {
            getFormEditorApp().setCurrentlySelectedFormElement(formElementIdentifierPath);
            getViewModel().selectStructureNode();
            getViewModel().renewStructure();
            getViewModel().refreshSelectedElementItemsBatch();
            getViewModel().addAbstractViewValidationResults();
            getViewModel().showInspectorSidebar();
            getViewModel().renderInspectorEditors();
            getViewModel().focusFirstInspectorInput();
        }
    });
    /**
     * @subscribe view/stage/abstract/elementToolbar/button/newElement/clicked
     */
    subscribeUnlessReadOnly('view/stage/abstract/elementToolbar/button/newElement/clicked', (topic, [targetEvent, modalConfiguration]) => {
        if (getFormEditorApp().isRootFormElementSelected()) {
            getViewModel().selectPageBatch(0);
        }
        getViewModel().showInsertElementsModal(targetEvent, modalConfiguration);
    });
    /**
     * @subscribe view/newElementButton/clicked
     */
    subscribeUnlessReadOnly('view/stage/abstract/button/newElement/clicked', (topic, [targetEvent, modalConfiguration]) => {
        if (getFormEditorApp().isRootFormElementSelected()) {
            getViewModel().selectPageBatch(0);
        }
        getViewModel().showInsertElementsModal(targetEvent, modalConfiguration || undefined);
    });
    /**
     * @subscribe view/stage/abstract/dnd/start
     */
    subscribeUnlessReadOnly('view/stage/abstract/dnd/start', (topic, [draggedFormElementDomElement, draggedFormPlaceholderDomElement]) => {
        getViewModel().onAbstractViewDndStartBatch(draggedFormElementDomElement, draggedFormPlaceholderDomElement);
    });
    /**
     * @subscribe view/stage/abstract/dnd/stop
     */
    subscribeUnlessReadOnly('view/stage/abstract/dnd/stop', (topic, [draggedFormElementIdentifierPath]) => {
        getFormEditorApp().setCurrentlySelectedFormElement(draggedFormElementIdentifierPath);
        getViewModel().renewStructure();
        getViewModel().setPreviewMode(false);
        getViewModel().renderAbstractStageArea();
        getViewModel().refreshSelectedElementItemsBatch();
        getViewModel().addAbstractViewValidationResults();
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/stage/abstract/dnd/change
     */
    subscribeUnlessReadOnly('view/stage/abstract/dnd/change', (topic, [placeholderDomElement, parentFormElementIdentifierPath, enclosingCompositeFormElement]) => {
        getViewModel().onAbstractViewDndChangeBatch(placeholderDomElement, parentFormElementIdentifierPath, enclosingCompositeFormElement);
    });
    /**
     * @subscribe view/stage/abstract/dnd/update
     */
    subscribeUnlessReadOnly('view/stage/abstract/dnd/update', (topic, [movedDomElement, movedFormElementIdentifierPath, previousFormElementIdentifierPath, nextFormElementIdentifierPath]) => {
        getViewModel().onAbstractViewDndUpdateBatch(movedDomElement, movedFormElementIdentifierPath, previousFormElementIdentifierPath, nextFormElementIdentifierPath);
    });
    /**
     * @subscribe view/viewModeButton/abstract/clicked
     */
    getPublisherSubscriber().subscribe('view/viewModeButton/abstract/clicked', () => {
        if (getViewModel().getPreviewMode()) {
            getViewModel().setPreviewMode(false);
            getViewModel().renderAbstractStageArea();
        }
    });
    /**
     * @subscribe view/viewModeButton/preview/clicked
     */
    getPublisherSubscriber().subscribe('view/viewModeButton/preview/clicked', () => {
        if (!getViewModel().getPreviewMode()) {
            getViewModel().setPreviewMode(true);
            getFormEditorApp().renderCurrentFormPage();
        }
    });
    /**
     * @subscribe view/paginationPrevious/clicked
     */
    getPublisherSubscriber().subscribe('view/paginationPrevious/clicked', () => {
        getViewModel().selectPageBatch(getFormEditorApp().getCurrentlySelectedPageIndex() - 1);
        if (getViewModel().getPreviewMode()) {
            getFormEditorApp().renderCurrentFormPage();
        }
        else {
            getViewModel().renderAbstractStageArea();
        }
    });
    /**
     * @subscribe view/paginationNext/clicked
     */
    getPublisherSubscriber().subscribe('view/paginationNext/clicked', () => {
        getViewModel().selectPageBatch(getFormEditorApp().getCurrentlySelectedPageIndex() + 1);
        if (getViewModel().getPreviewMode()) {
            getFormEditorApp().renderCurrentFormPage();
        }
        else {
            getViewModel().renderAbstractStageArea();
        }
    });
    /**
     * @subscribe view/stage/abstract/render/postProcess
     */
    getPublisherSubscriber().subscribe('view/stage/abstract/render/postProcess', () => {
        getViewModel().renderUndoRedo();
        getViewModel().addAbstractViewValidationResults();
    });
    /**
     * @subscribe view/stage/preview/render/postProcess
     */
    getPublisherSubscriber().subscribe('view/stage/preview/render/postProcess', () => {
        getViewModel().renderUndoRedo();
    });
    /* *********************************************************
     * Structure
     * ********************************************************/
    /**
     * @subscribe view/tree/node/clicked
     */
    getPublisherSubscriber().subscribe('view/tree/node/clicked', (topic, [formElementIdentifierPath]) => {
        if (getCurrentlySelectedFormElement().get('__identifierPath') !== formElementIdentifierPath) {
            getFormEditorApp().setCurrentlySelectedFormElement(formElementIdentifierPath);
            getViewModel().setPreviewMode(false);
            getViewModel().renderAbstractStageArea();
            getViewModel().renderPagination();
            getViewModel().addAbstractViewValidationResults();
            getViewModel().showInspectorSidebar();
            getViewModel().renderInspectorEditors();
            getViewModel().focusFirstInspectorInput();
        }
    });
    /**
     * @subscribe view/tree/node/clicked
     */
    subscribeUnlessReadOnly('view/tree/node/changed', (topic, [formElementIdentifierPath, newLabel]) => {
        const formElement = getFormEditorApp().getFormElementByIdentifierPath(formElementIdentifierPath);
        formElement.set('label', newLabel);
        getViewModel().getStructure().setTreeNodeTitle(null, formElement);
        if (getCurrentlySelectedFormElement().get('__identifierPath') === formElementIdentifierPath) {
            getViewModel().renderInspectorEditors(formElementIdentifierPath);
        }
    });
    /**
     * @subscribe view/structure/root/selected
     */
    getPublisherSubscriber().subscribe('view/structure/root/selected', () => {
        if (!getFormEditorApp().isRootFormElementSelected()) {
            getViewModel().addStructureRootElementSelection();
            getFormEditorApp().setCurrentlySelectedFormElement(getRootFormElement());
            getViewModel().setPreviewMode(false);
            getViewModel().renderAbstractStageArea();
            getViewModel().renewStructure();
            getViewModel().renderPagination();
            getViewModel().renderInspectorEditors();
        }
    });
    /**
     * @subscribe view/header/button/newPage/clicked
     */
    subscribeUnlessReadOnly('view/structure/button/newPage/clicked', (topic, [targetEvent]) => {
        if (getFormEditorApp().isRootFormElementSelected()) {
            getViewModel().selectPageBatch(0);
        }
        getViewModel().showInsertPagesModal(targetEvent);
    });
    /**
     * @subscribe view/tree/dnd/stop
     */
    subscribeUnlessReadOnly('view/tree/dnd/stop', (topic, [draggedFormElementIdentifierPath]) => {
        getFormEditorApp().setCurrentlySelectedFormElement(draggedFormElementIdentifierPath);
        getViewModel().renewStructure();
        getViewModel().renderPagination();
        getViewModel().setPreviewMode(false);
        getViewModel().renderAbstractStageArea();
        getViewModel().addAbstractViewValidationResults();
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/tree/dnd/change
     */
    subscribeUnlessReadOnly('view/tree/dnd/change', (topic, [placeholderDomElement, parentFormElementIdentifierPath, enclosingCompositeFormElement]) => {
        getViewModel().onStructureDndChangeBatch(placeholderDomElement, parentFormElementIdentifierPath, enclosingCompositeFormElement);
    });
    /**
     * @subscribe view/tree/dnd/update
     */
    subscribeUnlessReadOnly('view/tree/dnd/update', (topic, [movedDomElement, movedFormElementIdentifierPath, previousFormElementIdentifierPath, nextFormElementIdentifierPath]) => {
        getViewModel().onStructureDndUpdateBatch(movedDomElement, movedFormElementIdentifierPath, previousFormElementIdentifierPath, nextFormElementIdentifierPath);
    });
    /**
     * @subscribe view/structure/renew/postProcess
     */
    getPublisherSubscriber().subscribe('view/structure/renew/postProcess', () => {
        getViewModel().addStructureValidationResults();
    });
    /* *********************************************************
     * Inspector
     * ********************************************************/
    /**
     * @subscribe view/inspector/removeCollectionElement/perform
     */
    subscribeUnlessReadOnly('view/inspector/removeCollectionElement/perform', (topic, [collectionElementIdentifier, collectionName, formElement]) => {
        getViewModel().removePropertyCollectionElement(collectionElementIdentifier, collectionName, formElement || undefined);
    });
    /**
     * @subscribe view/inspector/collectionElement/selected
     */
    subscribeUnlessReadOnly('view/inspector/collectionElement/new/selected', (topic, [collectionElementIdentifier, collectionName,]) => {
        getViewModel().createAndAddPropertyCollectionElement(collectionElementIdentifier, collectionName);
    });
    /**
     * @subscribe view/inspector/collectionElement/selected
     */
    getPublisherSubscriber().subscribe('view/inspector/collectionElement/existing/selected', (topic, [collectionElementIdentifier, collectionName,]) => {
        getViewModel().renderInspectorCollectionElementEditors(collectionName, collectionElementIdentifier);
    });
    /**
     * @subscribe view/inspector/collectionElements/dnd/update
     * @throws 1477407673
     */
    subscribeUnlessReadOnly('view/inspector/collectionElements/dnd/update', (topic, [movedCollectionElementIdentifier, previousCollectionElementIdentifier, nextCollectionElementIdentifier, collectionName]) => {
        if (nextCollectionElementIdentifier) {
            getViewModel().movePropertyCollectionElement(movedCollectionElementIdentifier, 'before', nextCollectionElementIdentifier, collectionName);
        }
        else if (previousCollectionElementIdentifier) {
            getViewModel().movePropertyCollectionElement(movedCollectionElementIdentifier, 'after', previousCollectionElementIdentifier, collectionName);
        }
        else {
            assert(false, 'Next element or previous element need to be set.', 1477407673);
        }
    });
    /* *********************************************************
     * Form element
     * ********************************************************/
    /**
     * @subscribe core/formElement/somePropertyChanged
     */
    getPublisherSubscriber().subscribe('core/formElement/somePropertyChanged', (topic, [propertyPath, value, // eslint-disable-line @typescript-eslint/no-unused-vars
    oldValue, // eslint-disable-line @typescript-eslint/no-unused-vars
    formElementIdentifierPath]) => {
        if ('renderables' !== propertyPath) {
            if (!getFormEditorApp().isRootFormElementSelected() && 'label' === propertyPath) {
                // setTreeNodeTitle() internally calls renew(), so no additional renewStructure() needed
                getViewModel().getStructure().setTreeNodeTitle();
            }
            else {
                if (!getFormEditorApp().getUtility().isUndefinedOrNull(formElementIdentifierPath) && getRootFormElement().get('__identifierPath') === formElementIdentifierPath) {
                    getViewModel().setStructureRootElementTitle();
                    getViewModel().setStageHeadline();
                }
                // Renew the tree for all other property changes (e.g. renderingOptions.enabled)
                getViewModel().renewStructure();
            }
            if (getViewModel().getPreviewMode()) {
                getFormEditorApp().renderCurrentFormPage();
            }
            else {
                getViewModel().renderAbstractStageArea();
            }
            getViewModel().addStructureValidationResults();
        }
        getFormEditorApp().setUnsavedContent(true);
    });
    /**
     * @subscribe view/formElement/removed
     */
    getPublisherSubscriber().subscribe('view/formElement/removed', (topic, [parentFormElement]) => {
        getFormEditorApp().setCurrentlySelectedFormElement(parentFormElement);
        getViewModel().renewStructure();
        getViewModel().renderAbstractStageArea();
        getViewModel().renderPagination();
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/formElement/inserted
     */
    getPublisherSubscriber().subscribe('view/formElement/inserted', (topic, [newFormElement]) => {
        getFormEditorApp().setCurrentlySelectedFormElement(newFormElement);
        getViewModel().renewStructure();
        getViewModel().renderAbstractStageArea();
        getViewModel().renderPagination();
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/collectionElement/new/added
     */
    getPublisherSubscriber().subscribe('view/collectionElement/new/added', () => {
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/collectionElement/moved
     */
    getPublisherSubscriber().subscribe('view/collectionElement/moved', () => {
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/collectionElement/removed
     */
    getPublisherSubscriber().subscribe('view/collectionElement/removed', () => {
        getViewModel().renderInspectorEditors();
    });
    /**
     * @subscribe view/insertElements/perform/bottom
     */
    subscribeUnlessReadOnly('view/insertElements/perform/bottom', (topic, [formElementType]) => {
        const lastRenderable = getFormEditorApp().getLastTopLevelElementOnCurrentPage();
        if (!lastRenderable) {
            getViewModel().createAndAddFormElement(formElementType, getFormEditorApp().getCurrentlySelectedPage());
        }
        else {
            if (!getFormEditorApp().getFormElementDefinition(lastRenderable, '_isTopLevelFormElement')
                && getFormEditorApp().getFormElementDefinition(lastRenderable, '_isCompositeFormElement')) {
                getViewModel().createAndAddFormElement(formElementType, getFormEditorApp().getCurrentlySelectedPage());
            }
            else {
                getViewModel().createAndAddFormElement(formElementType, lastRenderable);
            }
        }
    });
    /**
     * @publish view/formElement/inserted
     * @subscribe view/insertElements/perform/before
     */
    subscribeUnlessReadOnly('view/insertElements/perform/before', (topic, [formElementType]) => {
        let newFormElement;
        newFormElement = getViewModel().createAndAddFormElement(formElementType, undefined, true);
        newFormElement = getViewModel().moveFormElement(newFormElement, 'before', getFormEditorApp().getCurrentlySelectedFormElement());
        getPublisherSubscriber().publish('view/formElement/inserted', [newFormElement]);
    });
    /**
     * @publish view/formElement/inserted
     * @subscribe view/insertElements/perform/after
     */
    subscribeUnlessReadOnly('view/insertElements/perform/after', (topic, [formElementType]) => {
        let newFormElement;
        newFormElement = getViewModel().createAndAddFormElement(formElementType, undefined, true);
        newFormElement = getViewModel().moveFormElement(newFormElement, 'after', getFormEditorApp().getCurrentlySelectedFormElement());
        getPublisherSubscriber().publish('view/formElement/inserted', [newFormElement]);
    });
    /**
     * @subscribe view/insertElements/perform/inside
     */
    subscribeUnlessReadOnly('view/insertElements/perform/inside', (topic, [formElementType]) => {
        getViewModel().createAndAddFormElement(formElementType);
    });
    /**
     * @subscribe view/insertElements/perform/after
     */
    subscribeUnlessReadOnly('view/insertPages/perform', (topic, [formElementType]) => {
        getViewModel().createAndAddFormElement(formElementType);
    });
    /* *********************************************************
     * Modals
     * ********************************************************/
    /**
     * @subscribe view/modal/close/perform
     */
    getPublisherSubscriber().subscribe('view/modal/close/perform', () => {
        getFormEditorApp().setUnsavedContent(false);
        getViewModel().closeEditor();
    });
    /**
     * @subscribe view/modal/removeFormElement/perform
     */
    subscribeUnlessReadOnly('view/modal/removeFormElement/perform', (topic, [formElement]) => {
        getViewModel().removeFormElement(formElement);
    });
    /**
     * @subscribe view/modal/removeCollectionElement/perform
     */
    subscribeUnlessReadOnly('view/modal/removeCollectionElement/perform', (topic, [collectionElementIdentifier, collectionName, formElement,]) => {
        getViewModel().removePropertyCollectionElement(collectionElementIdentifier, collectionName, formElement);
    });
    /**
     * @subscribe view/modal/validationErrors/element/clicked
     */
    getPublisherSubscriber().subscribe('view/modal/validationErrors/element/clicked', (topic, [formElementIdentifierPath]) => {
        if (getCurrentlySelectedFormElement().get('__identifierPath') !== formElementIdentifierPath) {
            getFormEditorApp().setCurrentlySelectedFormElement(formElementIdentifierPath);
            if (getViewModel().getPreviewMode()) {
                getViewModel().setPreviewMode(false);
            }
            getViewModel().renderAbstractStageArea();
            getViewModel().renderPagination();
            getViewModel().renderInspectorEditors();
        }
    });
}
export function bootstrap(_formEditorApp, _viewModel) {
    formEditorApp = _formEditorApp;
    viewModel = _viewModel;
    Helper.bootstrap(formEditorApp);
    subscribeEvents();
}
=======
import*as u from"@typo3/form/backend/form-editor/helper.js";let m=null,b=null;function n(){return m}function e(){return b}function g(){return n().getUtility()}function w(i,t,o){return n().assert(i,t,o)}function d(i){return g().isUndefinedOrNull(i)?u.setConfiguration(e().getConfiguration()):u.setConfiguration(i)}function l(){return n().getCurrentlySelectedFormElement()}function r(){return n().getPublisherSubscriber()}function a(){return n().getRootFormElement()}function v(){window.onbeforeunload=function(i){if(n().getUnsavedContent())return i=i||window.event,i&&(i.returnValue=n().getFormElementDefinition(a(),"modalCloseDialogMessage")),n().getFormElementDefinition(a(),"modalCloseDialogTitle")},r().subscribe("view/ready",()=>{e().onViewReadyBatch()}),r().subscribe("core/applicationState/add",(i,[t,o,s])=>{e().disableButton(document.querySelector(d().getDomElementDataIdentifierSelector("buttonHeaderRedo"))),s>1&&o<=s?e().enableButton(document.querySelector(d().getDomElementDataIdentifierSelector("buttonHeaderUndo"))):e().disableButton(document.querySelector(d().getDomElementDataIdentifierSelector("buttonHeaderUndo")))}),r().subscribe("core/ajax/saveFormDefinition/success",(i,[t])=>{n().setUnsavedContent(!1),e().setPreviewMode(!1),e().showSaveSuccessMessage(),e().showSaveButtonSaveIcon(),n().setFormDefinition(t.formDefinition),e().addStructureRootElementSelection(),n().setCurrentlySelectedFormElement(a()),e().setStructureRootElementTitle(),e().setStageHeadline(),e().renderAbstractStageArea(),e().renewStructure(),e().renderPagination(),e().renderInspectorEditors()}),r().subscribe("core/ajax/saveFormDefinition/error",(i,[t])=>{e().showSaveButtonSaveIcon(),e().showSaveErrorMessage({message:t.message})}),r().subscribe("core/ajax/renderFormDefinitionPage/success",(i,[t,o])=>{e().renderPreviewStageArea(t)}),r().subscribe("core/ajax/error",(i,[t,o])=>{e().showErrorFlashMessage(t,o),e().renderPreviewStageArea(o)}),r().subscribe("view/header/button/save/clicked",()=>{n().validationResultsHasErrors(n().validateFormElementRecursive(a(),!0))?e().showValidationErrorsModal():(e().showSaveButtonSpinnerIcon(),n().saveFormDefinition())}),r().subscribe("view/header/formSettings/clicked",()=>{e().setPreviewMode(!1),e().addStructureRootElementSelection(),n().setCurrentlySelectedFormElement(a()),e().renderAbstractStageArea(),e().renewStructure(),e().renderPagination(),e().showInspectorSidebar(),e().renderInspectorEditors()}),r().subscribe("view/header/button/newPage/clicked",(i,[t])=>{n().isRootFormElementSelected()&&e().selectPageBatch(0),e().showInsertPagesModal(t)}),r().subscribe("view/header/button/close/clicked",()=>{e().showCloseConfirmationModal()}),r().subscribe("view/undoButton/clicked",()=>{e().disableButton(document.querySelector(d().getDomElementDataIdentifierSelector("buttonHeaderUndo"))),e().disableButton(document.querySelector(d().getDomElementDataIdentifierSelector("buttonHeaderRedo"))),n().undoApplicationState(),e().getPreviewMode()?n().renderCurrentFormPage():e().renderAbstractStageArea(),n().setUnsavedContent(!0),e().renewStructure(),e().renderPagination(),e().renderInspectorEditors()}),r().subscribe("view/redoButton/clicked",()=>{e().disableButton(document.querySelector(d().getDomElementDataIdentifierSelector("buttonHeaderUndo"))),e().disableButton(document.querySelector(d().getDomElementDataIdentifierSelector("buttonHeaderRedo"))),n().redoApplicationState(),e().getPreviewMode()?n().renderCurrentFormPage():e().renderAbstractStageArea(),n().setUnsavedContent(!0),e().renewStructure(),e().renderPagination(),e().renderInspectorEditors()}),r().subscribe("view/stage/element/clicked",(i,[t])=>{e().getPreviewMode()||(l().get("__identifierPath")!==t?(n().setCurrentlySelectedFormElement(t),e().selectStructureNode(),e().renewStructure(),e().refreshSelectedElementItemsBatch(),e().addAbstractViewValidationResults(),e().showInspectorSidebar(),e().renderInspectorEditors(),e().focusInspectorSidebar()):e().showInspectorSidebar())}),r().subscribe("view/stage/abstract/elementToolbar/button/newElement/clicked",(i,[t,o])=>{n().isRootFormElementSelected()&&e().selectPageBatch(0),e().showInsertElementsModal(t,o)}),r().subscribe("view/stage/abstract/button/newElement/clicked",(i,[t,o])=>{n().isRootFormElementSelected()&&e().selectPageBatch(0),e().showInsertElementsModal(t,o||void 0)}),r().subscribe("view/stage/abstract/dnd/start",(i,[t,o])=>{e().onAbstractViewDndStartBatch(t,o)}),r().subscribe("view/stage/abstract/dnd/stop",(i,[t])=>{n().setCurrentlySelectedFormElement(t),e().renewStructure(),e().setPreviewMode(!1),e().renderAbstractStageArea(),e().refreshSelectedElementItemsBatch(),e().addAbstractViewValidationResults(),e().renderInspectorEditors()}),r().subscribe("view/stage/abstract/dnd/change",(i,[t,o,s])=>{e().onAbstractViewDndChangeBatch(t,o,s)}),r().subscribe("view/stage/abstract/dnd/update",(i,[t,o,s,c])=>{e().onAbstractViewDndUpdateBatch(t,o,s,c)}),r().subscribe("view/viewModeButton/abstract/clicked",()=>{e().getPreviewMode()&&(e().setPreviewMode(!1),e().renderAbstractStageArea())}),r().subscribe("view/viewModeButton/preview/clicked",()=>{e().getPreviewMode()||(e().setPreviewMode(!0),n().renderCurrentFormPage())}),r().subscribe("view/paginationPrevious/clicked",()=>{e().selectPageBatch(n().getCurrentlySelectedPageIndex()-1),e().getPreviewMode()?n().renderCurrentFormPage():e().renderAbstractStageArea()}),r().subscribe("view/paginationNext/clicked",()=>{e().selectPageBatch(n().getCurrentlySelectedPageIndex()+1),e().getPreviewMode()?n().renderCurrentFormPage():e().renderAbstractStageArea()}),r().subscribe("view/stage/abstract/render/postProcess",()=>{e().renderUndoRedo(),e().addAbstractViewValidationResults()}),r().subscribe("view/stage/preview/render/postProcess",()=>{e().renderUndoRedo()}),r().subscribe("view/tree/node/clicked",(i,[t])=>{l().get("__identifierPath")!==t&&(n().setCurrentlySelectedFormElement(t),e().setPreviewMode(!1),e().renderAbstractStageArea(),e().renderPagination(),e().addAbstractViewValidationResults(),e().showInspectorSidebar(),e().renderInspectorEditors(),e().focusInspectorSidebar())}),r().subscribe("view/tree/node/changed",(i,[t,o])=>{const s=n().getFormElementByIdentifierPath(t);s.set("label",o),e().getStructure().setTreeNodeTitle(null,s),l().get("__identifierPath")===t&&e().renderInspectorEditors(t)}),r().subscribe("view/structure/root/selected",()=>{n().isRootFormElementSelected()||(e().addStructureRootElementSelection(),n().setCurrentlySelectedFormElement(a()),e().setPreviewMode(!1),e().renderAbstractStageArea(),e().renewStructure(),e().renderPagination(),e().renderInspectorEditors())}),r().subscribe("view/structure/button/newPage/clicked",(i,[t])=>{n().isRootFormElementSelected()&&e().selectPageBatch(0),e().showInsertPagesModal(t)}),r().subscribe("view/tree/dnd/stop",(i,[t])=>{n().setCurrentlySelectedFormElement(t),e().renewStructure(),e().renderPagination(),e().setPreviewMode(!1),e().renderAbstractStageArea(),e().addAbstractViewValidationResults(),e().renderInspectorEditors()}),r().subscribe("view/tree/dnd/change",(i,[t,o,s])=>{e().onStructureDndChangeBatch(t,o,s)}),r().subscribe("view/tree/dnd/update",(i,[t,o,s,c])=>{e().onStructureDndUpdateBatch(t,o,s,c)}),r().subscribe("view/structure/renew/postProcess",()=>{e().addStructureValidationResults()}),r().subscribe("view/inspector/removeCollectionElement/perform",(i,[t,o,s])=>{e().removePropertyCollectionElement(t,o,s||void 0)}),r().subscribe("view/inspector/collectionElement/new/selected",(i,[t,o])=>{e().createAndAddPropertyCollectionElement(t,o)}),r().subscribe("view/inspector/collectionElement/existing/selected",(i,[t,o])=>{e().renderInspectorCollectionElementEditors(o,t)}),r().subscribe("view/inspector/collectionElements/dnd/update",(i,[t,o,s,c])=>{s?e().movePropertyCollectionElement(t,"before",s,c):o?e().movePropertyCollectionElement(t,"after",o,c):w(!1,"Next element or previous element need to be set.",1477407673)}),r().subscribe("core/formElement/somePropertyChanged",(i,[t,o,s,c])=>{t!=="renderables"&&(!n().isRootFormElementSelected()&&t==="label"?e().getStructure().setTreeNodeTitle():(!n().getUtility().isUndefinedOrNull(c)&&a().get("__identifierPath")===c&&(e().setStructureRootElementTitle(),e().setStageHeadline()),e().renewStructure()),e().getPreviewMode()?n().renderCurrentFormPage():e().renderAbstractStageArea(),e().addStructureValidationResults()),n().setUnsavedContent(!0)}),r().subscribe("view/formElement/removed",(i,[t])=>{n().setCurrentlySelectedFormElement(t),e().renewStructure(),e().renderAbstractStageArea(),e().renderPagination(),e().renderInspectorEditors()}),r().subscribe("view/formElement/inserted",(i,[t])=>{n().setCurrentlySelectedFormElement(t),e().renewStructure(),e().renderAbstractStageArea(),e().renderPagination(),e().renderInspectorEditors()}),r().subscribe("view/collectionElement/new/added",()=>{e().renderInspectorEditors()}),r().subscribe("view/collectionElement/moved",()=>{e().renderInspectorEditors()}),r().subscribe("view/collectionElement/removed",()=>{e().renderInspectorEditors()}),r().subscribe("view/insertElements/perform/bottom",(i,[t])=>{const o=n().getLastTopLevelElementOnCurrentPage();o?!n().getFormElementDefinition(o,"_isTopLevelFormElement")&&n().getFormElementDefinition(o,"_isCompositeFormElement")?e().createAndAddFormElement(t,n().getCurrentlySelectedPage()):e().createAndAddFormElement(t,o):e().createAndAddFormElement(t,n().getCurrentlySelectedPage())}),r().subscribe("view/insertElements/perform/before",(i,[t])=>{let o;o=e().createAndAddFormElement(t,void 0,!0),o=e().moveFormElement(o,"before",n().getCurrentlySelectedFormElement()),r().publish("view/formElement/inserted",[o])}),r().subscribe("view/insertElements/perform/after",(i,[t])=>{let o;o=e().createAndAddFormElement(t,void 0,!0),o=e().moveFormElement(o,"after",n().getCurrentlySelectedFormElement()),r().publish("view/formElement/inserted",[o])}),r().subscribe("view/insertElements/perform/inside",(i,[t])=>{e().createAndAddFormElement(t)}),r().subscribe("view/insertPages/perform",(i,[t])=>{e().createAndAddFormElement(t)}),r().subscribe("view/modal/close/perform",()=>{n().setUnsavedContent(!1),e().closeEditor()}),r().subscribe("view/modal/removeFormElement/perform",(i,[t])=>{e().removeFormElement(t)}),r().subscribe("view/modal/removeCollectionElement/perform",(i,[t,o,s])=>{e().removePropertyCollectionElement(t,o,s)}),r().subscribe("view/modal/validationErrors/element/clicked",(i,[t])=>{l().get("__identifierPath")!==t&&(n().setCurrentlySelectedFormElement(t),e().getPreviewMode()&&e().setPreviewMode(!1),e().renderAbstractStageArea(),e().renderPagination(),e().renderInspectorEditors())})}function p(i,t){m=i,b=t,u.bootstrap(m),v()}export{p as bootstrap};
>>>>>>> ca975574 ([TASK] Show the form editor inspector as sheet on small screens)
