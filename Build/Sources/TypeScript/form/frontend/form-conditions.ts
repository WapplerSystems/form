/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

/**
 * Frontend live-conditions (Feature 6).
 *
 * Mirrors variant conditions client-side so fields on the SAME page react while
 * the user fills the controlling field — show/hide (renderingOptions.enabled) and
 * required toggling (NotEmpty validator). Reads the JSON island emitted by
 * InjectFrontendConditions, evaluates a small subset of the TYPO3
 * ExpressionLanguage in the browser and applies the effects. The server stays
 * authoritative on submit; this is purely UX. Conditions the small evaluator does
 * not understand are skipped (the server decides).
 *
 * Scope: frontend. Loaded as a classic script via AssetCollector (no ESM imports).
 */
interface WsVariantRule {
  condition: string;
  enabled?: boolean;
  required?: boolean;
}

(function (): void {
  'use strict';

  // --- expression evaluation (subset) -------------------------------------

  type FormValues = Record<string, string | string[]>;

  interface Token { type: string; value: string; }

  function tokenize(input: string): Token[] | null {
    const tokens: Token[] = [];
    let i = 0;
    const fieldRe = /^traverse\s*\(\s*formValues\s*,\s*("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*')\s*\)/;
    const valueRe = /^("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|-?\d+(?:\.\d+)?|true|false)/;
    while (i < input.length) {
      const rest = input.slice(i);
      const ws = rest.match(/^\s+/);
      if (ws) { i += ws[0].length; continue; }
      const field = rest.match(fieldRe);
      if (field) { tokens.push({ type: 'field', value: unquote(field[1]) }); i += field[0].length; continue; }
      if (rest.startsWith('==') || rest.startsWith('!=') || rest.startsWith('<=') || rest.startsWith('>=')) {
        tokens.push({ type: 'op', value: rest.slice(0, 2) }); i += 2; continue;
      }
      if (rest[0] === '<' || rest[0] === '>') { tokens.push({ type: 'op', value: rest[0] }); i += 1; continue; }
      if (rest.startsWith('&&') || rest.startsWith('||')) { tokens.push({ type: 'logic', value: rest.slice(0, 2) }); i += 2; continue; }
      if (rest[0] === '(') { tokens.push({ type: 'lparen', value: '(' }); i += 1; continue; }
      if (rest[0] === ')') { tokens.push({ type: 'rparen', value: ')' }); i += 1; continue; }
      const notin = rest.match(/^not\s+in\b/);
      if (notin) { tokens.push({ type: 'notin', value: 'not in' }); i += notin[0].length; continue; }
      if (rest.match(/^in\b/)) { tokens.push({ type: 'in', value: 'in' }); i += 2; continue; }
      const value = rest.match(valueRe);
      if (value) { tokens.push({ type: 'value', value: unquote(value[1]) }); i += value[0].length; continue; }
      return null;
    }
    return tokens;
  }

  function unquote(raw: string): string {
    if ((raw.startsWith('"') && raw.endsWith('"')) || (raw.startsWith("'") && raw.endsWith("'"))) {
      return raw.slice(1, -1).replace(/\\(["'\\])/g, '$1');
    }
    return raw;
  }

  function isNumeric(value: string): boolean {
    return /^-?\d+(\.\d+)?$/.test(value);
  }

  function looseEquals(a: string | string[], b: string): boolean {
    const av = Array.isArray(a) ? a.join(',') : a;
    if (isNumeric(av) && isNumeric(b)) {
      return Number(av) === Number(b);
    }
    return String(av) === String(b);
  }

  function evaluateExpression(condition: string, values: FormValues): boolean | null {
    const tokens = tokenize(condition);
    if (tokens === null) { return null; }
    let pos = 0;
    const peek = (): Token | undefined => tokens[pos];

    const resolveField = (id: string): string | string[] => {
      const v = values[id];
      return v === undefined ? '' : v;
    };

    const parseComparison = (): boolean => {
      const token = peek();
      if (!token) { throw new Error('eof'); }
      if (token.type === 'field') {
        pos++;
        const op = peek();
        if (op?.type !== 'op') { throw new Error('op expected'); }
        pos++;
        const val = peek();
        if (val?.type !== 'value') { throw new Error('value expected'); }
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
        if (op?.type !== 'in' && op?.type !== 'notin') { throw new Error('in expected'); }
        pos++;
        const field = peek();
        if (field?.type !== 'field') { throw new Error('field expected'); }
        pos++;
        const haystack = resolveField(field.value);
        const member = Array.isArray(haystack) && haystack.map(String).includes(String(token.value));
        return op.type === 'notin' ? !member : member;
      }
      throw new Error('unexpected');
    };

    const parsePrimary = (): boolean => {
      if (peek()?.type === 'lparen') {
        pos++;
        const inner = parseOr();
        if (peek()?.type !== 'rparen') { throw new Error('rparen expected'); }
        pos++;
        return inner;
      }
      return parseComparison();
    };

    const parseAnd = (): boolean => {
      let result = parsePrimary();
      while (peek()?.type === 'logic' && peek()?.value === '&&') {
        pos++;
        const next = parsePrimary();
        result = result && next;
      }
      return result;
    };

    function parseOr(): boolean {
      let result = parseAnd();
      while (peek()?.type === 'logic' && peek()?.value === '||') {
        pos++;
        const next = parseAnd();
        result = result || next;
      }
      return result;
    }

    try {
      if (tokens.length === 0) { return true; }
      const result = parseOr();
      if (pos !== tokens.length) { return null; }
      return result;
    } catch {
      return null;
    }
  }

  // --- DOM glue -----------------------------------------------------------

  function extractIdentifier(name: string): string | null {
    const segments = Array.from(name.matchAll(/\[([^\]]*)\]/g)).map((m) => m[1]).filter((s) => s !== '');
    return segments.length > 0 ? segments[segments.length - 1] : null;
  }

  function collectValues(form: HTMLFormElement): FormValues {
    const values: FormValues = {};
    form.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input[name], select[name], textarea[name]').forEach((el) => {
      const id = extractIdentifier(el.name);
      if (!id) { return; }
      if (el instanceof HTMLInputElement && el.type === 'checkbox') {
        const current = Array.isArray(values[id]) ? values[id] as string[] : [];
        if (el.checked) { current.push(el.value); }
        values[id] = current;
      } else if (el instanceof HTMLInputElement && el.type === 'radio') {
        if (el.checked) { values[id] = el.value; } else if (!(id in values)) { values[id] = ''; }
      } else if (el instanceof HTMLSelectElement && el.multiple) {
        values[id] = Array.from(el.selectedOptions).map((o) => o.value);
      } else {
        values[id] = el.value;
      }
    });
    return values;
  }

  function applyElement(container: HTMLElement, rules: WsVariantRule[], values: FormValues): void {
    const inputs = Array.from(container.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea'));
    const hasEnabledRule = rules.some((r) => Object.prototype.hasOwnProperty.call(r, 'enabled'));
    const hasRequiredRule = rules.some((r) => r.required === true);

    let effEnabled: boolean | undefined;
    let effRequired = false;
    rules.forEach((rule) => {
      if (evaluateExpression(rule.condition, values) === true) {
        if (Object.prototype.hasOwnProperty.call(rule, 'enabled')) { effEnabled = rule.enabled; }
        if (rule.required === true) { effRequired = true; }
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
        if (input instanceof HTMLInputElement && input.type === 'hidden') { return; }
        input.required = !hidden && effRequired;
      });
    } else if (hidden) {
      // a hidden field must never block native submit validation
      inputs.forEach((input) => { input.required = false; });
    }
  }

  function setupForm(form: HTMLFormElement, elements: Record<string, WsVariantRule[]>): void {
    const apply = (): void => {
      const values = collectValues(form);
      Object.keys(elements).forEach((identifier) => {
        const container = form.querySelector<HTMLElement>('[data-form-element="' + (window.CSS && CSS.escape ? CSS.escape(identifier) : identifier) + '"]');
        if (container) {
          applyElement(container, elements[identifier], values);
        }
      });
    };
    form.addEventListener('change', apply);
    form.addEventListener('input', apply);
    apply();
  }

  function init(): void {
    document.querySelectorAll<HTMLScriptElement>('script[type="application/json"][data-form-conditions]').forEach((island) => {
      const form = island.closest('form');
      if (!(form instanceof HTMLFormElement)) { return; }
      let data: { elements?: Record<string, WsVariantRule[]> };
      try {
        data = JSON.parse(island.textContent || '{}');
      } catch {
        return;
      }
      if (data.elements && typeof data.elements === 'object') {
        setupForm(form, data.elements);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
