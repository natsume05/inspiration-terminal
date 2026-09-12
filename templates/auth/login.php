<?php

/**
 * Login form.
 *
 * @var string $csrfField
 * @var string $csrfToken
 * @var string $error
 */

use App\Http\View;

$error = $error ?? '';
?>
<section class="panel panel-narrow">
    <h1>连接虚空网络</h1>

    <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= View::escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/login" class="form">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label for="username">代号</label>
        <input id="username" name="username" type="text" required maxlength="32"
               autocomplete="username" autofocus>

        <label for="password">密钥</label>
        <input id="password" name="password" type="password" required
               autocomplete="current-password">

        <button type="submit" class="btn btn-primary">接入</button>
    </form>

    <p class="muted">还没有账号？<a href="/register">注册新身份</a></p>
</section>
