<?php

/**
 * Profile page.
 *
 * @var array<string,mixed> $profile
 * @var int $noteCount
 * @var int $itemCount
 * @var list<array<string,mixed>> $decorations
 * @var string $csrfField
 * @var string $csrfToken
 */

use App\Http\View;

$nameClass = '';
$avatarClass = '';
$badge = '';

foreach ($decorations as $decoration) {
    if ($decoration['type'] === 'effect' && $nameClass === '') {
        $nameClass = (string) $decoration['css_class'];
    }

    if ($decoration['type'] === 'avatar_frame' && $avatarClass === '') {
        $avatarClass = (string) $decoration['css_class'];
    }

    if ($decoration['type'] === 'badge') {
        $badge = (string) $decoration['icon'];
    }
}

$avatar = trim((string) ($profile['avatar_path'] ?? '')) !== ''
    ? (string) $profile['avatar_path']
    : '/assets/images/default-avatar.svg';
?>
<section class="panel profile-card">
    <div class="avatar-wrap <?= View::escape($avatarClass) ?>">
        <img class="avatar-large" src="<?= View::escape($avatar) ?>" alt="<?= View::escape($profile['display_name'] ?? $profile['username']) ?> 的头像">
    </div>

    <h1 class="<?= View::escape($nameClass) ?>">
        <?= View::escape($profile['display_name'] ?? $profile['username']) ?>
        <?php if ($badge !== ''): ?><span aria-hidden="true"><?= View::escape($badge) ?></span><?php endif; ?>
    </h1>

    <p class="profile-meta">
        <span class="pill">@<?= View::escape($profile['username']) ?></span>
        <span class="pill"><?= View::escape((string) $profile['role']) ?></span>
        <?php if (!empty($profile['custom_title'])): ?>
            <span class="pill pill-gold"><?= View::escape($profile['custom_title']) ?></span>
        <?php endif; ?>
    </p>

    <?php if (!empty($profile['bio'])): ?>
        <p class="profile-bio"><?= View::escape($profile['bio']) ?></p>
    <?php endif; ?>

    <dl class="stat-grid">
        <div><dt>等级经验</dt><dd><?= (int) $profile['exp'] ?></dd></div>
        <div><dt>星尘余额</dt><dd><?= number_format((int) $profile['stardust']) ?></dd></div>
        <div><dt>思维碎片</dt><dd><?= (int) $noteCount ?></dd></div>
        <div><dt>已收藏遗物</dt><dd><?= (int) $itemCount ?></dd></div>
    </dl>
</section>

<section class="panel">
    <h2>修改档案</h2>

    <form method="post" action="/profile/display-name" class="form">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label for="display_name">显示名称</label>
        <input id="display_name" name="display_name" type="text" required minlength="2" maxlength="48"
               value="<?= View::escape($profile['display_name'] ?? '') ?>">

        <label for="bio">个性签名</label>
        <input id="bio" name="bio" type="text" maxlength="255" value="<?= View::escape($profile['bio'] ?? '') ?>">

        <button type="submit" class="btn btn-primary">保存资料</button>
    </form>
</section>

<section class="panel">
    <h2>更换头像</h2>

    <form method="post" action="/profile/avatar" enctype="multipart/form-data" class="form">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label for="avatar">图片文件</label>
        <input id="avatar" name="avatar" type="file"
               accept="image/jpeg,image/png,image/gif,image/webp" required>
        <p class="field-hint">支持 JPEG、PNG、GIF、WebP，最大 5 MB。上传后会统一转换为 WebP 并限制在 250 像素宽。</p>

        <button type="submit" class="btn btn-primary">上传头像</button>
    </form>
</section>

<section class="panel">
    <h2>重置密钥</h2>

    <form method="post" action="/profile/password" class="form">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label for="password">新密钥</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

        <label for="password_confirm">确认新密钥</label>
        <input id="password_confirm" name="password_confirm" type="password" required minlength="8"
               autocomplete="new-password">
        <p class="field-hint">至少 8 个字符。重置后当前会话会重新生成标识。</p>

        <button type="submit" class="btn btn-primary">重置密钥</button>
    </form>
</section>
