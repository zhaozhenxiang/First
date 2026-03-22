<?php

$content = '
<div class="card">
    <h2>欢迎使用 First PHP 框架示例应用</h2>
    <p>这是一个简单的博客应用，展示了框架的核心功能：</p>
    <ul>
        <li><strong>MVC 架构</strong> - 模型-视图-控制器分离</li>
        <li><strong>路由系统</strong> - 灵活的路由定义和中间件</li>
        <li><strong>ORM</strong> - 优雅的数据库操作</li>
        <li><strong>认证系统</strong> - 用户登录和权限管理</li>
        <li><strong>API 资源</strong> - JSON API 响应转换</li>
        <li><strong>视图模板</strong> - 简洁的模板引擎</li>
    </ul>
</div>

<div class="card">
    <h3>快速开始</h3>
    <ol>
        <li><a href="/login">登录</a> (admin@example.com / password)</li>
        <li><a href="/posts">浏览文章</a></li>
        <li><a href="/posts/create">创建文章</a></li>
    </ol>
</div>

<div class="card">
    <h3>API 接口</h3>
    <ul>
        <li><code>GET /api/posts</code> - 获取文章列表</li>
        <li><code>GET /api/posts/{id}</code> - 获取单篇文章</li>
        <li><code>POST /api/login</code> - 用户登录</li>
    </ul>
</div>
';

return view('layouts/app.php', [
    'title' => '首页',
    'content' => $content,
    'user' => null,
]);
