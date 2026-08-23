/**
 * Live password-policy indicator for the TYPO3 form framework's
 * Password / AdvancedPassword elements.
 *
 * Markup contract:
 *   <input type="password" id="..." />
 *   <div class="fe-password-policy" data-fe-password-policy data-target="..."></div>
 *
 * The script fetches the active frontend password policy once per
 * page from /_form/password-policy/, then renders one
 * <li> per rule. Each <li> toggles between `is-met` and `is-unmet`
 * as the user types — same regexes the TYPO3 CorePasswordValidator
 * uses on the server, so what the indicator shows green is also
 * what the form will accept.
 *
 * Multiple password fields on one page each get their own container;
 * a single fetch is shared via a module-scoped promise.
 *
 * Promoted from wapplersystems/form_extended into the fork as part of
 * Phase 3 of the form_extended migration (endpoint path renamed from
 * /_form_extended/password-policy to /_form/password-policy/).
 */
(function () {
    'use strict';

    const ENDPOINT = '/_form/password-policy/';
    let policyPromise = null;

    /**
     * Build the endpoint URL with a `lang=` query parameter taken from
     * the document language. The middleware uses this to localize the
     * rule labels to the page the user is actually viewing, since the
     * endpoint itself has no language prefix in the URL and would
     * otherwise always fall back to the site's default language.
     */
    function endpointUrl() {
        const lang = (document.documentElement.lang || '').trim();
        return lang ? ENDPOINT + '?lang=' + encodeURIComponent(lang) : ENDPOINT;
    }

    function loadPolicy() {
        if (policyPromise === null) {
            policyPromise = fetch(endpointUrl(), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { rules: [] }; })
                .catch(function () { return { rules: [] }; });
        }
        return policyPromise;
    }

    /**
     * Same evaluation logic as TYPO3 core's CorePasswordValidator
     * (sysext/core/Classes/PasswordPolicy/Validator/CorePasswordValidator.php).
     * Keep in sync with that class — both regexes and the "special"
     * char class.
     */
    const RULES = {
        minimumLength: function (pw, value) { return pw.length >= Number(value || 0); },
        upperCaseCharacterRequired: function (pw) { return /[A-Z]/.test(pw); },
        lowerCaseCharacterRequired: function (pw) { return /[a-z]/.test(pw); },
        digitCharacterRequired: function (pw) { return /[0-9]/.test(pw); },
        // Matches the same Unicode class TYPO3 core uses.
        specialCharacterRequired: function (pw) { return /[\p{P}\p{Sm}\p{Sc}\p{Sk}\p{So}]/u.test(pw); },
    };

    function init(container) {
        const targetId = container.dataset.target;
        if (!targetId) return;
        const input = document.getElementById(targetId);
        if (!(input instanceof HTMLInputElement)) return;

        loadPolicy().then(function (policy) {
            const rules = (policy.rules || []).filter(function (r) {
                return typeof RULES[r.id] === 'function';
            });
            if (rules.length === 0) {
                // No active rules → no indicator. Empty container = no
                // visual noise on policies that disable client-side checks.
                container.hidden = true;
                return;
            }
            renderList(container, rules);
            const items = container.querySelectorAll('[data-rule-id]');
            const update = function () {
                const pw = input.value || '';
                items.forEach(function (li) {
                    const ruleId = li.dataset.ruleId;
                    const value = li.dataset.ruleValue;
                    const met = RULES[ruleId](pw, value);
                    li.classList.toggle('is-met', met);
                    li.classList.toggle('is-unmet', !met);
                });
            };
            input.addEventListener('input', update);
            // Set the initial empty-input state on attach.
            update();
        });
    }

    function renderList(container, rules) {
        // Replace whatever placeholder was there with a fresh list. This
        // is the only DOM write we do; updates after that are class
        // toggles on the existing nodes.
        const heading = container.querySelector('[data-heading]');
        const ul = document.createElement('ul');
        ul.className = 'fe-password-policy__rules';
        rules.forEach(function (rule) {
            const li = document.createElement('li');
            li.className = 'fe-password-policy__rule is-unmet';
            li.dataset.ruleId = rule.id;
            if (rule.value !== undefined && rule.value !== null) {
                li.dataset.ruleValue = String(rule.value);
            }
            const marker = document.createElement('span');
            marker.className = 'fe-password-policy__marker';
            marker.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.className = 'fe-password-policy__label';
            label.textContent = rule.label;
            li.appendChild(marker);
            li.appendChild(label);
            ul.appendChild(li);
        });
        // Wipe everything except the heading element if present, then
        // append the freshly built list.
        Array.from(container.children).forEach(function (c) {
            if (c !== heading) container.removeChild(c);
        });
        container.appendChild(ul);
    }

    /* --------------------------------------------------------------------
     * Reveal toggle + policy-compliant password generator.
     *
     * Both are opt-in per element (showPasswordToggle / showPasswordGenerator)
     * and reuse the same policy fetch above, so a generated password always
     * satisfies exactly what the live indicator — and the server-side
     * CorePasswordValidator — will accept.
     *
     * Markup contract:
     *   <button data-fe-password-toggle   data-target="…" [data-target-confirmation="…"]
     *           data-label-show="…" data-label-hide="…">…</button>
     *   <button data-fe-password-generate data-target="…" [data-target-confirmation="…"]>…</button>
     * ------------------------------------------------------------------ */

    function targetInputs(button) {
        return [button.dataset.target, button.dataset.targetConfirmation]
            .filter(Boolean)
            .map(function (id) { return document.getElementById(id); })
            .filter(function (el) { return el instanceof HTMLInputElement; });
    }

    function applyReveal(toggle, reveal) {
        targetInputs(toggle).forEach(function (el) {
            el.type = reveal ? 'text' : 'password';
        });
        toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        const labelEl = toggle.querySelector('.fe-password-tools__btn-label') || toggle;
        labelEl.textContent = reveal
            ? (toggle.dataset.labelHide || 'Hide')
            : (toggle.dataset.labelShow || 'Show');
    }

    function initToggle(toggle) {
        // Normalise to the hidden state on attach (covers a browser that
        // restored a revealed field on back/forward navigation).
        applyReveal(toggle, false);
        toggle.addEventListener('click', function () {
            applyReveal(toggle, toggle.getAttribute('aria-pressed') !== 'true');
        });
    }

    // Cryptographically secure, unbiased random integer in [0, max).
    function randomInt(max) {
        const buf = new Uint32Array(1);
        const limit = Math.floor(0x100000000 / max) * max;
        let x;
        do { crypto.getRandomValues(buf); x = buf[0]; } while (x >= limit);
        return x % max;
    }

    function pick(pool) { return pool.charAt(randomInt(pool.length)); }

    /**
     * Build a password that satisfies the active policy. Character pools
     * omit visually ambiguous glyphs (0/O, 1/l/I). One character is seeded
     * from every required class, the remainder is filled from the union of
     * those classes, then the whole array is shuffled so the seeded
     * characters aren't always at the front. The special-character pool
     * stays inside the Unicode classes CorePasswordValidator accepts.
     */
    function buildPassword(policy) {
        const pools = {
            lowerCaseCharacterRequired: 'abcdefghijkmnpqrstuvwxyz',
            upperCaseCharacterRequired: 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            digitCharacterRequired: '23456789',
            specialCharacterRequired: '!@#$%^&*()-_=+[]{}?'
        };
        const ids = new Set((policy.rules || []).map(function (r) { return r.id; }));
        let minLen = 12;
        (policy.rules || []).forEach(function (r) {
            if (r.id === 'minimumLength' && r.value) {
                minLen = Math.max(minLen, Number(r.value));
            }
        });

        const required = [];
        ['lowerCaseCharacterRequired', 'upperCaseCharacterRequired',
            'digitCharacterRequired', 'specialCharacterRequired'].forEach(function (id) {
            if (ids.has(id)) required.push(pools[id]);
        });
        // Empty policy / no class requirements → still emit a strong mix
        // rather than something trivial.
        if (required.length === 0) {
            required.push(
                pools.lowerCaseCharacterRequired, pools.upperCaseCharacterRequired,
                pools.digitCharacterRequired, pools.specialCharacterRequired
            );
        }

        const union = required.join('');
        const chars = required.map(pick);
        while (chars.length < minLen) chars.push(pick(union));
        for (let i = chars.length - 1; i > 0; i--) { // Fisher–Yates
            const j = randomInt(i + 1);
            const tmp = chars[i]; chars[i] = chars[j]; chars[j] = tmp;
        }
        return chars.join('');
    }

    function initGenerator(button) {
        button.addEventListener('click', function () {
            loadPolicy().then(function (policy) {
                const pw = buildPassword(policy);
                targetInputs(button).forEach(function (el) {
                    el.value = pw;
                    // Let the live indicator + any framework validators react.
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });
                // Reveal the generated value: drive the sibling toggle if one
                // exists (keeps its label/aria in sync), else flip directly.
                const tools = button.closest('.fe-password-tools');
                const toggle = tools && tools.querySelector('[data-fe-password-toggle]');
                if (toggle) {
                    applyReveal(toggle, true);
                } else {
                    targetInputs(button).forEach(function (el) { el.type = 'text'; });
                }
            });
        });
    }

    // Attribut, Stempel und Initialisierer je Bestandteil - alle drei koennen
    // unabhaengig voneinander im Markup stehen.
    const PARTS = [
        ['data-fe-password-policy', 'data-fe-password-policy-bound', init],
        ['data-fe-password-toggle', 'data-fe-password-toggle-bound', initToggle],
        ['data-fe-password-generate', 'data-fe-password-generate-bound', initGenerator],
    ];

    function selectorFor(part) {
        return '[' + part[0] + ']:not([' + part[1] + '])';
    }

    function start() {
        PARTS.forEach(function (part) {
            document.querySelectorAll(selectorFor(part)).forEach(function (element) {
                element.setAttribute(part[1], '1');
                part[2](element);
            });
        });
    }

    // Ein Passwortfeld kann in einem nachgeladenen Formular stecken - etwa
    // einer Registrierung im Modal. Ohne das hier fehlten dort die
    // Live-Anzeige der Regeln, der Anzeigen-Umschalter und der Generator,
    // waehrend das Feld selbst normal funktioniert und serverseitig weiter
    // geprueft wird. Also kein kaputtes Formular, aber still fehlende Hilfe
    // genau an der Stelle, an der sie am meisten bringt.
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
                    const hit = PARTS.some(function (part) {
                        const s = selectorFor(part);
                        return node.matches(s) || node.querySelector(s) !== null;
                    });
                    if (hit) {
                        start();
                        return;
                    }
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    function boot() {
        start();
        observe();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
