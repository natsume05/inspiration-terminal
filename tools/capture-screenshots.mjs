/**
 * Capture documentation screenshots from the running development server.
 *
 * Uses the Chrome DevTools Protocol over the WebSocket client built into Node,
 * so there is no dependency to install. Authenticated pages are reached by
 * signing in through the real login form, which means the screenshots show the
 * same session handling a visitor gets.
 *
 * Usage:
 *   node tools/capture-screenshots.mjs [baseUrl] [outputDirectory]
 *
 * Requires a browser started with a debugging port, for example:
 *   msedge --headless=new --remote-debugging-port=9222 --user-data-dir=<dir>
 */

import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';

const baseUrl = process.argv[2] ?? 'http://127.0.0.1:8099';
const outputDirectory = resolve(process.argv[3] ?? 'docs/images');
const debuggingPort = process.env.CDP_PORT ?? '9222';

/**
 * Credentials for the pages behind sign-in.
 *
 * Overridable because the account that exists depends on how the database was
 * populated: `bin/seed-demo.php` creates `MingMo` with `demo-password`, while a
 * database imported from an older installation carries the original accounts and
 * their original passwords.
 *
 *   CAPTURE_USER=someone CAPTURE_PASSWORD=secret node tools/capture-screenshots.mjs
 */
const credentials = {
    username: process.env.CAPTURE_USER ?? 'MingMo',
    password: process.env.CAPTURE_PASSWORD ?? 'demo-password',
};

const viewport = { width: 1440, height: 1000 };
const mobileViewport = { width: 420, height: 900 };

/**
 * Scroll an element to the top of the viewport before the next capture.
 *
 * A viewport-only capture cuts the page at a fixed height, so a long page loses
 * whatever sits below it — which is how a screenshot of the Steam page came to
 * advertise a sale calendar that was not in the frame. Naming an element here
 * gives that section a capture of its own instead of cropping it away.
 *
 * @param {Cdp} cdp Client.
 * @param {string} selector CSS selector to bring into view.
 * @returns {Promise<boolean>} True when the element was found.
 */
async function scrollTo(cdp, selector) {
    const { result } = await cdp.send('Runtime.evaluate', {
        expression: `(() => {
            const node = document.querySelector(${JSON.stringify(selector)});
            if (node === null) return false;
            node.scrollIntoView({ block: 'start' });
            return true;
        })()`,
        returnByValue: true,
    });

    // One frame for the scroll to apply and any lazy image to start loading.
    await new Promise((r) => setTimeout(r, 400));

    return result.value === true;
}

/**
 * Minimal DevTools Protocol client.
 */
class Cdp {
    #socket;
    #nextId = 1;
    #pending = new Map();

    static async connect(port) {
        const list = await fetch(`http://127.0.0.1:${port}/json/list`).then((r) => r.json());
        const page = list.find((target) => target.type === 'page');

        if (!page) {
            throw new Error('No page target found. Is the browser running with --remote-debugging-port?');
        }

        const client = new Cdp();
        client.#socket = new WebSocket(page.webSocketDebuggerUrl);

        await new Promise((resolvePromise, rejectPromise) => {
            client.#socket.addEventListener('open', resolvePromise, { once: true });
            client.#socket.addEventListener('error', rejectPromise, { once: true });
        });

        client.#socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            const entry = client.#pending.get(message.id);

            if (!entry) {
                return;
            }

            client.#pending.delete(message.id);

            if (message.error) {
                entry.reject(new Error(`${entry.method}: ${message.error.message}`));
            } else {
                entry.resolve(message.result);
            }
        });

        return client;
    }

    send(method, params = {}) {
        const id = this.#nextId++;

        return new Promise((resolvePromise, rejectPromise) => {
            this.#pending.set(id, { resolve: resolvePromise, reject: rejectPromise, method });
            this.#socket.send(JSON.stringify({ id, method, params }));
        });
    }

    /**
     * Wait for the next `Page.loadEventFired` event.
     *
     * Polling `document.readyState` is not safe here: `Page.navigate` returns as
     * soon as the navigation begins, so a poll can still be reading the previous
     * document, find it already `complete`, and let the caller capture a blank page.
     * Waiting on the event ties the wait to the navigation actually finishing.
     *
     * @returns {Promise<void>} Resolves when the load event fires.
     */
    waitForLoadEvent() {
        return new Promise((resolvePromise) => {
            const onMessage = (event) => {
                const message = JSON.parse(event.data);

                if (message.method === 'Page.loadEventFired') {
                    this.#socket.removeEventListener('message', onMessage);
                    resolvePromise();
                }
            };

            this.#socket.addEventListener('message', onMessage);
        });
    }

    /**
     * Subscribe to protocol events that are not replies to a command.
     *
     * @param {(method: string, params: object) => void} listener Event handler.
     * @returns {void}
     */
    onNotification(listener) {
        this.#socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);

            if (message.id === undefined) {
                listener(message.method, message.params ?? {});
            }
        });
    }

    close() {
        this.#socket.close();
    }
}

