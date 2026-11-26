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
 * Module: @typo3/form/backend/form-editor
 */
import Notification from '@typo3/backend/notification.js';
import * as Core from '@typo3/form/backend/form-editor/core.js';
import { cloneDeep } from 'lodash-es';
import failsafeLabels from '~labels/form.form_editor_fail_safe_error_handling_javascript';
const assert = Core.assert;
export class FormEditor {
    constructor(configuration, mediator, viewModel) {
        this.isRunning = false;
        this.unsavedContent = false;
        this.configuration = configuration || {};
        this.mediator = mediator;
        this.viewModel = viewModel;
    }
    getPublisherSubscriber() {
        return Core.getPublisherSubscriber();
    }
    /**
     * WapplerSystems fork: whether this form may only be viewed. The mediator
     * subscribes no mutating topic in that case and the view leaves out the
     * affordances that would publish them, so nothing can change a form the
     * server would refuse to save anyway.
     */
    isReadOnly() {
        return this.configuration.readOnly === true;
    }
    undoApplicationState() {
        this.getApplicationStateStack().incrementCurrentStackPointer();
    }
    redoApplicationState() {
        this.getApplicationStateStack().decrementCurrentStackPointer();
    }
    getMaximalApplicationStates() {
        return this.getApplicationStateStack().getMaximalStackSize();
    }
    getCurrentApplicationStates() {
        return this.getApplicationStateStack().getCurrentStackSize();
    }
    getCurrentApplicationStatePosition() {
        return this.getApplicationStateStack().getCurrentStackPointer();
    }
    /**
     * @internal
     * @throws 1519855175
     */
    setFormDefinition(formDefinition) {
        assert(typeof formDefinition === 'object' && formDefinition !== null && !Array.isArray(formDefinition), 'Invalid parameter "formDefinition"', 1519855175);
        this.getApplicationStateStack().setCurrentState('formDefinition', this.getFactory().createFormElement(formDefinition, undefined, undefined, true));
    }
    /**
     * @throws 1475378543
     */
    getRunningAjaxRequest(type) {
        assert(this.getUtility().isNonEmptyString(type), 'Invalid parameter "type"', 1475378543);
        return Core.getRunningAjaxRequest(type);
    }
    getUtility() {
        return Core.getUtility();
    }
    assert(test, message, messageCode) {
        this.getUtility().assert(test, message, messageCode);
    }
    buildPropertyPath(propertyPath, collectionElementIdentifier, collectionName, _formElement, allowEmptyReturnValue) {
        if (this.getUtility().isUndefinedOrNull(_formElement)) {
            _formElement = this.getCurrentlySelectedFormElement();
        }
        const formElement = this.getRepository().findFormElement(_formElement);
        return this.getUtility().buildPropertyPath(propertyPath, collectionElementIdentifier, collectionName, formElement, allowEmptyReturnValue);
    }
    addPropertyValidationValidator(validatorIdentifier, func) {
        this.getPropertyValidationService().addValidator(validatorIdentifier, func);
    }
    validateCurrentlySelectedFormElementProperty(propertyPath) {
        return this.validateFormElementProperty(this.getCurrentlySelectedFormElement(), propertyPath);
    }
    validateFormElementProperty(_formElement, propertyPath) {
        const formElement = this.getRepository().findFormElement(_formElement);
        return this.getPropertyValidationService().validateFormElementProperty(formElement, propertyPath);
    }
    validateFormElement(_formElement) {
        const formElement = this.getRepository().findFormElement(_formElement);
        return this.getPropertyValidationService().validateFormElement(formElement);
    }
    validationResultsHasErrors(validationResults) {
        return this.getPropertyValidationService().validationResultsHasErrors(validationResults);
    }
    validateFormElementRecursive(_formElement, returnAfterFirstMatch) {
        const formElement = this.getRepository().findFormElement(_formElement);
        return this.getPropertyValidationService().validateFormElementRecursive(formElement, returnAfterFirstMatch);
    }
    /**
     * @throws 1475378544
     */
    setUnsavedContent(unsavedContent) {
        assert(typeof unsavedContent === 'boolean', 'Invalid parameter "unsavedContent"', 1475378544);
        this.unsavedContent = unsavedContent;
    }
    getUnsavedContent() {
        return this.unsavedContent;
    }
    getRootFormElement() {
        return this.getRepository().getRootFormElement();
    }
    getCurrentlySelectedFormElement() {
        return this.getRepository().findFormElementByIdentifierPath(this.getApplicationStateStack().getCurrentState('currentlySelectedFormElementIdentifierPath'));
    }
    /**
     * @publish core/currentlySelectedFormElementChanged
     */
    setCurrentlySelectedFormElement(_formElement, doNotRefreshCurrentlySelectedPageIndex) {
        doNotRefreshCurrentlySelectedPageIndex = !!doNotRefreshCurrentlySelectedPageIndex;
        const formElement = this.getRepository().findFormElement(_formElement);
        this.getApplicationStateStack().setCurrentState('currentlySelectedFormElementIdentifierPath', formElement.get('__identifierPath'));
        if (!doNotRefreshCurrentlySelectedPageIndex) {
            this.refreshCurrentlySelectedPageIndex();
        }
        this.getPublisherSubscriber().publish('core/currentlySelectedFormElementChanged', [formElement]);
    }
    /**
     * @throws 1475378545
     */
    getFormElementByIdentifierPath(identifierPath) {
        assert(this.getUtility().isNonEmptyString(identifierPath), 'Invalid parameter "identifierPath"', 1475378545);
        return this.getRepository().findFormElementByIdentifierPath(identifierPath);
    }
    isFormElementIdentifierUsed(formElementIdentifier) {
        return this.getRepository().isFormElementIdentifierUsed(formElementIdentifier);
    }
    createAndAddFormElement(formElementType, referenceFormElement, disablePublishersOnSet) {
        const formElement = this.addFormElement(this.createFormElement(formElementType, disablePublishersOnSet), referenceFormElement, disablePublishersOnSet);
        formElement.set('renderables', formElement.get('renderables'));
        return formElement;
    }
    /**
     * @throws 1475434337
     */
    addFormElement(formElement, _referenceFormElement, disablePublishersOnSet) {
        this.saveApplicationState();
        if (this.getUtility().isUndefinedOrNull(_referenceFormElement)) {
            _referenceFormElement = this.getCurrentlySelectedFormElement();
        }
        const referenceFormElement = this.getRepository().findFormElement(_referenceFormElement);
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475434337);
        return this.getRepository().addFormElement(formElement, referenceFormElement, true, disablePublishersOnSet);
    }
    /**
     * @throws 1475434336
     * @throws 1475435857
     */
    createFormElement(formElementType, disablePublishersOnSet) {
        assert(this.getUtility().isNonEmptyString(formElementType), 'Invalid parameter "formElementType"', 1475434336);
        const identifier = this.getRepository().getNextFreeFormElementIdentifier(formElementType);
        const formElementDefinition = this.getFormElementDefinitionByType(formElementType, undefined);
        return this.getFactory().createFormElement({
            type: formElementType,
            identifier: identifier,
            label: formElementDefinition.label || formElementType
        }, undefined, undefined, undefined, disablePublishersOnSet);
    }
    removeFormElement(_formElementToRemove, disablePublishersOnSet) {
        this.saveApplicationState();
        const formElementToRemove = this.getRepository().findFormElement(_formElementToRemove);
        const parentFormElement = formElementToRemove.get('__parentRenderable');
        this.getRepository().removeFormElement(formElementToRemove, true, disablePublishersOnSet);
        return parentFormElement;
    }
    /**
     * @throws 1475378551
     */
    moveFormElement(_formElementToMove, position, _referenceFormElement, disablePublishersOnSet) {
        this.saveApplicationState();
        let formElementToMove = this.getRepository().findFormElement(_formElementToMove);
        const referenceFormElement = this.getRepository().findFormElement(_referenceFormElement);
        assert('after' === position || 'before' === position || 'inside' === position, 'Invalid position "' + position + '"', 1475378551);
        formElementToMove = this.getRepository().moveFormElement(formElementToMove, position, referenceFormElement, true);
        disablePublishersOnSet = !!disablePublishersOnSet;
        if (!disablePublishersOnSet) {
            formElementToMove.get('__parentRenderable').set('renderables', formElementToMove.get('__parentRenderable').get('renderables'));
        }
        return formElementToMove;
    }
    /**
     * @throws 1475378555
     * @throws 1475378556
     * @throws 1475446108
     */
    getPropertyCollectionElementConfiguration(collectionElementIdentifier, collectionName, _formElement) {
        let collection, collectionElement;
        if (this.getUtility().isUndefinedOrNull(_formElement)) {
            _formElement = this.getCurrentlySelectedFormElement();
        }
        const formElement = this.getRepository().findFormElement(_formElement);
        assert(this.getUtility().isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475378555);
        assert(this.getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475378556);
        const formElementDefinition = this.getFormElementDefinitionByType(formElement.get('type'), undefined);
        if (!this.getUtility().isUndefinedOrNull(formElementDefinition.propertyCollections)) {
            collection = formElementDefinition.propertyCollections[collectionName];
            assert(!this.getUtility().isUndefinedOrNull(collection), 'Invalid collection name "' + collectionName + '"', 1475446108);
            collectionElement = this.getRepository().findCollectionElementByIdentifierPath(collectionElementIdentifier, collection);
            // Return a dereferenced object
            return cloneDeep(collectionElement);
        }
        else {
            return {};
        }
    }
    /**
     * @throws 1475378557
     * @throws 1475378558
     */
    getIndexFromPropertyCollectionElement(collectionElementIdentifier, collectionName, _formElement) {
        if (this.getUtility().isUndefinedOrNull(_formElement)) {
            _formElement = this.getCurrentlySelectedFormElement();
        }
        const formElement = this.getRepository().findFormElement(_formElement);
        assert(this.getUtility().isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475378557);
        assert(this.getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475378558);
        const indexFromPropertyCollectionElement = this.getRepository().getIndexFromPropertyCollectionElementByIdentifier(collectionElementIdentifier, collectionName, formElement);
        return indexFromPropertyCollectionElement;
    }
    createAndAddPropertyCollectionElement(collectionElementIdentifier, collectionName, formElement, collectionElementConfiguration, referenceCollectionElementIdentifier) {
        return this.addPropertyCollectionElement(this.createPropertyCollectionElement(collectionElementIdentifier, collectionName, collectionElementConfiguration), collectionName, formElement, referenceCollectionElementIdentifier);
    }
    /**
     * @throws 1475443300
     * @throws 1475443301
     */
    addPropertyCollectionElement(collectionElement, collectionName, _formElement, referenceCollectionElementIdentifier) {
        let collection;
        this.saveApplicationState();
        if (this.getUtility().isUndefinedOrNull(_formElement)) {
            _formElement = this.getCurrentlySelectedFormElement();
        }
        const formElement = this.getRepository().findFormElement(_formElement);
        assert(typeof collectionElement === 'object' && collectionElement !== null && !Array.isArray(collectionElement), 'Invalid parameter "collectionElement"', 1475443301);
        assert(this.getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475443300);
        if (this.getUtility().isUndefinedOrNull(referenceCollectionElementIdentifier)) {
            collection = formElement.get(collectionName);
            if (Array.isArray(collection) && collection.length > 0) {
                referenceCollectionElementIdentifier = collection[collection.length - 1].identifier;
            }
        }
        return this.getRepository().addPropertyCollectionElement(collectionElement, collectionName, formElement, referenceCollectionElementIdentifier, false);
    }
    /**
     * @throws 1475378559
     * @throws 1475378560
     */
    createPropertyCollectionElement(collectionElementIdentifier, collectionName, collectionElementConfiguration) {
        assert(this.getUtility().isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475378559);
        assert(this.getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475378560);
        if (typeof collectionElementConfiguration !== 'object' || collectionElementConfiguration === null || Array.isArray(collectionElementConfiguration)) {
            collectionElementConfiguration = {};
        }
        return this.getFactory().createPropertyCollectionElement(collectionElementIdentifier, collectionElementConfiguration, collectionName);
    }
    /**
     * @throws 1475378561
     * @throws 1475378562
     */
    removePropertyCollectionElement(collectionElementIdentifier, collectionName, _formElement, disablePublishersOnSet) {
        this.saveApplicationState();
        if (this.getUtility().isUndefinedOrNull(_formElement)) {
            _formElement = this.getCurrentlySelectedFormElement();
        }
        const formElement = this.getRepository().findFormElement(_formElement);
        assert(this.getUtility().isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475378561);
        assert(this.getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475378562);
        this.getRepository().removePropertyCollectionElementByIdentifier(formElement, collectionElementIdentifier, collectionName, true);
        disablePublishersOnSet = !!disablePublishersOnSet;
        if (!disablePublishersOnSet) {
            this.getPublisherSubscriber().publish('core/formElement/somePropertyChanged', ['__fakeProperty']);
        }
    }
    /**
     * @throws 1477404352
     * @throws 1477404353
     * @throws 1477404354
     * @throws 1477404355
     */
    movePropertyCollectionElement(collectionElementToMove, position, referenceCollectionElement, collectionName, formElement, disablePublishersOnSet) {
        this.saveApplicationState();
        formElement = this.getRepository().findFormElement(formElement);
        assert(typeof collectionElementToMove === 'string', 'Invalid parameter "collectionElementToMove"', 1477404352);
        assert(typeof referenceCollectionElement === 'string', 'Invalid parameter "referenceCollectionElement"', 1477404353);
        assert('after' === position || 'before' === position, 'Invalid position "' + position + '"', 1477404354);
        assert(this.getUtility().isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1477404355);
        this.getRepository().movePropertyCollectionElement(collectionElementToMove, position, referenceCollectionElement, collectionName, formElement, disablePublishersOnSet);
    }
    /**
     * @throws 1475378563
     */
    getFormElementDefinitionByType(elementType, formElementDefinitionKey) {
        assert(this.getUtility().isNonEmptyString(elementType), 'Invalid parameter "elementType"', 1475378563);
        const formElementDefinition = this.getRepository().getFormEditorDefinition('formElements', elementType);
        if (formElementDefinitionKey !== undefined /* && formElementDefinitionKey !== null*/) {
            const formElementDefinitionEntry = formElementDefinition[formElementDefinitionKey];
            if (formElementDefinitionEntry !== null && (typeof formElementDefinitionEntry === 'object')) {
                return cloneDeep(formElementDefinitionEntry);
            }
            else {
                return formElementDefinitionEntry;
            }
        }
        if (formElementDefinition !== null && (typeof formElementDefinition === 'object')) {
            return cloneDeep(formElementDefinition);
        }
        else {
            return formElementDefinition;
        }
    }
    getFormElementDefinition(formElement, formElementDefinitionKey) {
        formElement = this.getRepository().findFormElement(formElement);
        return this.getFormElementDefinitionByType(formElement.get('type'), formElementDefinitionKey);
    }
    getFormEditorDefinition(definitionName, subject) {
        return this.getRepository().getFormEditorDefinition(definitionName, subject);
    }
    /**
     * @throws 1475672362
     */
    getFormElementPropertyValidatorDefinition(validatorIdentifier) {
        assert(this.getUtility().isNonEmptyString(validatorIdentifier), 'Invalid parameter "validatorIdentifier"', 1475672362);
        const validatorDefinition = this.getRepository().getFormEditorDefinition('formElementPropertyValidators', validatorIdentifier);
        // Return a dereferenced object
        return cloneDeep(validatorDefinition);
    }
    getCurrentlySelectedPageIndex() {
        return this.getApplicationStateStack().getCurrentState('currentlySelectedPageIndex');
    }
    refreshCurrentlySelectedPageIndex() {
        this.getApplicationStateStack().setCurrentState('currentlySelectedPageIndex', this.getPageIndexFromFormElement(this.getCurrentlySelectedFormElement()));
    }
    /**
     * @throws 1477786068
     */
    getCurrentlySelectedPage() {
        const currentPage = this.getRepository().getRootFormElement().get('renderables')[this.getCurrentlySelectedPageIndex()];
        assert(typeof currentPage === 'object' && currentPage !== null && !Array.isArray(currentPage), 'No page found', 1477786068);
        return currentPage;
    }
    getLastTopLevelElementOnCurrentPage() {
        const renderables = this.getCurrentlySelectedPage().get('renderables');
        if (this.getUtility().isUndefinedOrNull(renderables)) {
            return undefined;
        }
        return renderables[renderables.length - 1];
    }
    getLastFormElementWithinParentFormElement(formElement) {
        formElement = this.getRepository().findFormElement(formElement);
        if (formElement.get('__identifierPath') === this.getRootFormElement().get('__identifierPath')) {
            return formElement;
        }
        return formElement.get('__parentRenderable').get('renderables')[formElement.get('__parentRenderable').get('renderables').length - 1];
    }
    getPageIndexFromFormElement(formElement) {
        formElement = this.getRepository().findFormElement(formElement);
        return this.getRepository().getIndexForEnclosingCompositeFormElementWhichIsOnTopLevelForFormElement(formElement);
    }
    renderCurrentFormPage() {
        this.renderFormPage(this.getCurrentlySelectedPageIndex());
    }
    /**
     * @throws 1475446442
     */
    renderFormPage(pageIndex) {
        assert(typeof pageIndex === 'number', 'Invalid parameter "pageIndex"', 1475446442);
        this.getDataBackend().renderFormDefinitionPage(pageIndex);
    }
    findEnclosingCompositeFormElementWhichIsNotOnTopLevel(formElement) {
        return this.getRepository().findEnclosingCompositeFormElementWhichIsNotOnTopLevel(this.getRepository().findFormElement(formElement));
    }
    /**
     * @todo deprecate, method is unused
     */
    findEnclosingGridRowFormElement(formElement) {
        return this.getRepository().findEnclosingGridRowFormElement(this.getRepository().findFormElement(formElement));
    }
    getNonCompositeNonToplevelFormElements() {
        return this.getRepository().getNonCompositeNonToplevelFormElements();
    }
    isRootFormElementSelected() {
        return (this.getCurrentlySelectedFormElement().get('__identifierPath') === this.getRootFormElement().get('__identifierPath'));
    }
    getViewModel() {
        return this.viewModel;
    }
    saveFormDefinition() {
        this.getDataBackend().saveFormDefinition();
    }
    /**
     * @throws 1473200696
     */
    run() {
        if (this.isRunning) {
            throw 'You can not run the app twice (1473200696)';
        }
        try {
            this.bootstrap();
            this.isRunning = true;
        }
        catch (error) {
            if (!(error instanceof Error)) {
                throw error;
            }
            console.error('Form editor error:', error);
            Notification.error(failsafeLabels.get('formEditor.error.headline'), failsafeLabels.get('formEditor.error.message')
                + '\r\n'
                + '\r\n'
                + failsafeLabels.get('formEditor.error.technicalReason')
                + '\r\n'
                + error.message);
        }
        return this;
    }
    saveApplicationState() {
        this.getApplicationStateStack().addAndReset({
            formDefinition: this.getApplicationStateStack().getCurrentState('formDefinition').clone(),
            currentlySelectedPageIndex: this.getApplicationStateStack().getCurrentState('currentlySelectedPageIndex'),
            currentlySelectedFormElementIdentifierPath: this.getApplicationStateStack().getCurrentState('currentlySelectedFormElementIdentifierPath')
        });
    }
    getDataBackend() {
        return Core.getDataBackend();
    }
    getFactory() {
        return Core.getFactory();
    }
    getRepository() {
        return Core.getRepository();
    }
    getPropertyValidationService() {
        return Core.getPropertyValidationService();
    }
    getApplicationStateStack() {
        return Core.getApplicationStateStack();
    }
    /**
     * @throws 1475379748
     * @throws 1475379749
     * @throws 1475927876
     */
    dataBackendSetup(endpoints, prototypeName, formPersistenceIdentifier) {
        assert(typeof endpoints === 'object' && endpoints !== null && !Array.isArray(endpoints), 'Invalid parameter "endpoints"', 1475379748);
        assert(this.getUtility().isNonEmptyString(prototypeName), 'Invalid parameter "prototypeName"', 1475927876);
        assert(this.getUtility().isNonEmptyString(formPersistenceIdentifier), 'Invalid parameter "formPersistenceIdentifier"', 1475379749);
        Core.getDataBackend().setEndpoints(endpoints);
        Core.getDataBackend().setPrototypeName(prototypeName);
        Core.getDataBackend().setPersistenceIdentifier(formPersistenceIdentifier);
    }
    /**
     * @throws 1475379750
     */
    repositorySetup(formEditorDefinitions) {
        assert(typeof formEditorDefinitions === 'object' && formEditorDefinitions !== null && !Array.isArray(formEditorDefinitions), 'Invalid parameter "formEditorDefinitions"', 1475379750);
        this.getRepository().setFormEditorDefinitions(formEditorDefinitions);
    }
    /**
     * @throws 1475492374
     */
    viewSetup(additionalViewModelModules) {
        assert(typeof this.viewModel.bootstrap === 'function', 'The view model does not implement the method "bootstrap"', 1475492374);
        if (this.getUtility().isUndefinedOrNull(additionalViewModelModules)) {
            additionalViewModelModules = [];
        }
        this.viewModel.bootstrap(formEditorInstance, additionalViewModelModules);
    }
    /**
     * @throws 1475492032
     */
    mediatorSetup() {
        assert(typeof this.mediator.bootstrap === 'function', 'The mediator does not implement the method "bootstrap"', 1475492032);
        this.mediator.bootstrap(formEditorInstance, this.viewModel);
    }
    /**
     * @throws 1475379751
     */
    applicationStateStackSetup(rootFormElement, maximumUndoSteps) {
        assert(typeof rootFormElement === 'object' && rootFormElement !== null && !Array.isArray(rootFormElement), 'Invalid parameter "rootFormElement"', 1475379751);
        if (typeof maximumUndoSteps !== 'number') {
            maximumUndoSteps = 10;
        }
        this.getApplicationStateStack().setMaximalStackSize(maximumUndoSteps);
        this.getApplicationStateStack().addAndReset({
            currentlySelectedPageIndex: 0,
            currentlySelectedFormElementIdentifierPath: rootFormElement.identifier
        }, true);
        this.getApplicationStateStack().setCurrentState('formDefinition', this.getFactory().createFormElement(rootFormElement, undefined, undefined, true));
    }
    bootstrap() {
        this.mediatorSetup();
        this.dataBackendSetup(this.configuration.endpoints, this.configuration.prototypeName, this.configuration.formPersistenceIdentifier);
        this.repositorySetup(this.configuration.formEditorDefinitions);
        this.applicationStateStackSetup(this.configuration.formDefinition, this.configuration.maximumUndoSteps);
        this.setCurrentlySelectedFormElement(this.getRepository().getRootFormElement());
        this.viewSetup(this.configuration.additionalViewModelModules);
    }
}
let formEditorInstance = null;
/**
 * @public
 * @static
 *
 * Implement the "Singleton Pattern".
 *
 * Return a singleton instance of a
 * "FormEditor" object.
 */
