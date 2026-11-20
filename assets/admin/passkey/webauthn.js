export function isWebauthnSupported() {
    return typeof window.PublicKeyCredential === 'function'
        && typeof window.PublicKeyCredential.parseCreationOptionsFromJSON === 'function'
        && typeof window.PublicKeyCredential.parseRequestOptionsFromJSON === 'function';
}

export function isCancellation(error) {
    return error.name === 'NotAllowedError' || error.name === 'AbortError';
}

export async function postJson(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(data),
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok || payload.status === 'error') {
        throw new Error(payload.errorMessage || `HTTP ${response.status}`);
    }

    return payload;
}

export function showError(element, message) {
    element.textContent = message;
    element.classList.toggle('d-none', message === '');
}
