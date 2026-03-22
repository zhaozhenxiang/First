<?php

$content = '
    <h1>创建文章</h1>

    <form method="POST" action="/posts" class="card">
        <div class="form-group">
            <label>标题</label>
            <input type="text" name="title" required>
        </div>

        <div class="form-group">
            <label>内容</label>
            <textarea name="content" required></textarea>
        </div>

        <button type="submit" class="btn">保存草稿</button>
        <a href="/posts" class="btn" style="background: #6c757d;">取消</a>
    </form>
';

return view('layouts/app.php', [
    'title' => '创建文章',
    'content' => $content,
    'user' => $user,
]);