/**
 * Navigate to a path and wait until the browser settles on a final document.
 *
 * The browser is given the path, then polled until its location stops changing and
 * the document is complete. Waiting on a single `Page.loadEventFired` is not enough
 * because a redirect can follow the first load, which meant a navigation to
 * `/login` was reported as landing on the page it redirected to.
 *
 * @param {Cdp} cdp Client.
 * @param {string} path Path beginning with a slash.
 * @returns {Promise<string>} The path the browser finally settled on.
 */
async function goto(cdp, path) {
    await cdp.send('Page.navigate', { url: `${baseUrl}${path}` });

    let previous = null;
    let stableCount = 0;

    for (let attempt = 0; attempt < 150; attempt++) {
        await new Promise((r) => setTimeout(r, 100));

        const { result } = await cdp.send('Runtime.evaluate', {
            expression: 'JSON.stringify({ path: location.pathname, ready: document.readyState })',
            returnByValue: true,
        });

        let state;

        try {
            state = JSON.parse(result.value);
        } catch {
            continue;
        }

        if (state.ready !== 'complete') {
            stableCount = 0;

            continue;
        }

        if (state.path === previous) {
            stableCount++;
        } else {
            previous = state.path;
            stableCount = 0;
        }

        // Two consecutive identical readings mean any redirect has finished.
        if (stableCount >= 2) {
            // A short pause lets webfonts and the layout settle.
            await new Promise((r) => setTimeout(r, 250));

            return state.path;
        }
    }

    throw new Error(
        `Navigation to ${path} never settled (last seen: ${previous ?? 'nothing'}). `
        + 'Is the development server running?',
    );
}

/**
 * Inspect every image on the page before it is captured.
 *
 * A page can look fine and still be full of empty boxes: an `<img>` whose source
 * failed to load keeps its layout slot, so the screenshot shows a correctly
 * sized hole where the artwork should be, and nothing in the HTML admits it. The
 * Steam page is the reason this check exists — its deal thumbnails come from a
 * third-party CDN.
 *
 * Only images inside the viewport count as pending. An image below the fold is
 * still `loading="lazy"` on purpose and has not been given a reason to load yet,
 * so treating it as a defect would make the report noise, and a report that
 * cries wolf gets ignored.
 *
 * @param {Cdp} cdp Client.
 * @returns {Promise<{total: number, broken: string[], pending: string[]}>} Findings.
 */
async function inspectImages(cdp) {
    const { result } = await cdp.send('Runtime.evaluate', {
        expression: `(() => {
            const name = (image) => image.currentSrc || image.src;
            const visible = (image) => {
                const box = image.getBoundingClientRect();
                return box.bottom > 0 && box.top < window.innerHeight
                    && box.right > 0 && box.left < window.innerWidth;
            };
            const images = [...document.images];
            return JSON.stringify({
                total: images.length,
                broken: images.filter((i) => i.complete && i.naturalWidth === 0).map(name),
                pending: images.filter((i) => !i.complete && visible(i)).map(name),
            });
        })()`,
        returnByValue: true,
    });

    try {
        return JSON.parse(result.value);
    } catch {
        return { total: 0, broken: [], pending: [] };
    }
}

/**
 * Capture the current page to a PNG file.
 *
 * The path, title and byte count are recorded with each capture, and the run
 * reports any duplicates at the end. Identical file sizes across every page is the
 * signature of a capture that fired before navigation finished — and that failure
 * is invisible in the images themselves, so a run that produced the same
 * screenshot seventeen times would otherwise look like a success.
 *
 * @param {Cdp} cdp Client.
 * @param {string} name File name without extension.
 * @returns {Promise<void>} Resolves once written.
 */
