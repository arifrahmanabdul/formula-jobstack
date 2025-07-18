<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class SkillController
{
    private Twig $view;
    private Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    public function index(Request $request, Response $response): Response
    {
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return $this->getSkillsData($request, $response);
        }
        $user = $_SESSION['user'];
        $template = $user['role'] === 'admin' ? 'skills/admin/index.twig' : 'skills/recruiter/index.twig';
        return $this->view->render($response, $template, ['skills' => []]);
    }

    public function getSkillsData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = isset($params['search']) ? trim($params['search']) : '';

            $whereConditions = ['archived' => 0];
            if (!empty($search)) {
                $whereConditions['name[~]'] = $search;
            }

            $totalCount = $this->db->count('tbl_skills', $whereConditions);
            $skills = $this->db->select('tbl_skills', ['id', 'name'], array_merge($whereConditions, [
                'ORDER' => ['name' => 'ASC'],
                'LIMIT' => [$offset, $limit]
            ]));
            
            $totalPages = ceil($totalCount / $limit);

            $responseData = [
                'success' => true,
                'data' => $skills ?: [],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalCount,
                    'items_per_page' => $limit
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'error' => 'Gagal memuat data skills: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($errorData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    public function create(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        $template = $user['role'] === 'admin' ? 'skills/admin/create.twig' : 'skills/recruiter/create.twig';
        return $this->view->render($response, $template);
    }

    public function store(Request $request, Response $response): Response
    {
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
        
        if (!$isAjax) {
            return $response->withStatus(405);
        }

        try {
            // Get data from request body (both form data and JSON)
            $data = $request->getParsedBody();
            
            // If parsed body is null, try to get from form data
            if (empty($data)) {
                $data = [];
                parse_str((string)$request->getBody(), $data);
            }
            
            $skillName = trim($data['name'] ?? '');

            // Validation
            if (empty($skillName)) {
                throw new \Exception('Nama skill tidak boleh kosong');
            }

            if (strlen($skillName) > 100) {
                throw new \Exception('Nama skill terlalu panjang (maksimal 100 karakter)');
            }

            // Check for existing skill
            $existing = $this->db->get('tbl_skills', 'id', [
                'name' => $skillName,
                'archived' => 0
            ]);
            
            if ($existing) {
                throw new \Exception('Skill dengan nama tersebut sudah ada');
            }

            // Insert new skill
            $insertResult = $this->db->insert('tbl_skills', [
                'name' => $skillName,
                'archived' => 0
            ]);

            if (!$insertResult) {
                $error = $this->db->pdo->errorInfo();
                throw new \Exception('Gagal menyimpan skill ke database: ' . ($error[2] ?? 'Unknown error'));
            }

            $lastId = $this->db->id();
            
            if (!$lastId) {
                throw new \Exception('Gagal mendapatkan ID skill yang baru dibuat');
            }
            
            $responseData = [
                'success' => true,
                'message' => 'Skill berhasil ditambahkan!',
                'data' => [
                    'id' => (int)$lastId,
                    'name' => $skillName
                ]
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            $response->getBody()->write(json_encode($errorData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
        $skillId = (int)($args['id'] ?? 0);
        
        if (!$isAjax) {
            // Non-AJAX request - render edit form
            $user = $_SESSION['user'];
            $skill = $this->db->get('tbl_skills', '*', ['id' => $skillId, 'archived' => 0]);
            
            if (!$skill) {
                $redirect = $user['role'] === 'admin' ? '/admin/skills' : '/recruiter/skills';
                return $response->withHeader('Location', $redirect)->withStatus(302);
            }
            
            $template = $user['role'] === 'admin' ? 'skills/admin/edit.twig' : 'skills/recruiter/edit.twig';
            return $this->view->render($response, $template, compact('skill'));
        }

        // AJAX request - return skill data
        try {
            if ($skillId <= 0) {
                throw new \Exception('ID skill tidak valid');
            }
            
            $skill = $this->db->get('tbl_skills', ['id', 'name'], [
                'id' => $skillId,
                'archived' => 0
            ]);
            
            if (!$skill) {
                throw new \Exception('Skill tidak ditemukan');
            }

            $responseData = [
                'success' => true,
                'data' => $skill
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            $response->getBody()->write(json_encode($errorData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    }

    public function update(Request $request, Response $response, $args): Response
    {
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
        $skillId = (int)($args['id'] ?? 0);

        if (!$isAjax) {
            // Non-AJAX request - handle form submission and redirect
            $data = $request->getParsedBody();
            $user = $_SESSION['user'];
            
            try {
                $this->db->update('tbl_skills', ['name' => $data['name']], ['id' => $skillId]);
                $redirect = $user['role'] === 'admin' ? '/admin/skills' : '/recruiter/skills';
                return $response->withHeader('Location', $redirect)->withStatus(302);
            } catch (\Exception $e) {
                $redirect = $user['role'] === 'admin' ? '/admin/skills' : '/recruiter/skills';
                return $response->withHeader('Location', $redirect)->withStatus(302);
            }
        }

        // AJAX request - handle update
        try {
            // Get data from request body
            $data = $request->getParsedBody();
            
            // If parsed body is null, try to get from form data
            if (empty($data)) {
                $data = [];
                parse_str((string)$request->getBody(), $data);
            }
            
            $skillName = trim($data['name'] ?? '');

            // Validation
            if (empty($skillName)) {
                throw new \Exception('Nama skill tidak boleh kosong');
            }

            if (strlen($skillName) > 100) {
                throw new \Exception('Nama skill terlalu panjang (maksimal 100 karakter)');
            }

            if ($skillId <= 0) {
                throw new \Exception('ID skill tidak valid');
            }

            // Check if skill exists
            $existingSkill = $this->db->get('tbl_skills', ['id', 'name'], [
                'id' => $skillId,
                'archived' => 0
            ]);
            
            if (!$existingSkill) {
                throw new \Exception('Skill yang akan diupdate tidak ditemukan');
            }

            // Check for duplicate name (excluding current skill)
            $duplicate = $this->db->get('tbl_skills', 'id', [
                'name' => $skillName,
                'id[!]' => $skillId,
                'archived' => 0
            ]);
            
            if ($duplicate) {
                throw new \Exception('Skill dengan nama tersebut sudah ada');
            }

            // Update the skill
            $updateResult = $this->db->update('tbl_skills', [
                'name' => $skillName
            ], [
                'id' => $skillId,
                'archived' => 0
            ]);

            // Check if update was successful
            if ($updateResult === false) {
                $error = $this->db->pdo->errorInfo();
                throw new \Exception('Gagal memperbarui skill: ' . ($error[2] ?? 'Unknown error'));
            }
            
            $responseData = [
                'success' => true,
                'message' => 'Skill berhasil diperbarui!',
                'data' => [
                    'id' => $skillId,
                    'name' => $skillName
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            $response->getBody()->write(json_encode($errorData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    public function delete(Request $request, Response $response, $args): Response
    {
        $user = $_SESSION['user'];
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
        $skillId = (int)($args['id'] ?? 0);

        try {
            if ($skillId <= 0) {
                throw new \Exception('ID skill tidak valid');
            }

            // Check if skill exists and is not archived
            $skill = $this->db->get('tbl_skills', ['id', 'name'], [
                'id' => $skillId,
                'archived' => 0
            ]);
            
            if (!$skill) {
                throw new \Exception('Skill tidak ditemukan atau sudah dihapus');
            }
            
            // Soft delete - set archived to 1
            $deleteResult = $this->db->update('tbl_skills', [
                'archived' => 1
            ], [
                'id' => $skillId
            ]);
            
            if ($deleteResult === false) {
                $error = $this->db->pdo->errorInfo();
                throw new \Exception('Gagal menghapus skill: ' . ($error[2] ?? 'Unknown error'));
            }
            
            if ($isAjax) {
                $responseData = [
                    'success' => true,
                    'message' => 'Skill berhasil dihapus!'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
            
            // Non-AJAX redirect
            $redirect = $user['role'] === 'admin' ? '/admin/skills' : '/recruiter/skills';
            return $response->withHeader('Location', $redirect)->withStatus(302);

        } catch (\Exception $e) {
            if ($isAjax) {
                $errorData = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $response->getBody()->write(json_encode($errorData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Non-AJAX redirect
            $redirect = $user['role'] === 'admin' ? '/admin/skills' : '/recruiter/skills';
            return $response->withHeader('Location', $redirect)->withStatus(302);
        }
    }
}
