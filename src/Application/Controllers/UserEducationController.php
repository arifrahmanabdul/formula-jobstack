<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class UserEducationController
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
            // Untuk admin, render halaman utama. Data akan diambil via AJAX.
            return $this->view->render($response, 'user_educations/admin/index.twig', [
                'current_page' => 'user-educations'
            ]);
        }

        if ($role === 'seeker') {
            // Untuk seeker, ambil dan tampilkan data pendidikannya sendiri.
            $educations = $this->db->select("tbl_user_educations", "*", [
                "id_user" => $user['id'],
                "archived" => 0
            ]);
            return $this->view->render($response, 'user_educations/seeker/index.twig', compact('educations'));
        }

        // Jika bukan admin atau seeker, akses ditolak.
        return $response->withStatus(403);
    }

    // [ADMIN] Menyediakan data untuk AJAX request dengan paginasi
    public function getData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $page = (int) ($params['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = $params['search'] ?? '';

            $where = ["tbl_user_educations.archived" => 0];
            if (!empty($search)) {
                $where['OR'] = [
                    "tbl_users.full_name[~]" => $search,
                    "tbl_user_educations.institution[~]" => $search,
                    "tbl_user_educations.degree[~]" => $search,
                    "tbl_user_educations.field_of_study[~]" => $search
                ];
            }

            $totalCount = $this->db->count("tbl_user_educations", ["[>]tbl_users" => ["id_user" => "id"]], "tbl_user_educations.id", $where);

            $educations = $this->db->select('tbl_user_educations', ["[>]tbl_users" => ["id_user" => "id"]], [
                'tbl_user_educations.id(id)',
                'tbl_user_educations.institution(institution)',
                'tbl_user_educations.degree(degree)',
                'tbl_user_educations.field_of_study(field_of_study)',
                'tbl_user_educations.start_date(start_date)',
                'tbl_user_educations.end_date(end_date)',
                'tbl_user_educations.gpa(gpa)',
                'tbl_users.full_name(user_name)' 
            ], array_merge($where, [
                    "ORDER" => ["tbl_user_educations.id" => "DESC"],
                    "LIMIT" => [$offset, $limit]
                ]));

            if ($educations === false)
                throw new \Exception($this->db->error);

            $totalPages = ceil($totalCount / $limit);
            $responseData = ['success' => true, 'data' => $educations, 'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total_items' => $totalCount]];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("UserEducationController getData Error: " . $e->getMessage());
            $errorResponse = ['success' => false, 'message' => 'Gagal memuat data pendidikan.'];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // --- FUNGSI UNTUK SEEKER (DIKEMBALIKAN SEPERTI SEMULA) ---

    public function create(Request $request, Response $response): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker')
            return $response->withStatus(403);
        return $this->view->render($response, 'user_educations/seeker/create.twig');
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker')
            return $response->withStatus(403);

        try {
            $data = $request->getParsedBody();
            $files = $request->getUploadedFiles();
            $certificatePath = null;

            if (isset($files['certificate']) && $files['certificate']->getError() === UPLOAD_ERR_OK) {
                $file = $files['certificate'];
                $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                if ($ext === 'pdf' && $file->getSize() <= 1 * 1024 * 1024) {
                    $filename = 'edu_' . uniqid() . '.' . $ext;
                    $uploadPath = __DIR__ . '/../../../public/uploads/educations/';
                    if (!is_dir($uploadPath))
                        mkdir($uploadPath, 0777, true);
                    $file->moveTo($uploadPath . $filename);
                    $certificatePath = 'uploads/educations/' . $filename;
                } else {
                    throw new \Exception("File harus PDF dan maksimal 1MB");
                }
            }

            $this->db->insert("tbl_user_educations", [
                "id_user" => $user['id'],
                "institution" => $data['institution'],
                "degree" => $data['degree'],
                "field_of_study" => $data['field_of_study'],
                "start_date" => $data['start_date'],
                "end_date" => $data['end_date'],
                "gpa" => $data['gpa'],
                "description" => $data['description'],
                "certificate_url" => $certificatePath,
                "archived" => 0
            ]);

            return $response->withHeader('Location', '/seeker/user-educations')->withStatus(302);
        } catch (\Exception $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker')
            return $response->withStatus(403);

        $education = $this->db->get("tbl_user_educations", "*", [
            "id" => $args['id'],
            "id_user" => $user['id'],
            "archived" => 0
        ]);

        if (!$education)
            return $response->withStatus(403);

        return $this->view->render($response, 'user_educations/seeker/edit.twig', compact('education'));
    }

    public function update(Request $request, Response $response, $args): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker')
            return $response->withStatus(403);

        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $existing = $this->db->get("tbl_user_educations", "*", [
            "id" => $args['id'],
            "id_user" => $user['id'],
            "archived" => 0
        ]);

        if (!$existing)
            return $response->withStatus(403);

        try {
            $certificatePath = $existing['certificate_url'];

            if (isset($files['certificate']) && $files['certificate']->getError() === UPLOAD_ERR_OK) {
                $file = $files['certificate'];
                $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                if ($ext === 'pdf' && $file->getSize() <= 1 * 1024 * 1024) {
                    $filename = 'edu_' . uniqid() . '.' . $ext;
                    $uploadPath = __DIR__ . '/../../../public/uploads/educations/';
                    if (!is_dir($uploadPath))
                        mkdir($uploadPath, 0777, true);
                    $file->moveTo($uploadPath . $filename);
                    $certificatePath = 'uploads/educations/' . $filename;
                } else {
                    throw new \Exception("File harus PDF dan maksimal 1MB");
                }
            }

            $this->db->update("tbl_user_educations", [
                "institution" => $data['institution'],
                "degree" => $data['degree'],
                "field_of_study" => $data['field_of_study'],
                "start_date" => $data['start_date'],
                "end_date" => $data['end_date'],
                "gpa" => $data['gpa'],
                "description" => $data['description'],
                "certificate_url" => $certificatePath
            ], ["id" => $args['id'], "id_user" => $user['id']]);

            return $response->withHeader('Location', '/seeker/user-educations')->withStatus(302);
        } catch (\Exception $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }
    }

    public function delete(Request $request, Response $response, $args): Response
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker')
            return $response->withStatus(403);

        $id = (int) $args['id'];

        $education = $this->db->get("tbl_user_educations", ["certificate_url"], [
            "id" => $id,
            "id_user" => $user['id'],
            "archived" => 0
        ]);

        if (!$education)
            return $response->withStatus(403);

        if (!empty($education['certificate_url'])) {
            $path = __DIR__ . '/../../../public/' . $education['certificate_url'];
            if (file_exists($path))
                unlink($path);
        }

        $this->db->update("tbl_user_educations", ['archived' => 1], ['id' => $id]);

        return $response->withHeader('Location', '/seeker/user-educations')->withStatus(302);
    }
}
