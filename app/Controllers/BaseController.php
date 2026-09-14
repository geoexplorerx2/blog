<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\AuthService;

abstract class BaseController
{
    protected Request $request;
    protected AuthService $authService;

    public function __construct(?Request $request = null, ?AuthService $authService = null)
    {
        $this->request = $request ?? Request::createFromGlobals();
        $this->authService = $authService ?? new AuthService();
    }

    protected function render(string $viewPath, array $data = []): void
    {
        $data['isLoggedIn'] = $this->authService->isLoggedIn();
        $data['currentUser'] = $this->authService->getCurrentUser();
        View::render($viewPath, $data);
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    protected function success(array $data = [], int $statusCode = 200): void
    {
        Response::success($data, $statusCode);
    }

    protected function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        Response::error($message, $statusCode, $extra);
    }

    protected function requireAuth(): void
    {
        if (!$this->authService->isLoggedIn()) {
            $this->error('Authentication required.', 401, ['require_login' => true]);
        }
    }
}
