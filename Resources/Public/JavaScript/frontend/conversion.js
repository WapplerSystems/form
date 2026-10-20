/**
 * Announces a successful form submission as "form:conversion" DOM event.
 *
 * Two sources, one event (see Classes/Service/ConversionTagResolver.php):
 *
 *  - The ConfirmationFinisher renders data-form-conversion="<tag>" on its
 *    output. That output only exists after a successful submission.
 *  - The RedirectFinisher appends ?form_conversion=<tag> to the target page.
 *    The parameter is removed from the address bar afterwards, so a reload, a
 *    bookmark or a shared link does not announce the submission again.
 *
 * Analytics code listens on document, e.g. for Matomo:
 *
 *   document.addEventListener('form:conversion', function (event) {
 *     window._paq = window._paq || [];
 *     window._paq.push(['trackEvent', 'Form', 'submitted', event.detail.tag]);
 *   });
 *
 * event.detail is {tag, source}, source being "confirmation" or "redirect".
 * Every announced tag is also recorded in window.formConversions, for a
 * listener registered only after this script ran.
 *
 * Load with defer: the script then runs after the document is parsed, when
 * inline listeners anywhere on the page are already registered.
 */
(function () {
  'use strict';

  // Keep in sync with ConversionTagResolver::PATTERN.
  var tagPattern = /^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/;
  var queryParameter = 'form_conversion';

  function announce(tag, source) {
    if (typeof tag !== 'string' || !tagPattern.test(tag)) {
      return;
    }
    var detail = { tag: tag, source: source };
    (window.formConversions = window.formConversions || []).push(detail);
    document.dispatchEvent(new CustomEvent('form:conversion', { detail: detail }));
  }

  var elements = document.querySelectorAll('[data-form-conversion]');
  for (var i = 0; i < elements.length; i++) {
    announce(elements[i].getAttribute('data-form-conversion'), 'confirmation');
  }

  var url = new URL(window.location.href);
  if (url.searchParams.has(queryParameter)) {
    announce(url.searchParams.get(queryParameter), 'redirect');
    url.searchParams.delete(queryParameter);
    window.history.replaceState(window.history.state, '', url.toString());
  }
})();
