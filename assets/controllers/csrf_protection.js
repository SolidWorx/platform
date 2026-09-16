const nameCheck = /^[-_a-zA-Z0-9]{4,22}$/;
const tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/;

// Generate and double-submit a CSRF token in a form field and a cookie, as defined by Symfony's SameOriginCsrfTokenManager
document.addEventListener('submit', function (event) {
    generateCsrfToken(event.target);
}, true);

// When @hotwired/turbo handles form submissions, send the CSRF token in a header in addition to a cookie
// The `framework.csrf_protection.check_header` config option needs to be enabled for the header to be checked
document.addEventListener('turbo:submit-start', function (event) {
    const h = generateCsrfHeaders(event.detail.formSubmission.formElement);
    Object.keys(h).map(function (k) {
        event.detail.formSubmission.fetchRequest.headers[k] = h[k];
    });
});

// When @hotwired/turbo handles form submissions, remove the CSRF cookie once a form has been submitted
document.addEventListener('turbo:submit-end', function (event) {
    removeCsrfToken(event.detail.formSubmission.formElement);
});

// A live component never submits its form: the payload goes out by fetch, and the button that sends
// it is a plain `type="button"`. So none of the events above ever fire, the field keeps the token
// *id* Symfony rendered into it, and the matching cookie is never written — every submission then
// fails as an invalid CSRF token.
//
// Catching the click in the capture phase puts the token in place before the component reads the
// form: `generateCsrfToken` swaps the id for a real token, sets the cookie, and fires `change`,
// which is what the component listens to in order to pick the new value up.
//
// It is a no-op under session-based CSRF, where the rendered value is already a token rather than
// an id, so this is safe whichever mode an application configures.
document.addEventListener('click', function (event) {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const trigger = target.closest('[data-action*="live#action"]');

    if (!trigger) {
        return;
    }

    const form = trigger.closest('form')
        ?? trigger.closest('[data-controller~="live"]')?.querySelector('form');

    if (form) {
        generateCsrfToken(form);
    }
}, true);

export function generateCsrfToken (formElement) {
    const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');

    if (!csrfField) {
        return;
    }

    let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
    let csrfToken = csrfField.value;

    if (!csrfCookie && nameCheck.test(csrfToken)) {
        csrfField.setAttribute('data-csrf-protection-cookie-value', csrfCookie = csrfToken);
        csrfField.defaultValue = csrfToken = btoa(String.fromCharCode.apply(null, (window.crypto || window.msCrypto).getRandomValues(new Uint8Array(18))));
    }
    csrfField.dispatchEvent(new Event('change', { bubbles: true }));

    if (csrfCookie && tokenCheck.test(csrfToken)) {
        const cookie = csrfCookie + '_' + csrfToken + '=' + csrfCookie + '; path=/; samesite=strict';
        document.cookie = window.location.protocol === 'https:' ? '__Host-' + cookie + '; secure' : cookie;
    }
}

export function generateCsrfHeaders (formElement) {
    const headers = {};
    const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');

    if (!csrfField) {
        return headers;
    }

    const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');

    if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
        headers[csrfCookie] = csrfField.value;
    }

    return headers;
}

export function removeCsrfToken (formElement) {
    const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');

    if (!csrfField) {
        return;
    }

    const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');

    if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
        const cookie = csrfCookie + '_' + csrfField.value + '=0; path=/; samesite=strict; max-age=0';

        document.cookie = window.location.protocol === 'https:' ? '__Host-' + cookie + '; secure' : cookie;
    }
}

/* stimulusFetch: 'lazy' */
export default 'csrf-protection-controller';
