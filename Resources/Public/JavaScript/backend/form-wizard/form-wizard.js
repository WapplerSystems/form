var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
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
import '@typo3/backend/wizard/wizard.js';
import { customElement, property, query, state } from 'lit/decorators.js';
import SettingsStep, {} from '@typo3/form/backend/form-wizard/steps/settings-step.js';
import { html, LitElement } from 'lit';
import formManagerLabels from '~labels/form.form_manager_javascript';
import { StepSummaryEvent } from '@typo3/backend/wizard/events/step-summary-event.js';
import { DuplicateFormSubmissionService } from '@typo3/form/backend/form-wizard/finisher/duplicate-form-submission-service.js';
import ModeStep, {} from '@typo3/form/backend/form-wizard/steps/mode-step.js';
import { CreateFormSubmissionService } from '@typo3/form/backend/form-wizard/finisher/create-form-submission-service.js';
import { FormManager } from '@typo3/form/backend/form-manager.js';
import { AutoAdvanceEvent } from '@typo3/backend/wizard/events/auto-advance-event.js';
import { StorageStep } from '@typo3/form/backend/form-wizard/steps/storage-step.js';
let FormWizard = class FormWizard extends LitElement {
    constructor() {
        super(...arguments);
        this.steps = [];
        this.errorMessage = null;
        this.duplicateForm = null;
    }
    connectedCallback() {
        super.connectedCallback();
        this.addEventListener(StepSummaryEvent.eventName, this.handleStepSummary);
    }
    disconnectedCallback() {
        super.disconnectedCallback();
        this.removeEventListener(StepSummaryEvent.eventName, this.handleStepSummary);
    }
    firstUpdated(_changedProperties) {
        super.firstUpdated(_changedProperties);
        if (this.formManager.getAccessibleStorageAdapters().length < 1) {
            this.errorMessage = formManagerLabels.get('formManager.newFormWizard.step1.noStorages');
            return;
        }
        const context = {
            wizard: this.wizard,
            formManager: this.formManager,
            getStoreData: this.wizard.getStoreData.bind(this.wizard),
            setStoreData: this.wizard.setStoreData.bind(this.wizard),
            clearStoreData: this.wizard.clearStoreData.bind(this.wizard),
            getDataStore: this.wizard.getDataStore.bind(this.wizard),
            dispatchAutoAdvance: () => this.wizard.dispatchEvent(new AutoAdvanceEvent())
        };
        if (this.duplicateForm != null) {
            this.steps = [new StorageStep(context), new SettingsStep(context)];
            this.submissionService = new DuplicateFormSubmissionService(context, this.duplicateForm.persistenceIdentifier);
        }
        else {
            this.steps = [new ModeStep(context), new StorageStep(context), new SettingsStep(context)];
            this.submissionService = new CreateFormSubmissionService(context);
        }
    }
    createRenderRoot() {
        return this;
    }
    render() {
        if (this.errorMessage) {
            return this.wizard.renderError(this.errorMessage);
        }
        return html `
      <typo3-backend-wizard .steps="${this.steps}"
                            .submissionService="${this.submissionService}"
                            confirm-button-label="${formManagerLabels.get('formManager.newFormWizard.step1.title')}"
      ></typo3-backend-wizard>
    `;
    }
    handleStepSummary(event) {
        if (this.duplicateForm === null) {
            return;
        }
        event.detail.summaryData = [
            {
                label: formManagerLabels.get('formManager.form_copied'),
                value: html `
          <typo3-backend-icon identifier="content-form" size="small" class="me-1"></typo3-backend-icon>
          ${this.duplicateForm.name}`
            },
            ...event.detail.summaryData
        ];
    }
};
__decorate([
    state()
], FormWizard.prototype, "steps", void 0);
__decorate([
    state()
], FormWizard.prototype, "submissionService", void 0);
__decorate([
    state()
], FormWizard.prototype, "errorMessage", void 0);
__decorate([
    property({ type: FormManager, attribute: false })
], FormWizard.prototype, "formManager", void 0);
__decorate([
    property({ type: Object, attribute: false })
], FormWizard.prototype, "duplicateForm", void 0);
__decorate([
    query('typo3-backend-wizard')
], FormWizard.prototype, "wizard", void 0);
FormWizard = __decorate([
    customElement('typo3-backend-form-wizard')
], FormWizard);
export { FormWizard };
=======
import"@typo3/backend/wizard/wizard.js";import{state as d,property as l,query as S,customElement as w}from"lit/decorators.js";import u from"@typo3/form/backend/form-wizard/steps/settings-step.js";import{LitElement as g,html as f}from"lit";import c from"~labels/form.form_manager_javascript";import{StepSummaryEvent as h}from"@typo3/backend/wizard/events/step-summary-event.js";import{DuplicateFormSubmissionService as v}from"@typo3/form/backend/form-wizard/finisher/duplicate-form-submission-service.js";import y from"@typo3/form/backend/form-wizard/steps/mode-step.js";import{CreateFormSubmissionService as z}from"@typo3/form/backend/form-wizard/finisher/create-form-submission-service.js";import{FormManager as M}from"@typo3/form/backend/form-manager.js";import{AutoAdvanceEvent as F}from"@typo3/backend/wizard/events/auto-advance-event.js";import{StorageStep as b}from"@typo3/form/backend/form-wizard/steps/storage-step.js";var a=function(o,t,e,s){var n=arguments.length,i=n<3?t:s===null?s=Object.getOwnPropertyDescriptor(t,e):s,m;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")i=Reflect.decorate(o,t,e,s);else for(var p=o.length-1;p>=0;p--)(m=o[p])&&(i=(n<3?m(i):n>3?m(t,e,i):m(t,e))||i);return n>3&&i&&Object.defineProperty(t,e,i),i};let r=class extends g{constructor(){super(...arguments),this.steps=[],this.errorMessage=null,this.duplicateForm=null}connectedCallback(){super.connectedCallback(),this.addEventListener(h.eventName,this.handleStepSummary)}disconnectedCallback(){super.disconnectedCallback(),this.removeEventListener(h.eventName,this.handleStepSummary)}firstUpdated(t){if(super.firstUpdated(t),this.formManager.getAccessibleStorageAdapters().length<1){this.errorMessage=c.get("formManager.newFormWizard.step1.noStorages");return}const e={wizard:this.wizard,formManager:this.formManager,getStoreData:this.wizard.getStoreData.bind(this.wizard),setStoreData:this.wizard.setStoreData.bind(this.wizard),clearStoreData:this.wizard.clearStoreData.bind(this.wizard),getDataStore:this.wizard.getDataStore.bind(this.wizard),dispatchAutoAdvance:()=>this.wizard.dispatchEvent(new F)};this.duplicateForm!=null?(this.steps=[new b(e),new u(e)],this.submissionService=new v(e,this.duplicateForm.persistenceIdentifier)):(this.steps=[new y(e),new b(e),new u(e)],this.submissionService=new z(e))}createRenderRoot(){return this}render(){return this.errorMessage?this.wizard.renderError(this.errorMessage):f`<typo3-backend-wizard .steps=${this.steps} .submissionService=${this.submissionService} confirm-button-label=${c.get("formManager.newFormWizard.step1.title")} skip-summary></typo3-backend-wizard>`}handleStepSummary(t){this.duplicateForm!==null&&(t.detail.summaryData=[{label:c.get("formManager.form_copied"),value:f`<typo3-backend-icon identifier=content-form size=small class=me-1></typo3-backend-icon>${this.duplicateForm.name}`},...t.detail.summaryData])}};a([d()],r.prototype,"steps",void 0),a([d()],r.prototype,"submissionService",void 0),a([d()],r.prototype,"errorMessage",void 0),a([l({type:M,attribute:!1})],r.prototype,"formManager",void 0),a([l({type:Object,attribute:!1})],r.prototype,"duplicateForm",void 0),a([S("typo3-backend-wizard")],r.prototype,"wizard",void 0),r=a([w("typo3-backend-form-wizard")],r);export{r as FormWizard};
>>>>>>> dd92374e ([TASK] Allow skipping the summary step in the backend wizard)