async function capture(cdp, name) {
    const { result } = await cdp.send('Runtime.evaluate', {
        expression: 'JSON.stringify({ path: location.pathname, title: document.title, ready: document.readyState })',
        returnByValue: true,
    });

    const state = JSON.parse(result.value);
    const images = await inspectImages(cdp);

    if (images.broken.length > 0) {
        problems.push(`${state.path}: ${images.broken.length} of ${images.total} image(s) failed to decode: ${images.broken.slice(0, 3).join(', ')}`);
    }

    if (images.pending.length > 0) {
        problems.push(`${state.path}: ${images.pending.length} image(s) had not loaded when the page was captured: ${images.pending.slice(0, 3).join(', ')}`);
    }

    const { data } = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    const target = join(outputDirectory, `${name}.png`);

    await mkdir(dirname(target), { recursive: true });

    const buffer = Buffer.from(data, 'base64');
    await writeFile(target, buffer);

    captures.push({ name, bytes: buffer.length, images: images.total, ...state });

    console.log(
        `  captured ${name}.png  ${(buffer.length / 1024).toFixed(1)} KB  ${images.total} image(s)  ${state.path}  "${state.title}"`,
    );
}

/**
 * Fill and submit the login form, then wait for the redirect.
 *
 * Repeatability matters: the browser profile keeps cookies between runs, so a
 * second run would already be signed in and `/login` would redirect away, leaving
 * no form to submit. An existing session is therefore treated as success.
 *
 * Cookies are deliberately *not* cleared here. Clearing them invalidates the
 * session that the public-page captures were taken with, so those pages would be
 * rendered signed out while the run reported a successful sign-in — which is
 * exactly the kind of quiet inconsistency these screenshots exist to rule out.
 * Start the browser with a fresh `--user-data-dir` when a signed-out first visit
 * is what you want to capture.
 *
 * @param {Cdp} cdp Client.
 * @returns {Promise<void>} Resolves once signed in.
 */
async function signIn(cdp) {
    const landingPath = await goto(cdp, '/login');

    if (landingPath !== '/login') {
        // The login page redirects when a session already exists, so arriving
        // somewhere else means the sign-in is already valid.
        console.log(`  /login redirected to ${landingPath}; the session is already valid`);

        return;
    }

    const state = await cdp.send('Runtime.evaluate', {
        expression: `document.querySelector('form[action="/login"]') ? 'form' : 'no-form'`,
        returnByValue: true,
    });

    if (state.result.value !== 'form') {
        throw new Error('The login page loaded but contains no sign-in form.');
    }

    const script = `
        (() => {
            const form = document.querySelector('form[action="/login"]');
            form.querySelector('input[name="username"]').value = ${JSON.stringify(credentials.username)};
            form.querySelector('input[name="password"]').value = ${JSON.stringify(credentials.password)};
            form.requestSubmit ? form.requestSubmit() : form.submit();
            return 'submitted';
        })()
    `;

    const { result } = await cdp.send('Runtime.evaluate', { expression: script, returnByValue: true });

    if (result.value !== 'submitted') {
        throw new Error(`Could not submit the login form (${result.value}).`);
    }

    await cdp.waitForLoadEvent();
    await new Promise((r) => setTimeout(r, 500));

    const { result: where } = await cdp.send('Runtime.evaluate', {
        expression: 'location.pathname',
        returnByValue: true,
    });

    if (where.value === '/login') {
        throw new Error('Sign-in failed: the login page was returned again.');
    }

    console.log(`  signed in, landed on ${where.value}`);
}

/**
 * End any existing session before the signed-out captures.
 *
 * The browser profile keeps cookies between runs, so a second run reaches `/login`
 * already signed in, gets redirected to `/community`, and writes a logged-in page
 * into the file that is supposed to show the sign-in form. Signing out first is
 * what makes the signed-out captures actually signed out.
 *
 * @param {Cdp} cdp Client.
 * @returns {Promise<boolean>} True when the session is gone afterwards.
 */
