<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\QuestionService;
use Exception;

class QuestionController extends BaseController
{
    private QuestionService $questionService;
    private AuthController $authController;

    public function __construct(
        ?Request $request = null,
        ?AuthService $authService = null,
        ?QuestionService $questionService = null
    ) {
        parent::__construct($request, $authService);
        $this->questionService = $questionService ?? new QuestionService();
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
            Response::redirect('index.php');
            return;
        }

        // 2. Direct GET Export
        if ($this->request->query('export') === 'json') {
            $this->handleExport();
            return;
        }

        // 3. API Actions
        $apiAction = $this->request->query('api_action');
        if ($apiAction !== null) {
            $this->handleApi((string)$apiAction);
            return;
        }

        // 4. File Import POST
        $importMessage = '';
        $importSuccess = false;
        if ($this->request->isPost() && $this->request->hasFile('jsonfile')) {
            $result = $this->handleImport();
            $importMessage = $result['message'];
            $importSuccess = $result['success'];
        }

        // 5. Normal Page Render
        $this->renderIndex($importMessage, $importSuccess);
    }

    public function handleExport(): void
    {
        $exportCategory = $this->request->query('category');
        if ($exportCategory !== null && trim($exportCategory) !== '' && strcasecmp($exportCategory, 'all') !== 0) {
            $fileCatSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower(trim($exportCategory)));
        } else {
            $fileCatSlug = 'all';
            $exportCategory = null;
        }

        $jsonOutput = $this->questionService->getExportJson($exportCategory);
        $filename = 'questions-' . $fileCatSlug . '-' . date('Y-m-d') . '.json';
        Response::download($jsonOutput, $filename);
    }

    public function handleImport(): array
    {
        if (!$this->authService->isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Authentication required. Please log in as admin before importing files.'
            ];
        }

        $file = $this->request->file('jsonfile');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'File upload error. Please try again.'
            ];
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            return [
                'success' => false,
                'message' => 'Could not read the uploaded file.'
            ];
        }

        $replaceExisting = !empty($this->request->post('replace_existing'));
        return $this->questionService->importJson($content, $replaceExisting);
    }

    public function handleApi(string $action): void
    {
        try {
            // Check Auth APIs first (DRY)
            if (in_array($action, ['login', 'logout', 'check_auth'], true)) {
                $this->authController->handleApi($action);
                return;
            }

            // All remaining API actions require Authentication
            $this->requireAuth();

            switch ($action) {
                case 'add':
                    $question = trim((string)$this->request->input('question', ''));
                    $answer = trim((string)$this->request->input('answer', ''));
                    $category = trim((string)$this->request->input('category', ''));

                    if ($question === '' || $answer === '') {
                        $this->error('Question and answer cannot be empty.');
                    }

                    $newId = $this->questionService->addQuestion($question, $answer, $category ?: 'General');
                    $this->success([
                        'id' => $newId,
                        'question' => $question,
                        'answer' => $answer,
                        'category' => $category ?: 'General',
                        'question_html' => $this->questionService->renderMarkdown($question),
                        'answer_html' => $this->questionService->renderMarkdown($answer)
                    ]);
                    break;

                case 'edit':
                    $id = (int)$this->request->input('id', 0);
                    $question = trim((string)$this->request->input('question', ''));
                    $answer = trim((string)$this->request->input('answer', ''));
                    $category = trim((string)$this->request->input('category', ''));

                    if ($id <= 0 || $question === '' || $answer === '') {
                        $this->error('Question and answer cannot be empty.');
                    }

                    $this->questionService->updateQuestion($id, $question, $answer, $category ?: 'General');
                    $this->success([
                        'id' => $id,
                        'question' => $question,
                        'answer' => $answer,
                        'category' => $category ?: 'General',
                        'question_html' => $this->questionService->renderMarkdown($question),
                        'answer_html' => $this->questionService->renderMarkdown($answer)
                    ]);
                    break;

                case 'delete':
                    $id = (int)$this->request->input('id', 0);
                    if ($id <= 0) {
                        $this->error('Invalid question ID.');
                    }
                    $this->questionService->deleteQuestion($id);
                    $this->success(['id' => $id]);
                    break;

                case 'add_category':
                    $name = trim((string)$this->request->input('name', ''));
                    $description = trim((string)$this->request->input('description', ''));
                    $image = trim((string)$this->request->input('image', ''));

                    if ($name === '') {
                        $this->error('Card title is required.');
                    }

                    $this->questionService->addCategory($name, $description !== '' ? $description : null, $image !== '' ? $image : null);
                    $this->success([
                        'name' => $name,
                        'description' => $description !== '' ? $description : null,
                        'image' => $image !== '' ? $image : null
                    ]);
                    break;

                case 'update_category':
                    $oldName = trim((string)$this->request->input('old_name', ''));
                    $newName = trim((string)$this->request->input('name', ''));
                    $description = trim((string)$this->request->input('description', ''));
                    $image = trim((string)$this->request->input('image', ''));

                    if ($oldName === '' || $newName === '') {
                        $this->error('Card title is required.');
                    }

                    $updated = $this->questionService->updateCategory($oldName, $newName, $description !== '' ? $description : null, $image !== '' ? $image : null);
                    $this->success([
                        'old_name' => $oldName,
                        'name' => $newName,
                        'description' => $description !== '' ? $description : null,
                        'image' => $image !== '' ? $image : null,
                        'updated' => $updated
                    ]);
                    break;

                case 'rename_category':
                    $oldCategory = trim((string)$this->request->input('old_category', ''));
                    $newCategory = trim((string)$this->request->input('new_category', ''));

                    if ($oldCategory === '' || $newCategory === '') {
                        $this->error('Category names are required.');
                    }

                    $updated = $this->questionService->renameCategory($oldCategory, $newCategory);
                    $this->success([
                        'old_category' => $oldCategory,
                        'new_category' => $newCategory,
                        'updated' => $updated
                    ]);
                    break;

                case 'delete_category':
                    $category = trim((string)$this->request->input('category', ''));
                    if ($category === '') {
                        $this->error('Category name is required.');
                    }

                    $deleted = $this->questionService->deleteCategory($category);
                    $this->success([
                        'category' => $category,
                        'deleted' => $deleted
                    ]);
                    break;

                default:
                    $this->error('Unknown API action.');
                    break;
            }
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function renderIndex(string $importMessage = '', bool $importSuccess = false): void
    {
        $selectedCategory = $this->request->query('category');
        $totalQuestionsCount = $this->questionService->countAll();
        $categories = $this->questionService->getCategories();

        if ($selectedCategory) {
            $questions = $this->questionService->getQuestions($selectedCategory);
        } else {
            $questions = [];
        }

        $questionsJson = json_encode(array_map(fn($q) => [
            'id' => $q->id,
            'question' => $q->question,
            'answer' => $q->answer,
            'category' => $q->category,
            'question_html' => $this->questionService->renderMarkdown($q->question),
            'answer_html' => $this->questionService->renderMarkdown($q->answer),
        ], $selectedCategory ? $questions : []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $this->render('questions/index', [
            'totalQuestionsCount' => $totalQuestionsCount,
            'selectedCategory' => $selectedCategory,
            'questions' => $questions,
            'categories' => $categories,
            'questionsJson' => $questionsJson,
            'importMessage' => $importMessage,
            'importSuccess' => $importSuccess,
            'questionService' => $this->questionService
        ]);
    }
}
