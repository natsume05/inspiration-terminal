/**
 * Markdown rendering for blog entries and community posts.
 *
 * The order matters and is not negotiable: `marked` turns Markdown into HTML but
 * performs no sanitisation, and Markdown allows raw HTML. Its output therefore
 * always passes through DOMPurify before it reaches the document. Rendering
 * marked's output directly would let a single `<img onerror=...>` in a post
 * execute.
 *
 * Three decisions here are deliberate:
 *
 *   * The libraries are fetched on demand rather than referenced by every
 *     template, so a page that has no Markdown on it downloads neither bundle,
 *     and there is exactly one place that knows which bundles are needed.
 *   * Every render target arrives with its text already inside it, escaped by
 *     the server. If a bundle fails to load, or parsing throws, that text stays
 *     where it is: the reader sees the source instead of an empty article.
 *   * Shortcodes are expanded before parsing, not after, so `[s:smile]` inside a
 *     code span survives as written.
 */

import { expandShortcodes } from './emojis.js';

/** Tags a post is allowed to produce. */
const ALLOWED_TAGS = [
    'p', 'br', 'hr', 'strong', 'em', 'del', 'code', 'pre', 'blockquote',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'ul', 'ol', 'li', 'a', 'img',
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
];

/** Attributes kept on those tags. */
const ALLOWED_ATTR = ['href', 'title', 'alt', 'src', 'class', 'align'];

/** Browser bundles, both UMD, both served from this origin. */
const LIBRARIES = ['/assets/js/vendor/marked.min.js', '/assets/js/vendor/purify.min.js'];

/** @type {Promise<{marked: object, purify: object}|null>|null} */
let libraries = null;

/**
 * Load one classic script and resolve when it has run.
 *
 * @param {string} source Script URL.
 * @returns {Promise<void>} Resolves on load, rejects on error.
 */
function loadScript(source) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = source;
        script.async = false;
        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener('error', () => reject(new Error(`could not load ${source}`)), { once: true });
        document.head.append(script);
    });
}

/**
 * Fetch the rendering libraries once per page.
 *
 * A failure is cached as well: retrying on every target would fire the same
 * broken request for every element on the page.
 *
 * @returns {Promise<{marked: object, purify: object}|null>} The globals, or null.
 */
async function ensureLibraries() {
    if (libraries !== null) {
        return libraries;
    }

    libraries = (async () => {
        for (const source of LIBRARIES) {
            await loadScript(source);
        }

        const marked = globalThis.marked;
        const purify = globalThis.DOMPurify;

        if (typeof marked?.parse !== 'function' || typeof purify?.sanitize !== 'function') {
            throw new Error('the bundles loaded but did not register their globals');
        }

        return { marked, purify };
    })().catch((error) => {
        console.error(`[markdown] ${error.message}; leaving the source text in place.`);
        return null;
    });

    return libraries;
}

/**
 * Render every element that carries Markdown source.
 *
 * The raw text travels through a `data-` attribute as a JSON string rather than
 * through inline script, so a closing script tag inside the content cannot break
 * out of the element it was placed in.
 *
 * @returns {Promise<void>} Resolves once every target has been handled.
 */
export async function renderMarkdown() {
    const targets = document.querySelectorAll('[data-markdown-source]');

    if (targets.length === 0) {
        return;
    }

    const loaded = await ensureLibraries();

    if (loaded === null) {
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

        const html = loaded.marked.parse(expandShortcodes(String(source)), { gfm: true, breaks: true });

        target.innerHTML = loaded.purify.sanitize(html, {
            ALLOWED_TAGS,
            ALLOWED_ATTR,
            // Block javascript: and data: targets rather than filtering them.
            ALLOW_UNKNOWN_PROTOCOLS: false,
            // Strip anything the allow-list does not mention, including comments.
            FORBID_TAGS: ['style', 'script', 'iframe', 'form', 'input', 'textarea'],
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
