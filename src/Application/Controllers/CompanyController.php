<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class CompanyController
{
    private Twig $view;
    private Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    private function getUser(): array
    {
        return $_SESSION['user'] ?? ['id' => null, 'id_company' => null];
    }

    // [ADMIN] Menampilkan halaman utama daftar perusahaan
    public function index(Request $req, Response $res): Response
    {
        return $this->view->render($res, 'companies/admin/index.twig');
    }

    // [ADMIN] Menyediakan data untuk AJAX request (dengan paginasi dan search)
    public function getData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = isset($params['search']) ? trim($params['search']) : '';

            $whereConditions = ['tbl_companies.archived' => 0];
            if (!empty($search)) {
                $whereConditions['OR'] = [
                    'tbl_companies.name[~]' => $search,
                    'tbl_companies.website[~]' => $search,
                    'tbl_industries.name[~]' => $search
                ];
            }

            $totalCount = $this->db->count('tbl_companies', [
                "[>]tbl_industries" => ["id_industry" => "id"]
            ], 'tbl_companies.id', $whereConditions);

            $companies = $this->db->select('tbl_companies', [
                '[>]tbl_industries' => ['id_industry' => 'id']
            ], [
                'tbl_companies.id',
                'tbl_companies.name',
                'tbl_companies.website',
                'tbl_companies.logo_filename(logo_url)',
                'tbl_industries.name(industry_name)'
            ], array_merge($whereConditions, [
                'ORDER' => ['tbl_companies.id' => 'DESC'],
                'LIMIT' => [$offset, $limit]
            ]));

            $totalPages = ceil($totalCount / $limit);

            $responseData = [
                'success' => true,
                'data' => $companies,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalCount,
                    'items_per_page' => $limit
                ]
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $errorResponse = ['success' => false, 'message' => 'Gagal memuat data perusahaan.'];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // [ADMIN] Menampilkan form untuk membuat perusahaan baru
    public function create(Request $req, Response $res): Response
    {
        $industries = $this->db->select('tbl_industries', '*', ['archived' => 0]);
        $recruiters = $this->db->select('tbl_users', ['id', 'full_name'], [
            'role' => 'recruiter',
            'archived' => 0
        ]);

        return $this->view->render($res, 'companies/admin/form.twig', [
            'industries' => $industries,
            'recruiters' => $recruiters,
            'company' => null,
            'isEdit' => false
        ]);
    }

    // [ADMIN] Menyimpan perusahaan baru
    public function store(Request $req, Response $res): Response
    {
        $data = $req->getParsedBody();
        $files = $req->getUploadedFiles();
        $user = $this->getUser();

        $logoFilename = $this->handleLogoUpload($files['logo_file'] ?? null, $res);
        if ($logoFilename instanceof Response) return $logoFilename;
        
        $id_recruiter = !empty($data['id_recruiter']) && is_numeric($data['id_recruiter']) ? (int)$data['id_recruiter'] : null;

        $this->db->insert('tbl_companies', [
            'id_industry' => $data['id_industry'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'],
            'website' => $data['website'],
            'logo_filename' => $logoFilename,
            'address' => $data['address'],
            'create_id' => $user['id'],
            'update_id' => $user['id'],
            'id_recruiter' => $id_recruiter,
        ]);

        return $res->withHeader('Location', '/admin/companies')->withStatus(302);
    }

    // [ADMIN] Menampilkan form untuk mengedit perusahaan
    public function edit(Request $req, Response $res, array $args): Response
    {
        $id = (int)$args['id'];
        $company = $this->db->get('tbl_companies', '*', ['id' => $id]);
        $industries = $this->db->select('tbl_industries', '*', ['archived' => 0]);
        $recruiters = $this->db->select('tbl_users', ['id', 'full_name'], [
            'role' => 'recruiter',
            'archived' => 0
        ]);

        return $this->view->render($res, 'companies/admin/form.twig', [
            'industries' => $industries,
            'recruiters' => $recruiters,
            'company' => $company,
            'isEdit' => true
        ]);
    }

    // [ADMIN] Memperbarui data perusahaan
    public function update(Request $req, Response $res, array $args): Response
    {
        $user = $this->getUser();
        $id = (int)$args['id'];
        $data = $req->getParsedBody();
        $files = $req->getUploadedFiles();

        $id_recruiter = !empty($data['id_recruiter']) && is_numeric($data['id_recruiter']) ? (int)$data['id_recruiter'] : null;

        $updateData = [
            'id_industry' => $data['id_industry'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'],
            'website' => $data['website'],
            'address' => $data['address'],
            'update_id' => $user['id'],
            'id_recruiter' => $id_recruiter,
        ];
        
        $logoFilename = $this->handleLogoUpload($files['logo_file'] ?? null, $res);
        if ($logoFilename instanceof Response) return $logoFilename;

        if ($logoFilename !== null) {
            $oldLogo = $this->db->get('tbl_companies', 'logo_filename', ['id' => $id]);
            if ($oldLogo) {
                $oldLogoPath = __DIR__ . '/../../../public/uploads/logos/' . $oldLogo;
                if (file_exists($oldLogoPath)) {
                    unlink($oldLogoPath);
                }
            }
            $updateData['logo_filename'] = $logoFilename;
        }

        $this->db->update('tbl_companies', $updateData, ['id' => $id]);

        return $res->withHeader('Location', '/admin/companies')->withStatus(302);
    }
    
    // [ADMIN] Menghapus (soft delete) perusahaan
    public function delete(Request $req, Response $res, array $args): Response
    {
        $id = (int)$args['id'];
        $this->db->update('tbl_companies', ['archived' => 1], ['id' => $id]);

        if ($req->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $res->getBody()->write(json_encode(['success' => true, 'message' => 'Perusahaan berhasil diarsipkan.']));
            return $res->withHeader('Content-Type', 'application/json');
        }

        return $res->withHeader('Location', '/admin/companies')->withStatus(302);
    }

    // [RECRUITER] Menampilkan form edit untuk perusahaan milik recruiter
    public function editOwn(Request $request, Response $response): Response
    {
        $user = $this->getUser();
        $company = $this->db->get('tbl_companies', '*', ['id_recruiter' => $user['id']]);
        $industries = $this->db->select('tbl_industries', '*', ['archived' => 0]);

        return $this->view->render($response, 'companies/recruiter/form.twig', [
            'industries' => $industries,
            'company' => $company,
            'isEdit' => true
        ]);
    }

    // [RECRUITER] Memperbarui data perusahaan milik recruiter
    public function updateOwn(Request $req, Response $res): Response
    {
        $user = $this->getUser();
        $data = $req->getParsedBody();
        $files = $req->getUploadedFiles();

        $companyId = $this->db->get('tbl_companies', 'id', ['id_recruiter' => $user['id']]);
        if (!$companyId) {
            $res->getBody()->write("Perusahaan tidak ditemukan untuk recruiter ini.");
            return $res->withStatus(404);
        }

        $updateData = [
            'id_industry' => $data['id_industry'] ?: null,
            'name'        => $data['name'],
            'description' => $data['description'],
            'website'     => $data['website'],
            'address'     => $data['address'],
            'update_id'   => $user['id']
        ];

        $logoFilename = $this->handleLogoUpload($files['logo_file'] ?? null, $res);
        if ($logoFilename instanceof Response) return $logoFilename;
        if ($logoFilename !== null) {
            $oldLogo = $this->db->get('tbl_companies', 'logo_filename', ['id' => $companyId]);
            if ($oldLogo) {
                $oldLogoPath = __DIR__ . '/../../../public/uploads/logos/' . $oldLogo;
                if (file_exists($oldLogoPath)) {
                    unlink($oldLogoPath);
                }
            }
            $updateData['logo_filename'] = $logoFilename;
        }

        $this->db->update('tbl_companies', $updateData, ['id' => $companyId]);

        return $res->withHeader('Location', '/recruiter/profile/edit')->withStatus(302);
    }

    // Private helper untuk menangani upload logo
    private function handleLogoUpload($logoFile, Response $res)
    {
        if (!$logoFile || $logoFile->getError() !== UPLOAD_ERR_OK) return null;

        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($logoFile->getClientMediaType(), $allowedTypes)) {
            $res->getBody()->write("File logo harus PNG, JPG, atau GIF.");
            return $res->withStatus(400);
        }

        if ($logoFile->getSize() > $maxSize) {
            $res->getBody()->write("Ukuran file logo maksimal 2MB.");
            return $res->withStatus(400);
        }

        $ext = pathinfo($logoFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = 'company_' . uniqid() . '.' . $ext;
        $uploadPath = __DIR__ . '/../../../public/uploads/logos/';

        if (!is_dir($uploadPath)) mkdir($uploadPath, 0777, true);
        $logoFile->moveTo($uploadPath . $filename);

        return $filename;
    }
}
