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
export class FormManager {
    constructor(configuration, viewModel) {
        this.isRunning = false;
        this.configuration = configuration;
        this.viewModel = viewModel;
    }
    /**
     * @todo deprecate, use exported `assert()` method instead
     */
    assert(test, message, messageCode) {
        assert(test, message, messageCode);
    }
    getPrototypes() {
        if (!Array.isArray(this.configuration.selectablePrototypesConfiguration)) {
            return [];
        }
        return this.configuration.selectablePrototypesConfiguration.map((selectablePrototype) => {
            return {
                label: selectablePrototype.label,
                value: selectablePrototype.identifier,
            };
        });
    }
    getTemplatesForPrototype(prototypeName) {
        assert('string' === typeof prototypeName, 'Invalid parameter "prototypeName"', 1475945286);
        if (!Array.isArray(this.configuration.selectablePrototypesConfiguration)) {
            return [];
        }
        const templates = [];
        this.configuration.selectablePrototypesConfiguration.forEach((selectablePrototype) => {
            if (!Array.isArray(selectablePrototype.newFormTemplates)) {
                return;
            }
            if (selectablePrototype.identifier !== prototypeName) {
                return;
            }
            selectablePrototype.newFormTemplates.forEach((newFormTemplate) => {
                templates.push({
                    label: newFormTemplate.label,
                    value: newFormTemplate.templatePath,
                });
            });
        });
        return templates;
    }
    getAccessibleStorageAdapters() {
        if (!Array.isArray(this.configuration.accessibleStorageAdapters)) {
            return [];
        }
        return this.configuration.accessibleStorageAdapters;
    }
    getAccessibleStorageLocationsForAdapter(storageAdapter) {
        const adapter = this.configuration.accessibleStorageAdapters?.find((adapter) => adapter.typeIdentifier === storageAdapter.typeIdentifier);
        if (!adapter || !adapter.options?.allowedStorageLocations) {
            return [];
        }
        return adapter.options.allowedStorageLocations;
    }
    /**
     * @throws 1477506508
     */
    getAjaxEndpoint(endpointName) {
        assert(typeof this.configuration.endpoints[endpointName] !== 'undefined', 'Endpoint ' + endpointName + ' does not exist', 1477506508);
        return this.configuration.endpoints[endpointName];
    }
    /**
     * @throws 1475942618
     */
    run() {
        if (this.isRunning) {
            throw 'You can not run the app twice (1475942618)';
        }
        this.bootstrap();
        this.isRunning = true;
        return this;
    }
    /**
     * @throws 1475942906
     */
    viewSetup() {
        assert('function' === typeof this.viewModel.bootstrap, 'The view model does not implement the method "bootstrap"', 1475942906);
        this.viewModel.bootstrap(this);
    }
    /**
     * @throws 1477506504
     */
    bootstrap() {
        this.configuration = this.configuration || {};
        assert('object' === typeof this.configuration.endpoints, 'Invalid parameter "endpoints"', 1477506504);
        this.viewSetup();
    }
}
let formManagerInstance = null;
/**
 * Return a singleton instance of a "FormManager" object.
 */
export function getInstance(configuration, viewModel) {
    if (formManagerInstance === null) {
        formManagerInstance = new FormManager(configuration, viewModel);
    }
    return formManagerInstance;
}
=======
function n(r,t,i){if(typeof r=="function"&&(r=r()!==!1),!r)throw t=t||"Assertion failed",i&&(t=t+" ("+i+")"),new Error(t)}class s{constructor(t,i){this.isRunning=!1,this.configuration=t,this.viewModel=i}assert(t,i,e){n(t,i,e)}getPrototypes(){return Array.isArray(this.configuration.selectablePrototypesConfiguration)?this.configuration.selectablePrototypesConfiguration.map(t=>({label:t.label,value:t.identifier})):[]}getTemplatesForPrototype(t){if(n(typeof t=="string",'Invalid parameter "prototypeName"',1475945286),!Array.isArray(this.configuration.selectablePrototypesConfiguration))return[];const i=[];return this.configuration.selectablePrototypesConfiguration.forEach(e=>{Array.isArray(e.newFormTemplates)&&e.identifier===t&&e.newFormTemplates.forEach(a=>{i.push({label:a.label,value:a.templatePath})})}),i}getAccessibleStorageAdapters(){return Array.isArray(this.configuration.accessibleStorageAdapters)?this.configuration.accessibleStorageAdapters:[]}getAccessibleStorageLocationsForAdapter(t){const i=this.configuration.accessibleStorageAdapters?.find(e=>e.typeIdentifier===t.typeIdentifier);return!i||!i.options?.allowedStorageLocations?[]:i.options.allowedStorageLocations}getAjaxEndpoint(t){return n(typeof this.configuration.endpoints[t]<"u","Endpoint "+t+" does not exist",1477506508),this.configuration.endpoints[t]}run(){if(this.isRunning)throw new Error("You can not run the app twice (1475942618)");return this.bootstrap(),this.isRunning=!0,this}viewSetup(){n(typeof this.viewModel.bootstrap=="function",'The view model does not implement the method "bootstrap"',1475942906),this.viewModel.bootstrap(this)}bootstrap(){this.configuration=this.configuration||{},n(typeof this.configuration.endpoints=="object",'Invalid parameter "endpoints"',1477506504),this.viewSetup()}}let o=null;function u(r,t){return o===null&&(o=new s(r,t)),o}export{s as FormManager,n as assert,u as getInstance};
>>>>>>> e78ac586 ([TASK] Throw only Error objects in TypeScript)