async function signOut(cdp) {
    await goto(cdp, '/');

    const { result } = await cdp.send('Runtime.evaluate', {
        expression: `(() => {
            const form = document.querySelector('form[action="/logout"]');
            if (form === null) return 'already-signed-out';
            form.submit();
            return 'submitted';
        })()`,
        returnByValue: true,
    });

    if (result.value !== 'submitted') {
        return true;
    }

    // The logout handler answers with a redirect, so the page is re-read once it
    // has had time to arrive.
    await new Promise((r) => setTimeout(r, 700));
    await goto(cdp, '/');

    const { result: state } = await cdp.send('Runtime.evaluate', {
        expression: `document.querySelector('form[action="/logout"]') === null`,
        returnByValue: true,
    });

    return state.value === true;
}

/**
 * Resize the viewport for the next captures.
 *
 * @param {Cdp} cdp Client.
 * @param {{width: number, height: number}} size Target size.
 * @returns {Promise<void>} Resolves once applied.
 */
async function setViewport(cdp, size) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: size.width,
        height: size.height,
        deviceScaleFactor: 1,
        mobile: false,
    });
}

/**
 * Read the slug of the first blog entry linked on the index page.
 *
 * @param {Cdp} cdp Client.
 * @returns {Promise<string|null>} The slug, or null when none is present.
 */
async function firstBlogSlug(cdp) {
    await goto(cdp, '/blog');

    const { result } = await cdp.send('Runtime.evaluate', {
        expression: `document.querySelector('a[href^="/blog/"]')?.getAttribute('href') ?? ''`,
        returnByValue: true,
    });

    const href = String(result.value ?? '');

    return href.startsWith('/blog/') ? href : null;
}

const cdp = await Cdp.connect(debuggingPort);

/**
 * Problems observed while rendering. A screenshot can look plausible while the
 * page is quietly broken — a missing stylesheet, a script that threw — so the
 * capture run reports those instead of leaving them to be noticed later.
 *
 * @type {string[]}
 */
const problems = [];

/**
 * Rendering notes that are not the application's fault, such as a third-party
 * image host that refused a request during the run.
 *
 * @type {string[]}
 */
const warnings = [];

/**
 * What each capture actually rendered, used to detect duplicates afterwards.
 *
 * @type {Array<{name: string, bytes: number, path: string, title: string}>}
 */
const captures = [];

/**
 * Captures whose image is legitimately expected to repeat an earlier one.
 *
 * Empty on purpose. `/tools` used to render identically signed in and signed out,
 * so its second capture was excused here; now that every signed-out capture is
 * taken after an explicit sign-out, the two really do differ, and excusing them
 * would only hide a sign-in that silently failed.
 *
 * @type {Set<string>}
 */
const expectedDuplicates = new Set();

/** Set once every page has been captured successfully. */
let captured = false;


