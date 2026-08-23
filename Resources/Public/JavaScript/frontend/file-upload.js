/**
 * Client side of the multi-file upload field.
 *
 * A native <input type="file" multiple> replaces its FileList on every pick, and
 * it offers no way to drop a single file again. So without this, "choose files"
 * twice means the first selection is silently gone, and a mis-picked file can
 * only be corrected by re-picking everything. Both are what the field promises
 * when it says "you may upload individual documents".
 *
 * Markup contract (emitted by FileUpload.fluid.html when properties.multiple is
 * on; several upload fields per page stay independent):
 *
 *   <input type="file" multiple id="…" data-form-multi-upload
 *          data-remove-label="Remove" />
 *   <ul class="form-element-fileupload-list" data-form-multi-upload-list="…inputId">
 *     …server-rendered <li> for files already persisted (with their own
 *        __deleteFile checkbox, see UploadDeleteCheckboxViewHelper)…
 *   </ul>
 *
 * Pending files — picked but not yet submitted — are rendered into the same list
 * so the field reads as one thing, marked with the "-pending" class so styling
 * and the server-rendered entries stay distinguishable.
 *
 * The DataTransfer is the source of truth: browsers accept an assignment to
 * `input.files` only from a FileList, and DataTransfer is the sole way to build
 * one. Everything is derived from it, so submitting sends exactly what the list
 * shows.
 *
 * Promoted from wapplersystems/form_extended into the fork, rewritten: the
 * original matched every `input[multiple]` on the page (including selects),
 * addressed its chip container via nextElementSibling walking, and removed
 * DataTransfer entries by name only — so two picked files sharing a name
 * removed the wrong one, and re-picking an identical file added a duplicate.
 */
(function () {
    'use strict';

    /**
     * Files are identified by name + size + lastModified rather than by name
     * alone: picking the same file twice must not create a duplicate, and two
     * different files that happen to share a name must both survive.
     */
    function fileKey(file) {
        return [file.name, file.size, file.lastModified].join(':');
    }

    /**
     * The list belongs directly after the input. The server renders it there
     * (FileUpload.fluid.html captures it and prints it below the ViewHelper) but
     * only when files are already persisted - so reuse it when present, otherwise
     * create it in the same position, so pending and persisted files always
     * appear in one place.
     */
    function buildList(input) {
        const listId = input.dataset.formMultiUploadList;
        if (listId) {
            const existing = document.getElementById(listId);
            if (existing) {
                return existing;
            }
        }
        const list = document.createElement('ul');
        list.className = 'form-element-fileupload-list';
        if (listId) {
            list.id = listId;
        }
        input.insertAdjacentElement('afterend', list);
        return list;
    }

    function init(input) {
        if (input.dataset.formMultiUploadInitialized === '1') {
            return;
        }
        input.dataset.formMultiUploadInitialized = '1';

        const list = buildList(input);
        const removeLabel = input.dataset.removeLabel || 'Remove';
        const transfer = new DataTransfer();

        const render = function () {
            list.querySelectorAll('[data-form-multi-upload-pending]').forEach(function (item) {
                item.remove();
            });

            Array.from(transfer.files).forEach(function (file) {
                const item = document.createElement('li');
                item.className = 'form-element-fileupload-item form-element-fileupload-item-pending';
                item.setAttribute('data-form-multi-upload-pending', fileKey(file));

                const name = document.createElement('span');
                name.className = 'form-element-fileupload-name';
                name.textContent = file.name;

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'form-element-fileupload-remove-pending';
                // The visible label is the same word the server-rendered entries
                // use; the file name is appended for assistive technology, where
                // a list of identical "Remove" buttons is useless.
                button.textContent = removeLabel;
                const hidden = document.createElement('span');
                hidden.className = 'visually-hidden';
                hidden.textContent = ' ' + file.name;
                button.append(hidden);
                button.addEventListener('click', function () {
                    remove(fileKey(file));
                });

                item.append(name, button);
                list.append(item);
            });
        };

        const remove = function (key) {
            // DataTransfer has no "remove by value", and removing while iterating
            // shifts the indices - so collect what stays and rebuild.
            const keep = Array.from(transfer.files).filter(function (file) {
                return fileKey(file) !== key;
            });
            transfer.items.clear();
            keep.forEach(function (file) {
                transfer.items.add(file);
            });
            input.files = transfer.files;
            render();
            // Let the browser re-run constraint validation (a required upload
            // field that just lost its last file must become invalid again).
            input.dispatchEvent(new Event('change', {bubbles: true}));
        };

        input.addEventListener('change', function () {
            const known = new Set(Array.from(transfer.files).map(fileKey));
            let added = false;
            Array.from(input.files).forEach(function (file) {
                if (!known.has(fileKey(file))) {
                    transfer.items.add(file);
                    known.add(fileKey(file));
                    added = true;
                }
            });
            // Nothing new: either this is the change event dispatched by remove()
            // above, or the user re-picked the very same files. Assigning
            // input.files again would fire another change event - hence the guard.
            if (!added && input.files.length === transfer.files.length) {
                render();
                return;
            }
            input.files = transfer.files;
            render();
        });

        render();
    }

    const SELECTOR = 'input[type="file"][data-form-multi-upload]:not([data-form-multi-upload-bound])';

    function initAll() {
        document.querySelectorAll(SELECTOR).forEach(function (input) {
            input.setAttribute('data-form-multi-upload-bound', '1');
            init(input);
        });
    }

    // Ein Formular kann per XHR nachkommen - in ein Modal, einen Tab. Ohne das
    // hier bliebe das Feld dort unbedient: die Mehrfachauswahl ueber mehrere
    // Runden und das Entfernen einzelner Dateien fehlen, weil beides an diesem
    // Skript haengt. Der Versand bricht dadurch nicht, das native Feld
    // funktioniert weiter - es ist Komfort, der still verschwindet.
    // Der Stempel verhindert, dass ein zweiter Durchlauf demselben Feld ein
    // zweites DataTransfer und doppelte Listener verpasst.
    function observe() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }
        new MutationObserver(function (mutations) {
            for (const mutation of mutations) {
                for (const node of Array.prototype.slice.call(mutation.addedNodes)) {
                    if (!(node instanceof Element)) {
                        continue;
                    }
                    if (node.matches(SELECTOR) || node.querySelector(SELECTOR) !== null) {
                        initAll();
                        return;
                    }
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    function boot() {
        initAll();
        observe();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
