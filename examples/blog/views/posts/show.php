<?php

$content = '
    <article class="card">
        <h1>' . htmlspecialchars($post->title) . '</h1>
        <div style="margin-bottom: 20px; color: #999;">
            发布于 ' . $post->created_at . '
        </div>
        <div style="line-height: 1.8;">
            ' . nl2br(htmlspecialchars($post->content)) . '
        </div>
    </article>

    <a href="/posts" class="btn">← 返回列表</a>
';

return view('layouts/app.php', [
    'title' => $post->title,
    'content' => $content,
    'user' => $user,
]);
