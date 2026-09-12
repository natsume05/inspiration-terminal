/**
 * Application entry point.
 *
 * Loads the behaviour each page actually uses, so no page downloads code for a
 * feature it does not have. Previously every page carried inline script blocks,
 * which meant every visitor received all of it and none of it could be cached.
 */

import { renderMarkdown } from './markdown.js';

/**
 * Decide which modules this page needs and load them.
 *
 * @returns {Promise<void>} Resolves once the required modules have run.
 */
async function boot() {
    const jobs = [];

    // Community interactions: likes, comments, and composing a post.
    if (document.querySelector('[data-like], [data-comment-form], [data-compose-form]')) {
        jobs.push(import('./community.js'));
    }

    // The emoji palette only exists where a post can be written.
    if (document.querySelector('[data-emoji-picker]')) {
        jobs.push(import('./emoji.js'));
    }

    // Blog interactions: liking an entry and commenting on it.
    if (document.querySelector('[data-blog-like], [data-blog-comment-form]')) {
        jobs.push(import('./blog.js'));
    }

    await Promise.all(jobs);

    // Markdown is rendered after the behaviour modules attach, so a re-rendered
    // comment list cannot detach a listener that was bound to its elements.
    await renderMarkdown();
}

void boot();
