<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\FreelanceService;
use Exception;

class FreelanceController extends BaseController
{
    private FreelanceService $freelanceService;
    private AuthController $authController;

    public function __construct(
        ?Request $request = null,
        ?AuthService $authService = null,
        ?FreelanceService $freelanceService = null
    ) {
        parent::__construct($request, $authService);
        $this->freelanceService = $freelanceService ?? new FreelanceService();
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
            Response::redirect('freelance.php');
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

            // Public Unauthenticated APIs
            if ($action === 'get_shared_project') {
                $token = trim((string)$this->request->query('token', ''));
                if (empty($token)) {
                    $this->error('Share token missing.', 400);
                }
                $data = $this->freelanceService->getSharedProject($token);
                if (!$data) {
                    $this->error('Project not found or share link expired.', 404);
                }
                $this->json(array_merge(['success' => true], $data));
                return;
            }

            if ($action === 'get_shared_company') {
                $token = trim((string)$this->request->query('token', ''));
                if (empty($token)) {
                    $this->error('Share token missing.', 400);
                }
                $data = $this->freelanceService->getSharedCompany($token);
                if (!$data) {
                    $this->error('Company not found or share link expired.', 404);
                }
                $this->json(array_merge(['success' => true], $data));
                return;
            }

            // All subsequent APIs require Authentication
            $this->requireAuth();

            $inputData = $this->request->allInput();

            switch ($action) {
                case 'get_tree':
                    $tree = $this->freelanceService->getTree();
                    $this->success(['data' => $tree]);
                    break;

                case 'create_company':
                    $name = trim($inputData['name'] ?? '');
                    if (empty($name)) {
                        $this->error('Company name is required.', 400);
                    }
                    $id = $this->freelanceService->saveCompany($inputData);
                    $this->success(['id' => $id, 'message' => 'Company created successfully!']);
                    break;

                case 'update_company':
                    $id = (int)($inputData['id'] ?? 0);
                    $name = trim($inputData['name'] ?? '');
                    if ($id <= 0 || empty($name)) {
                        $this->error('Valid ID and Company name are required.', 400);
                    }
                    $this->freelanceService->saveCompany($inputData);
                    $this->success(['message' => 'Company updated successfully!']);
                    break;

                case 'delete_company':
                    $id = (int)($inputData['id'] ?? 0);
                    if ($id <= 0) {
                        $this->error('Invalid company ID.', 400);
                    }
                    $this->freelanceService->deleteCompany($id);
                    $this->success(['message' => 'Company and all associated projects/tasks deleted.']);
                    break;

                case 'delete_all_companies':
                    $this->freelanceService->deleteAllCompanies();
                    $this->success(['message' => 'All companies, projects, and tasks have been permanently deleted.']);
                    break;

                case 'seed_demo_data':
                    $this->freelanceService->seedDemoData();
                    $this->success(['message' => 'Sample demo companies, projects, and tasks have been loaded!']);
                    break;

                case 'get_project_details':
                    $id = (int)$this->request->query('id', 0);
                    if ($id <= 0) {
                        $this->error('Invalid project ID.', 400);
                    }
                    $details = $this->freelanceService->getProjectDetails($id);
                    if (!$details) {
                        $this->error('Project not found.', 404);
                    }
                    $this->json(array_merge(['success' => true], $details));
                    break;

                case 'create_project':
                    $companyId = (int)($inputData['company_id'] ?? 0);
                    $title = trim($inputData['title'] ?? '');
                    if ($companyId <= 0 || empty($title)) {
                        $this->error('Company and Project Title are required.', 400);
                    }
                    $id = $this->freelanceService->saveProject($inputData);
                    $this->success(['id' => $id, 'message' => 'Project created successfully!']);
                    break;

                case 'update_project':
                    $id = (int)($inputData['id'] ?? 0);
                    $title = trim($inputData['title'] ?? '');
                    if ($id <= 0 || empty($title)) {
                        $this->error('Valid ID and Project Title are required.', 400);
                    }
                    $this->freelanceService->saveProject($inputData);
                    $this->success(['message' => 'Project updated successfully!']);
                    break;

                case 'update_project_currency':
                    $id = (int)($inputData['id'] ?? 0);
                    $currency = trim($inputData['currency'] ?? 'تومان');
                    $hourlyRate = isset($inputData['hourly_rate']) ? (float)$inputData['hourly_rate'] : null;
                    $applyToTasks = !empty($inputData['apply_to_tasks']);

                    if ($id <= 0) {
                        $this->error('Valid Project ID is required.', 400);
                    }

                    $this->freelanceService->updateProjectCurrency($id, $currency, $hourlyRate, $applyToTasks);
                    $this->success(['message' => 'Project currency and rate updated successfully!']);
                    break;

                case 'delete_project':
                    $id = (int)($inputData['id'] ?? 0);
                    if ($id <= 0) {
                        $this->error('Invalid project ID.', 400);
                    }
                    $this->freelanceService->deleteProject($id);
                    $this->success(['message' => 'Project and its tasks deleted successfully!']);
                    break;

                case 'create_task':
                    $projectId = (int)($inputData['project_id'] ?? 0);
                    $title = trim($inputData['title'] ?? '');
                    if ($projectId <= 0 || empty($title)) {
                        $this->error('Project and Task title are required.', 400);
                    }
                    $id = $this->freelanceService->saveTask($inputData);
                    $this->success(['id' => $id, 'message' => 'Task created successfully!']);
                    break;

                case 'update_task':
                    $id = (int)($inputData['id'] ?? 0);
                    $title = trim($inputData['title'] ?? '');
                    if ($id <= 0 || empty($title)) {
                        $this->error('Valid task ID and title are required.', 400);
                    }
                    $this->freelanceService->saveTask($inputData);
                    $this->success(['message' => 'Task updated successfully!']);
                    break;

                case 'delete_task':
                    $id = (int)($inputData['id'] ?? 0);
                    if ($id <= 0) {
                        $this->error('Invalid task ID.', 400);
                    }
                    $this->freelanceService->deleteTask($id);
                    $this->success(['message' => 'Task deleted successfully!']);
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
        $isPublicProjectShare = !empty($this->request->query('share'));
        $isPublicCompanyShare = !empty($this->request->query('share_company'));
        $isPublicMode = $isPublicProjectShare || $isPublicCompanyShare;

        $this->render('freelance/index', [
            'isPublicProjectShare' => $isPublicProjectShare,
            'isPublicCompanyShare' => $isPublicCompanyShare,
            'isPublicMode' => $isPublicMode,
            'freelanceService' => $this->freelanceService
        ]);
    }
}
