<?php

/**
 * Registration form.
 *
 * @var string $csrfField
 * @var string $csrfToken
 * @var string $error
 */

use App\Http\View;

$error = $error ?? '';
?>
<section class="panel panel-narrow">
    <h1>申请虚空通行证</h1>

    <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= View::escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/register" class="form">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label for="username">代号</label>
        <input id="username" name="username" type="text" required minlength="2" maxlength="32"
               autocomplete="username" autofocus>
        <p class="field-hint">2-32 个字符，可使用中文、字母、数字、下划线和连字符。</p>

        <label for="password">密钥</label>
        <input id="password" name="password" type="password" required minlength="8"
               autocomplete="new-password">
        <p class="field-hint">至少 8 个字符。</p>

        <label for="password_confirm">确认密钥</label>
        <input id="password_confirm" name="password_confirm" type="password" required minlength="8"
               autocomplete="new-password">

        <button type="submit" class="btn btn-primary">注册</button>
    </form>

    <p class="muted">已有通行证？<a href="/login">去登录</a></p>
</section>
