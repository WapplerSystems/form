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
 * Module: @typo3/form/backend/form-editor/core
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import { AjaxResponse } from '@typo3/core/ajax/ajax-response.js';
import { cloneDeep } from 'lodash-es';
export function assert(test, message, messageCode) {
    if (typeof test === 'function') {
        test = (test() !== false);
    }
    if (!test) {
        message = message || 'Assertion failed';
        if (messageCode) {
            message = message + ' (' + messageCode + ')';
        }
        if ('undefined' !== typeof Error) {
            throw new Error(message);
        }
        throw message;
    }
}
export class Utility {
    assert(test, message, messageCode) {
        assert(test, message, messageCode);
    }
    isUndefinedOrNull(value) {
        return value === undefined || value === null;
    }
    isNonEmptyArray(value) {
        return Array.isArray(value) && value.length > 0;
    }
    isNonEmptyString(value) {
        return typeof value === 'string' && value.length > 0;
    }
    canBeInterpretedAsInteger(value) {
        if (typeof value === 'number') {
            return true;
        }
        if (typeof value !== 'string') {
            return false;
        }
        const v = value;
        return (v * 1).toString() === v.toString() && v.toString().indexOf('.') === -1;
    }
    /**
     * @throws 1475412569
     * @throws 1475412570
     * @throws 1475415988
     * @throws 1475663210
     */
    buildPropertyPath(propertyPath, collectionElementIdentifier, collectionName, formElement, allowEmptyReturnValue) {
        let newPropertyPath = '';
        allowEmptyReturnValue = !!allowEmptyReturnValue;
        if (this.isNonEmptyString(collectionElementIdentifier) || this.isNonEmptyString(collectionName)) {
            assert(this.isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475412569);
            assert(this.isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475412570);
            newPropertyPath = collectionName + '.' + repository.getIndexFromPropertyCollectionElementByIdentifier(collectionElementIdentifier, collectionName, formElement);
        }
        else {
            newPropertyPath = '';
        }
        if (!this.isUndefinedOrNull(propertyPath)) {
            assert(this.isNonEmptyString(propertyPath), 'Invalid parameter "propertyPath"', 1475415988);
            if (this.isNonEmptyString(newPropertyPath)) {
                newPropertyPath = newPropertyPath + '.' + propertyPath;
            }
            else {
                newPropertyPath = propertyPath;
            }
        }
        if (!allowEmptyReturnValue) {
            assert(this.isNonEmptyString(newPropertyPath), 'The property path could not be resolved', 1475663210);
        }
        return newPropertyPath;
    }
    /**
     * @throws 1475377782
     */
    convertToSimpleObject(formElement) {
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475377782);
        const simpleObject = {};
        const objectData = ('getObjectData' in formElement && typeof formElement.getObjectData === 'function') ? formElement.getObjectData() : formElement;
        const childFormElements = objectData.renderables;
        delete objectData.renderables;
        for (const [key, value] of Object.entries(objectData)) {
            if (key.match(/^__/)) {
                continue;
            }
            if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
                simpleObject[key] = this.convertToSimpleObject(value);
            }
            else if (typeof value !== 'function' && typeof value !== 'undefined') {
                simpleObject[key] = value;
            }
        }
        if (Array.isArray(childFormElements)) {
            simpleObject.renderables = [];
            for (let i = 0, len = childFormElements.length; i < len; ++i) {
                simpleObject.renderables.push(this.convertToSimpleObject(childFormElements[i]));
            }
        }
        return simpleObject;
    }
}
export class PropertyValidationService {
    constructor() {
        this.validators = {};
    }
    /**
     * @throws 1475661025
     * @throws 1475661026
     * @throws 1479238074
     */
    addValidatorIdentifiersToFormElementProperty(formElement, validators, propertyPath, collectionElementIdentifier, collectionName, configuration) {
        assert(Array.isArray(validators), 'Invalid parameter "validators"', 1475661026);
        assert(Array.isArray(validators), 'Invalid parameter "validators"', 1479238074);
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475661025);
        const formElementIdentifierPath = formElement.get('__identifierPath');
        propertyPath = utility.buildPropertyPath(propertyPath, collectionElementIdentifier, collectionName, formElement);
        const propertyValidationServiceRegisteredValidators = getApplicationStateStack().getCurrentState('propertyValidationServiceRegisteredValidators');
        if (utility.isUndefinedOrNull(propertyValidationServiceRegisteredValidators[formElementIdentifierPath])) {
            propertyValidationServiceRegisteredValidators[formElementIdentifierPath] = {};
        }
        if (utility.isUndefinedOrNull(propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath])) {
            propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath] = {
                validators: [],
                configuration: configuration
            };
        }
        for (const validator of validators) {
            if (propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath].validators.indexOf(validator) === -1) {
                propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath].validators.push(validator);
            }
        }
        getApplicationStateStack().setCurrentState('propertyValidationServiceRegisteredValidators', propertyValidationServiceRegisteredValidators);
    }
    /**
     * @throws 1475700618
     * @throws 1475706896
     */
    removeValidatorIdentifiersFromFormElementProperty(formElement, propertyPath) {
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475700618);
        assert(utility.isNonEmptyString(propertyPath), 'Invalid parameter "propertyPath"', 1475706896);
        const formElementIdentifierPath = formElement.get('__identifierPath');
        const registeredValidators = {};
        const propertyValidationServiceRegisteredValidators = getApplicationStateStack().getCurrentState('propertyValidationServiceRegisteredValidators');
        if (formElementIdentifierPath in propertyValidationServiceRegisteredValidators) {
            for (const registeredPropertyPath of Object.keys(propertyValidationServiceRegisteredValidators[formElementIdentifierPath] || {})) {
                if (registeredPropertyPath.indexOf(propertyPath) > -1) {
                    continue;
                }
                registeredValidators[registeredPropertyPath] = propertyValidationServiceRegisteredValidators[formElementIdentifierPath][registeredPropertyPath];
            }
        }
        propertyValidationServiceRegisteredValidators[formElementIdentifierPath] = registeredValidators;
        getApplicationStateStack().setCurrentState('propertyValidationServiceRegisteredValidators', propertyValidationServiceRegisteredValidators);
    }
    /**
     * @throws 1475668189
     */
    removeAllValidatorIdentifiersFromFormElement(formElement) {
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475668189);
        const registeredValidators = {};
        const propertyValidationServiceRegisteredValidators = getApplicationStateStack().getCurrentState('propertyValidationServiceRegisteredValidators');
        for (const formElementIdentifierPath of Object.keys(propertyValidationServiceRegisteredValidators || {})) {
            if (formElementIdentifierPath === formElement.get('__identifierPath')
                || formElementIdentifierPath.indexOf(formElement.get('__identifierPath') + '/') > -1) {
                continue;
            }
            registeredValidators[formElementIdentifierPath] = propertyValidationServiceRegisteredValidators[formElementIdentifierPath];
        }
        getApplicationStateStack().setCurrentState('propertyValidationServiceRegisteredValidators', registeredValidators);
    }
    /**
     * @throws 1475669143
     * @throws 1475669144
     * @throws 1475669145
     */
    addValidator(validatorIdentifier, func) {
        assert(utility.isNonEmptyString(validatorIdentifier), 'Invalid parameter "validatorIdentifier"', 1475669143);
        assert(typeof func === 'function', 'Invalid parameter "func"', 1475669144);
        assert(typeof this.validators[validatorIdentifier] !== 'function', 'The validator "' + validatorIdentifier + '" is already registered', 1475669145);
        this.validators[validatorIdentifier] = func;
    }
    /**
     * @throws 1475676517
     * @throws 1475676518
     */
    validateFormElementProperty(formElement, propertyPath) {
        let configuration;
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475676517);
        assert(utility.isNonEmptyString(propertyPath), 'Invalid parameter "propertyPath"', 1475676518);
        const formElementIdentifierPath = formElement.get('__identifierPath');
        const validationResults = [];
        const propertyValidationServiceRegisteredValidators = getApplicationStateStack().getCurrentState('propertyValidationServiceRegisteredValidators');
        configuration = {
            propertyValidatorsMode: 'AND'
        };
        if (!utility.isUndefinedOrNull(propertyValidationServiceRegisteredValidators[formElementIdentifierPath])
            && typeof propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath] === 'object' && propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath] !== null && !Array.isArray(propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath])
            && Array.isArray(propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath].validators)) {
            configuration = propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath].configuration;
            for (let i = 0, len = propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath].validators.length; i < len; ++i) {
                const validatorIdentifier = propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath].validators[i];
                if (typeof this.validators[validatorIdentifier] !== 'function') {
                    continue;
                }
                const validationResult = this.validators[validatorIdentifier](formElement, propertyPath);
                if (utility.isNonEmptyString(validationResult)) {
                    validationResults.push(validationResult);
                }
            }
        }
        if (validationResults.length > 0
            && configuration.propertyValidatorsMode === 'OR'
            && validationResults.length !== propertyValidationServiceRegisteredValidators[formElementIdentifierPath][propertyPath].validators.length) {
            return [];
        }
        return validationResults;
    }
    /**
     * @throws 1475749668
     */
    validateFormElement(formElement) {
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475749668);
        const formElementIdentifierPath = formElement.get('__identifierPath');
        const validationResults = [];
        const propertyValidationServiceRegisteredValidators = getApplicationStateStack().getCurrentState('propertyValidationServiceRegisteredValidators');
        if (!utility.isUndefinedOrNull(propertyValidationServiceRegisteredValidators[formElementIdentifierPath])) {
            for (const registeredPropertyPath of Object.keys(propertyValidationServiceRegisteredValidators[formElementIdentifierPath])) {
                validationResults.push({
                    propertyPath: registeredPropertyPath,
                    validationResults: this.validateFormElementProperty(formElement, registeredPropertyPath)
                });
            }
        }
        return validationResults;
    }
    /**
     * @throws 1478613477
     */
    validationResultsHasErrors(validationResults) {
        assert(Array.isArray(validationResults), 'Invalid parameter "validationResults"', 1478613477);
        for (let i = 0, len = validationResults.length; i < len; ++i) {
            for (let j = 0, len2 = validationResults[i].validationResults.length; j < len2; ++j) {
                if (validationResults[i].validationResults[j].validationResults
                    && validationResults[i].validationResults[j].validationResults.length > 0) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * @throws 1475749668
     */
    validateFormElementRecursive(formElement, returnAfterFirstMatch, validationResults) {
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475756764);
        returnAfterFirstMatch = !!returnAfterFirstMatch;
        validationResults = validationResults || [];
        validationResults.push({
            formElementIdentifierPath: formElement.get('__identifierPath'),
            validationResults: this.validateFormElement(formElement)
        });
        if (returnAfterFirstMatch && this.validationResultsHasErrors(validationResults)) {
            return validationResults;
        }
        const formElements = formElement.get('renderables');
        if (Array.isArray(formElements)) {
            for (let i = 0, len = formElements.length; i < len; ++i) {
                this.validateFormElementRecursive(formElements[i], returnAfterFirstMatch, validationResults);
                if (returnAfterFirstMatch && this.validationResultsHasErrors(validationResults)) {
                    return validationResults;
                }
            }
        }
        return validationResults;
    }
    /**
     * @throws 1475707334
     */
    addValidatorIdentifiersFromFormElementPropertyCollections(formElement) {
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475707334);
        const formElementTypeDefinition = repository.getFormEditorDefinition('formElements', formElement.get('type'));
        if (!utility.isUndefinedOrNull(formElementTypeDefinition.propertyCollections)) {
            for (const collectionName of Object.keys(formElementTypeDefinition.propertyCollections)) {
                if (!Array.isArray(formElementTypeDefinition.propertyCollections[collectionName])) {
                    continue;
                }
                for (let i = 0, len1 = formElementTypeDefinition.propertyCollections[collectionName].length; i < len1; ++i) {
                    if (!Array.isArray(formElementTypeDefinition.propertyCollections[collectionName][i].editors)
                        || repository.getIndexFromPropertyCollectionElementByIdentifier(formElementTypeDefinition.propertyCollections[collectionName][i].identifier, collectionName, formElement) === -1) {
                        continue;
                    }
                    for (let j = 0, len2 = formElementTypeDefinition.propertyCollections[collectionName][i].editors.length; j < len2; ++j) {
                        if (!Array.isArray(formElementTypeDefinition.propertyCollections[collectionName][i].editors[j].propertyValidators)) {
                            continue;
                        }
                        const propertyValidatorConfiguration = {
                            propertyValidatorsMode: 'AND'
                        };
                        if (!utility.isUndefinedOrNull(formElementTypeDefinition.propertyCollections[collectionName][i].editors[j].propertyValidatorsMode)
                            && formElementTypeDefinition.propertyCollections[collectionName][i].editors[j].propertyValidatorsMode === 'OR') {
                            propertyValidatorConfiguration.propertyValidatorsMode = 'OR';
                        }
                        this.addValidatorIdentifiersToFormElementProperty(formElement, formElementTypeDefinition.propertyCollections[collectionName][i].editors[j].propertyValidators, formElementTypeDefinition.propertyCollections[collectionName][i].editors[j].propertyPath, formElementTypeDefinition.propertyCollections[collectionName][i].identifier, collectionName, propertyValidatorConfiguration);
                    }
                }
            }
        }
    }
}
/**
 * Implements the "Publish/Subscribe Pattern"
 * @credits Addy Osmani https://addyosmani.com/resources/essentialjsdesignpatterns/book/#highlighter_634280
 */
