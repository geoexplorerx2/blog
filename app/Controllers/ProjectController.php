<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\ProjectService;
use Exception;

class ProjectController extends BaseController
{
    private ProjectService $projectService;
    private AuthController $authController;

    public function __construct(
        ?Request $request = null,
        ?AuthService $authService = null,
        ?ProjectService $projectService = null
    ) {
        parent::__construct($request, $authService);
        $this->projectService = $projectService ?? new ProjectService();
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
            Response::redirect('projects.php');
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

            if ($action === 'get_projects') {
                $projects = $this->projectService->getAllProjectsArray();
                $this->success(['data' => $projects]);
                return;
            }

            // All modifying project APIs require authentication
            $this->requireAuth();

            switch ($action) {
                case 'save_project':
                    $inputData = $this->request->allInput();
                    $title = trim($inputData['title'] ?? '');
                    $description = trim($inputData['description'] ?? '');

                    if (empty($title) || empty($description)) {
                        $this->error('Project Title and Description are required.', 400);
                    }

                    $id = $this->projectService->saveProject($inputData);
                    $this->success(['id' => $id, 'message' => 'Project saved successfully!']);
                    break;

                case 'delete_project':
                    $id = (int)$this->request->input('id', 0);
                    if ($id <= 0) {
                        $this->error('Invalid project ID.', 400);
                    }
                    $this->projectService->deleteProject($id);
                    $this->success(['id' => $id, 'message' => 'Project deleted successfully!']);
                    break;

                case 'toggle_featured':
                    $id = (int)$this->request->input('id', 0);
                    if ($id <= 0) {
                        $this->error('Invalid project ID.', 400);
                    }
                    $this->projectService->toggleFeatured($id);
                    $this->success(['id' => $id, 'message' => 'Featured status updated.']);
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
        $projects = $this->projectService->getAllProjects();
        $this->render('projects/index', [
            'projects' => $projects,
            'projectService' => $this->projectService
        ]);
    }
}
