import { isCancellation, isWebauthnSupported, postJson, showError } from './webauthn';

const CONTAINER = '[data-skrull-passkey]';

function errorElement(container) {
    return container.querySelector('[data-skrull-passkey-error]');
}

function errorMessageFor(container, error) {
    if (isCancellation(error)) {
        return container.dataset.messageCancelled;
    }
    if (error.name === 'InvalidStateError') {
        return container.dataset.messageAlreadyRegistered;
    }

    return `${container.dataset.messageGeneric} ${error.message}`;
}

async function addPasskey(container, button) {
    const nameInput = container.querySelector('[data-skrull-passkey-name]');

    button.disabled = true;
    showError(errorElement(container), '');

    try {
        const optionsJson = await postJson(container.dataset.optionsUrl, {});
        const publicKey = PublicKeyCredential.parseCreationOptionsFromJSON(optionsJson);
        const credential = await navigator.credentials.create({ publicKey });
        await postJson(container.dataset.resultUrl, { ...credential.toJSON(), name: nameInput.value });

        window.location.reload();
    } catch (error) {
        showError(errorElement(container), errorMessageFor(container, error));
        button.disabled = false;
    }
}

async function deletePasskey(container, button) {
    if (!window.confirm(button.dataset.confirm)) {
        return;
    }

    button.disabled = true;
    showError(errorElement(container), '');

    try {
        const body = new FormData();
        body.append('_csrf_token', button.dataset.csrfToken);

        const response = await fetch(button.dataset.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body,
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        window.location.reload();
    } catch (error) {
        showError(errorElement(container), `${container.dataset.messageGeneric} ${error.message}`);
        button.disabled = false;
    }
}

document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-skrull-passkey-add]');
    const deleteButton = event.target.closest('[data-skrull-passkey-delete]');
    const container = event.target.closest(CONTAINER);

    if (container === null || (addButton === null && deleteButton === null)) {
        return;
    }

    event.preventDefault();

    if (addButton !== null) {
        addPasskey(container, addButton);
    } else {
        deletePasskey(container, deleteButton);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || !event.target.matches(`${CONTAINER} [data-skrull-passkey-name]`)) {
        return;
    }

    event.preventDefault();
    event.target.closest(CONTAINER).querySelector('[data-skrull-passkey-add]').click();
});

document.addEventListener('DOMContentLoaded', () => {
    if (isWebauthnSupported()) {
        return;
    }

    document.querySelectorAll(CONTAINER).forEach((container) => {
        container.querySelector('[data-skrull-passkey-add]').disabled = true;
        showError(errorElement(container), container.dataset.messageUnsupported);
    });
});