export class PublisherSubscriber {
    constructor() {
        this.topics = {};
        this.subscriberUid = -1;
    }
    /**
     * @throws 1475358066
     */
    publish(topic, args) {
        assert(utility.isNonEmptyString(topic), 'Invalid parameter "topic"', 1475358066);
        if (utility.isUndefinedOrNull(this.topics[topic])) {
            return;
        }
        const topicFunctions = this.topics[topic];
        for (const entry of topicFunctions) {
            entry.func(topic, args);
        }
    }
    /**
     * @throws 1475358067
     */
    subscribe(topic, func) {
        assert(utility.isNonEmptyString(topic), 'Invalid parameter "topic"', 1475358067);
        assert(typeof func === 'function', 'Invalid parameter "func"', 1475411986);
        if (utility.isUndefinedOrNull(this.topics[topic])) {
            this.topics[topic] = [];
        }
        const token = (++this.subscriberUid).toString();
        this.topics[topic].push({
            token: token,
            //func: func as PublisherSubscriberFunction<U, U>
            func: func
            //func: func as F
        });
        return token;
    }
    /**
     * @throws 1475358068
     */
    unsubscribe(token) {
        assert(utility.isNonEmptyString(token), 'Invalid parameter "token"', 1475358068);
        for (const tmp of Object.values(this.topics)) {
            const entries = tmp;
            for (let i = 0, len = entries.length; i < len; ++i) {
                if (entries[i].token === token) {
                    entries.splice(i, 1);
                    return token;
                }
            }
        }
        return null;
    }
}
/**
 * @throws 1474640022
 * @throws 1475358069
 * @throws 1475358070
 * @publish core/formElement/somePropertyChanged
 */
