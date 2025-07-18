<?php

declare(strict_types=1);

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;
use Exception;

class JobCategoryController
{
    private Medoo $db;
    private Twig $twig;

    public function __construct(Medoo $db, Twig $twig)
    {
        $this->db = $db;
        $this->twig = $twig;
    }

    public function index(Request $request, Response $response): Response
    {
        // Check if it's an AJAX request for data
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return $this->getCategoriesData($request, $response);
        }

        // For initial page load, just render the template
        return $this->twig->render($response, 'job_categories/index.twig', [
            'categories' => []
        ]);
    }

    public function getCategoriesData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $page = isset($params['page']) ? (int) $params['page'] : 1;
            $limit = 10; // Items per page
            $offset = ($page - 1) * $limit;
            $search = isset($params['search']) ? trim($params['search']) : '';

            // Build where conditions
            $whereConditions = [];
            if (!empty($search)) {
                $whereConditions['OR'] = [
                    'name[~]' => $search,
                    'description[~]' => $search,
                    'slug[~]' => $search
                ];
            }

            // Get total count for pagination
            $totalCount = $this->db->count('tbl_job_categories', $whereConditions);

            // Get categories with pagination
            $categories = $this->db->select('tbl_job_categories', [
                'id',
                'name',
                'slug',
                'icon_image',
                'description',
                'job_count'
            ], array_merge($whereConditions, [
                    'ORDER' => ['id' => 'DESC'],
                    'LIMIT' => [$offset, $limit]
                ]));

            // Process image paths
            $projectRoot = __DIR__ . '/../../../'; // Navigasi dari src/Application/Controllers ke root proyek
            foreach ($categories as &$category) {
                if ($category['icon_image']) {
                    // Ensure the path starts with a slash
                    if (!str_starts_with($category['icon_image'], '/')) {
                        $category['icon_image'] = '/uploads/job_categories/' . $category['icon_image'];
                    }

                    // Check if file exists using an absolute path
                    $fullPath = realpath($projectRoot . 'public' . $category['icon_image']);
                    if ($fullPath === false || !file_exists($fullPath)) {
                        // Jika file tidak ada, kosongkan path gambarnya
                        $category['icon_image'] = null;
                    }
                }
            }

            $totalPages = ceil($totalCount / $limit);

            $responseData = [
                'success' => true,
                'data' => $categories,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalCount,
                    'items_per_page' => $limit
                ]
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log("Error in getCategoriesData: " . $e->getMessage());

            $errorResponse = [
                'success' => false,
                'message' => 'Gagal memuat data kategori pekerjaan: ' . $e->getMessage()
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'job_categories/create.twig');
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        try {
            // Validation
            $errors = [];

            if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
                $errors['name'] = 'Nama kategori minimal 2 karakter';
            }

            if (empty($data['slug']) || strlen(trim($data['slug'])) < 2) {
                $errors['slug'] = 'Slug minimal 2 karakter';
            }

            if (!empty($data['description']) && strlen($data['description']) > 1000) {
                $errors['description'] = 'Deskripsi maksimal 1000 karakter';
            }

            // Check for duplicate name
            if (empty($errors['name'])) {
                $existing = $this->db->get('tbl_job_categories', 'id', [
                    'name' => trim($data['name'])
                ]);

                if ($existing) {
                    $errors['name'] = 'Nama kategori sudah ada';
                }
            }

            // Check for duplicate slug
            if (empty($errors['slug'])) {
                $existing = $this->db->get('tbl_job_categories', 'id', [
                    'slug' => trim($data['slug'])
                ]);

                if ($existing) {
                    $errors['slug'] = 'Slug sudah ada';
                }
            }

            // Handle image upload
            $iconImagePath = null;
            if (isset($files['icon_image']) && $files['icon_image']->getError() === UPLOAD_ERR_OK) {
                $uploadedFile = $files['icon_image'];
                $filename = $uploadedFile->getClientFilename();
                $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);

                // Validate file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                if (!in_array(strtolower($fileExtension), $allowedTypes)) {
                    $errors['icon_image'] = 'Format file harus JPG, PNG, GIF, atau SVG';
                } else {
                    // Generate filename with YmdHis format
                    $timestamp = date('YmdHis');
                    $newFilename = $timestamp . '-' . $filename;
                    $uploadPath = 'uploads/job_categories/' . $newFilename;

                    // Create directory if not exists
                    $uploadDir = dirname($uploadPath);
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Move uploaded file
                    $uploadedFile->moveTo($uploadPath);
                    $iconImagePath = '/uploads/job_categories/' . $newFilename;
                }
            }

            if (!empty($errors)) {
                if ($isAjax) {
                    $errorResponse = [
                        'success' => false,
                        'message' => 'Data tidak valid',
                        'errors' => $errors
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                return $this->twig->render($response, 'job_categories/create.twig', [
                    'errors' => $errors,
                    'old' => $data
                ]);
            }

            // Insert new category
            $result = $this->db->insert('tbl_job_categories', [
                'name' => trim($data['name']),
                'slug' => trim($data['slug']),
                'icon_image' => $iconImagePath,
                'description' => !empty($data['description']) ? trim($data['description']) : null,
                'job_count' => 0
            ]);

            if ($result) {
                if ($isAjax) {
                    $successResponse = [
                        'success' => true,
                        'message' => 'Kategori pekerjaan berhasil ditambahkan'
                    ];
                    $response->getBody()->write(json_encode($successResponse));
                    return $response->withHeader('Content-Type', 'application/json');
                }
                return $response->withHeader('Location', '/admin/job-categories')->withStatus(302);
            }

            throw new Exception('Gagal menyimpan data ke database');

        } catch (Exception $e) {
            error_log("Error in store: " . $e->getMessage());

            if ($isAjax) {
                $errorResponse = [
                    'success' => false,
                    'message' => 'Gagal menyimpan data: ' . $e->getMessage()
                ];
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            return $this->twig->render($response, 'job_categories/create.twig', [
                'error' => 'Gagal menyimpan data: ' . $e->getMessage(),
                'old' => $data
            ]);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return $this->edit($request, $response, $args);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        try {
            $category = $this->db->get('tbl_job_categories', '*', [
                'id' => $id
            ]);

            if (!$category) {
                return $response->withHeader('Location', '/admin/job-categories')->withStatus(302);
            }

            return $this->twig->render($response, 'job_categories/edit.twig', [
                'category' => $category
            ]);

        } catch (Exception $e) {
            error_log("Error in edit: " . $e->getMessage());
            return $response->withHeader('Location', '/admin/job-categories')->withStatus(302);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        try {
            // Check if category exists
            $category = $this->db->get('tbl_job_categories', '*', [
                'id' => $id
            ]);

            if (!$category) {
                if ($isAjax) {
                    $errorResponse = [
                        'success' => false,
                        'message' => 'Kategori pekerjaan tidak ditemukan'
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                }
                return $response->withHeader('Location', '/admin/job-categories')->withStatus(302);
            }

            // Validation
            $errors = [];

            if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
                $errors['name'] = 'Nama kategori minimal 2 karakter';
            }

            if (empty($data['slug']) || strlen(trim($data['slug'])) < 2) {
                $errors['slug'] = 'Slug minimal 2 karakter';
            }

            if (!empty($data['description']) && strlen($data['description']) > 1000) {
                $errors['description'] = 'Deskripsi maksimal 1000 karakter';
            }

            // Check for duplicate name (excluding current record)
            if (empty($errors['name'])) {
                $existing = $this->db->get('tbl_job_categories', 'id', [
                    'name' => trim($data['name']),
                    'id[!]' => $id
                ]);

                if ($existing) {
                    $errors['name'] = 'Nama kategori sudah ada';
                }
            }

            // Check for duplicate slug (excluding current record)
            if (empty($errors['slug'])) {
                $existing = $this->db->get('tbl_job_categories', 'id', [
                    'slug' => trim($data['slug']),
                    'id[!]' => $id
                ]);

                if ($existing) {
                    $errors['slug'] = 'Slug sudah ada';
                }
            }

            // Handle image upload
            $iconImagePath = $category['icon_image']; // Keep existing image by default
            if (isset($files['icon_image']) && $files['icon_image']->getError() === UPLOAD_ERR_OK) {
                $uploadedFile = $files['icon_image'];
                $filename = $uploadedFile->getClientFilename();
                $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);

                // Validate file type
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                if (!in_array(strtolower($fileExtension), $allowedTypes)) {
                    $errors['icon_image'] = 'Format file harus JPG, PNG, GIF, atau SVG';
                } else {
                    // Delete old image if exists
                    if ($category['icon_image'] && file_exists('public' . $category['icon_image'])) {
                        unlink('public' . $category['icon_image']);
                    }

                    // Generate filename with YmdHis format
                    $timestamp = date('YmdHis');
                    $newFilename = $timestamp . '-' . $filename;
                    $uploadPath = 'uploads/job_categories/' . $newFilename;

                    // Create directory if not exists
                    $uploadDir = dirname($uploadPath);
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Move uploaded file
                    $uploadedFile->moveTo($uploadPath);
                    $iconImagePath = '/uploads/job_categories/' . $newFilename;
                }
            }

            if (!empty($errors)) {
                if ($isAjax) {
                    $errorResponse = [
                        'success' => false,
                        'message' => 'Data tidak valid',
                        'errors' => $errors
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                return $this->twig->render($response, 'job_categories/edit.twig', [
                    'category' => $category,
                    'errors' => $errors,
                    'old' => $data
                ]);
            }

            // Update category
            $result = $this->db->update('tbl_job_categories', [
                'name' => trim($data['name']),
                'slug' => trim($data['slug']),
                'icon_image' => $iconImagePath,
                'description' => !empty($data['description']) ? trim($data['description']) : null
            ], [
                'id' => $id
            ]);

            if ($result !== false) {
                if ($isAjax) {
                    $successResponse = [
                        'success' => true,
                        'message' => 'Kategori pekerjaan berhasil diperbarui'
                    ];
                    $response->getBody()->write(json_encode($successResponse));
                    return $response->withHeader('Content-Type', 'application/json');
                }
                return $response->withHeader('Location', '/admin/job-categories')->withStatus(302);
            }

            throw new Exception('Gagal memperbarui data ke database');

        } catch (Exception $e) {
            error_log("Error in update: " . $e->getMessage());

            if ($isAjax) {
                $errorResponse = [
                    'success' => false,
                    'message' => 'Gagal memperbarui data: ' . $e->getMessage()
                ];
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            return $this->twig->render($response, 'job_categories/edit.twig', [
                'category' => $category ?? null,
                'error' => 'Gagal memperbarui data: ' . $e->getMessage(),
                'old' => $data
            ]);
        }
    }
}
