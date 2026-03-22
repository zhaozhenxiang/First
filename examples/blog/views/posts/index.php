<?php

$postsHtml = '';

foreach ($posts as $post) {
    $badgeClass = $post->status === 'published' ? 'badge-published' : 'badge-draft';
    $statusText = $post->status === 'published' ? '已发布' : '草稿';

    $postsHtml .= '
        <div class="card">
            <h2><a href="/posts/' . $post->id . '">' . htmlspecialchars($post->title) . '</a></h2>
            <p>' . nl2br(htmlspecialchars(substr($post->content, 0, 200))) . '...</p>
            <div>
                <span class="badge ' . $badgeClass . '">' . $statusText . '</span>
                <small style="color: #999;">发布于 ' . $post->created_at . '</small>
            </div>
        </div>
    ';
}

$content = '
    <h1>文章列表</h1>
    ' . ($user ?? null ? '<a href="/posts/create" class="btn">写文章</a>' : '') . '
    ' . $postsHtml . '
';

return view('layouts/app.php', [
    'title' => '文章列表',
    'content' => $content,
    'user' => $user,
]);
