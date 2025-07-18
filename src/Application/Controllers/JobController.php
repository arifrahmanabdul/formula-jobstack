<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use Medoo\Medoo;

class JobController
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
        return $_SESSION['user'] ?? ['id' => null, 'role' => 'guest', 'id_company' => null];
    }

    private function handleJobImageUpload($uploadedFile)
    {
        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return null;
        }

        $uploadPath = __DIR__ . '/../../../public/uploads/jobs/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = 'job_' . uniqid() . '.' . $extension;
        $uploadedFile->moveTo($uploadPath . $filename);
        return $filename;
    }
    
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->getUser();
        $template = $user['role'] === 'admin' 
            ? "jobs/admin/index.twig" 
            : "jobs/recruiter/index.twig";
        return $this->view->render($response, $template);
    }

    public function getData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $user = $this->getUser();
            $params = $request->getQueryParams();
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $limit = 5; // PENYESUAIAN: Jumlah data per halaman diubah menjadi 5
            $offset = ($page - 1) * $limit;
            $search = isset($params['search']) ? trim($params['search']) : '';

            $where = ["tbl_jobs.archived" => 0];
            
            if ($user['role'] === 'recruiter') {
                if (empty($user['id_company'])) {
                    $response->getBody()->write(json_encode(['success' => true, 'data' => [], 'pagination' => ['current_page' => 1, 'total_pages' => 0, 'total_items' => 0]]));
                    return $response->withHeader('Content-Type', 'application/json');
                }
                $where["tbl_jobs.id_company"] = $user['id_company'];
            }

            if (!empty($search)) {
                $where['OR'] = ["tbl_jobs.title[~]" => $search, "tbl_companies.name[~]" => $search];
            }

            $totalCount = $this->db->count("tbl_jobs", ["[>]tbl_companies" => ["id_company" => "id"]], "tbl_jobs.id", $where);

            $jobs = $this->db->select('tbl_jobs', [
                "[>]tbl_companies" => ["id_company" => "id"],
                "[>]tbl_locations" => ["id_location" => "id"],
                "[>]tbl_job_categories" => ["id_category" => "id"],
            ], [
                "tbl_jobs.id", "tbl_jobs.title", "tbl_jobs.status", "tbl_jobs.job_type",
                "tbl_jobs.job_image",
                "tbl_companies.name(company_name)", "tbl_companies.logo_filename(company_logo)",
                "tbl_locations.city(location_name)", "tbl_job_categories.name(category_name)",
            ], array_merge($where, ["ORDER" => ["tbl_jobs.id" => "DESC"], "LIMIT" => [$offset, $limit]]));

            if ($jobs === false) throw new \Exception($this->db->error);

            $totalPages = ceil($totalCount / $limit);
            $responseData = ['success' => true, 'data' => $jobs, 'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total_items' => $totalCount, 'items_per_page' => $limit]];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("JobController getData Error: " . $e->getMessage());
            $errorResponse = ['success' => false, 'message' => 'Gagal memuat data lowongan.'];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args): ResponseInterface
    {
        $user = $this->getUser();
        $id_job = (int)$args['id'];

        $where = ['id' => $id_job];
        if ($user['role'] === 'recruiter') {
            $where['id_company'] = $user['id_company'];
        }

        if (!$this->db->has('tbl_jobs', $where)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Akses ditolak atau lowongan tidak ditemukan.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Melakukan soft delete dengan mengubah archived menjadi 1
        $this->db->update('tbl_jobs', ['archived' => 1], ['id' => $id_job]);
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Lowongan berhasil diarsipkan.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->getUser();
        $data = [
            'categories' => $this->db->select('tbl_job_categories', ['id', 'name']),
            'locations' => $this->db->select('tbl_locations', ['id', 'city'], ['archived' => 0]),
            'user' => $user
        ];

        if ($user['role'] === 'admin') {
            $data['companies'] = $this->db->select('tbl_companies', ['id', 'name'], ['archived' => 0]);
            return $this->view->render($response, 'jobs/admin/create.twig', $data);
        } else {
            $data['company'] = $this->db->get('tbl_companies', ['id', 'name'], ['id' => $user['id_company']]);
            return $this->view->render($response, 'jobs/recruiter/create.twig', $data);
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $user = $this->getUser();
        $uploadedFiles = $request->getUploadedFiles();
        
        $jobImageFilename = $this->handleJobImageUpload($uploadedFiles['job_image'] ?? null);

        $id_company = ($user['role'] === 'recruiter') ? $user['id_company'] : $data['id_company'];

        $this->db->insert('tbl_jobs', [
            'id_company'   => $id_company, 
            'id_category'  => $data['id_category'],
            'id_location'  => $data['id_location'], 
            'title'        => $data['title'],
            'job_type'     => $data['job_type'], 
            'salary_min'   => $data['salary_min'] ?: null,
            'salary_max'   => $data['salary_max'] ?: null, 
            'status'       => $data['status'],
            'description'  => $data['description'], 
            'requirements' => $data['requirements'],
            'job_image'    => $jobImageFilename,
            'create_id'    => $user['id'], 
            'update_id'    => $user['id'],
            'posted_date'  => date('Y-m-d H:i:s'),
        ]);

        $redirectUrl = $user['role'] === 'admin' ? '/admin/jobs' : '/recruiter/jobs';
        return $response->withHeader('Location', $redirectUrl)->withStatus(302);
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, $args): ResponseInterface
    {
        $user = $this->getUser();
        $id_job = (int)$args['id'];
        
        $where = ['id' => $id_job];
        if ($user['role'] === 'recruiter') {
            $where['id_company'] = $user['id_company'];
        }

        $job = $this->db->get('tbl_jobs', '*', $where);
        $redirectUrl = $user['role'] === 'admin' ? '/admin/jobs' : '/recruiter/jobs';

        if (!$job) {
             return $response->withHeader('Location', $redirectUrl)->withStatus(404);
        }

        $data = [
            'job' => $job,
            'categories' => $this->db->select('tbl_job_categories', ['id', 'name']),
            'locations' => $this->db->select('tbl_locations', ['id', 'city'], ['archived' => 0]),
            'user' => $user
        ];
        
        if ($user['role'] === 'admin') {
            $data['companies'] = $this->db->select('tbl_companies', ['id', 'name'], ['archived' => 0]);
            return $this->view->render($response, 'jobs/admin/edit.twig', $data);
        } else {
            $data['company'] = $this->db->get('tbl_companies', ['id', 'name'], ['id' => $user['id_company']]);
            return $this->view->render($response, 'jobs/recruiter/edit.twig', $data);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $user = $this->getUser();
        $id_job = (int)$args['id'];
        $uploadedFiles = $request->getUploadedFiles();

        $where = ['id' => $id_job];
        if ($user['role'] === 'recruiter') {
            $where['id_company'] = $user['id_company'];
        }
        $job = $this->db->get('tbl_jobs', ['id', 'job_image'], $where);
        
        $redirectUrl = $user['role'] === 'admin' ? '/admin/jobs' : '/recruiter/jobs';
        if (!$job) {
            return $response->withHeader('Location', $redirectUrl)->withStatus(403);
        }

        $updateData = [
            'id_category'  => $data['id_category'],
            'id_location'  => $data['id_location'], 
            'title'        => $data['title'],
            'job_type'     => $data['job_type'], 
            'salary_min'   => $data['salary_min'] ?: null,
            'salary_max'   => $data['salary_max'] ?: null, 
            'status'       => $data['status'],
            'description'  => $data['description'], 
            'requirements' => $data['requirements'],
            'update_id'    => $user['id'],
        ];

        if ($user['role'] === 'admin') {
            $updateData['id_company'] = $data['id_company'];
        }

        $jobImageFilename = $this->handleJobImageUpload($uploadedFiles['job_image'] ?? null);
        if ($jobImageFilename !== null) {
            if ($job && !empty($job['job_image'])) {
                $oldImagePath = __DIR__ . '/../../../public/uploads/jobs/' . $job['job_image'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            $updateData['job_image'] = $jobImageFilename;
        }

        $this->db->update('tbl_jobs', $updateData, ['id' => $id_job]);
        return $response->withHeader('Location', $redirectUrl)->withStatus(302);
    }
}