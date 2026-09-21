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
=======
import*as w from"@typo3/form/backend/form-editor/helper.js";import{merge as V}from"lodash-es";import d from"@typo3/backend/modal.js";import N from"@typo3/backend/severity.js";let G=null;const W={domElementClassNames:{buttonDefault:"btn-default",buttonInfo:"btn-info",buttonWarning:"btn-warning"},domElementDataAttributeNames:{elementType:"element-type",fullElementType:"data-element-type"},domElementDataAttributeValues:{rowItem:"rowItem",rowLink:"rowLink",rowsContainer:"rowsContainer",elementIdentifier:"elementIdentifier",elementType:"elementType",validationErrors:"validationErrors",validationErrorGroup:"validationErrorGroup",validationErrorGroupLabel:"validationErrorGroupLabel",validationErrorGroupItems:"validationErrorGroupItems",validationError:"validationError",templateInsertElements:"Modal-InsertElements",templateInsertPages:"Modal-InsertPages",templateValidationErrors:"Modal-ValidationErrors"}};let T=null;function p(){return T}function n(e){return v().isUndefinedOrNull(e)?w.setConfiguration(G):w.setConfiguration(e)}function v(){return p().getUtility()}function E(e,t,r){return p().assert(e,t,r)}function s(){return p().getRootFormElement()}function S(){return p().getPublisherSubscriber()}function a(e,t){return p().getFormElementDefinition(e,t)}function F(e,t){const r=[];E(v().isNonEmptyString(e),'Invalid parameter "publisherTopicName"',1478889049),E(Array.isArray(t),'Invalid parameter "formElement"',1478889044),r.push({text:a(s(),"modalRemoveElementCancelButton"),active:!0,btnClass:n().getDomElementClassName("buttonDefault"),name:"cancel",trigger:(o,i)=>{i.hideModal()}}),r.push({text:a(s(),"modalRemoveElementConfirmButton"),active:!0,btnClass:n().getDomElementClassName("buttonWarning"),name:"confirm",trigger:(o,i)=>{S().publish(e,t),i.hideModal()}}),d.show(a(s(),"modalRemoveElementDialogTitle"),a(s(),"modalRemoveElementDialogMessage"),N.warning,r)}function k(e,t,r){if(E(v().isNonEmptyString(t),'Invalid parameter "publisherTopicName"',1478910954),typeof r=="object"&&r!==null&&!Array.isArray(r))for(const o of Object.keys(r)){if(o==="disableElementTypes"&&Array.isArray(r[o]))for(let i=0,c=r[o].length;i<c;++i)e.querySelectorAll(n().getDomElementDataAttribute("fullElementType","bracesWithKeyValue",[r[o][i]])).forEach(l=>l.classList.add(n().getDomElementClassName("disabled")));o==="onlyEnableElementTypes"&&Array.isArray(r[o])&&e.querySelectorAll(n().getDomElementDataAttribute("fullElementType","bracesWithKey")).forEach(i=>{const c=i.getAttribute(n().getDomElementDataAttribute("elementType"));r[o].some(g=>g===c)||i.classList.add(n().getDomElementClassName("disabled"))})}[...e.children].forEach(o=>o.addEventListener("typo3:form:insert-element-click",function(i){S().publish(t,[i.detail.item.identifier])}))}function $(e,t){let r,o;E(Array.isArray(t),'Invalid parameter "validationResults"',1479161268);const i=n().getDomElementDataIdentifierSelector("rowItem"),c=e.querySelector(i)?.cloneNode(!0);e.querySelectorAll(i).forEach(l=>l.remove());for(let l=0,g=t.length;l<g;++l){let h=!1;for(let u=0,f=t[l].validationResults.length;u<f;++u)if(t[l].validationResults[u].validationResults&&t[l].validationResults[u].validationResults.length>0){h=!0;break}if(h){if(r=p().getFormElementByIdentifierPath(t[l].formElementIdentifierPath),o=c?.cloneNode(!0),o){const f=o;f.setAttribute(n().getDomElementDataAttribute("elementIdentifier"),t[l].formElementIdentifierPath),f.querySelector(".form-editor-validation-error-title")?.replaceChildren(j(r)),f.querySelector(n().getDomElementDataIdentifierSelector("elementIdentifier"))?.replaceChildren(document.createTextNode(r.get("identifier"))),f.querySelector(n().getDomElementDataIdentifierSelector("elementType"))?.replaceChildren(document.createTextNode(a(r,"label")||r.get("type")));const y=f.querySelector(n().getDomElementDataIdentifierSelector("validationErrors")),A=y?.querySelector(n().getDomElementDataIdentifierSelector("validationErrorGroup")),M=y?.querySelector(n().getDomElementDataIdentifierSelector("validationError"));y?.replaceChildren();const P=new Map;t[l].validationResults.forEach(b=>{const D=_(r,b.propertyPath),I=K(b.propertyPath),q=z(r,b.propertyPath),L=I?O(r,I):q,B=I?`finisher-${I}`:`validator-${q}`,C=b.validationResults.join(" ");if(L&&A){let m=P.get(B);m||(m=A.cloneNode(!0),m.querySelector(n().getDomElementDataIdentifierSelector("validationErrorGroupLabel"))?.replaceChildren(document.createTextNode(L)),m.querySelector(n().getDomElementDataIdentifierSelector("validationErrorGroupItems"))?.replaceChildren(),P.set(B,m),y?.append(m));const x=M?.cloneNode();x?.replaceChildren(document.createTextNode(D?`${D}: ${C}`:C)),m.querySelector(n().getDomElementDataIdentifierSelector("validationErrorGroupItems"))?.append(x)}else{const m=M?.cloneNode();m?.replaceChildren(document.createTextNode(D?`${D}: ${C}`:C)),y?.append(m)}})}const u=e.querySelector(n().getDomElementDataIdentifierSelector("rowsContainer"));u&&o&&u.append(o)}}e.querySelectorAll("a").forEach(l=>{l.addEventListener("click",function(g){g.preventDefault(),S().publish("view/modal/validationErrors/element/clicked",[l.getAttribute(n().getDomElementDataAttribute("elementIdentifier"))]),e.querySelectorAll("a").forEach(h=>h.replaceWith(h.cloneNode(!0))),d.currentModal.hideModal()})})}function j(e){E(typeof e=="object"&&e!==null&&!Array.isArray(e),'Invalid parameter "formElement"',1479162557);const t=document.createElement("span");return t.textContent=e.get("label")?e.get("label"):e.get("identifier"),t}function _(e,t){return R(e,t)?.label||""}function z(e,t){return R(e,t)?.propertyValidators?.find(i=>i!=="NotEmpty")||""}function R(e,t){const r=a(e,void 0);return[...r.editors||[],...Object.values(r.propertyCollections||{}).flatMap(i=>i.flatMap(c=>c.editors||[]))].find(i=>i.propertyPath===t||t.endsWith(`.${i.propertyPath}`))}function K(e){const t=e.match(/^finishers\.(\d+)\./);return t?t[1]:""}function O(e,t){const o=e.get("finishers")?.[Number(t)]?.identifier;return o&&p().getFormEditorDefinition("finishers",o)?.label||""}function U(e){F("view/modal/removeFormElement/perform",[e])}function H(e,t,r){E(v().isNonEmptyString(e),'Invalid parameter "collectionElementIdentifier"',1478894420),E(v().isNonEmptyString(t),'Invalid parameter "collectionName"',1478894421),F("view/modal/removeCollectionElement/perform",[e,t,r])}function J(){const e=[];e.push({text:a(s(),"modalCloseCancelButton"),active:!0,btnClass:n().getDomElementClassName("buttonDefault"),name:"cancel",trigger:(t,r)=>{r.hideModal()}}),e.push({text:a(s(),"modalCloseConfirmButton"),active:!0,btnClass:n().getDomElementClassName("buttonWarning"),name:"confirm",trigger:(t,r)=>{S().publish("view/modal/close/perform",[]),r.hideModal()}}),d.show(a(s(),"modalCloseDialogTitle"),a(s(),"modalCloseDialogMessage"),N.warning,e)}function Q(e,t){const r=n().getTemplateElement("templateInsertElements");if(r){const o=document.importNode(r.content,!0);k(o,e,t),d.advanced({title:a(s(),"modalInsertElementsDialogTitle"),size:d.sizes.large,content:o})}}function X(e){const t=n().getTemplateElement("templateInsertPages");if(t){const r=document.importNode(t.content,!0);k(r,e),d.advanced({title:a(s(),"modalInsertPagesDialogTitle"),size:d.sizes.small,content:r})}}function Y(e){const t=[];t.push({text:a(s(),"modalValidationErrorsConfirmButton"),active:!0,btnClass:n().getDomElementClassName("buttonDefault"),name:"confirm",trigger:function(o,i){i.hideModal()}});const r=n().getTemplateElement("templateValidationErrors");if(r){const o=document.importNode(r.content,!0);$(o,e),d.show(a(s(),"modalValidationErrorsDialogTitle"),o,N.error,t)}}function Z(e,t){return T=e,G=V({},W,t??{}),w.bootstrap(T),this}export{Z as bootstrap,J as showCloseConfirmationModal,Q as showInsertElementsModal,X as showInsertPagesModal,H as showRemoveCollectionElementModal,U as showRemoveFormElementModal,Y as showValidationErrorsModal};
>>>>>>> b83e65ce ([TASK] Improve form validation error modal UI)
