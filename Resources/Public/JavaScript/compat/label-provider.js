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

/**
 * release/v13 only: stand-in for v14's virtual `~labels/<domain>` modules.
 *
 * The backend scripts of this fork are shared with release/v14 and import
 * their labels as `~labels/<domain>`. TYPO3 13.4 cannot resolve those, so
 * Configuration/JavaScriptModules.php maps each used domain to a shim in
 * ./labels/, which loads the domain from the `form_javascript_labels` AJAX
 * route and exports a provider with the same surface as v14's LabelProvider:
 * get(key, args) and render(key, args).
 *
 * Arguments: an array fills %s/%d/%f, an object fills ICU-style placeholders.
 * Supported ICU subset: {name}, {name, number}, {name, plural, ...} with `#`
 * and `=n` cases, {name, select, ...}. That covers every label this fork uses;
 * v14 itself delegates to intl-messageformat, which 13.4 does not ship.
 */

const PREFIX = '[form]';

class LabelProvider {
  constructor(labels, domain) {
    this.labels = labels;
    this.domain = domain;
  }

  get(key, args) {
    const rendered = this.render(key, args);
    return Array.isArray(rendered) ? rendered.join('') : rendered;
  }

  render(key, args) {
    if (!Object.prototype.hasOwnProperty.call(this.labels, key)) {
      // v14 throws here; a missing label must not take the editor down on 13.4.
      console.warn(`${PREFIX} Label "${String(key)}" is not defined in domain "${this.domain}".`);
      return String(key);
    }
    const label = this.labels[key];
    if (args === undefined) {
      return label;
    }
    if (Array.isArray(args)) {
      return sprintf(label, args);
    }
    return formatMessage(label, args, locale());
  }
}

function sprintf(label, args) {
  let index = 0;
  return label.replace(/%[sdf]/g, (token) => {
    const value = args[index++];
    switch (token) {
      case '%s': return String(value);
      case '%d': return String(typeof value === 'number' ? value : parseInt(String(value), 10));
      case '%f': return String(typeof value === 'number' ? value : parseFloat(value).toFixed(2));
      default: return token;
    }
  });
}

function locale() {
  const lang = document.documentElement.lang || 'en';
  return lang === 'ch' ? 'zh' : lang;
}

/**
 * Minimal ICU MessageFormat: walks the message once, resolving `{...}`
 * arguments recursively. Quoting with apostrophes is not supported.
 */
function formatMessage(message, values, lang, pluralValue = null) {
  let out = '';
  let i = 0;
  while (i < message.length) {
    const char = message[i];
    if (char === '#' && pluralValue !== null) {
      out += new Intl.NumberFormat(lang).format(pluralValue);
      i++;
      continue;
    }
    if (char !== '{') {
      out += char;
      i++;
      continue;
    }
    const end = matchingBrace(message, i);
    if (end === -1) {
      out += message.slice(i);
      break;
    }
    out += formatArgument(message.slice(i + 1, end), values, lang);
    i = end + 1;
  }
  return out;
}

function formatArgument(body, values, lang) {
  const firstComma = body.indexOf(',');
  const name = (firstComma === -1 ? body : body.slice(0, firstComma)).trim();
  const value = values?.[name];
  if (firstComma === -1) {
    return value === undefined ? `{${body}}` : String(value);
  }
  const rest = body.slice(firstComma + 1);
  const secondComma = rest.indexOf(',');
  const type = (secondComma === -1 ? rest : rest.slice(0, secondComma)).trim();
  const options = secondComma === -1 ? '' : rest.slice(secondComma + 1);

  if (type === 'number') {
    return new Intl.NumberFormat(lang).format(Number(value));
  }
  if (type === 'plural' || type === 'select') {
    const cases = parseCases(options);
    let selected;
    if (type === 'plural') {
      const number = Number(value);
      selected = cases[`=${number}`] ?? cases[new Intl.PluralRules(lang).select(number)] ?? cases.other;
      return selected === undefined ? '' : formatMessage(selected, values, lang, number);
    }
    selected = cases[String(value)] ?? cases.other;
    return selected === undefined ? '' : formatMessage(selected, values, lang);
  }
  return value === undefined ? '' : String(value);
}

function parseCases(options) {
  const cases = {};
  let i = 0;
  while (i < options.length) {
    const open = options.indexOf('{', i);
    if (open === -1) {
      break;
    }
    const selector = options.slice(i, open).trim();
    const close = matchingBrace(options, open);
    if (close === -1) {
      break;
    }
    // Skip an `offset:n` prefix; it is not used by any label here.
    cases[selector.replace(/^offset:\s*\d+\s*/, '')] = options.slice(open + 1, close);
    i = close + 1;
  }
  return cases;
}

function matchingBrace(text, open) {
  let depth = 0;
  for (let i = open; i < text.length; i++) {
    if (text[i] === '{') {
      depth++;
    } else if (text[i] === '}') {
      depth--;
      if (depth === 0) {
        return i;
      }
    }
  }
  return -1;
}

function ajaxUrl() {
  const fromWindow = (win) => {
    try {
      return win?.TYPO3?.settings?.ajaxUrls?.form_javascript_labels ?? null;
    } catch {
      return null;
    }
  };
  return fromWindow(window) ?? fromWindow(window.top);
}

export async function loadLabels(domain) {
  const url = ajaxUrl();
  if (url === null) {
    console.error(`${PREFIX} AJAX route "form_javascript_labels" is not available; labels of "${domain}" fall back to their keys.`);
    return new LabelProvider({}, domain);
  }
  const target = new URL(url, window.location.href);
  target.searchParams.set('domain', domain);
  try {
    const response = await fetch(target, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return new LabelProvider(await response.json(), domain);
  } catch (error) {
    console.error(`${PREFIX} Could not load labels of "${domain}":`, error);
    return new LabelProvider({}, domain);
  }
}

export { LabelProvider, formatMessage };
