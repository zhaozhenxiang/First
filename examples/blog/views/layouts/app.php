<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Blog Example' ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        header { background: #fff; padding: 20px 0; margin-bottom: 20px; border-bottom: 1px solid #ddd; }
        header h1 { font-size: 24px; }
        nav a { color: #0066cc; text-decoration: none; margin-right: 15px; }
        nav a:hover { text-decoration: underline; }
        .card { background: #fff; padding: 20px; margin-bottom: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 8px 16px; background: #0066cc; color: #fff; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0052a3; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group textarea { min-height: 150px; }
        .alert { padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .badge { display: inline-block; padding: 2px 8px; background: #6c757d; color: #fff; border-radius: 12px; font-size: 12px; }
        .badge-draft { background: #ffc107; }
        .badge-published { background: #28a745; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>📝 Blog Example</h1>
            <nav>
                <a href="/">首页</a>
                <a href="/posts">文章列表</a>
                <?php if ($user ?? null): ?>
                    <a href="/posts/create">写文章</a>
                    <a href="/logout">登出 (<?= htmlspecialchars($user->name) ?>)</a>
                <?php else: ?>
                    <a href="/login">登录</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container">
        <?= $content ?>
    </main>

    <footer style="text-align: center; padding: 20px; color: #999; font-size: 14px;">
        <p>Built with First PHP Framework</p>
    </footer>
</body>
</html>
