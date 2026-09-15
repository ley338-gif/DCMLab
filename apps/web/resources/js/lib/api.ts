/**
 * Schlanker JSON-Client fuer die kleinen API-Endpunkte hinter einer
 * Node-Seite (exec/config/action/hint/flag) -- diese sind bewusst kein
 * Inertia-Visit (kein Seitenwechsel pro Terminal-Befehl), deshalb reicht
 * hier fetch() mit demselben XSRF-Cookie, das Inertia selbst benutzt.
 */
function readCookie(name: string): string | null {
    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name + '=([^;]*)'),
    );
    return match ? decodeURIComponent(match[1]) : null;
}

async function sendJson<T>(
    method: string,
    url: string,
    body: unknown = {},
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`${url} -> ${response.status}`);
    }

    return (await response.json()) as T;
}

export function postJson<T>(url: string, body: unknown = {}): Promise<T> {
    return sendJson<T>('POST', url, body);
}

export function deleteJson<T>(url: string, body: unknown = {}): Promise<T> {
    return sendJson<T>('DELETE', url, body);
}

/**
 * Fuer Datei-Uploads (z. B. Achievement-Bild, ADR 0088) -- kein
 * `Content-Type`-Header setzen, der Browser haengt selbst die
 * `multipart/form-data`-Boundary an.
 */
export async function postFormData<T>(
    url: string,
    formData: FormData,
): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
        },
        body: formData,
    });

    if (!response.ok) {
        const message = await response
            .json()
            .then((data) => data?.message)
            .catch(() => null);
        throw new Error(message ?? `${url} -> ${response.status}`);
    }

    return (await response.json()) as T;
}