function extendModel(modelToExtend, modelExtension, pathPrefix, disablePublishersOnSet) {
    assert(typeof modelToExtend === 'object' && modelToExtend !== null && !Array.isArray(modelToExtend), 'Invalid parameter "modelToExtend"', 1475358069);
    assert(typeof modelExtension === 'object' && modelExtension !== null, 'Invalid parameter "modelExtension"', 1475358070);
    disablePublishersOnSet = !!disablePublishersOnSet;
    pathPrefix = pathPrefix || '';
    if (typeof modelExtension === 'object' && Object.keys(modelExtension).length === 0) {
        assert('' !== pathPrefix, 'Empty path is not allowed', 1474640022);
        modelToExtend.on(pathPrefix, 'core/formElement/somePropertyChanged');
        modelToExtend.set(pathPrefix, modelExtension, disablePublishersOnSet);
    }
    else {
        const _modelExtension = { ...modelExtension };
        for (const key of Object.keys(_modelExtension)) {
            const path = (pathPrefix === '') ? key : pathPrefix + '.' + key;
            modelToExtend.on(path, 'core/formElement/somePropertyChanged');
            if (_modelExtension[key] !== null && (typeof (_modelExtension[key]) === 'object' || Array.isArray(_modelExtension[key]))) {
                extendModel(modelToExtend, _modelExtension[key], path, disablePublishersOnSet);
            }
            else if (pathPrefix === 'properties.options') {
                modelToExtend.set(pathPrefix, modelExtension, disablePublishersOnSet);
            }
            else {
                modelToExtend.set(path, _modelExtension[key], disablePublishersOnSet);
            }
        }
    }
}
export class Model {
    constructor() {
        this.objectData = {};
        this.publisherTopics = {};
    }
    /**
     * @throws 1475361755
     */
    get(key) {
        let firstPartOfPath;
        let obj;
        assert(utility.isNonEmptyString(key), 'Invalid parameter "key"', 1475361755);
        obj = this.objectData;
        while (key.indexOf('.') > 0) {
            firstPartOfPath = key.slice(0, key.indexOf('.'));
            key = key.slice(firstPartOfPath.length + 1);
            if (!(firstPartOfPath in obj)) {
                return undefined;
            }
            obj = obj[firstPartOfPath];
        }
        return obj[key];
    }
    /**
     * @throws 1475361756
     * @publish mixed
     */
    set(key, value, disablePublishersOnSet) {
        let path;
        let firstPartOfPath;
        let nextPartOfPath;
        let index;
        let obj;
        assert(utility.isNonEmptyString(key), 'Invalid parameter "key"', 1475361756);
        disablePublishersOnSet = !!disablePublishersOnSet;
        const oldValue = this.get(key);
        obj = this.objectData;
        path = key;
        while (path.indexOf('.') > 0) {
            firstPartOfPath = path.slice(0, path.indexOf('.'));
            path = path.slice(firstPartOfPath.length + 1);
            if (!isNaN(Number(firstPartOfPath))) {
                firstPartOfPath = parseInt(firstPartOfPath, 10);
            }
            index = path.indexOf('.');
            nextPartOfPath = index === -1 ? path : path.slice(0, index);
            // initialize objects case they are undefined by looking up the type
            // of the next path segment, the target type is guessed(!), thus e.g.
            // "key" results in having an object, "123" results in having an array
            if (typeof obj[firstPartOfPath] === 'undefined') {
                if (!isNaN(Number(nextPartOfPath))) {
                    obj[firstPartOfPath] = [];
                }
                else {
                    obj[firstPartOfPath] = {};
                }
                // in case the previous guess was wrong, the initialized array
                // is converted to an object when a non-numeric path segment is found
            }
            else if (isNaN(Number(nextPartOfPath)) && Array.isArray(obj[firstPartOfPath])) {
                obj[firstPartOfPath] = { ...obj[firstPartOfPath] };
            }
            obj = obj[firstPartOfPath];
        }
        obj[path] = value;
        if (!utility.isUndefinedOrNull(this.publisherTopics[key]) && !disablePublishersOnSet) {
            for (let i = 0, len = this.publisherTopics[key].length; i < len; ++i) {
                publisherSubscriber.publish(this.publisherTopics[key][i], [key, value, oldValue, this.objectData.__identifierPath]);
            }
        }
    }
    /**
     * @throws 1489321637
     * @throws 1489319753
     * @publish mixed
     */
    unset(key, disablePublishersOnSet) {
        let parentPropertyData, parentPropertyPath, propertyToRemove;
        assert(utility.isNonEmptyString(key), 'Invalid parameter "key"', 1489321637);
        disablePublishersOnSet = !!disablePublishersOnSet;
        const oldValue = this.get(key);
        if (key.indexOf('.') > 0) {
            parentPropertyPath = key.split('.');
            propertyToRemove = parentPropertyPath.pop();
            parentPropertyPath = parentPropertyPath.join('.');
            parentPropertyData = this.get(parentPropertyPath);
            if (typeof parentPropertyData !== 'undefined') {
                delete parentPropertyData[propertyToRemove];
            }
        }
        else {
            assert(false, 'remove toplevel properties is not supported', 1489319753);
        }
        if (!utility.isUndefinedOrNull(this.publisherTopics[key]) && !disablePublishersOnSet) {
            for (let i = 0, len = this.publisherTopics[key].length; i < len; ++i) {
                publisherSubscriber.publish(this.publisherTopics[key][i], [key, undefined, oldValue, this.objectData.__identifierPath]);
            }
        }
    }
    /**
     * @throws 1475361757
     * @throws 1475361758
     */
    on(key, topicName) {
        assert(utility.isNonEmptyString(key), 'Invalid parameter "key"', 1475361757);
        assert(utility.isNonEmptyString(topicName), 'Invalid parameter "topicName"', 1475361758);
        if (!Array.isArray(this.publisherTopics[key])) {
            this.publisherTopics[key] = [];
        }
        if (this.publisherTopics[key].indexOf(topicName) === -1) {
            this.publisherTopics[key].push(topicName);
        }
    }
    /**
     * @throws 1475361759
     * @throws 1475361760
     */
    off(key, topicName) {
        assert(utility.isNonEmptyString(key), 'Invalid parameter "key"', 1475361759);
        assert(utility.isNonEmptyString(topicName), 'Invalid parameter "topicName"', 1475361760);
        if (Array.isArray(this.publisherTopics[key])) {
            this.publisherTopics[key] = this.publisherTopics[key].filter((currentTopicName) => topicName !== currentTopicName);
        }
    }
    getObjectData() {
        // Return dereferenced object
        return cloneDeep(this.objectData);
    }
    toString() {
        const objectData = this.getObjectData();
        const { renderables, __parentRenderable, ...restObjectData } = objectData;
        const childFormElements = renderables || null;
        let parentRenderable = null;
        if (!utility.isUndefinedOrNull(__parentRenderable)) {
            parentRenderable = __parentRenderable.getObjectData().__identifierPath + ' (filtered)';
        }
        const myObjectData = restObjectData;
        if (parentRenderable !== null) {
            myObjectData.__parentRenderable = parentRenderable;
        }
        if (childFormElements !== null && Array.isArray(childFormElements)) {
            const renderables = [];
            for (let i = 0, len = childFormElements.length; i < len; ++i) {
                const childFormElement = childFormElements[i];
                renderables.push(JSON.parse(childFormElement.toString()));
            }
            myObjectData.renderables = renderables;
        }
        return JSON.stringify(myObjectData, null, 2);
    }
    clone() {
        const objectData = this.getObjectData();
        const childFormElements = objectData.renderables || null;
        delete objectData.renderables;
        delete objectData.__parentRenderable;
        objectData.renderables = (childFormElements) ? true : null;
        const newModel = new Model();
        extendModel(newModel, objectData, '', true);
        if (null !== childFormElements && Array.isArray(childFormElements)) {
            const newRenderables = [];
            for (let i = 0, len = childFormElements.length; i < len; ++i) {
                let childFormElement = childFormElements[i];
                childFormElement = childFormElement.clone();
                childFormElement.set('__parentRenderable', newModel, true);
                newRenderables.push(childFormElement);
            }
            newModel.set('renderables', newRenderables, true);
        }
        return newModel;
    }
}
function createModel(modelExtension) {
    modelExtension = modelExtension || {};
    const newModel = new Model();
    extendModel(newModel, modelExtension, '', true);
    return newModel;
}
export class Repository {
    /**
     * @throws 1475364394
     */
    setFormEditorDefinitions(formEditorDefinitions) {
        assert(typeof formEditorDefinitions === 'object' && formEditorDefinitions !== null && !Array.isArray(formEditorDefinitions), 'Invalid parameter "formEditorDefinitions"', 1475364394);
        for (const _key1 of Object.keys(formEditorDefinitions)) {
            const key1 = _key1;
            if (formEditorDefinitions[key1] !== null && typeof formEditorDefinitions[key1] !== 'object') {
                continue;
            }
            for (const key2 of Object.keys(formEditorDefinitions[key1])) {
                if (formEditorDefinitions[key1][key2] === null ||
                    typeof formEditorDefinitions[key1][key2] !== 'object') {
                    formEditorDefinitions[key1][key2] = {};
                }
            }
        }
        this.formEditorDefinitions = formEditorDefinitions;
    }
    /**
     * @throws 1475364952
     * @throws 1475364953
     */
    getFormEditorDefinition(definitionName, subject) {
        assert(utility.isNonEmptyString(definitionName), 'Invalid parameter "definitionName"', 1475364952);
        assert(utility.isNonEmptyString(subject), 'Invalid parameter "subject"', 1475364953);
        // Return dereferenced object
        return cloneDeep(this.formEditorDefinitions[definitionName][subject]);
    }
    getRootFormElement() {
        return getApplicationStateStack().getCurrentState('formDefinition');
    }
    /**
     * @throws 1475436224
     * @throws 1475364956
     */
    addFormElement(formElement, referenceFormElement, registerPropertyValidators, disablePublishersOnSet) {
        let enclosingCompositeFormElement, parentFormElementsArray, referenceFormElementElements;
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475436224);
        assert(typeof referenceFormElement === 'object' && referenceFormElement !== null && !Array.isArray(referenceFormElement), 'Invalid parameter "referenceFormElement"', 1475364956);
        if (utility.isUndefinedOrNull(disablePublishersOnSet)) {
            disablePublishersOnSet = true;
        }
        disablePublishersOnSet = !!disablePublishersOnSet;
        registerPropertyValidators = !!registerPropertyValidators;
        const formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        const referenceFormElementTypeDefinition = this.getFormEditorDefinition('formElements', referenceFormElement.get('type'));
        // formElement != Page / SummaryPage && referenceFormElement == Page / Fieldset / GridRow
        if (!formElementTypeDefinition._isTopLevelFormElement && referenceFormElementTypeDefinition._isCompositeFormElement) {
            if (!Array.isArray(referenceFormElement.get('renderables'))) {
                referenceFormElement.set('renderables', [], disablePublishersOnSet);
            }
            formElement.set('__parentRenderable', referenceFormElement, disablePublishersOnSet);
            formElement.set('__identifierPath', referenceFormElement.get('__identifierPath') + '/' + formElement.get('identifier'), disablePublishersOnSet);
            referenceFormElement.get('renderables').push(formElement);
        }
        else {
            // referenceFormElement == root form element
            if (referenceFormElement.get('__identifierPath') === getApplicationStateStack().getCurrentState('formDefinition').get('__identifierPath')) {
                referenceFormElementElements = referenceFormElement.get('renderables');
                // referenceFormElement = last page
                referenceFormElement = referenceFormElementElements[referenceFormElementElements.length - 1];
                // if formElement == Page / SummaryPage && referenceFormElement != Page / SummaryPage
            }
            else if (formElementTypeDefinition._isTopLevelFormElement && !referenceFormElementTypeDefinition._isTopLevelFormElement) {
                // referenceFormElement = parent Page
                referenceFormElement = this.findEnclosingCompositeFormElementWhichIsOnTopLevel(referenceFormElement);
                // formElement == Page / SummaryPage / Fieldset / GridRow
            }
            else if (formElementTypeDefinition._isCompositeFormElement) {
                enclosingCompositeFormElement = this.findEnclosingCompositeFormElementWhichIsNotOnTopLevel(referenceFormElement);
                if (enclosingCompositeFormElement) {
                    // referenceFormElement = parent Fieldset / GridRow
                    referenceFormElement = enclosingCompositeFormElement;
                }
            }
            formElement.set('__parentRenderable', referenceFormElement.get('__parentRenderable'), disablePublishersOnSet);
            formElement.set('__identifierPath', referenceFormElement.get('__parentRenderable').get('__identifierPath') + '/' + formElement.get('identifier'), disablePublishersOnSet);
            parentFormElementsArray = referenceFormElement.get('__parentRenderable').get('renderables');
            parentFormElementsArray.splice(parentFormElementsArray.indexOf(referenceFormElement) + 1, 0, formElement);
        }
        if (registerPropertyValidators) {
            if (Array.isArray(formElementTypeDefinition.editors)) {
                for (let i = 0, len1 = formElementTypeDefinition.editors.length; i < len1; ++i) {
                    if (!Array.isArray(formElementTypeDefinition.editors[i].propertyValidators)) {
                        continue;
                    }
                    const propertyValidatorConfiguration = {
                        propertyValidatorsMode: 'AND'
                    };
                    if (!utility.isUndefinedOrNull(formElementTypeDefinition.editors[i].propertyValidatorsMode)
                        && formElementTypeDefinition.editors[i].propertyValidatorsMode === 'OR') {
                        propertyValidatorConfiguration.propertyValidatorsMode = 'OR';
                    }
                    propertyValidationService.addValidatorIdentifiersToFormElementProperty(formElement, formElementTypeDefinition.editors[i].propertyValidators, formElementTypeDefinition.editors[i].propertyPath, undefined, undefined, propertyValidatorConfiguration);
                }
            }
        }
        return formElement;
    }
    /**
     * @throws 1472553024
     * @throws 1475364957
     */
    removeFormElement(formElement, removeRegisteredPropertyValidators, disablePublishersOnSet) {
        if (utility.isUndefinedOrNull(disablePublishersOnSet)) {
            disablePublishersOnSet = true;
        }
        disablePublishersOnSet = !!disablePublishersOnSet;
        removeRegisteredPropertyValidators = !!removeRegisteredPropertyValidators;
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475364957);
        assert(typeof formElement.get('__parentRenderable') === 'object' && formElement.get('__parentRenderable') !== null && !Array.isArray(formElement.get('__parentRenderable')), 'Removing the root element is not allowed', 1472553024);
        const parentFormElementElements = formElement.get('__parentRenderable').get('renderables');
        parentFormElementElements.splice(parentFormElementElements.indexOf(formElement), 1);
        formElement.get('__parentRenderable').set('renderables', parentFormElementElements, disablePublishersOnSet);
        if (removeRegisteredPropertyValidators) {
            propertyValidationService.removeAllValidatorIdentifiersFromFormElement(formElement);
        }
    }
    /**
     * @throws 1475364958
     * @throws 1475364959
     * @throws 1475364960
     * @throws 1475364961
     * @throws 1475364962
     * @throws 1476993731
     * @throws 1476993732
     */
    moveFormElement(formElementToMove, position, referenceFormElement, disablePublishersOnSet) {
        let referenceFormElementParentElements, referenceFormElementElements, referenceFormElementIndex;
        assert(typeof formElementToMove === 'object' && formElementToMove !== null && !Array.isArray(formElementToMove), 'Invalid parameter "formElementToMove"', 1475364958);
        assert('after' === position || 'before' === position || 'inside' === position, 'Invalid position "' + position + '"', 1475364959);
        assert(typeof referenceFormElement === 'object' && referenceFormElement !== null && !Array.isArray(referenceFormElement), 'Invalid parameter "referenceFormElement"', 1475364960);
        if (utility.isUndefinedOrNull(disablePublishersOnSet)) {
            disablePublishersOnSet = true;
        }
        disablePublishersOnSet = !!disablePublishersOnSet;
        const formElementToMoveTypeDefinition = this.getFormEditorDefinition('formElements', formElementToMove.get('type'));
        const referenceFormElementTypeDefinition = this.getFormEditorDefinition('formElements', referenceFormElement.get('type'));
        this.removeFormElement(formElementToMove, false);
        const reSetIdentifierPath = (formElement, pathPrefix) => {
            assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475364961);
            assert(utility.isNonEmptyString(pathPrefix), 'Invalid parameter "pathPrefix"', 1475364962);
            const oldIdentifierPath = formElement.get('__identifierPath');
            const newIdentifierPath = pathPrefix + '/' + formElement.get('identifier');
            const propertyValidationServiceRegisteredValidators = getApplicationStateStack().getCurrentState('propertyValidationServiceRegisteredValidators');
            if (!utility.isUndefinedOrNull(propertyValidationServiceRegisteredValidators[oldIdentifierPath])) {
                propertyValidationServiceRegisteredValidators[newIdentifierPath] = propertyValidationServiceRegisteredValidators[oldIdentifierPath];
                delete propertyValidationServiceRegisteredValidators[oldIdentifierPath];
            }
            getApplicationStateStack().setCurrentState('propertyValidationServiceRegisteredValidators', propertyValidationServiceRegisteredValidators);
            formElement.set('__identifierPath', newIdentifierPath, disablePublishersOnSet);
            const formElements = formElement.get('renderables');
            if (Array.isArray(formElements)) {
                for (let i = 0, len = formElements.length; i < len; ++i) {
                    reSetIdentifierPath(formElements[i], formElement.get('__identifierPath'));
                }
            }
        };
        /**
         * This is true on:
         * * Drag a Element on a Page Element (tree)
         * * Drag a Element on a Section Element (tree)
         */
        if (position === 'inside') {
            // formElementToMove == Page / SummaryPage
            assert(!formElementToMoveTypeDefinition._isTopLevelFormElement, 'This move is not allowed', 1476993731);
            // referenceFormElement != Page / Fieldset / GridRow
            assert(referenceFormElementTypeDefinition._isCompositeFormElement, 'This move is not allowed', 1476993732);
            formElementToMove.set('__parentRenderable', referenceFormElement, disablePublishersOnSet);
            reSetIdentifierPath(formElementToMove, referenceFormElement.get('__identifierPath'));
            referenceFormElementElements = referenceFormElement.get('renderables');
            if (utility.isUndefinedOrNull(referenceFormElementElements)) {
                referenceFormElementElements = [];
            }
            referenceFormElementElements.splice(0, 0, formElementToMove);
            referenceFormElement.set('renderables', referenceFormElementElements, disablePublishersOnSet);
        }
        else {
            /**
             * This is true on:
             * * Drag a Page before another Page (tree)
             * * Drag a Page after another Page (tree)
             */
            if (formElementToMoveTypeDefinition._isTopLevelFormElement && referenceFormElementTypeDefinition._isTopLevelFormElement) {
                referenceFormElementParentElements = referenceFormElement.get('__parentRenderable').get('renderables');
                referenceFormElementIndex = referenceFormElementParentElements.indexOf(referenceFormElement);
                if (position === 'after') {
                    referenceFormElementParentElements.splice(referenceFormElementIndex + 1, 0, formElementToMove);
                }
                else {
                    referenceFormElementParentElements.splice(referenceFormElementIndex, 0, formElementToMove);
                }
                referenceFormElement.get('__parentRenderable').set('renderables', referenceFormElementParentElements, disablePublishersOnSet);
            }
            else {
                /**
                 * This is true on:
                 * * Drag a Element before another Element within the same level (tree)
                 * * Drag a Element after another Element within the same level (tree)
                 * * Drag a Element before another Element (stage)
                 * * Drag a Element after another Element (stage)
                 */
                if (formElementToMove.get('__parentRenderable').get('identifier') === referenceFormElement.get('__parentRenderable').get('identifier')) {
                    referenceFormElementParentElements = referenceFormElement.get('__parentRenderable').get('renderables');
                    referenceFormElementIndex = referenceFormElementParentElements.indexOf(referenceFormElement);
                }
                else {
                    /**
                     * This is true on:
                     * * Drag a Element before an Element on another page (tree / stage)
                     * * Drag a Element after an Element on another page (tree / stage)
                     */
                    formElementToMove.set('__parentRenderable', referenceFormElement.get('__parentRenderable'), disablePublishersOnSet);
                    reSetIdentifierPath(formElementToMove, referenceFormElement.get('__parentRenderable').get('__identifierPath'));
                    referenceFormElementParentElements = referenceFormElement.get('__parentRenderable').get('renderables');
                    referenceFormElementIndex = referenceFormElementParentElements.indexOf(referenceFormElement);
                }
                if (position === 'after') {
                    referenceFormElementParentElements.splice(referenceFormElementIndex + 1, 0, formElementToMove);
                }
                else {
                    referenceFormElementParentElements.splice(referenceFormElementIndex, 0, formElementToMove);
                }
                referenceFormElement.get('__parentRenderable').set('renderables', referenceFormElementParentElements, disablePublishersOnSet);
            }
        }
        return formElementToMove;
    }
    /**
     * @throws 1475364963
     */
    getIndexForEnclosingCompositeFormElementWhichIsOnTopLevelForFormElement(formElement) {
        let enclosingCompositeFormElementWhichIsOnTopLevel;
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475364963);
        const formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        if (formElementTypeDefinition._isTopLevelFormElement && formElementTypeDefinition._isCompositeFormElement) {
            enclosingCompositeFormElementWhichIsOnTopLevel = formElement;
        }
        else if (formElement.get('__identifierPath') === getApplicationStateStack().getCurrentState('formDefinition').get('__identifierPath')) {
            enclosingCompositeFormElementWhichIsOnTopLevel = getApplicationStateStack().getCurrentState('formDefinition').get('renderables')[0];
        }
        else {
            enclosingCompositeFormElementWhichIsOnTopLevel = this.findEnclosingCompositeFormElementWhichIsOnTopLevel(formElement);
        }
        return enclosingCompositeFormElementWhichIsOnTopLevel.get('__parentRenderable').get('renderables').indexOf(enclosingCompositeFormElementWhichIsOnTopLevel);
    }
    /**
     * @throws 1472556223
     * @throws 1475364964
     */
    findEnclosingCompositeFormElementWhichIsOnTopLevel(formElement) {
        let formElementTypeDefinition;
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475364964);
        assert(typeof formElement.get('__parentRenderable') === 'object' && formElement.get('__parentRenderable') !== null && !Array.isArray(formElement.get('__parentRenderable')), 'The root element is never encloused by anything', 1472556223);
        formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        while (!formElementTypeDefinition._isTopLevelFormElement) {
            formElement = formElement.get('__parentRenderable');
            formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        }
        return formElement;
    }
    /**
     * @throws 1490520271
     */
    findEnclosingGridRowFormElement(formElement) {
        let formElementTypeDefinition;
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1490520271);
        formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        while (!formElementTypeDefinition._isGridRowFormElement) {
            if (formElementTypeDefinition._isTopLevelFormElement) {
                return null;
            }
            formElement = formElement.get('__parentRenderable');
            formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        }
        if (formElementTypeDefinition._isTopLevelFormElement) {
            return null;
        }
        return formElement;
    }
    /**
     * @throws 1475364965
     */
    findEnclosingCompositeFormElementWhichIsNotOnTopLevel(formElement) {
        let formElementTypeDefinition;
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475364965);
        formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        while (!formElementTypeDefinition._isCompositeFormElement) {
            if (formElementTypeDefinition._isTopLevelFormElement) {
                return null;
            }
            formElement = formElement.get('__parentRenderable');
            formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
        }
        if (formElementTypeDefinition._isTopLevelFormElement) {
            return null;
        }
        return formElement;
    }
    getNonCompositeNonToplevelFormElements() {
        const nonCompositeNonToplevelFormElements = [];
        const collect = (formElement) => {
            assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475364961);
            const formElementTypeDefinition = this.getFormEditorDefinition('formElements', formElement.get('type'));
            if (!formElementTypeDefinition._isTopLevelFormElement && !formElementTypeDefinition._isCompositeFormElement) {
                nonCompositeNonToplevelFormElements.push(formElement);
            }
            const formElements = formElement.get('renderables');
            if (Array.isArray(formElements)) {
                for (let i = 0, len = formElements.length; i < len; ++i) {
                    collect(formElements[i]);
                }
            }
        };
        collect(this.getRootFormElement());
        return nonCompositeNonToplevelFormElements;
    }
    /**
     * @throws 1475364966
     */
    isFormElementIdentifierUsed(identifier) {
        let identifierFound;
        assert(utility.isNonEmptyString(identifier), 'Invalid parameter "identifier"', 1475364966);
        const checkIdentifier = (formElement) => {
            let formElements;
            if (formElement.get('identifier') === identifier) {
                identifierFound = true;
            }
            if (!identifierFound) {
                formElements = formElement.get('renderables');
                if (Array.isArray(formElements)) {
                    for (let i = 0, len = formElements.length; i < len; ++i) {
                        checkIdentifier(formElements[i]);
                        if (identifierFound) {
                            break;
                        }
                    }
                }
            }
        };
        checkIdentifier(getApplicationStateStack().getCurrentState('formDefinition'));
        return identifierFound;
    }
    /**
     * @throws 1475373676
     */
    getNextFreeFormElementIdentifier(formElementType) {
        let i;
        assert(utility.isNonEmptyString(formElementType), 'Invalid parameter "formElementType"', 1475373676);
        const prefix = formElementType.toLowerCase().replace(/[^a-z0-9]/g, '-') + '-';
        i = 1;
        while (this.isFormElementIdentifierUsed(prefix + i)) {
            i++;
        }
        return prefix + i;
    }
    /**
     * @throws 1472424333
     * @throws 1472424334
     * @throws 1472424330
     * @throws 1475373677
     */
    findFormElementByIdentifierPath(identifierPath) {
        let obj, formElements;
        assert(utility.isNonEmptyString(identifierPath), 'Invalid parameter "identifierPath"', 1475373677);
        let formElement = getApplicationStateStack().getCurrentState('formDefinition');
        const pathParts = identifierPath.split('/');
        const pathPartsLength = pathParts.length;
        for (let i = 0; i < pathPartsLength; ++i) {
            const key = pathParts[i];
            if (i === 0 || i === pathPartsLength) {
                assert(key === formElement.get('identifier'), '"' + key + '" does not exist in path "' + identifierPath + '"', 1472424333);
                continue;
            }
            formElements = formElement.get('renderables');
            if (Array.isArray(formElements)) {
                obj = null;
                for (let j = 0, len = formElements.length; j < len; ++j) {
                    if (key === formElements[j].get('identifier')) {
                        obj = formElements[j];
                        break;
                    }
                }
                assert(obj !== null, 'Could not find form element "' + key + '" in path "' + identifierPath + '"', 1472424334);
                formElement = obj;
            }
            else {
                assert(false, 'No form elements found', 1472424330);
            }
        }
        return formElement;
    }
    findFormElement(formElement) {
        if (typeof formElement === 'object') {
            formElement = formElement.get('__identifierPath');
        }
        return this.findFormElementByIdentifierPath(formElement);
    }
    /**
     * @throws 1475375281
     * @throws 1475375282
     */
    findCollectionElementByIdentifierPath(collectionElementIdentifier, collection) {
        assert(utility.isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475375281);
        assert(Array.isArray(collection), 'Invalid parameter "collection"', 1475375282);
        for (let i = 0, len = collection.length; i < len; ++i) {
            if (collection[i].identifier === collectionElementIdentifier) {
                return collection[i];
            }
        }
        return undefined;
    }
    /**
     * @throws 1475375283
     * @throws 1475375284
     * @throws 1475375285
     */
    getIndexFromPropertyCollectionElementByIdentifier(collectionElementIdentifier, collectionName, formElement) {
        assert(utility.isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475375283);
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475375284);
        assert(utility.isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475375285);
        const collection = formElement.get(collectionName);
        if (Array.isArray(collection)) {
            for (let i = 0, len = collection.length; i < len; ++i) {
                if (collection[i].identifier === collectionElementIdentifier) {
                    return i;
                }
            }
        }
        return -1;
    }
    /**
     * @throws 1475375686
     * @throws 1475375687
     * @throws 1475375688
     * @throws 1477413154
     */
    addPropertyCollectionElement(collectionElementToAdd, collectionName, formElement, referenceCollectionElementIdentifier, disablePublishersOnSet) {
        let collection, newCollectionElementIndex;
        assert(typeof collectionElementToAdd === 'object' && collectionElementToAdd !== null, 'Invalid parameter "collectionElementToAdd"', 1475375686);
        assert(typeof formElement === 'object' && formElement !== null, 'Invalid parameter "formElement"', 1475375687);
        assert(utility.isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475375688);
        if (utility.isUndefinedOrNull(disablePublishersOnSet)) {
            disablePublishersOnSet = true;
        }
        disablePublishersOnSet = !!disablePublishersOnSet;
        collection = formElement.get(collectionName);
        if (!Array.isArray(collection)) {
            extendModel(formElement, [], collectionName, true);
            collection = formElement.get(collectionName);
        }
        if (utility.isUndefinedOrNull(referenceCollectionElementIdentifier)) {
            newCollectionElementIndex = 0;
        }
        else {
            newCollectionElementIndex = this.getIndexFromPropertyCollectionElementByIdentifier(referenceCollectionElementIdentifier, collectionName, formElement) + 1;
            assert(-1 < newCollectionElementIndex, 'Could not find collection element ' + referenceCollectionElementIdentifier + ' within collection ' + collectionName, 1477413154);
        }
        collection.splice(newCollectionElementIndex, 0, collectionElementToAdd);
        formElement.set(collectionName, collection, true);
        propertyValidationService.removeValidatorIdentifiersFromFormElementProperty(formElement, collectionName);
        for (let i = 0, len = collection.length; i < len; ++i) {
            extendModel(formElement, collection[i], collectionName + '.' + i, true);
        }
        formElement.set(collectionName, collection, true);
        propertyValidationService.addValidatorIdentifiersFromFormElementPropertyCollections(formElement);
        formElement.set(collectionName, collection, disablePublishersOnSet);
        return formElement;
    }
    /**
     * @throws 1475375689
     * @throws 1475375690
     * @throws 1475375691
     * @throws 1475375692
     */
    removePropertyCollectionElementByIdentifier(formElement, collectionElementIdentifier, collectionName, disablePublishersOnSet) {
        assert(utility.isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475375689);
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1475375690);
        assert(utility.isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475375691);
        const collection = formElement.get(collectionName);
        assert(Array.isArray(collection), 'The collection "' + collectionName + '" does not exist', 1475375692);
        if (utility.isUndefinedOrNull(disablePublishersOnSet)) {
            disablePublishersOnSet = true;
        }
        disablePublishersOnSet = !!disablePublishersOnSet;
        propertyValidationService.removeValidatorIdentifiersFromFormElementProperty(formElement, collectionName);
        const collectionElementIndex = this.getIndexFromPropertyCollectionElementByIdentifier(collectionElementIdentifier, collectionName, formElement);
        collection.splice(collectionElementIndex, 1);
        formElement.set(collectionName, collection, disablePublishersOnSet);
        propertyValidationService.addValidatorIdentifiersFromFormElementPropertyCollections(formElement);
    }
    /**
     * @throws 1477404484
     * @throws 1477404485
     * @throws 1477404486
     * @throws 1477404488
     * @throws 1477404489
     * @throws 1477404490
     */
    movePropertyCollectionElement(collectionElementToMoveIdentifier, position, referenceCollectionElementIdentifier, collectionName, formElement, disablePublishersOnSet) {
        let referenceCollectionElement;
        assert('after' === position || 'before' === position, 'Invalid position "' + position + '"', 1477404485);
        assert(typeof referenceCollectionElementIdentifier === 'string', 'Invalid parameter "referenceCollectionElementIdentifier"', 1477404486);
        assert(typeof formElement === 'object' && formElement !== null && !Array.isArray(formElement), 'Invalid parameter "formElement"', 1477404488);
        const collection = formElement.get(collectionName);
        assert(Array.isArray(collection), 'The collection "' + collectionName + '" does not exist', 1477404490);
        const collectionElementToMove = this.findCollectionElementByIdentifierPath(collectionElementToMoveIdentifier, collection);
        assert(typeof collectionElementToMove === 'object' && collectionElementToMove !== null && !Array.isArray(collectionElementToMove), 'Invalid parameter "collectionElementToMove"', 1477404484);
        this.removePropertyCollectionElementByIdentifier(formElement, collectionElementToMoveIdentifier, collectionName);
        const referenceCollectionElementIndex = this.getIndexFromPropertyCollectionElementByIdentifier(referenceCollectionElementIdentifier, collectionName, formElement);
        assert(-1 < referenceCollectionElementIndex, 'Could not find collection element ' + referenceCollectionElementIdentifier + ' within collection ' + collectionName, 1477404489);
        if ('before' === position) {
            referenceCollectionElement = collection[referenceCollectionElementIndex - 1];
            if (utility.isUndefinedOrNull(referenceCollectionElement)) {
                referenceCollectionElementIdentifier = undefined;
            }
            else {
                referenceCollectionElementIdentifier = referenceCollectionElement.identifier;
            }
        }
        this.addPropertyCollectionElement(collectionElementToMove, collectionName, formElement, referenceCollectionElementIdentifier, disablePublishersOnSet);
    }
}
export class Factory {
    /**
     * @throws 1475375693
     * @throws 1475436040
     * @throws 1475604050
     */
    createFormElement(configuration, identifierPathPrefix, parentFormElement, registerPropertyValidators, disablePublishersOnSet) {
        let currentChildFormElements;
        assert(typeof configuration === 'object' && configuration !== null && !Array.isArray(configuration), 'Invalid parameter "configuration"', 1475375693);
        assert(utility.isNonEmptyString(configuration.identifier), '"identifier" must not be empty', 1475436040);
        assert(utility.isNonEmptyString(configuration.type), '"type" must not be empty', 1475604050);
        registerPropertyValidators = !!registerPropertyValidators;
        if (utility.isUndefinedOrNull(disablePublishersOnSet)) {
            disablePublishersOnSet = true;
        }
        disablePublishersOnSet = !!disablePublishersOnSet;
        const formElementTypeDefinition = repository.getFormEditorDefinition('formElements', configuration.type);
        const rawChildFormElements = configuration.renderables;
        delete configuration.renderables;
        const collections = {};
        const predefinedDefaults = formElementTypeDefinition.predefinedDefaults || {};
        for (const collectionName of Object.keys(configuration)) {
            if (utility.isUndefinedOrNull(repository.formEditorDefinitions[collectionName])) {
                continue;
            }
            predefinedDefaults[collectionName] = predefinedDefaults[collectionName] || {};
            collections[collectionName] = Object.assign(predefinedDefaults[collectionName] || {}, configuration[collectionName]);
            delete predefinedDefaults[collectionName];
            delete configuration[collectionName];
        }
        identifierPathPrefix = identifierPathPrefix || '';
        const identifierPath = (identifierPathPrefix === '') ? configuration.identifier : identifierPathPrefix + '/' + configuration.identifier;
        const concreteConfiguration = {
            ...predefinedDefaults,
            ...configuration,
            ...{
                renderables: (rawChildFormElements) ? true : null,
                __parentRenderable: null,
                __identifierPath: identifierPath
            }
        };
        const formElement = createModel(concreteConfiguration);
        formElement.set('__parentRenderable', parentFormElement || null, disablePublishersOnSet);
        for (const [collectionName, collectionElementConfigurations] of Object.entries(collections)) {
            let i = 0;
            for (const collectionElementConfiguration of Object.values(collectionElementConfigurations)) {
                let previousCreatePropertyCollectionElementIdentifier;
                const propertyCollectionElement = this.createPropertyCollectionElement(collectionElementConfiguration.identifier, collectionElementConfiguration, collectionName);
                if (i > 0) {
                    previousCreatePropertyCollectionElementIdentifier = collections[collectionName][i - 1].identifier;
                }
                repository.addPropertyCollectionElement(propertyCollectionElement, collectionName, formElement, previousCreatePropertyCollectionElementIdentifier, true);
                ++i;
            }
        }
        // Register property change publishers for properties that have not
        // been configured yet, but may be added by inspector components.
        if (Array.isArray(formElementTypeDefinition.editors)) {
            for (const editorConfig of formElementTypeDefinition.editors) {
                if (editorConfig.propertyPath) {
                    formElement.on(editorConfig.propertyPath, 'core/formElement/somePropertyChanged');
                }
            }
        }
        if (registerPropertyValidators) {
            if (Array.isArray(formElementTypeDefinition.editors)) {
                for (let i = 0, len1 = formElementTypeDefinition.editors.length; i < len1; ++i) {
                    if (!Array.isArray(formElementTypeDefinition.editors[i].propertyValidators)) {
                        continue;
                    }
                    const propertyValidatorConfiguration = {
                        propertyValidatorsMode: 'AND'
                    };
                    if (!utility.isUndefinedOrNull(formElementTypeDefinition.editors[i].propertyValidatorsMode)
                        && formElementTypeDefinition.editors[i].propertyValidatorsMode === 'OR') {
                        propertyValidatorConfiguration.propertyValidatorsMode = 'OR';
                    }
                    propertyValidationService.addValidatorIdentifiersToFormElementProperty(formElement, formElementTypeDefinition.editors[i].propertyValidators, formElementTypeDefinition.editors[i].propertyPath, undefined, undefined, propertyValidatorConfiguration);
                }
            }
        }
        if (Array.isArray(rawChildFormElements)) {
            currentChildFormElements = [];
            for (let i = 0, len = rawChildFormElements.length; i < len; ++i) {
                currentChildFormElements.push(this.createFormElement(rawChildFormElements[i], identifierPath, formElement, registerPropertyValidators, disablePublishersOnSet));
            }
            formElement.set('renderables', currentChildFormElements, disablePublishersOnSet);
        }
        return formElement;
    }
    /**
     * @throws 1475377160
     * @throws 1475377161
     * @throws 1475377162
     */
    createPropertyCollectionElement(collectionElementIdentifier, collectionElementConfiguration, collectionName) {
        let collectionElementPresets;
        assert(utility.isNonEmptyString(collectionElementIdentifier), 'Invalid parameter "collectionElementIdentifier"', 1475377160);
        assert(typeof collectionElementConfiguration === 'object' && collectionElementConfiguration !== null && !Array.isArray(collectionElementConfiguration), 'Invalid parameter "collectionElementConfiguration"', 1475377161);
        assert(utility.isNonEmptyString(collectionName), 'Invalid parameter "collectionName"', 1475377162);
        collectionElementConfiguration.identifier = collectionElementIdentifier;
        const collectionDefinition = repository.getFormEditorDefinition(collectionName, collectionElementIdentifier);
        if ('predefinedDefaults' in collectionDefinition && collectionDefinition.predefinedDefaults) {
            collectionElementPresets = collectionDefinition.predefinedDefaults;
        }
        else {
            collectionElementPresets = {};
        }
        return Object.assign(collectionElementPresets, collectionElementConfiguration);
    }
}
export class DataBackend {
    constructor() {
        this.endpoints = {};
        this.prototypeName = null;
        this.persistenceIdentifier = null;
    }
    /**
     * @throws 1475377488
     */
    setEndpoints(endpoints) {
        assert(typeof endpoints === 'object' && endpoints !== null && !Array.isArray(endpoints), 'Invalid parameter "endpoints"', 1475377488);
        this.endpoints = endpoints;
    }
    /**
     * @throws 1475377489
     */
    setPrototypeName(prototypeName) {
        assert(utility.isNonEmptyString(prototypeName), 'Invalid parameter "prototypeName"', 1475928095);
        this.prototypeName = prototypeName;
    }
    /**
     * @throws 1475377489
     */
    setPersistenceIdentifier(persistenceIdentifier) {
        assert(utility.isNonEmptyString(persistenceIdentifier), 'Invalid parameter "persistenceIdentifier"', 1475377489);
        this.persistenceIdentifier = persistenceIdentifier;
    }
    /**
     * @publish core/ajax/saveFormDefinition/success
     * @publish core/ajax/error
     * @throws 1475520918
     */
    saveFormDefinition() {
        assert(utility.isNonEmptyString(this.endpoints.saveForm), 'The endpoint "saveForm" is not configured', 1475520918);
        if (runningAjaxRequests.saveForm) {
            runningAjaxRequests.saveForm.abort();
        }
        const request = new AjaxRequest(this.endpoints.saveForm);
        runningAjaxRequests.saveForm = request;
        request.post({
            formPersistenceIdentifier: this.persistenceIdentifier,
            formDefinition: JSON.stringify(utility.convertToSimpleObject(getApplicationStateStack().getCurrentState('formDefinition')))
        }).then(async (response) => {
            if (runningAjaxRequests.saveForm !== request) {
                return;
            }
            runningAjaxRequests.saveForm = null;
            const data = await response.resolve();
            if (data.status === 'success') {
                publisherSubscriber.publish('core/ajax/saveFormDefinition/success', [data]);
            }
            else {
                publisherSubscriber.publish('core/ajax/saveFormDefinition/error', [data]);
            }
        }).catch(async (error) => {
            if (error instanceof AjaxResponse) {
                const responseBody = await error.resolve();
                publisherSubscriber.publish('core/ajax/error', [error.response.statusText, responseBody]);
            }
        });
    }
    /**
     * @publish core/ajax/renderFormDefinitionPage/success
     * @publish core/ajax/error
     * @throws 1473447677
     * @throws 1475377781
     * @throws 1475377782
     */
    renderFormDefinitionPage(pageIndex) {
        assert(!isNaN(Number(pageIndex)), 'Invalid parameter "pageIndex"', 1475377781);
        assert(utility.isNonEmptyString(this.endpoints.formPageRenderer), 'The endpoint "formPageRenderer" is not configured', 1473447677);
        if (runningAjaxRequests.renderFormDefinitionPage) {
            runningAjaxRequests.renderFormDefinitionPage.abort();
        }
        const request = new AjaxRequest(this.endpoints.formPageRenderer);
        runningAjaxRequests.renderFormDefinitionPage = request;
        request.post({
            formDefinition: JSON.stringify(utility.convertToSimpleObject(getApplicationStateStack().getCurrentState('formDefinition'))),
            pageIndex: pageIndex,
            prototypeName: this.prototypeName,
            formPersistenceIdentifier: this.persistenceIdentifier
        }).then(async (response) => {
            if (runningAjaxRequests.renderFormDefinitionPage !== request) {
                return;
            }
            runningAjaxRequests.renderFormDefinitionPage = null;
            const data = await response.resolve();
            publisherSubscriber.publish('core/ajax/renderFormDefinitionPage/success', [data, pageIndex]);
        }).catch(async (error) => {
            if (error instanceof AjaxResponse) {
                const responseBody = await error.resolve();
                publisherSubscriber.publish('core/ajax/error', [error.response.statusText, responseBody]);
            }
        });
    }
}
export class ApplicationStateStack {
    constructor() {
        this.stackSize = 10;
        this.stackPointer = 0;
        this.stack = [];
    }
    /**
     * @publish core/applicationState/add
     * @throws 1477847415
     */
    add(applicationState, disablePublishersOnSet) {
        assert(typeof applicationState === 'object' && applicationState !== null && !Array.isArray(applicationState), 'Invalid parameter "applicationState"', 1477847415);
        disablePublishersOnSet = !!disablePublishersOnSet;
        Object.assign(applicationState, {
            propertyValidationServiceRegisteredValidators: cloneDeep(this.getCurrentState('propertyValidationServiceRegisteredValidators') ?? {})
        });
        this.stack.splice(0, 0, applicationState);
        if (this.stack.length > this.stackSize) {
            this.stack.splice(this.stackSize - 1, (this.stack.length - this.stackSize));
        }
        if (!disablePublishersOnSet) {
            publisherSubscriber.publish('core/applicationState/add', [
                applicationState,
                this.getCurrentStackPointer(),
                this.getCurrentStackSize()
            ]);
        }
    }
    /**
     * @publish core/applicationState/add
     * @throws 1477872641
     */
    addAndReset(applicationState, disablePublishersOnSet) {
        assert(typeof applicationState === 'object' && applicationState !== null && !Array.isArray(applicationState), 'Invalid parameter "applicationState"', 1477872641);
        if (this.stackPointer > 0) {
            this.stack.splice(0, this.stackPointer);
        }
        this.stackPointer = 0;
        this.add(applicationState, true);
        if (!disablePublishersOnSet) {
            publisherSubscriber.publish('core/applicationState/add', [
                this.getCurrentState(),
                this.getCurrentStackPointer(),
                this.getCurrentStackSize()
            ]);
        }
    }
    /**
     * @throws 1477932754
     */
    getCurrentState(type) {
        if (type === undefined) {
            return this.stack[this.stackPointer] || undefined;
        }
        assert('formDefinition' === type
            || 'currentlySelectedPageIndex' === type
            || 'currentlySelectedFormElementIdentifierPath' === type
            || 'propertyValidationServiceRegisteredValidators' === type, 'Invalid parameter "type"', 1477932754);
        if (typeof this.stack[this.stackPointer] === 'undefined') {
            return undefined;
        }
        return (this.stack[this.stackPointer][type]);
    }
    /**
     * @throws 1477934111
     */
    setCurrentState(type, value) {
        assert('formDefinition' === type
            || 'currentlySelectedPageIndex' === type
            || 'currentlySelectedFormElementIdentifierPath' === type
            || 'propertyValidationServiceRegisteredValidators' === type, 'Invalid parameter "type"', 1477934111);
        this.stack[this.stackPointer][type] = value;
    }
    /**
     * @throws 1477846933
     */
    setMaximalStackSize(stackSize) {
        assert(typeof stackSize === 'number', 'Invalid parameter "size"', 1477846933);
        this.stackSize = stackSize;
    }
    getMaximalStackSize() {
        return this.stackSize;
    }
    getCurrentStackSize() {
        return this.stack.length;
    }
    getCurrentStackPointer() {
        return this.stackPointer;
    }
    /**
     * @throws 1477852138
     */
    setCurrentStackPointer(stackPointer) {
        assert(typeof stackPointer === 'number', 'Invalid parameter "size"', 1477852138);
        if (stackPointer < 0) {
            this.stackPointer = 0;
        }
        else if (stackPointer > this.stack.length - 1) {
            this.stackPointer = this.stack.length - 1;
        }
        else {
            this.stackPointer = stackPointer;
        }
    }
    decrementCurrentStackPointer() {
        this.setCurrentStackPointer(--this.stackPointer);
    }
    incrementCurrentStackPointer() {
        this.setCurrentStackPointer(++this.stackPointer);
    }
}
/**
 * @throws 1475358064
 */