try {
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Log.enable');
    await cdp.send('Network.enable');

    cdp.onNotification((method, params) => {
        if (method === 'Runtime.exceptionThrown') {
            problems.push(`uncaught: ${params.exceptionDetails?.text ?? 'unknown'}`);
        }

        if (method === 'Log.entryAdded' && params.entry?.level === 'error') {
            problems.push(`console: ${params.entry.text}`);
        }

        if (method === 'Network.loadingFailed') {
            // A third-party image that will not load — a Steam capsule, say — is a
            // missing thumbnail, not a broken page. It is still reported, but it is
            // not counted as a defect in the application.
            const message = `asset failed: ${params.errorText} (${params.type})`;
            (params.type === 'Image' ? warnings : problems).push(message);
        }

        if (method === 'Network.responseReceived' && params.response?.status >= 400) {
            const message = `HTTP ${params.response.status}: ${params.response.url}`;
            (params.response.url.startsWith(baseUrl) ? problems : warnings).push(message);
        }
    });

    console.log('Opening the site...');
    await setViewport(cdp, viewport);

    if (!await signOut(cdp)) {
        problems.push('could not sign out, so the signed-out captures may show a logged-in page');
    }

    // Public pages first, so a sign-in failure still leaves useful output.
    const publicPages = [
        { name: '01-home', path: '/' },
        { name: '02-blog', path: '/blog' },
        { name: '03-tools', path: '/tools' },
        { name: '04-tools-github', path: '/tools/github' },
        { name: '05-tools-steam', path: '/tools/steam', height: 1500 },
        { name: '05b-tools-steam-calendar', path: '/tools/steam', scrollTo: '.calendar-grid' },
        { name: '06-login', path: '/login' },
    ];

    for (const page of publicPages) {
        await setViewport(cdp, { width: viewport.width, height: page.height ?? viewport.height });
        await goto(cdp, page.path);

        if (page.scrollTo !== undefined && !await scrollTo(cdp, page.scrollTo)) {
            problems.push(`${page.path}: ${page.scrollTo} was not found, so ${page.name} repeats the top of the page`);
        }

        await capture(cdp, page.name);
    }

    const blogPath = await firstBlogSlug(cdp);

    if (blogPath !== null) {
        await goto(cdp, blogPath);
        await capture(cdp, '07-blog-entry');

        if (!await scrollTo(cdp, '[data-blog-comments]')) {
            problems.push(`${blogPath}: the comment list was not found`);
        }

        await capture(cdp, '07b-blog-entry-comments');
    } else {
        console.log('  skipped the blog detail page: no entry was linked');
    }

    console.log('Signing in...');
    await signIn(cdp);

    const memberPages = [
        { name: '08-community', path: '/community', height: 1180 },
        { name: '09-notes', path: '/notes' },
        { name: '10-notifications', path: '/notifications' },
        { name: '11-profile', path: '/profile' },
        { name: '12-feedback', path: '/feedback' },
        { name: '13-admin', path: '/admin' },
        { name: '13b-admin-accounts', path: '/admin', scrollTo: '#admin-accounts', height: 760 },
        { name: '14-admin-audit', path: '/admin/audit' },
        { name: '15-tools-signed-in', path: '/tools' },
    ];

    for (const page of memberPages) {
        await setViewport(cdp, { width: viewport.width, height: page.height ?? viewport.height });
        await goto(cdp, page.path);

        if (page.scrollTo !== undefined && !await scrollTo(cdp, page.scrollTo)) {
            problems.push(`${page.path}: ${page.scrollTo} was not found, so ${page.name} repeats the top of the page`);
        }

        await capture(cdp, page.name);
    }

    console.log('Capturing the mobile layout...');
    await setViewport(cdp, mobileViewport);
    await goto(cdp, '/community');
    await capture(cdp, '16-mobile-community');
    await goto(cdp, '/');
    await capture(cdp, '17-mobile-home');

    console.log('Done.');
    captured = true;
} finally {
    cdp.close();

    // Deduplicate: the same missing asset can be requested on every page.
    const unique = [...new Set(problems)];
    const uniqueWarnings = [...new Set(warnings)];

    if (unique.length === 0) {
        console.log('No application errors during capture.');
    } else {
        console.log(`\n${unique.length} problem(s) seen while rendering:`);
        for (const problem of unique.slice(0, 25)) {
            console.log(`  - ${problem}`);
        }

        if (unique.length > 25) {
            console.log(`  ... and ${unique.length - 25} more`);
        }
    }

    if (uniqueWarnings.length > 0) {
        console.log(`\n${uniqueWarnings.length} third-party note(s) (not application defects):`);
        for (const warning of uniqueWarnings.slice(0, 10)) {
            console.log(`  - ${warning}`);
        }
    }

    if (!captured) {
        console.log('\nThe run did not finish, so the capture set is incomplete.');

        process.exitCode = 1;
    } else {
        // A capture that fired before navigation settled produces the previous page
        // again. Comparing byte counts catches it, because two genuinely different
        // pages essentially never compress to the same size.
        const bySize = new Map();

        for (const entry of captures) {
            bySize.set(entry.bytes, [...(bySize.get(entry.bytes) ?? []), entry.name]);
        }

        const duplicates = [...bySize.values()]
            .filter((names) => names.length > 1)
            .filter((names) => !names.some((name) => expectedDuplicates.has(name)));

        console.log(`\n${captures.length} capture(s):`);
        for (const entry of captures) {
            console.log(`  ${entry.name.padEnd(24)} ${String(entry.bytes).padStart(7)} B  ${entry.path}`);
        }

        if (duplicates.length === 0) {
            console.log('\nAll captures are distinct.');
        } else {
            console.log('\nWARNING: these captures produced identical images:');
            for (const names of duplicates) {
                console.log(`  ${names.join(', ')}`);
            }

            process.exitCode = 1;
        }
    }
}
