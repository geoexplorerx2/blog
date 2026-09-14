<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AuthController extends BaseController
{
    public function handleApi(string $action): void
    {
        switch ($action) {
            case 'login':
                $username = trim((string)$this->request->input('username', ''));
                $password = (string)$this->request->input('password', '');

                if ($username === '' || $password === '') {
                    $this->error('Username and password are required.');
                }

                if ($this->authService->login($username, $password)) {
                    $this->success(['username' => $this->authService->getCurrentUser()]);
                } else {
                    $this->error('Invalid username or password.', 401);
                }
                break;

            case 'logout':
                $this->authService->logout();
                $this->success();
                break;

            case 'check_auth':
                $this->json([
                    'success' => true,
                    'authenticated' => $this->authService->isLoggedIn(),
                    'username' => $this->authService->getCurrentUser()
                ]);
                break;

            default:
                $this->error('Unknown authentication action.');
                break;
        }
    }
}
