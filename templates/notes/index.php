<?php

/**
 * Private notes.
 *
 * @var list<array{id:int,content:string,created_at:string,readable:bool}> $notes
 * @var string $csrfField
 * @var string $csrfToken
 */

use App\Http\View;
?>
<section class="panel panel-narrow-wide">
    <h1>思维殿堂</h1>
    <p class="muted">
        这里的内容以 AES-256-GCM 加密后存储，密钥不在数据库中——即使数据库被导出，也无法读出这些文字。
        <br>代价是：如果应用密钥丢失，这些内容将永久无法恢复。
    </p>

    <form method="post" action="/notes" class="form">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label for="note-content">新的记忆</label>
        <textarea id="note-content" name="content" rows="5" required maxlength="20000"
                  placeholder="在此刻下那些无法对人言说的…"></textarea>

        <button type="submit" class="btn btn-primary">封存记忆</button>
    </form>
</section>

<section class="panel">
    <h2>已封存（<?= count($notes) ?>）</h2>

    <?php if ($notes === []): ?>
        <p class="muted">虚空之中暂无回响。</p>
    <?php endif; ?>

    <ol class="timeline">
        <?php foreach ($notes as $note): ?>
            <li class="timeline-item <?= $note['readable'] ? '' : 'timeline-item-broken' ?>">
                <div class="timeline-head">
                    <time datetime="<?= View::escape($note['created_at']) ?>"><?= View::escape($note['created_at']) ?></time>

                    <?php
                    // Deleting is a POST carrying a token: a GET link here would
                    // let any page on the internet delete a note by loading an
                    // image whose address points at this action.
                    ?>
                    <form method="post" action="/notes/delete" class="inline-form"
                          onsubmit="return confirm('要遗忘这段记忆吗？此操作不可撤销。');">
                        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                        <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                        <button type="submit" class="link-danger">遗忘</button>
                    </form>
                </div>

                <p class="timeline-content"><?= nl2br(View::escape($note['content'])) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
