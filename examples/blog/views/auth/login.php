<?php

$error = $_GET['error'] ?? null;

$content = '
    <div style="max-width: 400px; margin: 50px auto;">
        <h1 style="text-align: center;">登录</h1>

        ' . ($error ? '<div class="alert alert-error">' . htmlspecialchars($error) . '</div>' : '') . '

        <form method="POST" action="/login" class="card">
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn" style="width: 100%;">登录</button>
        </form>

        <p style="text-align: center; margin-top: 15px;">
            测试账号: admin@example.com / password
        </p>
    </div>
';

return view('layouts/app.php', [
    'title' => '登录',
    'content' => $content,
    'user' => null,
]);
