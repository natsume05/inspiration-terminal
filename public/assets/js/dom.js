/**
 * Small DOM helpers.
 *
 * Rendering is done with `textContent` and element construction rather than
 * building HTML strings, so user-supplied text can never be interpreted as
 * markup. That removes the need to remember an escaping call at every site.
 */

/**
 * Create an element with attributes and children.
 *
 * @param {string} tag Tag name.
 * @param {object} [options] Element options.
 * @param {string} [options.className] CSS class list.
 * @param {string} [options.text] Text content, always set as text.
 * @param {object} [options.attrs] Attributes to set.
 * @param {Array<Node|string>} [options.children] Child nodes.
 * @returns {HTMLElement} The created element.
 */
export function el(tag, { className = '', text = '', attrs = {}, children = [] } = {}) {
    const node = document.createElement(tag);

    if (className !== '') {
        node.className = className;
    }

    if (text !== '') {
        // textContent, never innerHTML: this is what keeps comment text inert.
        node.textContent = text;
    }

    for (const [name, value] of Object.entries(attrs)) {
        node.setAttribute(name, String(value));
    }

    for (const child of children) {
        node.append(child);
    }

    return node;
}

/**
 * Show a status message inside a form and update its state for styling.
 *
 * @param {HTMLElement|null} node Element receiving the message.
 * @param {string} message Message text.
 * @param {'success'|'error'|'pending'} state Visual state.
 * @returns {void}
 */
export function setStatus(node, message, state = 'pending') {
    if (!(node instanceof HTMLElement)) {
        return;
    }

    node.textContent = message;
    node.dataset.state = state;
}

/**
 * Run an asynchronous action with the button disabled.
 *
 * This is the client-side half of the double-submit protection: the database
 * constraint is the guarantee, and disabling the control stops the obvious
 * accidental repeat.
 *
 * @param {HTMLButtonElement|null} button Button to guard.
 * @param {() => Promise<void>} action Work to perform.
 * @returns {Promise<void>} Resolves when the action finishes.
 */
export async function withBusyButton(button, action) {
    if (!(button instanceof HTMLButtonElement)) {
        await action();
        return;
    }

    if (button.disabled) {
        return;
    }

    button.disabled = true;

    try {
        await action();
    } finally {
        button.disabled = false;
    }
}
