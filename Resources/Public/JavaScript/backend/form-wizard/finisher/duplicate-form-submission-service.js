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
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import formManagerLabels from '~labels/form.form_manager_javascript';
export class DuplicateFormSubmissionService {
    constructor(context, formPersistenceIdentifier) {
        this.context = context;
        this.formPersistenceIdentifier = formPersistenceIdentifier;
    }
    async execute() {
        const dataStore = this.context.getDataStore();
        const requestUrl = this.context.formManager.getAjaxEndpoint('duplicate');
        const response = await new AjaxRequest(requestUrl)
            .post({
            formName: dataStore.settings.formName,
            storage: dataStore.storage.typeIdentifier,
            storageLocation: dataStore.settings.storageLocation,
            formPersistenceIdentifier: this.formPersistenceIdentifier
        });
        const data = await response.resolve();
        if (data?.status === 'success') {
            return {
                success: true,
                finisher: {
                    identifier: 'redirect',
                    module: '@typo3/backend/wizard/finisher/redirect-finisher.js',
                    data: {
                        url: data.url,
                    },
                    labels: {
                        successTitle: formManagerLabels.get('formManager.finisher.redirect.success.title'),
                        successDescription: formManagerLabels.get('formManager.finisher.redirect.success.description'),
                    }
                }
            };
        }
        return {
            success: false,
            errors: [data?.message]
        };
    }
}
=======
import i from"@typo3/core/ajax/ajax-request.js";import r from"~labels/form.form_manager_javascript";class o{constructor(e,s){this.context=e,this.formPersistenceIdentifier=s}async execute(){const e=this.context.getDataStore(),s=this.context.formManager.getAjaxEndpoint("duplicate"),t=await(await new i(s).post({formName:e.settings.formName,storage:e.storage.typeIdentifier,storageLocation:e.settings.storageLocation,formPersistenceIdentifier:this.formPersistenceIdentifier})).resolve();return t?.status==="success"?{success:!0,finisher:{identifier:"redirect",module:"@typo3/backend/wizard/finisher/redirect-finisher.js",data:{url:t.url},labels:{successTitle:r.get("formManager.finisher.redirect.success.title"),successDescription:r.get("formManager.finisher.redirect.success.description")}}}:{success:!1,errors:[t?.message??""]}}}export{o as DuplicateFormSubmissionService};
>>>>>>> 637d4a4e ([TASK] Make the form editor null-safe)
