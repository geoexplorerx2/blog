<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\ProfileService;
use Exception;

class ProfileController extends BaseController
{
    private ProfileService $profileService;
    private AuthController $authController;

    public function __construct(
        ?Request $request = null,
        ?AuthService $authService = null,
        ?ProfileService $profileService = null
    ) {
        parent::__construct($request, $authService);
        $this->profileService = $profileService ?? new ProfileService();
        $this->authController = new AuthController($this->request, $this->authService);
    }

    public function handle(): void
    {
        // 1. Direct GET Logout
        if (
            ($this->request->query('action') === 'logout') ||
            ($this->request->query('api_action') === 'logout' && $this->request->isGet())
        ) {
            $this->authService->logout();
            Response::redirect('profile.php');
            return;
        }

        // 2. API Actions
        $apiAction = $this->request->query('api_action');
        if ($apiAction !== null) {
            $this->handleApi((string)$apiAction);
            return;
        }

        // 3. Normal Page Render
        $this->renderIndex();
    }

    public function handleApi(string $action): void
    {
        try {
            // Check Auth APIs first (DRY)
            if (in_array($action, ['login', 'logout', 'check_auth'], true)) {
                $this->authController->handleApi($action);
                return;
            }

            // Modifying profile requires authentication
            $this->requireAuth();

            switch ($action) {
                case 'save_resume':
                    $inputData = $this->request->allInput();
                    $this->profileService->saveProfile($inputData);
                    $this->success(['message' => 'Resume updated successfully!']);
                    break;

                default:
                    $this->error('Unknown API action.', 404);
                    break;
            }
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function renderIndex(): void
    {
        $profile = $this->profileService->getProfile();
        $profileData = $profile ? $profile->toArray() : [];

        $skills = $profileData['skills'] ?? [];
        $experiences = $profileData['experiences'] ?? [];
        $courses = $profileData['courses'] ?? [];
        $languages = $profileData['languages'] ?? [];
        $software = $profileData['software'] ?? [];

        $this->render('profile/index', [
            'profile' => $profileData,
            'skills' => $skills,
            'experiences' => $experiences,
            'courses' => $courses,
            'languages' => $languages,
            'software' => $software,
            'profileService' => $this->profileService
        ]);
    }
}