export function getRunningAjaxRequest(ajaxRequestIdentifier) {
    assert(utility.isNonEmptyString(ajaxRequestIdentifier), 'Invalid parameter "ajaxRequestIdentifier"', 1475358064);
    return runningAjaxRequests[ajaxRequestIdentifier] || null;
}
const utility = new Utility();
const dataBackend = new DataBackend();
const runningAjaxRequests = {};
const propertyValidationService = new PropertyValidationService();
const applicationStateStack = new ApplicationStateStack();
const publisherSubscriber = new PublisherSubscriber();
const repository = new Repository();
const factory = new Factory();
export function getUtility() {
    return utility;
}
export function getDataBackend() {
    return dataBackend;
}
export function getPropertyValidationService() {
    return propertyValidationService;
}
export function getApplicationStateStack() {
    return applicationStateStack;
}
export function getPublisherSubscriber() {
    return publisherSubscriber;
}
export function getFactory() {
    return factory;
}
export function getRepository() {
    return repository;
}
=======
import D from"@typo3/core/ajax/ajax-request.js";import{AjaxResponse as V}from"@typo3/core/ajax/ajax-response.js";import{cloneDeep as R}from"lodash-es";function a(y,e,t){if(typeof y=="function"&&(y=y()!==!1),!y)throw e=e||"Assertion failed",t&&(e=e+" ("+t+")"),typeof Error<"u"?new Error(e):e}class P{assert(e,t,r){a(e,t,r)}isUndefinedOrNull(e){return e==null}isNonEmptyArray(e){return Array.isArray(e)&&e.length>0}isNonEmptyString(e){return typeof e=="string"&&e.length>0}canBeInterpretedAsInteger(e){if(typeof e=="number")return!0;if(typeof e!="string")return!1;const t=e;return(t*1).toString()===t.toString()&&t.toString().indexOf(".")===-1}buildPropertyPath(e,t,r,i,n){let o="";return n=!!n,this.isNonEmptyString(t)||this.isNonEmptyString(r)?(a(this.isNonEmptyString(t),'Invalid parameter "collectionElementIdentifier"',1475412569),a(this.isNonEmptyString(r),'Invalid parameter "collectionName"',1475412570),o=r+"."+b.getIndexFromPropertyCollectionElementByIdentifier(t,r,i)):o="",this.isUndefinedOrNull(e)||(a(this.isNonEmptyString(e),'Invalid parameter "propertyPath"',1475415988),this.isNonEmptyString(o)?o=o+"."+e:o=e),n||a(this.isNonEmptyString(o),"The property path could not be resolved",1475663210),o}convertToSimpleObject(e){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475377782);const t={},r="getObjectData"in e&&typeof e.getObjectData=="function"?e.getObjectData():e,i=r.renderables;delete r.renderables;for(const[n,o]of Object.entries(r))n.match(/^__/)||(o!==null&&typeof o=="object"&&!Array.isArray(o)?t[n]=this.convertToSimpleObject(o):typeof o!="function"&&typeof o<"u"&&(t[n]=o));if(Array.isArray(i)){t.renderables=[];for(let n=0,o=i.length;n<o;++n)t.renderables.push(this.convertToSimpleObject(i[n]))}return t}}class O{constructor(){this.validators={}}addValidatorIdentifiersToFormElementProperty(e,t,r,i,n,o){a(Array.isArray(t),'Invalid parameter "validators"',1475661026),a(Array.isArray(t),'Invalid parameter "validators"',1479238074),a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475661025);const s=e.get("__identifierPath");r=l.buildPropertyPath(r,i,n,e);const d=u().getCurrentState("propertyValidationServiceRegisteredValidators");l.isUndefinedOrNull(d[s])&&(d[s]={}),l.isUndefinedOrNull(d[s][r])&&(d[s][r]={validators:[],configuration:o});for(const p of t)d[s][r].validators.indexOf(p)===-1&&d[s][r].validators.push(p);u().setCurrentState("propertyValidationServiceRegisteredValidators",d)}removeValidatorIdentifiersFromFormElementProperty(e,t){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475700618),a(l.isNonEmptyString(t),'Invalid parameter "propertyPath"',1475706896);const r=e.get("__identifierPath"),i={},n=u().getCurrentState("propertyValidationServiceRegisteredValidators");if(r in n)for(const o of Object.keys(n[r]||{}))o.indexOf(t)>-1||(i[o]=n[r][o]);n[r]=i,u().setCurrentState("propertyValidationServiceRegisteredValidators",n)}removeAllValidatorIdentifiersFromFormElement(e){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475668189);const t={},r=u().getCurrentState("propertyValidationServiceRegisteredValidators");for(const i of Object.keys(r||{}))i===e.get("__identifierPath")||i.indexOf(e.get("__identifierPath")+"/")>-1||(t[i]=r[i]);u().setCurrentState("propertyValidationServiceRegisteredValidators",t)}addValidator(e,t){a(l.isNonEmptyString(e),'Invalid parameter "validatorIdentifier"',1475669143),a(typeof t=="function",'Invalid parameter "func"',1475669144),a(typeof this.validators[e]!="function",'The validator "'+e+'" is already registered',1475669145),this.validators[e]=t}validateFormElementProperty(e,t){let r;a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475676517),a(l.isNonEmptyString(t),'Invalid parameter "propertyPath"',1475676518);const i=e.get("__identifierPath"),n=[],o=u().getCurrentState("propertyValidationServiceRegisteredValidators");if(r={propertyValidatorsMode:"AND"},!l.isUndefinedOrNull(o[i])&&typeof o[i][t]=="object"&&o[i][t]!==null&&!Array.isArray(o[i][t])&&Array.isArray(o[i][t].validators)){r=o[i][t].configuration;for(let s=0,d=o[i][t].validators.length;s<d;++s){const p=o[i][t].validators[s];if(typeof this.validators[p]!="function")continue;const c=this.validators[p](e,t);l.isNonEmptyString(c)&&n.push(c)}}return n.length>0&&r.propertyValidatorsMode==="OR"&&n.length!==o[i][t].validators.length?[]:n}validateFormElement(e){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475749668);const t=e.get("__identifierPath"),r=[],i=u().getCurrentState("propertyValidationServiceRegisteredValidators");if(!l.isUndefinedOrNull(i[t]))for(const n of Object.keys(i[t]))r.push({propertyPath:n,validationResults:this.validateFormElementProperty(e,n)});return r}validationResultsHasErrors(e){a(Array.isArray(e),'Invalid parameter "validationResults"',1478613477);for(let t=0,r=e.length;t<r;++t)for(let i=0,n=e[t].validationResults.length;i<n;++i)if(e[t].validationResults[i].validationResults&&e[t].validationResults[i].validationResults.length>0)return!0;return!1}validateFormElementRecursive(e,t,r){if(a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475756764),t=!!t,r=r||[],r.push({formElementIdentifierPath:e.get("__identifierPath"),validationResults:this.validateFormElement(e)}),t&&this.validationResultsHasErrors(r))return r;const i=e.get("renderables");if(Array.isArray(i)){for(let n=0,o=i.length;n<o;++n)if(this.validateFormElementRecursive(i[n],t,r),t&&this.validationResultsHasErrors(r))return r}return r}addValidatorIdentifiersFromFormElementPropertyCollections(e){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475707334);const t=b.getFormEditorDefinition("formElements",e.get("type"));if(!l.isUndefinedOrNull(t.propertyCollections)){for(const r of Object.keys(t.propertyCollections))if(Array.isArray(t.propertyCollections[r])){for(let i=0,n=t.propertyCollections[r].length;i<n;++i)if(!(!Array.isArray(t.propertyCollections[r][i].editors)||b.getIndexFromPropertyCollectionElementByIdentifier(t.propertyCollections[r][i].identifier,r,e)===-1))for(let o=0,s=t.propertyCollections[r][i].editors.length;o<s;++o){if(!Array.isArray(t.propertyCollections[r][i].editors[o].propertyValidators))continue;const d={propertyValidatorsMode:"AND"};!l.isUndefinedOrNull(t.propertyCollections[r][i].editors[o].propertyValidatorsMode)&&t.propertyCollections[r][i].editors[o].propertyValidatorsMode==="OR"&&(d.propertyValidatorsMode="OR"),this.addValidatorIdentifiersToFormElementProperty(e,t.propertyCollections[r][i].editors[o].propertyValidators,t.propertyCollections[r][i].editors[o].propertyPath,t.propertyCollections[r][i].identifier,r,d)}}}}}class k{constructor(){this.topics={},this.subscriberUid=-1}publish(e,t){if(a(l.isNonEmptyString(e),'Invalid parameter "topic"',1475358066),l.isUndefinedOrNull(this.topics[e]))return;const r=this.topics[e];for(const i of r)i.func(e,t)}subscribe(e,t){a(l.isNonEmptyString(e),'Invalid parameter "topic"',1475358067),a(typeof t=="function",'Invalid parameter "func"',1475411986),l.isUndefinedOrNull(this.topics[e])&&(this.topics[e]=[]);const r=(++this.subscriberUid).toString();return this.topics[e].push({token:r,func:t}),r}unsubscribe(e){a(l.isNonEmptyString(e),'Invalid parameter "token"',1475358068);for(const t of Object.values(this.topics)){const r=t;for(let i=0,n=r.length;i<n;++i)if(r[i].token===e)return r.splice(i,1),e}return null}}function C(y,e,t,r){if(a(typeof y=="object"&&y!==null&&!Array.isArray(y),'Invalid parameter "modelToExtend"',1475358069),a(typeof e=="object"&&e!==null,'Invalid parameter "modelExtension"',1475358070),r=!!r,t=t||"",typeof e=="object"&&Object.keys(e).length===0)a(t!=="","Empty path is not allowed",1474640022),y.on(t,"core/formElement/somePropertyChanged"),y.set(t,e,r);else{const i={...e};if(t!==""&&Object.values(i).every(o=>o===null||typeof o!="object"))y.on(t,"core/formElement/somePropertyChanged"),y.set(t,e,r);else for(const o of Object.keys(i)){const s=t===""?o:t+"."+o;y.on(s,"core/formElement/somePropertyChanged"),i[o]!==null&&(typeof i[o]=="object"||Array.isArray(i[o]))?C(y,i[o],s,r):y.set(s,i[o],r)}}}class N{constructor(){this.objectData={},this.publisherTopics={}}get(e){let t,r;for(a(l.isNonEmptyString(e),'Invalid parameter "key"',1475361755),r=this.objectData;e.indexOf(".")>0;){if(t=e.slice(0,e.indexOf(".")),e=e.slice(t.length+1),!(t in r))return;r=r[t]}return r[e]}set(e,t,r){let i,n,o,s,d;a(l.isNonEmptyString(e),'Invalid parameter "key"',1475361756),r=!!r;const p=this.get(e);for(d=this.objectData,i=e;i.indexOf(".")>0;)n=i.slice(0,i.indexOf(".")),i=i.slice(n.length+1),isNaN(Number(n))||(n=parseInt(n,10)),s=i.indexOf("."),o=s===-1?i:i.slice(0,s),typeof d[n]>"u"?isNaN(Number(o))?d[n]={}:d[n]=[]:isNaN(Number(o))&&Array.isArray(d[n])&&(d[n]={...d[n]}),d=d[n];if(d[i]=t,!l.isUndefinedOrNull(this.publisherTopics[e])&&!r)for(let c=0,g=this.publisherTopics[e].length;c<g;++c)_.publish(this.publisherTopics[e][c],[e,t,p,this.objectData.__identifierPath])}unset(e,t){let r,i,n;a(l.isNonEmptyString(e),'Invalid parameter "key"',1489321637),t=!!t;const o=this.get(e);if(e.indexOf(".")>0?(i=e.split("."),n=i.pop(),i=i.join("."),r=this.get(i),typeof r<"u"&&delete r[n]):a(!1,"remove toplevel properties is not supported",1489319753),!l.isUndefinedOrNull(this.publisherTopics[e])&&!t)for(let s=0,d=this.publisherTopics[e].length;s<d;++s)_.publish(this.publisherTopics[e][s],[e,void 0,o,this.objectData.__identifierPath])}on(e,t){a(l.isNonEmptyString(e),'Invalid parameter "key"',1475361757),a(l.isNonEmptyString(t),'Invalid parameter "topicName"',1475361758),Array.isArray(this.publisherTopics[e])||(this.publisherTopics[e]=[]),this.publisherTopics[e].indexOf(t)===-1&&this.publisherTopics[e].push(t)}off(e,t){a(l.isNonEmptyString(e),'Invalid parameter "key"',1475361759),a(l.isNonEmptyString(t),'Invalid parameter "topicName"',1475361760),Array.isArray(this.publisherTopics[e])&&(this.publisherTopics[e]=this.publisherTopics[e].filter(r=>t!==r))}getObjectData(){return R(this.objectData)}toString(){const e=this.getObjectData(),{renderables:t,__parentRenderable:r,...i}=e,n=t||null;let o=null;l.isUndefinedOrNull(r)||(o=r.getObjectData().__identifierPath+" (filtered)");const s=i;if(o!==null&&(s.__parentRenderable=o),n!==null&&Array.isArray(n)){const d=[];for(let p=0,c=n.length;p<c;++p){const g=n[p];d.push(JSON.parse(g.toString()))}s.renderables=d}return JSON.stringify(s,null,2)}clone(){const e=this.getObjectData(),t=e.renderables||null;delete e.renderables,delete e.__parentRenderable,e.renderables=t?!0:null;const r=new N;if(C(r,e,"",!0),t!==null&&Array.isArray(t)){const i=[];for(let n=0,o=t.length;n<o;++n){let s=t[n];s=s.clone(),s.set("__parentRenderable",r,!0),i.push(s)}r.set("renderables",i,!0)}return r}}function L(y){y=y||{};const e=new N;return C(e,y,"",!0),e}class T{setFormEditorDefinitions(e){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formEditorDefinitions"',1475364394);for(const t of Object.keys(e)){const r=t;if(!(e[r]!==null&&typeof e[r]!="object"))for(const i of Object.keys(e[r]))(e[r][i]===null||typeof e[r][i]!="object")&&(e[r][i]={})}this.formEditorDefinitions=e}getFormEditorDefinition(e,t){return a(l.isNonEmptyString(e),'Invalid parameter "definitionName"',1475364952),a(l.isNonEmptyString(t),'Invalid parameter "subject"',1475364953),R(this.formEditorDefinitions[e][t])}getRootFormElement(){return u().getCurrentState("formDefinition")}addFormElement(e,t,r,i){let n,o,s;a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475436224),a(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "referenceFormElement"',1475364956),l.isUndefinedOrNull(i)&&(i=!0),i=!!i,r=!!r;const d=this.getFormEditorDefinition("formElements",e.get("type")),p=this.getFormEditorDefinition("formElements",t.get("type"));if(!d._isTopLevelFormElement&&p._isCompositeFormElement?(Array.isArray(t.get("renderables"))||t.set("renderables",[],i),e.set("__parentRenderable",t,i),e.set("__identifierPath",t.get("__identifierPath")+"/"+e.get("identifier"),i),t.get("renderables").push(e)):(t.get("__identifierPath")===u().getCurrentState("formDefinition").get("__identifierPath")?(s=t.get("renderables"),t=s[s.length-1]):d._isTopLevelFormElement&&!p._isTopLevelFormElement?t=this.findEnclosingCompositeFormElementWhichIsOnTopLevel(t):d._isCompositeFormElement&&(n=this.findEnclosingCompositeFormElementWhichIsNotOnTopLevel(t),n&&(t=n)),e.set("__parentRenderable",t.get("__parentRenderable"),i),e.set("__identifierPath",t.get("__parentRenderable").get("__identifierPath")+"/"+e.get("identifier"),i),o=t.get("__parentRenderable").get("renderables"),o.splice(o.indexOf(t)+1,0,e)),r&&Array.isArray(d.editors))for(let c=0,g=d.editors.length;c<g;++c){if(!Array.isArray(d.editors[c].propertyValidators))continue;const j={propertyValidatorsMode:"AND"};!l.isUndefinedOrNull(d.editors[c].propertyValidatorsMode)&&d.editors[c].propertyValidatorsMode==="OR"&&(j.propertyValidatorsMode="OR"),I.addValidatorIdentifiersToFormElementProperty(e,d.editors[c].propertyValidators,d.editors[c].propertyPath,void 0,void 0,j)}return e}removeFormElement(e,t,r){l.isUndefinedOrNull(r)&&(r=!0),r=!!r,t=!!t,a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475364957),a(typeof e.get("__parentRenderable")=="object"&&e.get("__parentRenderable")!==null&&!Array.isArray(e.get("__parentRenderable")),"Removing the root element is not allowed",1472553024);const i=e.get("__parentRenderable").get("renderables");i.splice(i.indexOf(e),1),e.get("__parentRenderable").set("renderables",i,r),t&&I.removeAllValidatorIdentifiersFromFormElement(e)}moveFormElement(e,t,r,i){let n,o,s;a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElementToMove"',1475364958),a(t==="after"||t==="before"||t==="inside",'Invalid position "'+t+'"',1475364959),a(typeof r=="object"&&r!==null&&!Array.isArray(r),'Invalid parameter "referenceFormElement"',1475364960),l.isUndefinedOrNull(i)&&(i=!0),i=!!i;const d=this.getFormEditorDefinition("formElements",e.get("type")),p=this.getFormEditorDefinition("formElements",r.get("type"));this.removeFormElement(e,!1);const c=(g,j)=>{a(typeof g=="object"&&g!==null&&!Array.isArray(g),'Invalid parameter "formElement"',1475364961),a(l.isNonEmptyString(j),'Invalid parameter "pathPrefix"',1475364962);const h=g.get("__identifierPath"),f=j+"/"+g.get("identifier"),v=u().getCurrentState("propertyValidationServiceRegisteredValidators");l.isUndefinedOrNull(v[h])||(v[f]=v[h],delete v[h]),u().setCurrentState("propertyValidationServiceRegisteredValidators",v),g.set("__identifierPath",f,i);const A=g.get("renderables");if(Array.isArray(A))for(let F=0,S=A.length;F<S;++F)c(A[F],g.get("__identifierPath"))};return t==="inside"?(a(!d._isTopLevelFormElement,"This move is not allowed",1476993731),a(p._isCompositeFormElement,"This move is not allowed",1476993732),e.set("__parentRenderable",r,i),c(e,r.get("__identifierPath")),o=r.get("renderables"),l.isUndefinedOrNull(o)&&(o=[]),o.splice(0,0,e),r.set("renderables",o,i)):d._isTopLevelFormElement&&p._isTopLevelFormElement?(n=r.get("__parentRenderable").get("renderables"),s=n.indexOf(r),t==="after"?n.splice(s+1,0,e):n.splice(s,0,e),r.get("__parentRenderable").set("renderables",n,i)):(e.get("__parentRenderable").get("identifier")===r.get("__parentRenderable").get("identifier")?(n=r.get("__parentRenderable").get("renderables"),s=n.indexOf(r)):(e.set("__parentRenderable",r.get("__parentRenderable"),i),c(e,r.get("__parentRenderable").get("__identifierPath")),n=r.get("__parentRenderable").get("renderables"),s=n.indexOf(r)),t==="after"?n.splice(s+1,0,e):n.splice(s,0,e),r.get("__parentRenderable").set("renderables",n,i)),e}getIndexForEnclosingCompositeFormElementWhichIsOnTopLevelForFormElement(e){let t;a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475364963);const r=this.getFormEditorDefinition("formElements",e.get("type"));return r._isTopLevelFormElement&&r._isCompositeFormElement?t=e:e.get("__identifierPath")===u().getCurrentState("formDefinition").get("__identifierPath")?t=u().getCurrentState("formDefinition").get("renderables")[0]:t=this.findEnclosingCompositeFormElementWhichIsOnTopLevel(e),t.get("__parentRenderable").get("renderables").indexOf(t)}findEnclosingCompositeFormElementWhichIsOnTopLevel(e){let t;for(a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475364964),a(typeof e.get("__parentRenderable")=="object"&&e.get("__parentRenderable")!==null&&!Array.isArray(e.get("__parentRenderable")),"The root element is never encloused by anything",1472556223),t=this.getFormEditorDefinition("formElements",e.get("type"));!t._isTopLevelFormElement;)e=e.get("__parentRenderable"),t=this.getFormEditorDefinition("formElements",e.get("type"));return e}findEnclosingGridRowFormElement(e){let t;for(a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1490520271),t=this.getFormEditorDefinition("formElements",e.get("type"));!t._isGridRowFormElement;){if(t._isTopLevelFormElement)return null;e=e.get("__parentRenderable"),t=this.getFormEditorDefinition("formElements",e.get("type"))}return t._isTopLevelFormElement?null:e}findEnclosingCompositeFormElementWhichIsNotOnTopLevel(e){let t;for(a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475364965),t=this.getFormEditorDefinition("formElements",e.get("type"));!t._isCompositeFormElement;){if(t._isTopLevelFormElement)return null;e=e.get("__parentRenderable"),t=this.getFormEditorDefinition("formElements",e.get("type"))}return t._isTopLevelFormElement?null:e}getNonCompositeNonToplevelFormElements(){const e=[],t=r=>{a(typeof r=="object"&&r!==null&&!Array.isArray(r),'Invalid parameter "formElement"',1475364961);const i=this.getFormEditorDefinition("formElements",r.get("type"));!i._isTopLevelFormElement&&!i._isCompositeFormElement&&e.push(r);const n=r.get("renderables");if(Array.isArray(n))for(let o=0,s=n.length;o<s;++o)t(n[o])};return t(this.getRootFormElement()),e}isFormElementIdentifierUsed(e){let t;a(l.isNonEmptyString(e),'Invalid parameter "identifier"',1475364966);const r=i=>{let n;if(i.get("identifier")===e&&(t=!0),!t&&(n=i.get("renderables"),Array.isArray(n)))for(let o=0,s=n.length;o<s&&(r(n[o]),!t);++o);};return r(u().getCurrentState("formDefinition")),t}getNextFreeFormElementIdentifier(e){let t;a(l.isNonEmptyString(e),'Invalid parameter "formElementType"',1475373676);const r=e.toLowerCase().replace(/[^a-z0-9]/g,"-")+"-";for(t=1;this.isFormElementIdentifierUsed(r+t);)t++;return r+t}findFormElementByIdentifierPath(e){let t,r;a(l.isNonEmptyString(e),'Invalid parameter "identifierPath"',1475373677);let i=u().getCurrentState("formDefinition");const n=e.split("/"),o=n.length;for(let s=0;s<o;++s){const d=n[s];if(s===0||s===o){a(d===i.get("identifier"),'"'+d+'" does not exist in path "'+e+'"',1472424333);continue}if(r=i.get("renderables"),Array.isArray(r)){t=null;for(let p=0,c=r.length;p<c;++p)if(d===r[p].get("identifier")){t=r[p];break}a(t!==null,'Could not find form element "'+d+'" in path "'+e+'"',1472424334),i=t}else a(!1,"No form elements found",1472424330)}return i}findFormElement(e){return typeof e=="object"&&(e=e.get("__identifierPath")),this.findFormElementByIdentifierPath(e)}findCollectionElementByIdentifierPath(e,t){a(l.isNonEmptyString(e),'Invalid parameter "collectionElementIdentifier"',1475375281),a(Array.isArray(t),'Invalid parameter "collection"',1475375282);for(let r=0,i=t.length;r<i;++r)if(t[r].identifier===e)return t[r]}getIndexFromPropertyCollectionElementByIdentifier(e,t,r){a(l.isNonEmptyString(e),'Invalid parameter "collectionElementIdentifier"',1475375283),a(typeof r=="object"&&r!==null&&!Array.isArray(r),'Invalid parameter "formElement"',1475375284),a(l.isNonEmptyString(t),'Invalid parameter "collectionName"',1475375285);const i=r.get(t);if(Array.isArray(i)){for(let n=0,o=i.length;n<o;++n)if(i[n].identifier===e)return n}return-1}addPropertyCollectionElement(e,t,r,i,n){let o,s;a(typeof e=="object"&&e!==null,'Invalid parameter "collectionElementToAdd"',1475375686),a(typeof r=="object"&&r!==null,'Invalid parameter "formElement"',1475375687),a(l.isNonEmptyString(t),'Invalid parameter "collectionName"',1475375688),l.isUndefinedOrNull(n)&&(n=!0),n=!!n,o=r.get(t),Array.isArray(o)||(C(r,[],t,!0),o=r.get(t)),l.isUndefinedOrNull(i)?s=0:(s=this.getIndexFromPropertyCollectionElementByIdentifier(i,t,r)+1,a(-1<s,"Could not find collection element "+i+" within collection "+t,1477413154)),o.splice(s,0,e),r.set(t,o,!0),I.removeValidatorIdentifiersFromFormElementProperty(r,t);for(let d=0,p=o.length;d<p;++d)C(r,o[d],t+"."+d,!0);return r.set(t,o,!0),I.addValidatorIdentifiersFromFormElementPropertyCollections(r),r.set(t,o,n),r}removePropertyCollectionElementByIdentifier(e,t,r,i){a(l.isNonEmptyString(t),'Invalid parameter "collectionElementIdentifier"',1475375689),a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1475375690),a(l.isNonEmptyString(r),'Invalid parameter "collectionName"',1475375691);const n=e.get(r);a(Array.isArray(n),'The collection "'+r+'" does not exist',1475375692),l.isUndefinedOrNull(i)&&(i=!0),i=!!i,I.removeValidatorIdentifiersFromFormElementProperty(e,r);const o=this.getIndexFromPropertyCollectionElementByIdentifier(t,r,e);n.splice(o,1),e.set(r,n,i),I.addValidatorIdentifiersFromFormElementPropertyCollections(e)}movePropertyCollectionElement(e,t,r,i,n,o){let s;a(t==="after"||t==="before",'Invalid position "'+t+'"',1477404485),a(typeof r=="string",'Invalid parameter "referenceCollectionElementIdentifier"',1477404486),a(typeof n=="object"&&n!==null&&!Array.isArray(n),'Invalid parameter "formElement"',1477404488);const d=n.get(i);a(Array.isArray(d),'The collection "'+i+'" does not exist',1477404490);const p=this.findCollectionElementByIdentifierPath(e,d);a(typeof p=="object"&&p!==null&&!Array.isArray(p),'Invalid parameter "collectionElementToMove"',1477404484),this.removePropertyCollectionElementByIdentifier(n,e,i);const c=this.getIndexFromPropertyCollectionElementByIdentifier(r,i,n);a(-1<c,"Could not find collection element "+r+" within collection "+i,1477404489),t==="before"&&(s=d[c-1],l.isUndefinedOrNull(s)?r=void 0:r=s.identifier),this.addPropertyCollectionElement(p,i,n,r,o)}}class w{createFormElement(e,t,r,i,n){let o;a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "configuration"',1475375693),a(l.isNonEmptyString(e.identifier),'"identifier" must not be empty',1475436040),a(l.isNonEmptyString(e.type),'"type" must not be empty',1475604050),i=!!i,l.isUndefinedOrNull(n)&&(n=!0),n=!!n;const s=b.getFormEditorDefinition("formElements",e.type),d=e.renderables;delete e.renderables;const p={},c=s.predefinedDefaults||{};for(const f of Object.keys(e))l.isUndefinedOrNull(b.formEditorDefinitions[f])||(c[f]=c[f]||{},p[f]=Object.assign(c[f]||{},e[f]),delete c[f],delete e[f]);t=t||"";const g=t===""?e.identifier:t+"/"+e.identifier,j={...c,...e,renderables:d?!0:null,__parentRenderable:null,__identifierPath:g},h=L(j);h.set("__parentRenderable",r||null,n);for(const[f,v]of Object.entries(p)){let A=0;for(const F of Object.values(v)){let S;const x=this.createPropertyCollectionElement(F.identifier,F,f);A>0&&(S=p[f][A-1].identifier),b.addPropertyCollectionElement(x,f,h,S,!0),++A}}if(Array.isArray(s.editors))for(const f of s.editors)f.propertyPath&&h.on(f.propertyPath,"core/formElement/somePropertyChanged");if(i&&Array.isArray(s.editors))for(let f=0,v=s.editors.length;f<v;++f){if(!Array.isArray(s.editors[f].propertyValidators))continue;const A={propertyValidatorsMode:"AND"};!l.isUndefinedOrNull(s.editors[f].propertyValidatorsMode)&&s.editors[f].propertyValidatorsMode==="OR"&&(A.propertyValidatorsMode="OR"),I.addValidatorIdentifiersToFormElementProperty(h,s.editors[f].propertyValidators,s.editors[f].propertyPath,void 0,void 0,A)}if(Array.isArray(d)){o=[];for(let f=0,v=d.length;f<v;++f)o.push(this.createFormElement(d[f],g,h,i,n));h.set("renderables",o,n)}return h}createPropertyCollectionElement(e,t,r){let i;a(l.isNonEmptyString(e),'Invalid parameter "collectionElementIdentifier"',1475377160),a(typeof t=="object"&&t!==null&&!Array.isArray(t),'Invalid parameter "collectionElementConfiguration"',1475377161),a(l.isNonEmptyString(r),'Invalid parameter "collectionName"',1475377162),t.identifier=e;const n=b.getFormEditorDefinition(r,e);return"predefinedDefaults"in n&&n.predefinedDefaults?i=n.predefinedDefaults:i={},Object.assign(i,t)}}class E{constructor(){this.endpoints={},this.prototypeName=null,this.persistenceIdentifier=null}setEndpoints(e){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "endpoints"',1475377488),this.endpoints=e}setPrototypeName(e){a(l.isNonEmptyString(e),'Invalid parameter "prototypeName"',1475928095),this.prototypeName=e}setPersistenceIdentifier(e){a(l.isNonEmptyString(e),'Invalid parameter "persistenceIdentifier"',1475377489),this.persistenceIdentifier=e}saveFormDefinition(){a(l.isNonEmptyString(this.endpoints.saveForm),'The endpoint "saveForm" is not configured',1475520918),m.saveForm&&m.saveForm.abort();const e=new D(this.endpoints.saveForm);m.saveForm=e,e.post({formPersistenceIdentifier:this.persistenceIdentifier,formDefinition:JSON.stringify(l.convertToSimpleObject(u().getCurrentState("formDefinition")))}).then(async t=>{if(m.saveForm!==e)return;m.saveForm=null;const r=await t.resolve();r.status==="success"?_.publish("core/ajax/saveFormDefinition/success",[r]):_.publish("core/ajax/saveFormDefinition/error",[r])}).catch(async t=>{if(t instanceof V){const r=await t.resolve();_.publish("core/ajax/error",[t.response.statusText,r])}})}renderFormDefinitionPage(e){a(!isNaN(Number(e)),'Invalid parameter "pageIndex"',1475377781),a(l.isNonEmptyString(this.endpoints.formPageRenderer),'The endpoint "formPageRenderer" is not configured',1473447677),m.renderFormDefinitionPage&&m.renderFormDefinitionPage.abort();const t=new D(this.endpoints.formPageRenderer);m.renderFormDefinitionPage=t,t.post({formDefinition:JSON.stringify(l.convertToSimpleObject(u().getCurrentState("formDefinition"))),pageIndex:e,prototypeName:this.prototypeName,formPersistenceIdentifier:this.persistenceIdentifier}).then(async r=>{if(m.renderFormDefinitionPage!==t)return;m.renderFormDefinitionPage=null;const i=await r.resolve();_.publish("core/ajax/renderFormDefinitionPage/success",[i,e])}).catch(async r=>{if(r instanceof V){const i=await r.resolve();_.publish("core/ajax/error",[r.response.statusText,i])}})}}class U{constructor(){this.stackSize=10,this.stackPointer=0,this.stack=[]}add(e,t){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "applicationState"',1477847415),t=!!t,Object.assign(e,{propertyValidationServiceRegisteredValidators:R(this.getCurrentState("propertyValidationServiceRegisteredValidators")??{})}),this.stack.splice(0,0,e),this.stack.length>this.stackSize&&this.stack.splice(this.stackSize-1,this.stack.length-this.stackSize),t||_.publish("core/applicationState/add",[e,this.getCurrentStackPointer(),this.getCurrentStackSize()])}addAndReset(e,t){a(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "applicationState"',1477872641),this.stackPointer>0&&this.stack.splice(0,this.stackPointer),this.stackPointer=0,this.add(e,!0),t||_.publish("core/applicationState/add",[this.getCurrentState(),this.getCurrentStackPointer(),this.getCurrentStackSize()])}getCurrentState(e){if(e===void 0)return this.stack[this.stackPointer]||void 0;if(a(e==="formDefinition"||e==="currentlySelectedPageIndex"||e==="currentlySelectedFormElementIdentifierPath"||e==="propertyValidationServiceRegisteredValidators",'Invalid parameter "type"',1477932754),!(typeof this.stack[this.stackPointer]>"u"))return this.stack[this.stackPointer][e]}setCurrentState(e,t){a(e==="formDefinition"||e==="currentlySelectedPageIndex"||e==="currentlySelectedFormElementIdentifierPath"||e==="propertyValidationServiceRegisteredValidators",'Invalid parameter "type"',1477934111),this.stack[this.stackPointer][e]=t}setMaximalStackSize(e){a(typeof e=="number",'Invalid parameter "size"',1477846933),this.stackSize=e}getMaximalStackSize(){return this.stackSize}getCurrentStackSize(){return this.stack.length}getCurrentStackPointer(){return this.stackPointer}setCurrentStackPointer(e){a(typeof e=="number",'Invalid parameter "size"',1477852138),e<0?this.stackPointer=0:e>this.stack.length-1?this.stackPointer=this.stack.length-1:this.stackPointer=e}decrementCurrentStackPointer(){this.setCurrentStackPointer(--this.stackPointer)}incrementCurrentStackPointer(){this.setCurrentStackPointer(++this.stackPointer)}}function B(y){return a(l.isNonEmptyString(y),'Invalid parameter "ajaxRequestIdentifier"',1475358064),m[y]||null}const l=new P,z=new E,m={},I=new O,W=new U,_=new k,b=new T,M=new w;function q(){return l}function J(){return z}function H(){return I}function u(){return W}function G(){return _}function K(){return M}function Q(){return b}export{U as ApplicationStateStack,E as DataBackend,w as Factory,N as Model,O as PropertyValidationService,k as PublisherSubscriber,T as Repository,P as Utility,a as assert,u as getApplicationStateStack,J as getDataBackend,K as getFactory,H as getPropertyValidationService,G as getPublisherSubscriber,Q as getRepository,B as getRunningAjaxRequest,q as getUtility};
>>>>>>> 66ad3063 ([BUGFIX] Preserve dots in scalar map keys in form editor model)
