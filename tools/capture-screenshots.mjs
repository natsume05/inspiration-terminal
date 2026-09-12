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

/** Credentials for the demo administrator seeded by bin/seed-demo.php. */
const credentials = { username: 'MingMo', password: 'demo-password' };

const viewport = { width: 1440, height: 900 };
const mobileViewport = { width: 420, height: 900 };

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
    const { data } = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    const target = join(outputDirectory, `${name}.png`);

    await mkdir(dirname(target), { recursive: true });

    const buffer = Buffer.from(data, 'base64');
    await writeFile(target, buffer);

    captures.push({ name, bytes: buffer.length, ...state });

    console.log(
        `  captured ${name}.png  ${(buffer.length / 1024).toFixed(1)} KB  ${state.path}  "${state.title}"`,
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
 * Captures whose image is expected to repeat an earlier one.
 *
 * `/tools` renders the same markup whether or not a session exists, so capturing
 * it a second time while signed in adds a file with no new information. Naming it
 * here keeps the duplicate check meaningful instead of training the reader to
 * ignore its warning.
 *
 * @type {Set<string>}
 */
const expectedDuplicates = new Set(['15-tools-signed-in']);

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

    // Public pages first, so a sign-in failure still leaves useful output.
    const publicPages = [
        ['01-home', '/'],
        ['02-blog', '/blog'],
        ['03-tools', '/tools'],
        ['04-tools-github', '/tools/github'],
        ['05-tools-steam', '/tools/steam'],
        ['06-login', '/login'],
    ];

    for (const [name, path] of publicPages) {
        await goto(cdp, path);
        await capture(cdp, name);
    }

    const blogPath = await firstBlogSlug(cdp);

    if (blogPath !== null) {
        await goto(cdp, blogPath);
        await capture(cdp, '07-blog-entry');
    } else {
        console.log('  skipped the blog detail page: no entry was linked');
    }

    console.log('Signing in...');
    await signIn(cdp);

    const memberPages = [
        ['08-community', '/community'],
        ['09-notes', '/notes'],
        ['10-notifications', '/notifications'],
        ['11-profile', '/profile'],
        ['12-feedback', '/feedback'],
        ['13-admin', '/admin'],
        ['14-admin-audit', '/admin/audit'],
        ['15-tools-signed-in', '/tools'],
    ];

    for (const [name, path] of memberPages) {
        await goto(cdp, path);
        await capture(cdp, name);
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
