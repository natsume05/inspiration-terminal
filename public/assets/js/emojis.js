/**
 * The emoji shortcode table.
 *
 * Posts written on the previous version of this site store emoji as `[s:name]`
 * tokens rather than as the characters themselves, and the table below is the
 * one that installation used. It lives in a single module because two things
 * need the same answer: the renderer that expands the tokens, and the picker
 * that inserts them.
 *
 * An unknown token is left exactly as it was typed instead of being dropped, so
 * a name this table does not know about shows up as itself rather than
 * disappearing from the middle of a sentence.
 */

/** @type {Readonly<Record<string, string>>} */
export const EMOJI = Object.freeze({
    smile: '🙂',
    joy: '😂',
    lol: '🤣',
    love: '😍',
    cool: '😎',
    thinking: '🤔',
    cry: '😭',
    scared: '😱',
    angry: '😡',
    clown: '🤡',
    vomit: '🤮',
    shhh: '🤫',
    thumbsup: '👍',
    ok: '👌',
    heart: '❤️',
    broken: '💔',
    poop: '💩',
    ghost: '👻',
    alien: '👽',
    robot: '🤖',
    fire: '🔥',
    star: '✨',
    rocket: '🚀',
    moon: '🌙',
    game: '🎮',
    cat: '🐱',
    dog: '🐶',
    fox: '🦊',
    bug: '🐞',
    paimon: '🥘',
    primogem: '💎',
    gwent: '🃏',
    sword: '⚔️',
    objection: '👉',
    tree: '🌳',
    dragon: '🐉',
});

/** Names in the order the picker shows them. */
export const EMOJI_NAMES = Object.freeze(Object.keys(EMOJI));

const SHORTCODE = /\[s:([a-z0-9_]+)\]/gi;

/**
 * Replace every known shortcode with its emoji.
 *
 * @param {string} text Source text.
 * @returns {string} Text with known shortcodes replaced.
 */
export function expandShortcodes(text) {
    if (typeof text !== 'string' || !text.includes('[s:')) {
        return text;
    }

    return text.replace(SHORTCODE, (match, name) => EMOJI[String(name).toLowerCase()] ?? match);
}
