<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Bin\Auth\AuthManager;
use Bin\Auth\HashManager;
use Bin\Session\SessionManager;

class AuthController
{
    /**
     * 显示登录表单
     */
    public function loginForm(): string
    {
        return view('auth/login.php');
    }

    /**
     * 处理登录
     */
    public function login(): never
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = User::where('email', $email)->first();

        if ($user && HashManager::check($password, $user->password)) {
            AuthManager::login($user);

            redirect('/posts');
        }

        redirect('/login?error=Invalid credentials');
    }

    /**
     * 登出
     */
    public function logout(): never
    {
        AuthManager::logout();

        redirect('/login');
    }

    /**
     * API: 登录
     */
    public function apiLogin(): string
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (AuthManager::attempt(['email' => $email, 'password' => $password])) {
            $user = AuthManager::user();

            return response([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 200);
        }

        return response([
            'message' => 'Invalid credentials',
        ], 401);
    }

    /**
     * API: 登出
     */
    public function apiLogout(): string
    {
        AuthManager::logout();

        return response(['message' => 'Logged out'], 200);
    }
}
