/**
 * Client side of the form framework's JavaScript spam shield.
 *
 * Markup contract (emitted per form by InjectFormChallenge, both parts inside
 * the <form> so several forms on one page stay independent):
 *
 *   <script type="application/json" data-form-challenge="1">
 *     {"challenge":"…","method":"rot13reverse","delay":3000,
 *      "fields":{"response":"tx_form_challenge_response","time":"tx_form_fill_time"}}
 *   </script>
 *   <input type="hidden" name="tx_form_challenge_response" value="" autocomplete="off" />
 *   <input type="hidden" name="tx_form_fill_time" value="" autocomplete="off" />
 *
 * Two jobs, either of which may be absent depending on what the form switched on:
 *
 *  1. Challenge/response — reverse the obfuscation of `challenge` and write the
 *     result into the response field, but not before `delay` has passed. The
 *     server verifies the token's signature (FormChallengeService), so a client
 *     that never got here submits nothing usable.
 *
 *  2. Fill-time measurement — keep the time field up to date with the
 *     milliseconds spent on this step, for MinimumFillTimeValidator.
 *
 * The obfuscation transforms must stay identical to the PHP ones in
 * Classes/Security/FormChallengeService.php. They are not a secret and are not
 * meant to be: this module ships to every visitor. Their only job is to make a
 * bot that copies values out of the markup into the form submit something that
 * fails the signature check.
 */
(function () {
    'use strict';

    function now() {
        // performance.now() is monotonic, so a clock change mid-fill cannot
        // fabricate (or destroy) elapsed time. Date.now() is the fallback.
        return (window.performance && typeof window.performance.now === 'function')
            ? window.performance.now()
            : Date.now();
    }

    function rot13(value) {
        return value.replace(/[A-Za-z]/g, function (character) {
            const base = character <= 'Z' ? 65 : 97;
            return String.fromCharCode(((character.charCodeAt(0) - base + 13) % 26) + base);
        });
    }

    function reverse(value) {
        return value.split('').reverse().join('');
    }

    /**
     * Inverse of FormChallengeService::obfuscate(). An unknown method name is
     * treated as the default there, so it is treated as the default here too.
     */
    function deobfuscate(challenge, method) {
        switch (method) {
            case 'rot13':
                return rot13(challenge);
            case 'reverse':
                return reverse(challenge);
            case 'base64':
                try {
                    return window.atob(challenge);
                } catch (e) {
                    return '';
                }
            case 'none':
                return challenge;
            case 'rot13reverse':
            default:
                return rot13(reverse(challenge));
        }
    }

    function findField(form, name) {
        if (!name) {
            return null;
        }
        const field = form.querySelector('input[type="hidden"][name="' + name + '"]');
        return field instanceof HTMLInputElement ? field : null;
    }

    function setupForm(form, data) {
        const fields = data.fields || {};
        const responseField = findField(form, fields.response);
        const timeField = findField(form, fields.time);
        if (responseField === null && timeField === null) {
            return;
        }

        const startedAt = now();
        const elapsed = function () {
            return Math.max(0, Math.round(now() - startedAt));
        };

        const writeTime = function () {
            if (timeField !== null) {
                timeField.value = String(elapsed());
            }
        };

        const delay = typeof data.delay === 'number' && data.delay >= 0 ? data.delay : 0;
        const solve = function () {
            if (responseField !== null && responseField.value === '' && typeof data.challenge === 'string') {
                responseField.value = deobfuscate(data.challenge, data.method);
            }
        };

        if (responseField !== null && typeof data.challenge === 'string') {
            window.setTimeout(solve, delay);
        }

        form.addEventListener('submit', function () {
            // A background tab throttles timers, so the scheduled solve() may
            // still be pending on a form the visitor left open and came back to.
            // Filling in late is fine as long as the delay itself has really
            // passed — that is the property the server relies on, not the timer.
            if (elapsed() >= delay) {
                solve();
            }
            writeTime();
        });

        // form.submit() bypasses the submit event entirely, so keep the
        // measurement current from interaction as well. A submission with no
        // interaction at all leaves the field empty, which is exactly what
        // MinimumFillTimeValidator's requireTimingData option is about.
        form.addEventListener('input', writeTime, { passive: true });
        form.addEventListener('change', writeTime, { passive: true });
        form.addEventListener('click', writeTime, { passive: true });
    }

    function init() {
        document.querySelectorAll('script[type="application/json"][data-form-challenge]').forEach(function (island) {
            const form = island.closest('form');
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            let data;
            try {
                data = JSON.parse(island.textContent || '{}');
            } catch (e) {
                return;
            }
            if (data && typeof data === 'object') {
                setupForm(form, data);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
