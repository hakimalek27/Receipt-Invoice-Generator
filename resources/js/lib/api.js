function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function apiFetch(url, options = {}) {
    const isFormData = options.body instanceof FormData;
    const body = !isFormData && options.body && typeof options.body !== 'string'
        ? JSON.stringify(options.body)
        : options.body;
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken() } : {}),
        ...(options.headers ?? {}),
    };

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        body,
        headers,
    });

    const contentType = response.headers.get('content-type') ?? '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : await response.text();

    if (!response.ok) {
        const message = typeof payload === 'object'
            ? payload.error || Object.values(payload.errors ?? {})?.flat()?.[0] || 'Request failed'
            : payload || 'Request failed';
        throw new Error(message);
    }

    return payload;
}

export function money(value, currency = 'MYR') {
    return `${currency} ${Number(value || 0).toFixed(2)}`;
}

export function today() {
    return new Date().toISOString().slice(0, 10);
}
