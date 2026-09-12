/**
 * Shared fetch wrapper.
 *
 * All write requests go through here so the CSRF token is attached in exactly
 * one place. Previously each page sent its own `fetch` call, which is how some
 * of them ended up without a token at all.
 */

/** Header carrying the CSRF token for JSON requests. */
const CSRF_HEADER = 'X-CSRF-Token';

/**
 * Read the CSRF token that the layout rendered into the page.
 *
 * @returns {string} The current token, or an empty string.
 */
export function csrfToken() {
    const field = document.querySelector('input[name="csrf_token"]');
    return field instanceof HTMLInputElement ? field.value : '';
}

/**
 * Perform a JSON request and normalise the result.
 *
 * Never throws for an HTTP error status: callers get `{ ok: false, message }`
 * so a failure can be shown in the interface instead of vanishing into the
 * console.
 *
 * @param {string} url Request target.
 * @param {object} [options] Request options.
 * @param {string} [options.method] HTTP method.
 * @param {object} [options.body] JSON-serialisable request body.
 * @returns {Promise<{ok: boolean, status: number, data: object}>} Parsed result.
 */
export async function request(url, { method = 'POST', body = null } = {}) {
    const headers = { Accept: 'application/json', [CSRF_HEADER]: csrfToken() };
    let payload;

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });
        const text = await response.text();
        let data = {};

        if (text !== '') {
            try {
                data = JSON.parse(text);
            } catch {
                // A non-JSON body means the server failed before routing, so
                // surface the status rather than pretending it parsed.
                data = { ok: false, message: '服务器返回了无法解析的响应。' };
            }
        }

        const ok = response.ok && data.ok !== false;

        return {
            ok,
            status: response.status,
            data: ok ? data : { ...data, message: data.message ?? `请求失败（HTTP ${response.status}）` },
        };
    } catch {
        return { ok: false, status: 0, data: { ok: false, message: '网络连接失败，请检查后重试。' } };
    }
}