export function getInstance(configuration, mediator, viewModel) {
    if (formEditorInstance === null) {
        formEditorInstance = new FormEditor(configuration, mediator, viewModel);
    }
    return formEditorInstance;
}
=======
import y from"@typo3/backend/notification.js";import*as a from"@typo3/form/backend/form-editor/core.js";import{cloneDeep as m}from"lodash-es";import d from"~labels/form.form_editor_fail_safe_error_handling_javascript";const n=a.assert;class c{constructor(t,e,r){this.isRunning=!1,this.unsavedContent=!1,this.configuration=t||{},this.mediator=e,this.viewModel=r}getPublisherSubscriber(){return a.getPublisherSubscriber()}undoApplicationState(){this.getApplicationStateStack().incrementCurrentStackPointer()}redoApplicationState(){this.getApplicationStateStack().decrementCurrentStackPointer()}getMaximalApplicationStates(){return this.getApplicationStateStack().getMaximalStackSize()}getCurrentApplicationStates(){return this.getApplicationStateStack().getCurrentStackSize()}getCurrentApplicationStatePosition(){return this.getApplicationStateStack().getCurrentStackPointer()}setFormDefinition(t){n(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "formDefinition"',1519855175),this.getApplicationStateStack().setCurrentState("formDefinition",this.getFactory().createFormElement(t,void 0,void 0,!0))}getRunningAjaxRequest(t){return n(this.getUtility().isNonEmptyString(t),'Invalid parameter "type"',1475378543),a.getRunningAjaxRequest(t)}getUtility(){return a.getUtility()}assert(t,e,r){this.getUtility().assert(t,e,r)}buildPropertyPath(t,e,r,i,o){this.getUtility().isUndefinedOrNull(i)&&(i=this.getCurrentlySelectedFormElement());const l=this.getRepository().findFormElement(i);return this.getUtility().buildPropertyPath(t,e,r,l,o)}addPropertyValidationValidator(t,e){this.getPropertyValidationService().addValidator(t,e)}validateCurrentlySelectedFormElementProperty(t){return this.validateFormElementProperty(this.getCurrentlySelectedFormElement(),t)}validateFormElementProperty(t,e){const r=this.getRepository().findFormElement(t);return this.getPropertyValidationService().validateFormElementProperty(r,e)}validateFormElement(t){const e=this.getRepository().findFormElement(t);return this.getPropertyValidationService().validateFormElement(e)}validationResultsHasErrors(t){return this.getPropertyValidationService().validationResultsHasErrors(t)}validateFormElementRecursive(t,e){const r=this.getRepository().findFormElement(t);return this.getPropertyValidationService().validateFormElementRecursive(r,e)}setUnsavedContent(t){n(typeof t=="boolean",'Invalid parameter "unsavedContent"',1475378544),this.unsavedContent=t}getUnsavedContent(){return this.unsavedContent}getRootFormElement(){return this.getRepository().getRootFormElement()}getCurrentlySelectedFormElement(){return this.getRepository().findFormElementByIdentifierPath(this.getApplicationStateStack().getCurrentState("currentlySelectedFormElementIdentifierPath"))}setCurrentlySelectedFormElement(t,e){e=!!e;const r=this.getRepository().findFormElement(t);this.getApplicationStateStack().setCurrentState("currentlySelectedFormElementIdentifierPath",r.get("__identifierPath")),e||this.refreshCurrentlySelectedPageIndex(),this.getPublisherSubscriber().publish("core/currentlySelectedFormElementChanged",[r])}getFormElementByIdentifierPath(t){return n(this.getUtility().isNonEmptyString(t),'Invalid parameter "identifierPath"',1475378545),this.getRepository().findFormElementByIdentifierPath(t)}isFormElementIdentifierUsed(t){return this.getRepository().isFormElementIdentifierUsed(t)}createAndAddFormElement(t,e,r){const i=this.addFormElement(this.createFormElement(t,r),e,r);return i.set("renderables",i.get("renderables")),i}addFormElement(t,e,r){this.saveApplicationState(),this.getUtility().isUndefinedOrNull(e)&&(e=this.getCurrentlySelectedFormElement());const i=this.getRepository().findFormElement(e);return n(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "formElement"',1475434337),this.getRepository().addFormElement(t,i,!0,r)}createFormElement(t,e){n(this.getUtility().isNonEmptyString(t),'Invalid parameter "formElementType"',1475434336);const r=this.getRepository().getNextFreeFormElementIdentifier(t),i=this.getFormElementDefinitionByType(t,void 0);return this.getFactory().createFormElement({type:t,identifier:r,label:i.label||t},void 0,void 0,!1,e)}removeFormElement(t,e){this.saveApplicationState();const r=this.getRepository().findFormElement(t),i=r.get("__parentRenderable");return this.getRepository().removeFormElement(r,!0,e),i}moveFormElement(t,e,r,i){this.saveApplicationState();let o=this.getRepository().findFormElement(t);const l=this.getRepository().findFormElement(r);return n(e==="after"||e==="before"||e==="inside",'Invalid position "'+e+'"',1475378551),o=this.getRepository().moveFormElement(o,e,l,!0),i=!!i,i||o.get("__parentRenderable").set("renderables",o.get("__parentRenderable").get("renderables")),o}getPropertyCollectionElementConfiguration(t,e,r){let i,o;this.getUtility().isUndefinedOrNull(r)&&(r=this.getCurrentlySelectedFormElement());const l=this.getRepository().findFormElement(r);n(this.getUtility().isNonEmptyString(t),'Invalid parameter "collectionElementIdentifier"',1475378555),n(this.getUtility().isNonEmptyString(e),'Invalid parameter "collectionName"',1475378556);const p=this.getFormElementDefinitionByType(l.get("type"),void 0);return this.getUtility().isUndefinedOrNull(p.propertyCollections)?{}:(i=p.propertyCollections[e],n(!this.getUtility().isUndefinedOrNull(i),'Invalid collection name "'+e+'"',1475446108),o=this.getRepository().findCollectionElementByIdentifierPath(t,i),m(o))}getIndexFromPropertyCollectionElement(t,e,r){this.getUtility().isUndefinedOrNull(r)&&(r=this.getCurrentlySelectedFormElement());const i=this.getRepository().findFormElement(r);return n(this.getUtility().isNonEmptyString(t),'Invalid parameter "collectionElementIdentifier"',1475378557),n(this.getUtility().isNonEmptyString(e),'Invalid parameter "collectionName"',1475378558),this.getRepository().getIndexFromPropertyCollectionElementByIdentifier(t,e,i)}createAndAddPropertyCollectionElement(t,e,r,i,o){return this.addPropertyCollectionElement(this.createPropertyCollectionElement(t,e,i),e,r,o)}addPropertyCollectionElement(t,e,r,i){let o;this.saveApplicationState(),this.getUtility().isUndefinedOrNull(r)&&(r=this.getCurrentlySelectedFormElement());const l=this.getRepository().findFormElement(r);return n(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "collectionElement"',1475443301),n(this.getUtility().isNonEmptyString(e),'Invalid parameter "collectionName"',1475443300),this.getUtility().isUndefinedOrNull(i)&&(o=l.get(e),Array.isArray(o)&&o.length>0&&(i=o[o.length-1].identifier)),this.getRepository().addPropertyCollectionElement(t,e,l,i,!1)}createPropertyCollectionElement(t,e,r){return n(this.getUtility().isNonEmptyString(t),'Invalid parameter "collectionElementIdentifier"',1475378559),n(this.getUtility().isNonEmptyString(e),'Invalid parameter "collectionName"',1475378560),(typeof r!="object"||r===null||Array.isArray(r))&&(r={}),this.getFactory().createPropertyCollectionElement(t,r,e)}removePropertyCollectionElement(t,e,r,i){this.saveApplicationState(),this.getUtility().isUndefinedOrNull(r)&&(r=this.getCurrentlySelectedFormElement());const o=this.getRepository().findFormElement(r);n(this.getUtility().isNonEmptyString(t),'Invalid parameter "collectionElementIdentifier"',1475378561),n(this.getUtility().isNonEmptyString(e),'Invalid parameter "collectionName"',1475378562),this.getRepository().removePropertyCollectionElementByIdentifier(o,t,e,!0),i=!!i,i||this.getPublisherSubscriber().publish("core/formElement/somePropertyChanged",["__fakeProperty"])}movePropertyCollectionElement(t,e,r,i,o,l){this.saveApplicationState(),o=this.getRepository().findFormElement(o),n(typeof t=="string",'Invalid parameter "collectionElementToMove"',1477404352),n(typeof r=="string",'Invalid parameter "referenceCollectionElement"',1477404353),n(e==="after"||e==="before",'Invalid position "'+e+'"',1477404354),n(this.getUtility().isNonEmptyString(i),'Invalid parameter "collectionName"',1477404355),this.getRepository().movePropertyCollectionElement(t,e,r,i,o,l)}getFormElementDefinitionByType(t,e){n(this.getUtility().isNonEmptyString(t),'Invalid parameter "elementType"',1475378563);const r=this.getRepository().getFormEditorDefinition("formElements",t);if(e!==void 0){const i=r[e];return i!==null&&typeof i=="object"?m(i):i}return r!==null&&typeof r=="object"?m(r):r}getFormElementDefinition(t,e){return t=this.getRepository().findFormElement(t),this.getFormElementDefinitionByType(t.get("type"),e)}getFormEditorDefinition(t,e){return this.getRepository().getFormEditorDefinition(t,e)}getFormElementPropertyValidatorDefinition(t){n(this.getUtility().isNonEmptyString(t),'Invalid parameter "validatorIdentifier"',1475672362);const e=this.getRepository().getFormEditorDefinition("formElementPropertyValidators",t);return m(e)}getCurrentlySelectedPageIndex(){return this.getApplicationStateStack().getCurrentState("currentlySelectedPageIndex")}refreshCurrentlySelectedPageIndex(){this.getApplicationStateStack().setCurrentState("currentlySelectedPageIndex",this.getPageIndexFromFormElement(this.getCurrentlySelectedFormElement()))}getCurrentlySelectedPage(){const t=this.getRepository().getRootFormElement().get("renderables")[this.getCurrentlySelectedPageIndex()];return n(typeof t=="object"&&t!==null&&!Array.isArray(t),"No page found",1477786068),t}getLastTopLevelElementOnCurrentPage(){const t=this.getCurrentlySelectedPage().get("renderables");if(!this.getUtility().isUndefinedOrNull(t))return t[t.length-1]}getLastFormElementWithinParentFormElement(t){return t=this.getRepository().findFormElement(t),t.get("__identifierPath")===this.getRootFormElement().get("__identifierPath")?t:t.get("__parentRenderable").get("renderables")[t.get("__parentRenderable").get("renderables").length-1]}getPageIndexFromFormElement(t){return t=this.getRepository().findFormElement(t),this.getRepository().getIndexForEnclosingCompositeFormElementWhichIsOnTopLevelForFormElement(t)}renderCurrentFormPage(){this.renderFormPage(this.getCurrentlySelectedPageIndex())}renderFormPage(t){n(typeof t=="number",'Invalid parameter "pageIndex"',1475446442),this.getDataBackend().renderFormDefinitionPage(t)}findEnclosingCompositeFormElementWhichIsNotOnTopLevel(t){return this.getRepository().findEnclosingCompositeFormElementWhichIsNotOnTopLevel(this.getRepository().findFormElement(t))}findEnclosingGridRowFormElement(t){return this.getRepository().findEnclosingGridRowFormElement(this.getRepository().findFormElement(t))}getNonCompositeNonToplevelFormElements(){return this.getRepository().getNonCompositeNonToplevelFormElements()}isRootFormElementSelected(){return this.getCurrentlySelectedFormElement().get("__identifierPath")===this.getRootFormElement().get("__identifierPath")}getViewModel(){return this.viewModel}saveFormDefinition(){this.getDataBackend().saveFormDefinition()}run(){if(this.isRunning)throw new Error("You can not run the app twice (1473200696)");try{this.bootstrap(),this.isRunning=!0}catch(t){if(!(t instanceof Error))throw t;console.error("Form editor error:",t),y.error(d.get("formEditor.error.headline"),d.get("formEditor.error.message")+`\r
\r
`+d.get("formEditor.error.technicalReason")+`\r
`+t.message)}return this}saveApplicationState(){this.getApplicationStateStack().addAndReset({formDefinition:this.getApplicationStateStack().getCurrentState("formDefinition").clone(),currentlySelectedPageIndex:this.getApplicationStateStack().getCurrentState("currentlySelectedPageIndex"),currentlySelectedFormElementIdentifierPath:this.getApplicationStateStack().getCurrentState("currentlySelectedFormElementIdentifierPath")})}getDataBackend(){return a.getDataBackend()}getFactory(){return a.getFactory()}getRepository(){return a.getRepository()}getPropertyValidationService(){return a.getPropertyValidationService()}getApplicationStateStack(){return a.getApplicationStateStack()}dataBackendSetup(t,e,r){n(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "endpoints"',1475379748),n(this.getUtility().isNonEmptyString(e),'Invalid parameter "prototypeName"',1475927876),n(this.getUtility().isNonEmptyString(r),'Invalid parameter "formPersistenceIdentifier"',1475379749),a.getDataBackend().setEndpoints(t),a.getDataBackend().setPrototypeName(e),a.getDataBackend().setPersistenceIdentifier(r)}repositorySetup(t){n(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "formEditorDefinitions"',1475379750),this.getRepository().setFormEditorDefinitions(t)}viewSetup(t){n(typeof this.viewModel.bootstrap=="function",'The view model does not implement the method "bootstrap"',1475492374),this.getUtility().isUndefinedOrNull(t)&&(t=[]),this.viewModel.bootstrap(s,t)}mediatorSetup(){n(typeof this.mediator.bootstrap=="function",'The mediator does not implement the method "bootstrap"',1475492032),this.mediator.bootstrap(s,this.viewModel)}applicationStateStackSetup(t,e){n(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "rootFormElement"',1475379751),typeof e!="number"&&(e=10),this.getApplicationStateStack().setMaximalStackSize(e),this.getApplicationStateStack().addAndReset({currentlySelectedPageIndex:0,currentlySelectedFormElementIdentifierPath:t.identifier},!0),this.getApplicationStateStack().setCurrentState("formDefinition",this.getFactory().createFormElement(t,void 0,void 0,!0))}bootstrap(){this.mediatorSetup(),this.dataBackendSetup(this.configuration.endpoints,this.configuration.prototypeName,this.configuration.formPersistenceIdentifier),this.repositorySetup(this.configuration.formEditorDefinitions),this.applicationStateStackSetup(this.configuration.formDefinition,this.configuration.maximumUndoSteps),this.setCurrentlySelectedFormElement(this.getRepository().getRootFormElement()),this.viewSetup(this.configuration.additionalViewModelModules)}}let s=null;function h(g,t,e){return s===null&&(s=new c(g,t,e)),s}export{c as FormEditor,h as getInstance};
>>>>>>> e78ac586 ([TASK] Throw only Error objects in TypeScript)
