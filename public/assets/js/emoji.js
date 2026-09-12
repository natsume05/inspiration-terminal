/**
 * The emoji picker.
 *
 * Posts store emoji as `[s:name]` tokens, so the picker's only job is to type
 * one of those into the composer at the caret. Building the palette from
 * `emojis.js` rather than from markup means the buttons and the renderer cannot
 * disagree about what a token means.
 */

import { EMOJI, EMOJI_NAMES } from './emojis.js';

/**
 * Insert text into a textarea at the caret, keeping the caret after it.
 *
 * @param {HTMLTextAreaElement} field Target field.
 * @param {string} text Text to insert.
 * @returns {void}
 */
function insertAtCaret(field, text) {
    const start = field.selectionStart ?? field.value.length;
    const end = field.selectionEnd ?? start;

    field.value = field.value.slice(0, start) + text + field.value.slice(end);
    field.focus();
    field.setSelectionRange(start + text.length, start + text.length);
}

/**
 * Fill an empty panel with one button per known emoji.
 *
 * @param {HTMLElement} panel Panel element.
 * @returns {void}
 */
function buildPanel(panel) {
    if (panel.childElementCount > 0) {
        return;
    }

    for (const name of EMOJI_NAMES) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'emoji-option';
        button.dataset.emoji = name;
        button.textContent = EMOJI[name];
        button.title = `[s:${name}]`;
        button.setAttribute('aria-label', name);
        panel.append(button);
    }
}

/**
 * Close every open picker.
 *
 * @returns {void}
 */
function closeAll() {
    for (const panel of document.querySelectorAll('[data-emoji-panel]')) {
        panel.hidden = true;
    }

    for (const toggle of document.querySelectorAll('[data-emoji-toggle]')) {
        toggle.setAttribute('aria-expanded', 'false');
    }
}

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const toggle = target.closest('[data-emoji-toggle]');

    if (toggle instanceof HTMLButtonElement) {
        event.preventDefault();

        const panel = toggle.parentElement?.querySelector('[data-emoji-panel]');
        const opening = panel instanceof HTMLElement && panel.hidden;

        closeAll();

        if (panel instanceof HTMLElement && opening) {
            buildPanel(panel);
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
        }

        return;
    }

    const option = target.closest('[data-emoji]');

    if (option instanceof HTMLButtonElement) {
        event.preventDefault();

        const form = option.closest('[data-compose-form]');
        const field = form?.querySelector('textarea[name="content"]');

        if (field instanceof HTMLTextAreaElement) {
            insertAtCaret(field, `[s:${option.dataset.emoji}]`);
        }

        return;
    }

    // A click anywhere else dismisses the palette.
    if (target.closest('[data-emoji-panel]') === null) {
        closeAll();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAll();
    }
});
