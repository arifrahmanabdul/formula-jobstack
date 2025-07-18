<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class UserExperienceController
{
    protected Twig $view;
    protected Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }
    
    private function getUser(): array
    {
        return $_SESSION['user'] ?? ['id' => null, 'role' => 'guest'];
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->getUser();
        $role = $user['role'];

        if ($role === 'admin') {
            return $this->view->render($response, 'user_experiences/admin/index.twig', [
                'current_page' => 'user-experiences'
            ]);
        }

        if ($role === 'seeker') {
            $experiences = $this->db->select("tbl_user_experiences", "*", [
                "id_user" => $user['id'],
                "archived" => 0
            ]);
            return $this->view->render($response, 'user_experiences/seeker/index.twig', compact('experiences'));
        }

        if ($role === 'recruiter') {
            $experiences = $this->db->select("tbl_user_experiences", [
                "[>]tbl_users" => ["id_user" => "id"]
            ], [
                "tbl_user_experiences.id", "tbl_user_experiences.position", "tbl_user_experiences.company_name",
                "tbl_user_experiences.start_date", "tbl_user_experiences.end_date", "tbl_user_experiences.description",
                "tbl_users.full_name"
            ], [
                "tbl_user_experiences.archived" => 0
            ]);
            return $this->view->render($response, 'user_experiences/recruiter/index.twig', compact('experiences'));
        }

        return $response->withStatus(403);
    }

    // [ADMIN] Menyediakan data untuk AJAX request
    public function getData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = $params['search'] ?? '';

            $where = ["tbl_user_experiences.archived" => 0];
            if (!empty($search)) {
                $where['OR'] = [
                    "tbl_users.full_name[~]" => $search,
                    "tbl_user_experiences.position[~]" => $search,
                    "tbl_user_experiences.company_name[~]" => $search,
                ];
            }

            $totalCount = $this->db->count("tbl_user_experiences", ["[>]tbl_users" => ["id_user" => "id"]], "tbl_user_experiences.id", $where);

            $experiences = $this->db->select('tbl_user_experiences', ["[>]tbl_users" => ["id_user" => "id"]], [
                'tbl_user_experiences.id', 'tbl_user_experiences.position', 'tbl_user_experiences.company_name',
                'tbl_user_experiences.start_date', 'tbl_user_experiences.end_date', 'tbl_users.full_name(user_name)'
            ], array_merge($where, [
                "ORDER" => ["tbl_user_experiences.id" => "DESC"], "LIMIT" => [$offset, $limit]
            ]));
            
            if ($experiences === false) throw new \Exception($this->db->error);

            $totalPages = ceil($totalCount / $limit);
            $responseData = ['success' => true, 'data' => $experiences, 'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total_items' => $totalCount]];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("UserExperienceController getData Error: " . $e->getMessage());
            $errorResponse = ['success' => false, 'message' => 'Gagal memuat data pengalaman kerja.'];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // [ADMIN] Soft delete
    public function adminDelete(Request $request, Response $response, array $args): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'admin') {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $this->db->update('tbl_user_experiences', ['archived' => 1], ['id' => (int)$args['id']]);
        
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Data pengalaman berhasil diarsipkan.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // --- Seeker Methods (Lengkap) ---

    public function create(Request $request, Response $response): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker') return $response->withStatus(403);

        return $this->view->render($response, 'user_experiences/seeker/create.twig');
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $this->getUser();
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $certificatePath = null;

        if (isset($files['certificate']) && $files['certificate']->getError() === UPLOAD_ERR_OK) {
            $file = $files['certificate'];
            $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);

            if (in_array(strtolower($extension), ['pdf', 'jpg', 'jpeg', 'png'])) {
                if ($file->getSize() <= 1 * 1024 * 1024) {
                    $filename = 'cert_' . uniqid() . '.' . $extension;
                    $uploadPath = __DIR__ . '/../../../public/uploads/certificates/';
                    if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);

                    $file->moveTo($uploadPath . $filename);
                    $certificatePath = 'uploads/certificates/' . $filename;
                } else {
                    $response->getBody()->write("File harus maksimal 1MB.");
                    return $response->withStatus(400);
                }
            }
        }

        $this->db->insert('tbl_user_experiences', [
            'id_user' => $user['id'],
            'company_name' => $data['company_name'],
            'position' => $data['position'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'description' => $data['description'],
            'certificate_url' => $certificatePath,
            'create_time' => date('Y-m-d H:i:s'),
            'archived' => 0
        ]);

        return $response->withHeader('Location', '/seeker/user-experiences')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker') return $response->withStatus(403);

        $experience = $this->db->get("tbl_user_experiences", "*", [
            "id" => $args['id'],
            "id_user" => $user['id']
        ]);

        if (!$experience) return $response->withStatus(403);

        return $this->view->render($response, 'user_experiences/seeker/edit.twig', compact('experience'));
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker') return $response->withStatus(403);

        $data = $request->getParsedBody();

        $this->db->update("tbl_user_experiences", [
            "position" => $data['position'],
            "company_name" => $data['company_name'],
            "start_date" => $data['start_date'],
            "end_date" => $data['end_date'],
            "description" => $data['description']
        ], [
            "id" => $args['id'],
            "id_user" => $user['id']
        ]);

        return $response->withHeader('Location', '/seeker/user-experiences')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker') return $response->withStatus(403);

        $experience = $this->db->get("tbl_user_experiences", ["certificate_url"], [
            "id" => $args['id'],
            "id_user" => $user['id']
        ]);

        if (!$experience) return $response->withStatus(403);

        if (!empty($experience['certificate_url'])) {
            $filePath = __DIR__ . '/../../../public/' . $experience['certificate_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $this->db->update("tbl_user_experiences", ["archived" => 1], [
            "id" => $args['id'],
            "id_user" => $user['id']
        ]);

        return $response->withHeader('Location', '/seeker/user-experiences')->withStatus(302);
    }
}
