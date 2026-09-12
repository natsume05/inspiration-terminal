/**
 * Markdown rendering for blog entries.
 *
 * The order matters and is not negotiable: `marked` turns Markdown into HTML but
 * performs no sanitisation, and Markdown allows raw HTML. Its output therefore
 * always passes through DOMPurify before it reaches the document. Rendering
 * marked's output directly would let a single `<img onerror=...>` in a blog
 * entry execute.
 *
 * Both libraries are loaded as UMD bundles by the page; this module refuses to
 * render if either is missing rather than falling back to unsanitised output.
 */

/** Tags a blog entry is allowed to produce. */
const ALLOWED_TAGS = [
    'p', 'br', 'hr', 'strong', 'em', 'del', 'code', 'pre', 'blockquote',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'ul', 'ol', 'li', 'a', 'img',
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
];

/** Attributes kept on those tags. */
const ALLOWED_ATTR = ['href', 'title', 'alt', 'src', 'class', 'align'];

/**
 * Render every element that carries Markdown source.
 *
 * The raw Markdown is delivered through a `data-` attribute rather than inline
 * script, so a closing script tag inside the content cannot break out of the
 * element it was placed in.
 *
 * @returns {void}
 */
export function renderMarkdown() {
    const targets = document.querySelectorAll('[data-markdown-source]');

    if (targets.length === 0) {
        return;
    }

    const marked = globalThis.marked;
    const purify = globalThis.DOMPurify;

    if (typeof marked?.parse !== 'function' || typeof purify?.sanitize !== 'function') {
        // Leaving the noscript fallback in place is the safe outcome: it shows
        // the source as text instead of rendering it without sanitisation.
        console.error('[markdown] marked or DOMPurify did not load; showing plain text instead.');
        return;
    }

    for (const target of targets) {
        const raw = target.getAttribute('data-markdown-source');

        if (raw === null || raw === '') {
            continue;
        }

        let source = raw;

        try {
            // The attribute holds a JSON string so newlines survive the round trip.
            source = JSON.parse(raw);
        } catch {
            // Not JSON: treat the attribute as the literal text.
        }

        const html = marked.parse(source, { gfm: true, breaks: true });

        target.innerHTML = purify.sanitize(html, {
            ALLOWED_TAGS,
            ALLOWED_ATTR,
            // Block javascript: and data: targets rather than filtering them.
            ALLOW_UNKNOWN_PROTOCOLS: false,
            // Strip anything the allow-list does not mention, including comments.
            FORBID_TAGS: ['style', 'script', 'iframe', 'form', 'input'],
            FORBID_ATTR: ['style', 'onerror', 'onload', 'onclick'],
        });

        // Links inside user content open in a new tab and must not hand the
        // opener reference to the destination.
        for (const link of target.querySelectorAll('a[href]')) {
            const href = link.getAttribute('href') ?? '';

            if (!/^https?:\/\//i.test(href) && !href.startsWith('/')) {
                link.removeAttribute('href');

                continue;
            }

            if (/^https?:\/\//i.test(href)) {
                link.setAttribute('rel', 'noopener noreferrer');
                link.setAttribute('target', '_blank');
            }
        }
    }
}
