/**
 * Blog interactions: liking an entry and posting a comment.
 *
 * Kept separate from the community module so a reader of the blog downloads only
 * the behaviour that page uses.
 */

import { request } from './api.js';
import { setStatus, withBusyButton } from './dom.js';

/**
 * Toggle a like on the entry being read.
 *
 * @param {HTMLButtonElement} button The clicked control.
 * @returns {Promise<void>} Resolves when the request settles.
 */
async function handleBlogLike(button) {
    const slug = button.dataset.slug;

    if (!slug) {
        return;
    }

    await withBusyButton(button, async () => {
        const { ok, data } = await request(`/blog/${encodeURIComponent(slug)}/like`, { body: {} });

        if (!ok) {
            window.alert(data.message ?? '操作失败。');
            return;
        }

        // The server's count is authoritative: it reflects the unique key that
        // actually decided the outcome.
        const countNode = button.querySelector('[data-like-count]');

        if (countNode) {
            countNode.textContent = String(data.like_count);
        }

        button.classList.toggle('is-active', data.liked === true);
        button.setAttribute('aria-pressed', data.liked === true ? 'true' : 'false');
    });
}

/**
 * Submit a comment on the entry being read.
 *
 * @param {HTMLFormElement} form The comment form.
 * @returns {Promise<void>} Resolves when the request settles.
 */
async function handleBlogComment(form) {
    const slug = form.dataset.slug;
    const content = form.querySelector('input[name="content"]');
    const nameField = form.querySelector('input[name="display_name"]');
    const button = form.querySelector('button[type="submit"]');
    const status = document.querySelector('[data-form-status]');
    const list = document.querySelector('[data-blog-comments]');

    if (!slug || !(content instanceof HTMLInputElement)) {
        return;
    }

    const body = {
        content: content.value.trim(),
        display_name: nameField instanceof HTMLInputElement ? nameField.value.trim() : '',
    };

    if (body.content.length < 2) {
        setStatus(status, '评论至少需要 2 个字符。', 'error');
        return;
    }

    await withBusyButton(button, async () => {
        const { ok, data } = await request(`/blog/${encodeURIComponent(slug)}/comments`, { body });

        if (!ok) {
            setStatus(status, data.message ?? '评论失败。', 'error');
            return;
        }

        setStatus(status, '评论已发布。', 'success');
        content.value = '';

        if (list instanceof HTMLElement) {
            // Rebuild the list from the server so the rendered order and the
            // stored order cannot drift apart.
            const response = await fetch(`/blog/${encodeURIComponent(slug)}`, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            });

            if (response.ok) {
                window.location.reload();
            }
        }
    });
}

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const likeButton = target.closest('[data-blog-like]');

    if (likeButton instanceof HTMLButtonElement) {
        event.preventDefault();
        void handleBlogLike(likeButton);
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form instanceof HTMLFormElement && form.matches('[data-blog-comment-form]')) {
        event.preventDefault();
        void handleBlogComment(form);
    }
});
