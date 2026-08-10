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
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import formManagerLabels from '~labels/form.form_manager_javascript';
export class CreateFormSubmissionService {
    constructor(context) {
        this.context = context;
    }
    async execute() {
        const dataStore = this.context.getDataStore();
        const requestUrl = this.context.formManager.getAjaxEndpoint('create');
        const response = await new AjaxRequest(requestUrl)
            .post({
            formName: dataStore.settings.formName,
            templatePath: dataStore.settings.template,
            prototypeName: dataStore.settings.prototype,
            storage: dataStore.storage.typeIdentifier,
            storageLocation: dataStore.settings.storageLocation
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
