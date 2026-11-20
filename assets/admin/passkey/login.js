import { isCancellation, isWebauthnSupported, postJson, showError } from './webauthn';

const CONTAINER = '[data-skrull-passkey-login]';

async function loginWithPasskey(container, button) {
    const error = container.querySelector('[data-skrull-passkey-login-error]');

    button.disabled = true;
    showError(error, '');

    try {
        const optionsJson = await postJson(container.dataset.optionsUrl, {});
        const publicKey = PublicKeyCredential.parseRequestOptionsFromJSON(optionsJson);
        const credential = await navigator.credentials.get({ publicKey });
        const result = await postJson(container.dataset.resultUrl, credential.toJSON());

        window.location.assign(result.redirect);
    } catch (exception) {
        showError(error, isCancellation(exception) ? container.dataset.messageCancelled : container.dataset.messageFailed);
        button.disabled = false;
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-skrull-passkey-login-button]');
    if (button === null) {
        return;
    }

    event.preventDefault();
    loginWithPasskey(button.closest(CONTAINER), button);
});

document.addEventListener('DOMContentLoaded', () => {
    if (!isWebauthnSupported()) {
        document.querySelectorAll(CONTAINER).forEach((container) => container.classList.add('d-none'));
    }
});
