<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use App\Models\User;
use Bin\Auth\AuthManager;
use Bin\Resource\JsonResource;

/**
 * 文章资源类
 */
class PostResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'title' => $this->resource['title'],
            'content' => $this->resource['content'],
            'status' => $this->resource['status'],
            'published_at' => $this->resource['published_at']?->format('Y-m-d H:i:s'),
            'created_at' => $this->resource['created_at']->format('Y-m-d H:i:s'),
        ];
    }
}

class PostController
{
    /**
     * 列出所有文章
     */
    public function index(): string
    {
        $posts = Post::published()->orderBy('created_at', 'desc')->get();

        return view('posts/index.php', [
            'posts' => $posts,
            'user' => AuthManager::user(),
        ]);
    }

    /**
     * 显示单篇文章
     */
    public function show(int $id): string
    {
        $post = Post::findOrFail($id);

        return view('posts/show.php', [
            'post' => $post,
            'user' => AuthManager::user(),
        ]);
    }

    /**
     * 创建文章表单
     */
    public function create(): string
    {
        if (!AuthManager::check()) {
            redirect('/login');
        }

        return view('posts/create.php', [
            'user' => AuthManager::user(),
        ]);
    }

    /**
     * 保存文章
     */
    public function store(): never
    {
        if (!AuthManager::check()) {
            response('Unauthorized', 401)->send();
        }

        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';

        if (empty($title) || empty($content)) {
            redirect('/posts/create');
        }

        Post::create([
            'title' => $title,
            'content' => $content,
            'user_id' => AuthManager::id(),
            'status' => 'draft',
        ]);

        redirect('/posts');
    }

    /**
     * API: 获取所有文章
     */
    public function apiIndex(): string
    {
        $posts = Post::published()->orderBy('created_at', 'desc')->get();

        $collection = PostResource::collection($posts);

        return response($collection->jsonSerialize(), 200);
    }

    /**
     * API: 获取单篇文章
     */
    public function apiShow(int $id): string
    {
        $post = Post::findOrFail($id);

        $resource = new PostResource($post->toArray());

        return response($resource->jsonSerialize(), 200);
    }
}
