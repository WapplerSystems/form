/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */
/**
 * Visual condition builder (Feature 5) for the variants editor.
 *
 * Lets editors "click together" a TYPO3 ExpressionLanguage condition — pick a
 * field, an operator and a value, and nest rules into AND/OR groups — instead of
 * typing the expression by hand. Serializes the rule tree to an expression like
 *   traverse(formValues, "isCustomer") == "yes" && (traverse(formValues, "x") <= 5 || ...)
 * and parses such expressions back into the tree on open. Expressions that the
 * (deliberately small) parser does not understand open in a raw textarea fallback,
 * so nothing is ever lost.
 *
 * Pure editor JS — the generated expression is evaluated unchanged by the
 * server-side condition resolver.
 */
import Modal from '@typo3/backend/modal.js';
/**
 * WapplerSystems fork: resolve a localized editor label delivered server-side via
 * TYPO3.settings.FormEditor.labels (Database.xlf / de.Database.xlf). Falls back to
 * the English literal when the label bag or key is unavailable.
 */
function lll(key, fallback) {
    const labels = TYPO3?.settings?.FormEditor?.labels;
    const value = labels && labels[key];
    return typeof value === 'string' && value.length > 0 ? value : fallback;
}
const OPERATORS = [
    { value: '==', labelKey: 'conditionBuilder.op.eq', fallback: 'equals' },
    { value: '!=', labelKey: 'conditionBuilder.op.neq', fallback: 'not equals' },
    { value: '<', labelKey: 'conditionBuilder.op.lt', fallback: 'less than' },
    { value: '<=', labelKey: 'conditionBuilder.op.lte', fallback: 'less than or equal' },
    { value: '>', labelKey: 'conditionBuilder.op.gt', fallback: 'greater than' },
    { value: '>=', labelKey: 'conditionBuilder.op.gte', fallback: 'greater than or equal' },
    { value: 'in', labelKey: 'conditionBuilder.op.in', fallback: 'is one of' },
    { value: 'not in', labelKey: 'conditionBuilder.op.notIn', fallback: 'is not one of' },
];
function isRule(node) {
    return node.field !== undefined;
}
// --- Serialization (tree -> expression) -----------------------------------
function serializeValue(value) {
    if (/^-?\d+(\.\d+)?$/.test(value)) {
        return value;
    }
    if (value === 'true' || value === 'false') {
        return value;
    }
    return '"' + value.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
}
function serializeRule(rule) {
    const field = 'traverse(formValues, "' + rule.field + '")';
    if (rule.operator === 'in' || rule.operator === 'not in') {
        return serializeValue(rule.value) + ' ' + rule.operator + ' ' + field;
    }
    return field + ' ' + rule.operator + ' ' + serializeValue(rule.value);
}
function serializeNode(node) {
    if (isRule(node)) {
        return serializeRule(node);
    }
    const parts = node.children
        .filter((child) => isRule(child) || child.children.length > 0)
        .map((child) => {
        const serialized = serializeNode(child);
        // wrap nested non-trivial groups in parentheses
        if (!isRule(child) && child.children.length > 1) {
            return '(' + serialized + ')';
        }
        return serialized;
    });
    return parts.join(' ' + node.combinator + ' ');
}
function tokenize(input) {
    const tokens = [];
    let i = 0;
    const len = input.length;
    const fieldRe = /^traverse\s*\(\s*formValues\s*,\s*("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*')\s*\)/;
    const valueRe = /^("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|-?\d+(?:\.\d+)?|true|false)/;
    while (i < len) {
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
        const inMatch = rest.match(/^in\b/);
        if (inMatch) {
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
        // Unknown token -> not parseable by this small parser.
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
class Parser {
    constructor(tokens) {
        this.tokens = tokens;
        this.pos = 0;
    }
    parse() {
        if (this.tokens.length === 0) {
            return { combinator: '&&', children: [] };
        }
        const node = this.parseOr();
        if (node === null || this.pos !== this.tokens.length) {
            return null;
        }
        return isRule(node) ? { combinator: '&&', children: [node] } : node;
    }
    peek() {
        return this.tokens[this.pos];
    }
    parseOr() {
        const first = this.parseAnd();
        if (first === null) {
            return null;
        }
        const children = [first];
        while (this.peek()?.type === 'logic' && this.peek()?.value === '||') {
            this.pos++;
            const next = this.parseAnd();
            if (next === null) {
                return null;
            }
            children.push(next);
        }
        if (children.length === 1) {
            return first;
        }
        return { combinator: '||', children };
    }
    parseAnd() {
        const first = this.parsePrimary();
        if (first === null) {
            return null;
        }
        const children = [first];
        while (this.peek()?.type === 'logic' && this.peek()?.value === '&&') {
            this.pos++;
            const next = this.parsePrimary();
            if (next === null) {
                return null;
            }
            children.push(next);
        }
        if (children.length === 1) {
            return first;
        }
        return { combinator: '&&', children };
    }
    parsePrimary() {
        const token = this.peek();
        if (!token) {
            return null;
        }
        if (token.type === 'lparen') {
            this.pos++;
            const inner = this.parseOr();
            if (inner === null || this.peek()?.type !== 'rparen') {
                return null;
            }
            this.pos++;
            return isRule(inner) ? { combinator: '&&', children: [inner] } : inner;
        }
        return this.parseComparison();
    }
    parseComparison() {
        const token = this.peek();
        if (!token) {
            return null;
        }
        // field op value
        if (token.type === 'field') {
            const field = token.value;
            this.pos++;
            const op = this.peek();
            if (op?.type !== 'op') {
                return null;
            }
            this.pos++;
            const value = this.peek();
            if (value?.type !== 'value') {
                return null;
            }
            this.pos++;
            return { field, operator: op.value, value: value.value };
        }
        // value (in|not in) field
        if (token.type === 'value') {
            const value = token.value;
            this.pos++;
            const op = this.peek();
            if (op?.type !== 'in' && op?.type !== 'notin') {
                return null;
            }
            this.pos++;
            const field = this.peek();
            if (field?.type !== 'field') {
                return null;
            }
            this.pos++;
            return { field: field.value, operator: op.type === 'notin' ? 'not in' : 'in', value };
        }
        return null;
    }
}
function parseCondition(expression) {
    const trimmed = expression.trim();
    if (trimmed === '') {
        return { combinator: '&&', children: [] };
    }
    const tokens = tokenize(trimmed);
    if (tokens === null) {
        return null;
    }
    try {
        return new Parser(tokens).parse();
    }
    catch {
        return null;
    }
}
// --- Modal UI -------------------------------------------------------------
export function openConditionBuilderModal(options) {
    const { initialExpression, fields, onApply } = options;
    const parsed = parseCondition(initialExpression);
    const rawMode = parsed === null;
    const root = parsed ?? { combinator: '&&', children: [] };
    const content = document.createElement('div');
    content.className = 'form-editor-condition-builder';
    let rawTextarea = null;
    if (rawMode) {
        const hint = document.createElement('div');
        hint.className = 'alert alert-info';
        hint.append(document.createTextNode(lll('conditionBuilder.unparsed', 'This condition could not be parsed into the visual builder and is shown as raw expression. Edit it directly below.')));
        rawTextarea = document.createElement('textarea');
        rawTextarea.className = 'form-control';
        rawTextarea.rows = 4;
        rawTextarea.value = initialExpression;
        content.append(hint, rawTextarea);
    }
    else {
        const tree = document.createElement('div');
        const preview = document.createElement('pre');
        preview.className = 'form-text text-body-secondary';
        preview.style.marginTop = '1em';
        preview.style.whiteSpace = 'pre-wrap';
        const updatePreview = () => {
            preview.textContent = serializeNode(root) || lll('conditionBuilder.alwaysTrue', '(always true — no rules)');
        };
        const rerender = () => {
            tree.innerHTML = '';
            tree.append(renderGroup(root, root, rerender, fields));
            updatePreview();
        };
        rerender();
        // keep the preview in sync while typing values
        tree.addEventListener('keyup', updatePreview);
        tree.addEventListener('change', updatePreview);
        content.append(tree, preview);
    }
    Modal.advanced({
        type: Modal.types.default,
        title: lll('conditionBuilder.title', 'Condition builder'),
        content: content,
        size: Modal.sizes.large,
        buttons: [
            {
                text: lll('conditionBuilder.cancel', 'Cancel'),
                btnClass: 'btn-default',
                trigger: (_e, modal) => modal.hideModal(),
            },
            {
                text: lll('conditionBuilder.apply', 'Apply'),
                btnClass: 'btn-primary',
                trigger: (_e, modal) => {
                    const expression = rawMode && rawTextarea ? rawTextarea.value : serializeNode(root);
                    onApply(expression);
                    modal.hideModal();
                },
            },
        ],
    });
}
function renderGroup(group, root, rerender, fields) {
    const box = document.createElement('div');
    box.className = 'panel panel-default';
    box.style.border = '1px solid var(--typo3-component-border-color, #ccc)';
    box.style.borderRadius = '4px';
    box.style.padding = '0.75em';
    box.style.marginBottom = '0.5em';
    // header: combinator toggle + remove (only for non-root groups)
    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.alignItems = 'center';
    header.style.gap = '0.5em';
    header.style.marginBottom = '0.5em';
    const combinatorSelect = document.createElement('select');
    combinatorSelect.className = 'form-select form-select-sm';
    combinatorSelect.style.width = 'auto';
    [['&&', lll('conditionBuilder.and', 'AND — all must match')], ['||', lll('conditionBuilder.or', 'OR — any may match')]].forEach(([value, label]) => {
        combinatorSelect.append(new Option(label, value, false, group.combinator === value));
    });
    combinatorSelect.addEventListener('change', function () {
        group.combinator = this.value;
        rerender();
    });
    header.append(combinatorSelect);
    if (group !== root) {
        const removeGroup = document.createElement('button');
        removeGroup.type = 'button';
        removeGroup.className = 'btn btn-default btn-sm';
        removeGroup.append(document.createTextNode(lll('conditionBuilder.removeGroup', 'Remove group')));
        removeGroup.addEventListener('click', () => {
            removeNode(root, group);
            rerender();
        });
        header.append(removeGroup);
    }
    box.append(header);
    // children
    group.children.forEach((child) => {
        if (isRule(child)) {
            box.append(renderRule(child, group, root, rerender, fields));
        }
        else {
            box.append(renderGroup(child, root, rerender, fields));
        }
    });
    // footer: add rule / add group
    const footer = document.createElement('div');
    footer.style.display = 'flex';
    footer.style.gap = '0.5em';
    footer.style.marginTop = '0.25em';
    const addRule = document.createElement('button');
    addRule.type = 'button';
    addRule.className = 'btn btn-default btn-sm';
    addRule.append(document.createTextNode(lll('conditionBuilder.addRule', '+ Add rule')));
    addRule.addEventListener('click', () => {
        group.children.push({ field: fields[0]?.identifier ?? '', operator: '==', value: '' });
        rerender();
    });
    const addGroup = document.createElement('button');
    addGroup.type = 'button';
    addGroup.className = 'btn btn-default btn-sm';
    addGroup.append(document.createTextNode(lll('conditionBuilder.addGroup', '+ Add group')));
    addGroup.addEventListener('click', () => {
        group.children.push({ combinator: '&&', children: [] });
        rerender();
    });
    footer.append(addRule, addGroup);
    box.append(footer);
    return box;
}
function renderRule(rule, parent, root, rerender, fields) {
    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '0.5em';
    row.style.alignItems = 'center';
    row.style.marginBottom = '0.35em';
    // field select
    const fieldSelect = document.createElement('select');
    fieldSelect.className = 'form-select form-select-sm';
    if (fields.length === 0 || !fields.some((f) => f.identifier === rule.field)) {
        // keep an unknown/free identifier selectable
        fieldSelect.append(new Option(rule.field || lll('conditionBuilder.fieldPlaceholder', '— field —'), rule.field, true, true));
    }
    fields.forEach((f) => {
        fieldSelect.append(new Option(f.label + ' (' + f.identifier + ')', f.identifier, false, f.identifier === rule.field));
    });
    // operator select
    const operatorSelect = document.createElement('select');
    operatorSelect.className = 'form-select form-select-sm';
    operatorSelect.style.width = 'auto';
    OPERATORS.forEach((op) => {
        operatorSelect.append(new Option(lll(op.labelKey, op.fallback), op.value, false, op.value === rule.operator));
    });
    // value control — a <select> of options when the field has them, else text input
    const valueWrapper = document.createElement('span');
    valueWrapper.style.flex = '1';
    const buildValueControl = () => {
        valueWrapper.innerHTML = '';
        const fieldDef = fields.find((f) => f.identifier === rule.field);
        if (fieldDef?.options && fieldDef.options.length > 0) {
            const valueSelect = document.createElement('select');
            valueSelect.className = 'form-select form-select-sm';
            if (!fieldDef.options.some((o) => o.value === rule.value)) {
                valueSelect.append(new Option(rule.value === '' ? lll('conditionBuilder.valuePlaceholder', '— value —') : rule.value, rule.value, true, true));
            }
            fieldDef.options.forEach((o) => {
                valueSelect.append(new Option(o.label + ' (' + o.value + ')', o.value, false, o.value === rule.value));
            });
            valueSelect.addEventListener('change', function () {
                rule.value = this.value;
            });
            valueWrapper.append(valueSelect);
        }
        else {
            const valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.className = 'form-control form-control-sm';
            valueInput.value = rule.value;
            valueInput.placeholder = lll('conditionBuilder.value', 'value');
            valueInput.addEventListener('keyup', function () {
                rule.value = this.value;
            });
            valueInput.addEventListener('change', function () {
                rule.value = this.value;
            });
            valueWrapper.append(valueInput);
        }
    };
    fieldSelect.addEventListener('change', function () {
        rule.field = this.value;
        rule.value = '';
        buildValueControl();
    });
    operatorSelect.addEventListener('change', function () {
        rule.operator = this.value;
    });
    const removeRule = document.createElement('button');
    removeRule.type = 'button';
    removeRule.className = 'btn btn-default btn-sm';
    removeRule.append(document.createTextNode('×'));
    removeRule.title = lll('conditionBuilder.removeRule', 'Remove rule');
    removeRule.addEventListener('click', () => {
        const index = parent.children.indexOf(rule);
        if (index > -1) {
            parent.children.splice(index, 1);
        }
        rerender();
    });
    buildValueControl();
    row.append(fieldSelect, operatorSelect, valueWrapper, removeRule);
    return row;
}
function removeNode(root, target) {
    const visit = (group) => {
        const index = group.children.indexOf(target);
        if (index > -1) {
            group.children.splice(index, 1);
            return;
        }
        group.children.forEach((child) => {
            if (!isRule(child)) {
                visit(child);
            }
        });
    };
    visit(root);
}
