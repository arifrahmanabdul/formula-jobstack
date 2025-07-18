<?php

declare(strict_types=1);

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;
use Exception;

class IndustryController
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
            return $this->getIndustriesData($request, $response);
        }

        // For initial page load, just render the template
        return $this->twig->render($response, 'industries/index.twig', [
            'industries' => []
        ]);
    }

    public function getIndustriesData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $limit = 10; // Items per page
            $offset = ($page - 1) * $limit;
            $search = isset($params['search']) ? trim($params['search']) : '';

            // Build where conditions
            $whereConditions = ['tbl_industries.archived' => 0];
            if (!empty($search)) {
                $whereConditions['tbl_industries.name[~]'] = $search;
            }

            // Get total count for pagination
            $totalCount = $this->db->count('tbl_industries', $whereConditions);

            // Get industries with pagination
            $industries = $this->db->select('tbl_industries', [
                '[>]tbl_users' => ['create_id' => 'id']
            ], [
                'tbl_industries.id',
                'tbl_industries.name',
                'tbl_industries.description',
                'tbl_industries.create_time',
                'tbl_users.full_name(creator_name)'
            ], array_merge($whereConditions, [
                'ORDER' => ['tbl_industries.create_time' => 'DESC'],
                'LIMIT' => [$offset, $limit]
            ]));

            $totalPages = ceil($totalCount / $limit);

            $responseData = [
                'success' => true,
                'data' => $industries,
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
            error_log("Error in getIndustriesData: " . $e->getMessage());
            
            $errorResponse = [
                'success' => false,
                'message' => 'Gagal memuat data industri: ' . $e->getMessage()
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'industries/create.twig');
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        try {
            // Validation
            $errors = [];
            
            if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
                $errors['name'] = 'Nama industri minimal 2 karakter';
            }

            if (!empty($data['description']) && strlen($data['description']) > 500) {
                $errors['description'] = 'Deskripsi maksimal 500 karakter';
            }

            // Check for duplicate name
            if (empty($errors['name'])) {
                $existing = $this->db->get('tbl_industries', 'id', [
                    'name' => trim($data['name']),
                    'archived' => 0
                ]);
                
                if ($existing) {
                    $errors['name'] = 'Nama industri sudah ada';
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
                return $this->twig->render($response, 'industries/create.twig', [
                    'errors' => $errors,
                    'old' => $data
                ]);
            }

            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? 1;

            // Insert new industry
            $result = $this->db->insert('tbl_industries', [
                'name' => trim($data['name']),
                'description' => !empty($data['description']) ? trim($data['description']) : null,
                'create_time' => date('Y-m-d H:i:s'),
                'create_id' => $userId,
                'archived' => 0
            ]);

            if ($result) {
                if ($isAjax) {
                    $successResponse = [
                        'success' => true,
                        'message' => 'Industri berhasil ditambahkan'
                    ];
                    $response->getBody()->write(json_encode($successResponse));
                    return $response->withHeader('Content-Type', 'application/json');
                }
                return $response->withHeader('Location', '/admin/industries')->withStatus(302);
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

            return $this->twig->render($response, 'industries/create.twig', [
                'error' => 'Gagal menyimpan data: ' . $e->getMessage(),
                'old' => $data
            ]);
        }
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        
        try {
            $industry = $this->db->get('tbl_industries', '*', [
                'id' => $id,
                'archived' => 0
            ]);

            if (!$industry) {
                if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                    $errorResponse = [
                        'success' => false,
                        'message' => 'Industri tidak ditemukan'
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                }
                return $response->withHeader('Location', '/admin/industries')->withStatus(302);
            }

            if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                $successResponse = [
                    'success' => true,
                    'data' => $industry
                ];
                $response->getBody()->write(json_encode($successResponse));
                return $response->withHeader('Content-Type', 'application/json');
            }

            return $this->twig->render($response, 'industries/edit.twig', [
                'industry' => $industry
            ]);

        } catch (Exception $e) {
            error_log("Error in edit: " . $e->getMessage());
            
            if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                $errorResponse = [
                    'success' => false,
                    'message' => 'Gagal memuat data industri: ' . $e->getMessage()
                ];
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            return $response->withHeader('Location', '/admin/industries')->withStatus(302);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        try {
            // Check if industry exists
            $industry = $this->db->get('tbl_industries', '*', [
                'id' => $id,
                'archived' => 0
            ]);

            if (!$industry) {
                if ($isAjax) {
                    $errorResponse = [
                        'success' => false,
                        'message' => 'Industri tidak ditemukan'
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                }
                return $response->withHeader('Location', '/admin/industries')->withStatus(302);
            }

            // Validation
            $errors = [];
            
            if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
                $errors['name'] = 'Nama industri minimal 2 karakter';
            }

            if (!empty($data['description']) && strlen($data['description']) > 500) {
                $errors['description'] = 'Deskripsi maksimal 500 karakter';
            }

            // Check for duplicate name (excluding current record)
            if (empty($errors['name'])) {
                $existing = $this->db->get('tbl_industries', 'id', [
                    'name' => trim($data['name']),
                    'id[!]' => $id,
                    'archived' => 0
                ]);
                
                if ($existing) {
                    $errors['name'] = 'Nama industri sudah ada';
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
                return $this->twig->render($response, 'industries/edit.twig', [
                    'industry' => $industry,
                    'errors' => $errors,
                    'old' => $data
                ]);
            }

            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? 1;

            // Update industry
            $result = $this->db->update('tbl_industries', [
                'name' => trim($data['name']),
                'description' => !empty($data['description']) ? trim($data['description']) : null,
                'update_time' => date('Y-m-d H:i:s'),
                'update_id' => $userId
            ], [
                'id' => $id
            ]);

            if ($result !== false) {
                if ($isAjax) {
                    $successResponse = [
                        'success' => true,
                        'message' => 'Industri berhasil diperbarui'
                    ];
                    $response->getBody()->write(json_encode($successResponse));
                    return $response->withHeader('Content-Type', 'application/json');
                }
                return $response->withHeader('Location', '/admin/industries')->withStatus(302);
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

            return $this->twig->render($response, 'industries/edit.twig', [
                'industry' => $industry ?? null,
                'error' => 'Gagal memperbarui data: ' . $e->getMessage(),
                'old' => $data
            ]);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        // Log the delete request
        error_log("Delete request received - ID: {$id}, AJAX: " . ($isAjax ? 'true' : 'false'));

        try {
            // Validate ID
            if ($id <= 0) {
                throw new Exception('ID industri tidak valid');
            }

            // Check if industry exists
            $industry = $this->db->get('tbl_industries', '*', [
                'id' => $id
            ]);

            if (!$industry) {
                $message = 'Industri tidak ditemukan';
                error_log("Industry not found - ID: {$id}");
                
                if ($isAjax) {
                    $errorResponse = [
                        'success' => false,
                        'message' => $message
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                }
                return $response->withHeader('Location', '/admin/industries')->withStatus(302);
            }

            // Check if already archived
            if ($industry['archived'] == 1) {
                $message = 'Industri sudah dihapus sebelumnya';
                error_log("Industry already archived - ID: {$id}");
                
                if ($isAjax) {
                    $errorResponse = [
                        'success' => false,
                        'message' => $message
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                return $response->withHeader('Location', '/admin/industries')->withStatus(302);
            }

            // Check if industry is used by companies
            $companyCount = $this->db->count('tbl_companies', [
                'id_industry' => $id,
                'archived' => 0
            ]);

            if ($companyCount > 0) {
                $message = "Industri tidak dapat dihapus karena masih digunakan oleh {$companyCount} perusahaan";
                error_log("Industry in use - ID: {$id}, Companies: {$companyCount}");
                
                if ($isAjax) {
                    $errorResponse = [
                        'success' => false,
                        'message' => $message
                    ];
                    $response->getBody()->write(json_encode($errorResponse));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                return $response->withHeader('Location', '/admin/industries')->withStatus(302);
            }

            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? 1;

            // Soft delete (archive) the industry
            $updateResult = $this->db->update('tbl_industries', [
                'archived' => 1,
                'update_time' => date('Y-m-d H:i:s'),
                'update_id' => $userId
            ], [
                'id' => $id
            ]);

            error_log("Update result: " . ($updateResult !== false ? 'success' : 'failed'));

            if ($updateResult === false) {
                throw new Exception('Gagal memperbarui status industri di database');
            }

            // Verify the update was successful
            $updatedIndustry = $this->db->get('tbl_industries', 'archived', ['id' => $id]);
            
            if ($updatedIndustry != 1) {
                throw new Exception('Verifikasi update gagal - status archived tidak berubah');
            }

            $message = 'Industri berhasil dihapus';
            error_log("Industry successfully archived - ID: {$id}");
            
            if ($isAjax) {
                $successResponse = [
                    'success' => true,
                    'message' => $message
                ];
                $response->getBody()->write(json_encode($successResponse));
                return $response->withHeader('Content-Type', 'application/json');
            }
            return $response->withHeader('Location', '/admin/industries')->withStatus(302);

        } catch (Exception $e) {
            error_log("Delete industry error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
    
            $message = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            
            if ($isAjax) {
                $errorResponse = [
                    'success' => false,
                    'message' => $message
                ];
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            return $response->withHeader('Location', '/admin/industries')->withStatus(302);
        }
    }
}
