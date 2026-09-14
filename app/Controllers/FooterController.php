<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\FooterService;
use Exception;

class FooterController extends BaseController
{
    private FooterService $footerService;

    public function __construct(
        ?Request $request = null,
        ?AuthService $authService = null,
        ?FooterService $footerService = null
    ) {
        parent::__construct($request, $authService);
        $this->footerService = $footerService ?? new FooterService();
    }

    public function handleApi(): void
    {
        try {
            $this->requireAuth();
            $inputData = $this->request->allInput();
            $this->footerService->saveSettings($inputData);
            $this->success(['message' => 'Footer settings updated successfully!']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public static function renderComponent(): void
    {
        $footerService = new FooterService();
        $authService = new AuthService();
        $settings = $footerService->getSettingsArray();

        View::render('components/footer', [
            'footer' => $settings,
            'isLoggedIn' => $authService->isLoggedIn(),
            'currentUser' => $authService->getCurrentUser(),
        ]);
    }
}
