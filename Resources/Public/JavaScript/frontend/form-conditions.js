"use strict";
/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */
(function () {
    'use strict';
    function tokenize(input) {
        const tokens = [];
        let i = 0;
        const fieldRe = /^traverse\s*\(\s*formValues\s*,\s*("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*')\s*\)/;
        const valueRe = /^("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|-?\d+(?:\.\d+)?|true|false)/;
        while (i < input.length) {
            const rest = input.slice(i);
            const ws = rest.match(/^\s+/);
            if (ws) {
                i += ws[0].length;
                continue;
            }
            const field = rest.match(fieldRe);
            if (field) {
                tokens.push({ type: 'field', value: unquote(field[1]) });
                i += field[0].length;
                continue;
            }
            if (rest.startsWith('==') || rest.startsWith('!=') || rest.startsWith('<=') || rest.startsWith('>=')) {
                tokens.push({ type: 'op', value: rest.slice(0, 2) });
                i += 2;
                continue;
            }
            if (rest[0] === '<' || rest[0] === '>') {
                tokens.push({ type: 'op', value: rest[0] });
                i += 1;
                continue;
            }
            if (rest.startsWith('&&') || rest.startsWith('||')) {
                tokens.push({ type: 'logic', value: rest.slice(0, 2) });
                i += 2;
                continue;
            }
            if (rest[0] === '(') {
                tokens.push({ type: 'lparen', value: '(' });
                i += 1;
                continue;
            }
            if (rest[0] === ')') {
                tokens.push({ type: 'rparen', value: ')' });
                i += 1;
                continue;
            }
            const notin = rest.match(/^not\s+in\b/);
            if (notin) {
                tokens.push({ type: 'notin', value: 'not in' });
                i += notin[0].length;
                continue;
            }
            if (rest.match(/^in\b/)) {
                tokens.push({ type: 'in', value: 'in' });
                i += 2;
                continue;
            }
            const value = rest.match(valueRe);
            if (value) {
                tokens.push({ type: 'value', value: unquote(value[1]) });
                i += value[0].length;
                continue;
            }
            return null;
        }
        return tokens;
    }
    function unquote(raw) {
        if ((raw.startsWith('"') && raw.endsWith('"')) || (raw.startsWith("'") && raw.endsWith("'"))) {
            return raw.slice(1, -1).replace(/\\(["'\\])/g, '$1');
        }
        return raw;
    }
    function isNumeric(value) {
        return /^-?\d+(\.\d+)?$/.test(value);
    }
    function looseEquals(a, b) {
        const av = Array.isArray(a) ? a.join(',') : a;
        if (isNumeric(av) && isNumeric(b)) {
            return Number(av) === Number(b);
        }
        return String(av) === String(b);
    }
    function evaluateExpression(condition, values) {
        const tokens = tokenize(condition);
        if (tokens === null) {
            return null;
        }
        let pos = 0;
        const peek = () => tokens[pos];
        const resolveField = (id) => {
            const v = values[id];
            return v === undefined ? '' : v;
        };
        const parseComparison = () => {
            const token = peek();
            if (!token) {
                throw new Error('eof');
            }
            if (token.type === 'field') {
                pos++;
                const op = peek();
                if (op?.type !== 'op') {
                    throw new Error('op expected');
                }
                pos++;
                const val = peek();
                if (val?.type !== 'value') {
                    throw new Error('value expected');
                }
                pos++;
                const left = resolveField(token.value);
                const right = val.value;
                switch (op.value) {
                    case '==': return looseEquals(left, right);
                    case '!=': return !looseEquals(left, right);
                    case '<': return Number(Array.isArray(left) ? NaN : left) < Number(right);
                    case '<=': return Number(Array.isArray(left) ? NaN : left) <= Number(right);
                    case '>': return Number(Array.isArray(left) ? NaN : left) > Number(right);
                    case '>=': return Number(Array.isArray(left) ? NaN : left) >= Number(right);
                    default: throw new Error('op');
                }
            }
            if (token.type === 'value') {
                pos++;
                const op = peek();
                if (op?.type !== 'in' && op?.type !== 'notin') {
                    throw new Error('in expected');
                }
                pos++;
                const field = peek();
                if (field?.type !== 'field') {
                    throw new Error('field expected');
                }
                pos++;
                const haystack = resolveField(field.value);
                const member = Array.isArray(haystack) && haystack.map(String).includes(String(token.value));
                return op.type === 'notin' ? !member : member;
            }
            throw new Error('unexpected');
        };
        const parsePrimary = () => {
            if (peek()?.type === 'lparen') {
                pos++;
                const inner = parseOr();
                if (peek()?.type !== 'rparen') {
                    throw new Error('rparen expected');
                }
                pos++;
                return inner;
            }
            return parseComparison();
        };
        const parseAnd = () => {
            let result = parsePrimary();
            while (peek()?.type === 'logic' && peek()?.value === '&&') {
                pos++;
                const next = parsePrimary();
                result = result && next;
            }
            return result;
        };
        function parseOr() {
            let result = parseAnd();
            while (peek()?.type === 'logic' && peek()?.value === '||') {
                pos++;
                const next = parseAnd();
                result = result || next;
            }
            return result;
        }
        try {
            if (tokens.length === 0) {
                return true;
            }
            const result = parseOr();
            if (pos !== tokens.length) {
                return null;
            }
            return result;
        }
        catch {
            return null;
        }
    }
    // --- DOM glue -----------------------------------------------------------
    function extractIdentifier(name) {
        const segments = Array.from(name.matchAll(/\[([^\]]*)\]/g)).map((m) => m[1]).filter((s) => s !== '');
        return segments.length > 0 ? segments[segments.length - 1] : null;
    }
    function collectValues(form) {
        const values = {};
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
            const id = extractIdentifier(el.name);
            if (!id) {
                return;
            }
            if (el instanceof HTMLInputElement && el.type === 'checkbox') {
                const current = Array.isArray(values[id]) ? values[id] : [];
                if (el.checked) {
                    current.push(el.value);
                }
                values[id] = current;
            }
            else if (el instanceof HTMLInputElement && el.type === 'radio') {
                if (el.checked) {
                    values[id] = el.value;
                }
                else if (!(id in values)) {
                    values[id] = '';
                }
            }
            else if (el instanceof HTMLSelectElement && el.multiple) {
                values[id] = Array.from(el.selectedOptions).map((o) => o.value);
            }
            else {
                values[id] = el.value;
            }
        });
        return values;
    }
    function applyElement(container, rules, values) {
        const inputs = Array.from(container.querySelectorAll('input, select, textarea'));
        const hasEnabledRule = rules.some((r) => Object.prototype.hasOwnProperty.call(r, 'enabled'));
        const hasRequiredRule = rules.some((r) => r.required === true);
        let effEnabled;
        let effRequired = false;
        rules.forEach((rule) => {
            if (evaluateExpression(rule.condition, values) === true) {
                if (Object.prototype.hasOwnProperty.call(rule, 'enabled')) {
                    effEnabled = rule.enabled;
                }
                if (rule.required === true) {
                    effRequired = true;
                }
            }
        });
        const hidden = hasEnabledRule && effEnabled === false;
        if (hasEnabledRule) {
            container.style.display = hidden ? 'none' : '';
            container.toggleAttribute('hidden', hidden);
            inputs.forEach((input) => { input.disabled = hidden; });
        }
        if (hasRequiredRule) {
            inputs.forEach((input) => {
                if (input instanceof HTMLInputElement && input.type === 'hidden') {
                    return;
                }
                input.required = !hidden && effRequired;
            });
        }
        else if (hidden) {
            // a hidden field must never block native submit validation
            inputs.forEach((input) => { input.required = false; });
        }
    }
    function setupForm(form, elements) {
        const apply = () => {
            const values = collectValues(form);
            Object.keys(elements).forEach((identifier) => {
                const container = form.querySelector('[data-form-element="' + (window.CSS && CSS.escape ? CSS.escape(identifier) : identifier) + '"]');
                if (container) {
                    applyElement(container, elements[identifier], values);
                }
            });
        };
        form.addEventListener('change', apply);
        form.addEventListener('input', apply);
        apply();
    }
    const ISLAND_SELECTOR = 'script[type="application/json"][data-form-conditions]:not([data-form-conditions-bound])';
    /**
     * Binds every island that is not bound yet. Islands are stamped so a re-scan
     * (see observe()) never attaches a second pair of change/input listeners to
     * the same form.
     */
    function scan(root = document) {
        root.querySelectorAll(ISLAND_SELECTOR).forEach((island) => {
            island.setAttribute('data-form-conditions-bound', '1');
            const form = island.closest('form');
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            let data;
            try {
                data = JSON.parse(island.textContent || '{}');
            }
            catch {
                return;
            }
            if (data.elements && typeof data.elements === 'object') {
                setupForm(form, data.elements);
            }
        });
    }
    /**
     * A form may reach the DOM long after DOMContentLoaded - loaded over XHR,
     * or re-rendered in place after an AJAX submit. Without this the conditions
     * of such a form would never be applied and every consumer would have to
     * re-trigger us by hand. MutationObserver callbacks run before the next
     * paint, so an injected form is already in its correct state when it is
     * first shown - no flash of the fields that a condition hides.
     */
    function observe() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }
        new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of Array.from(mutation.addedNodes)) {
                    if (!(node instanceof Element)) {
                        continue;
                    }
                    if (node.matches(ISLAND_SELECTOR) || node.querySelector(ISLAND_SELECTOR) !== null) {
                        scan();
                        return;
                    }
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
    function init() {
        scan();
        observe();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    }
    else {
        init();
    }
})();
