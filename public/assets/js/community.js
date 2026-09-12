/**
 * Community interactions: likes, comment loading, comment submission, and
 * post composition.
 *
 * Behaviour is attached through delegated listeners on `document`, so it keeps
 * working for content added after load without any per-element wiring.
 */

import { request } from './api.js';
import { el, setStatus, withBusyButton } from './dom.js';

/**
 * Handle a like or unlike click.
 *
 * @param {HTMLButtonElement} button The clicked control.
 * @returns {Promise<void>} Resolves when the request settles.
 */
async function handleLike(button) {
    const postId = Number(button.dataset.postId);

    if (!Number.isInteger(postId) || postId <= 0) {
        return;
    }

    await withBusyButton(button, async () => {
        const { ok, data } = await request('/api/like', { body: { post_id: postId } });

        if (!ok) {
            window.alert(data.message ?? '操作失败。');
            return;
        }

        // Trust the server's count rather than guessing locally: it is the only
        // value that reflects the database constraint.
        const countNode = button.querySelector('[data-like-count]');
        if (countNode) {
            countNode.textContent = String(data.like_count);
        }

        const liked = data.state === 'liked';
        button.classList.toggle('is-active', liked);
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');

        if (data.drop) {
            window.alert(data.drop);
        }
    });
}

/**
 * Load and render the comments for a post.
 *
 * @param {HTMLElement} card The post card.
 * @param {HTMLElement} list The list element to fill.
 * @returns {Promise<void>} Resolves when comments are rendered.
 */
async function loadComments(card, list) {
    const postId = card.dataset.postId;
    const response = await fetch(`/api/comments?post_id=${encodeURIComponent(postId)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        list.replaceChildren(el('li', { className: 'comment-item muted', text: '评论加载失败。' }));
        return;
    }

    const data = await response.json();
    const comments = Array.isArray(data.comments) ? data.comments : [];

    if (comments.length === 0) {
        list.replaceChildren(el('li', { className: 'comment-item muted', text: '还没有回响。' }));
        return;
    }

    list.replaceChildren(...comments.map((comment) => el('li', {
        className: 'comment-item',
        children: [
            el('span', { className: 'comment-author', text: comment.display_name ?? comment.username ?? '匿名' }),
            // Built as a text node, so markup in a comment renders literally.
            document.createTextNode(comment.content ?? ''),
        ],
    })));
}

/**
 * Toggle the comment region for a post.
 *
 * @param {HTMLButtonElement} button The toggle control.
 * @returns {Promise<void>} Resolves when the region is updated.
 */
async function handleToggleComments(button) {
    const card = button.closest('[data-post-id]');
    const region = card?.querySelector('[data-comment-region]');
    const list = region?.querySelector('[data-comment-list]');

    if (!(card instanceof HTMLElement) || !(region instanceof HTMLElement) || !(list instanceof HTMLElement)) {
        return;
    }

    const expanding = region.hidden;
    region.hidden = !expanding;
    button.setAttribute('aria-expanded', expanding ? 'true' : 'false');

    if (expanding) {
        await loadComments(card, list);
    }
}

/**
 * Submit a comment.
 *
 * @param {HTMLFormElement} form The comment form.
 * @returns {Promise<void>} Resolves when the request settles.
 */
async function handleCommentSubmit(form) {
    const card = form.closest('[data-post-id]');
    const input = form.querySelector('input[name="content"]');
    const button = form.querySelector('button[type="submit"]');

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const content = input.value.trim();

    if (content === '') {
        return;
    }

    await withBusyButton(button, async () => {
        const { ok, data } = await request('/api/comment', {
            body: { post_id: Number(form.dataset.postId), content },
        });

        if (!ok) {
            window.alert(data.message ?? '评论失败。');
            return;
        }

        input.value = '';

        const countNode = card?.querySelector('[data-comment-count]');
        if (countNode) {
            countNode.textContent = String(Number(countNode.textContent ?? '0') + 1);
        }

        const list = card?.querySelector('[data-comment-list]');
        if (list instanceof HTMLElement) {
            await loadComments(card, list);
        }

        if (data.reward && data.reward.awarded) {
            window.alert(`评论成功，获得 ${data.reward.amount} 星尘。`);
        }
    });
}

/**
 * Submit a new post.
 *
 * @param {HTMLFormElement} form The compose form.
 * @returns {Promise<void>} Resolves when the request settles.
 */
async function handleCompose(form) {
    const status = form.querySelector('[data-form-status]');
    const button = form.querySelector('button[type="submit"]');
    const content = form.querySelector('textarea[name="content"]');

    if (!(content instanceof HTMLTextAreaElement)) {
        return;
    }

    if (content.value.trim() === '') {
        setStatus(status, '内容不能为空。', 'error');
        return;
    }

    await withBusyButton(button, async () => {
        setStatus(status, '发布中…', 'pending');
        form.submit();
    });
}

// Delegated listeners: one handler per concern for the whole document.
document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const likeButton = target.closest('[data-like]');
    if (likeButton instanceof HTMLButtonElement) {
        event.preventDefault();
        void handleLike(likeButton);
        return;
    }

    const commentToggle = target.closest('[data-toggle-comments]');
    if (commentToggle instanceof HTMLButtonElement) {
        event.preventDefault();
        void handleToggleComments(commentToggle);
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (form.matches('[data-comment-form]')) {
        event.preventDefault();
        void handleCommentSubmit(form);
        return;
    }

    if (form.matches('[data-compose-form]')) {
        // Left to normal form submission so the file upload is included; the
        // status message just gives immediate feedback.
        handleCompose(form);
    }
});
