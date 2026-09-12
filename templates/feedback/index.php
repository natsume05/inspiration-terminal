<?php

/**
 * Feedback submission form.
 *
 * @var string $csrfField
 * @var string $csrfToken
 */

use App\Http\View;
?>
<section class="panel panel-narrow-wide">
    <h1>信号塔</h1>
    <p class="muted">提交缺陷、建议或问题。管理员回复后你会在「信号记录」中收到通知。</p>

    <form method="post" action="/feedback" class="form">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label for="type">类型</label>
        <select id="type" name="type">
            <option value="bug">缺陷报告</option>
            <option value="suggestion">功能建议</option>
            <option value="question">使用疑问</option>
            <option value="other" selected>其他</option>
        </select>

        <label for="content">内容</label>
        <textarea id="content" name="content" rows="6" required minlength="5" maxlength="2000"
                  placeholder="请尽量描述清楚：你做了什么、期望发生什么、实际发生了什么。"></textarea>

        <button type="submit" class="btn btn-primary">发送信号</button>
    </form>
</section>
