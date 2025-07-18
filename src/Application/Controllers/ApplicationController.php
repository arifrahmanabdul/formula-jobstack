<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use Medoo\Medoo;

class ApplicationController
{
    protected Medoo $db;
    protected Twig $view;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    private function getUser(): array
    {
        return $_SESSION['user'] ?? ['id' => null, 'role' => 'guest'];
    }

    // Menampilkan halaman daftar lamaran untuk Admin dan Recruiter
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->getUser();
        $role = $user['role'];

        // Admin menggunakan halaman dengan AJAX
        if ($role === 'admin') {
            return $this->view->render($response, "applications/admin/index.twig");
        }

        // Recruiter menggunakan halaman dengan AJAX juga
        if ($role === 'recruiter') {
            return $this->view->render($response, "applications/recruiter/index.twig");
        }

        return $response->withHeader('Location', '/login')->withStatus(403);
    }

    // [ADMIN] Menyediakan data lamaran untuk AJAX
    public function getData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $page = (int) ($params['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = $params['search'] ?? '';

            $where = ["tbl_applications.archived" => 0];
            if (!empty($search)) {
                $where['OR'] = [
                    "tbl_users.full_name[~]" => $search,
                    "tbl_jobs.title[~]" => $search,
                    "tbl_applications.status[~]" => $search,
                ];
            }

            $totalCount = $this->db->count("tbl_applications", [
                "[>]tbl_users" => ["id_user" => "id"],
                "[>]tbl_jobs" => ["id_job" => "id"],
            ], "tbl_applications.id", $where);

            $applications = $this->db->select('tbl_applications', [
                "[>]tbl_jobs" => ["id_job" => "id"],
                "[>]tbl_users" => ["id_user" => "id"],
                "[>]tbl_companies" => ["tbl_jobs.id_company" => "id"]
            ], [
                'tbl_applications.id',
                'tbl_applications.status',
                'tbl_applications.application_date',
                'tbl_jobs.title(job_title)',
                'tbl_users.full_name(user_name)',
                'tbl_companies.name(company_name)'
            ], array_merge($where, [
                    "ORDER" => ["tbl_applications.id" => "DESC"],
                    "LIMIT" => [$offset, $limit]
                ]));

            if ($applications === false)
                throw new \Exception($this->db->error);

            $totalPages = ceil($totalCount / $limit);
            $responseData = ['success' => true, 'data' => $applications, 'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total_items' => $totalCount]];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("ApplicationController getData Error: " . $e->getMessage());
            $errorResponse = ['success' => false, 'message' => 'Gagal memuat data lamaran.'];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // [RECRUITER] Menyediakan data lamaran untuk AJAX
    public function getRecruiterData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $user = $this->getUser();
            if ($user['role'] !== 'recruiter' || !isset($user['id_company'])) {
                $errorResponse = ['success' => false, 'message' => 'Akses ditolak atau Anda tidak terhubung ke perusahaan.'];
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $params = $request->getQueryParams();
            $page = (int) ($params['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = $params['search'] ?? '';
            $statusFilter = $params['status'] ?? '';

            $where = [
                "tbl_applications.archived" => 0,
                "tbl_jobs.id_company" => $user['id_company'] // Menggunakan id_company dari session
            ];

            if (!empty($search)) {
                $where['OR'] = [
                    "tbl_users.full_name[~]" => $search,
                    "tbl_jobs.title[~]" => $search,
                ];
            }

            if (!empty($statusFilter)) {
                $where["tbl_applications.status"] = $statusFilter;
            }

            $totalCount = $this->db->count("tbl_applications", [
                "[>]tbl_jobs" => ["id_job" => "id"],
            ], "tbl_applications.id", $where);

            $applications = $this->db->select('tbl_applications', [
                "[>]tbl_jobs" => ["id_job" => "id"],
                "[>]tbl_users" => ["id_user" => "id"],
            ], [
                'tbl_applications.id',
                'tbl_applications.status',
                'tbl_applications.application_date',
                'tbl_jobs.title(job_title)',
                'tbl_users.full_name(user_name)'
            ], array_merge($where, [
                    "ORDER" => ["tbl_applications.id" => "DESC"],
                    "LIMIT" => [$offset, $limit]
                ]));

            if ($applications === false)
                throw new \Exception($this->db->error);

            $totalPages = ceil($totalCount / $limit);
            $responseData = [
                'success' => true,
                'data' => $applications,
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
            error_log("ApplicationController getRecruiterData Error: " . $e->getMessage());
            $errorResponse = ['success' => false, 'message' => 'Gagal memuat data lamaran.'];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // [SEEKER] Menyediakan data lamaran untuk AJAX
    public function getSeekerData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $user = $this->getUser();
            if ($user['role'] !== 'seeker') {
                $errorResponse = ['success' => false, 'message' => 'Akses ditolak.'];
                $response->getBody()->write(json_encode($errorResponse));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $params = $request->getQueryParams();
            $page = (int) ($params['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = $params['search'] ?? '';
            $statusFilter = $params['status'] ?? '';

            $where = [
                "tbl_applications.archived" => 0,
                "tbl_applications.id_user" => $user['id']
            ];

            if (!empty($search)) {
                $where['OR'] = [
                    "tbl_jobs.title[~]" => $search,
                    "tbl_companies.name[~]" => $search,
                ];
            }

            if (!empty($statusFilter)) {
                $where["tbl_applications.status"] = $statusFilter;
            }

            $totalCount = $this->db->count("tbl_applications", [
                "[>]tbl_jobs" => ["id_job" => "id"],
                "[>]tbl_companies" => ["tbl_jobs.id_company" => "id"]
            ], "tbl_applications.id", $where);

            $applications = $this->db->select('tbl_applications', [
                "[>]tbl_jobs" => ["id_job" => "id"],
                "[>]tbl_companies" => ["tbl_jobs.id_company" => "id"],
                "[>]tbl_locations" => ["tbl_jobs.id_location" => "id"]
            ], [
                'tbl_applications.id',
                'tbl_applications.status',
                'tbl_applications.application_date',
                'tbl_applications.cover_letter',
                'tbl_jobs.id(job_id)',
                'tbl_jobs.title(job_title)',
                'tbl_companies.name(company_name)',
                'tbl_locations.city',
                'tbl_locations.province'
            ], array_merge($where, [
                "ORDER" => ["tbl_applications.id" => "DESC"],
                "LIMIT" => [$offset, $limit]
            ]));

            if ($applications === false)
                throw new \Exception($this->db->error);

            $totalPages = ceil($totalCount / $limit);
            $responseData = [
                'success' => true,
                'data' => $applications,
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
            error_log("ApplicationController getSeekerData Error: " . $e->getMessage());
            $errorResponse = ['success' => false, 'message' => 'Gagal memuat data lamaran.'];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // [ADMIN] Soft delete lamaran
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        if ($user['role'] !== 'admin') {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $this->db->update('tbl_applications', ['archived' => 1], ['id' => (int) $args['id']]);
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Lamaran berhasil diarsipkan.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // [RECRUITER] Soft delete lamaran
    public function deleteRecruiter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        if ($user['role'] !== 'recruiter') {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $applicationId = (int) $args['id'];

        // Verify that this application belongs to recruiter's company
        $application = $this->db->get('tbl_applications', [
            "[>]tbl_jobs" => ["id_job" => "id"],
            "[>]tbl_companies" => ["tbl_jobs.id_company" => "id"]
        ], [
            'tbl_applications.id'
        ], [
            'tbl_applications.id' => $applicationId,
            'tbl_companies.id_recruiter' => $user['id']
        ]);

        if (!$application) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Lamaran tidak ditemukan atau tidak memiliki akses.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $this->db->update('tbl_applications', ['archived' => 1], ['id' => $applicationId]);
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Lamaran berhasil diarsipkan.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // [SEEKER] Soft delete lamaran
    public function deleteSeeker(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker') {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $applicationId = (int) $args['id'];

        // Verify that this application belongs to the seeker
        $application = $this->db->get('tbl_applications', [
            'id'
        ], [
            'id' => $applicationId,
            'id_user' => $user['id'],
            'archived' => 0
        ]);

        if (!$application) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Lamaran tidak ditemukan atau tidak memiliki akses.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $this->db->update('tbl_applications', ['archived' => 1], ['id' => $applicationId]);
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Lamaran berhasil ditarik.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Menampilkan detail lamaran untuk semua role yang berhak
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        $id = (int) $args['id'];

        $application = $this->db->get('tbl_applications', [
            "[>]tbl_jobs" => ["id_job" => "id"],
            "[>]tbl_users" => ["id_user" => "id"],
            "[>]tbl_companies" => ["tbl_jobs.id_company" => "id"]
        ], [
            "tbl_applications.id",
            "tbl_applications.status",
            "tbl_applications.cover_letter",
            "tbl_applications.application_date",
            "tbl_jobs.title(job_title)",
            "tbl_users.id(user_id)",
            "tbl_users.full_name(user_name)",
            "tbl_users.email(user_email)",
            "tbl_users.phone(user_phone)",
            "tbl_companies.name(company_name)"
        ], ["tbl_applications.id" => $id]);

        if (!$application) {
            $response->getBody()->write("Aplikasi tidak ditemukan.");
            return $response->withStatus(404);
        }

        // TODO: Tambahkan validasi hak akses untuk seeker dan recruiter

        $notes = $this->db->select('tbl_application_notes', ["[>]tbl_users" => ["id_recruiter" => "id"]], [
            "tbl_application_notes.id",
            "tbl_application_notes.note_type",
            "tbl_application_notes.note_text",
            "tbl_application_notes.created_at",
            "tbl_users.full_name(recruiter_name)"
        ], ["tbl_application_notes.id_application" => $id]);

        $applicant_id = $application['user_id'];
        $skills = $this->db->select('tbl_user_skills', ["[>]tbl_skills" => ["id_skill" => "id"]], ["tbl_skills.name", "tbl_user_skills.pdf"], ["id_user" => $applicant_id]);
        $experiences = $this->db->select('tbl_user_experiences', '*', ["id_user" => $applicant_id, "archived" => 0]);
        $educations = $this->db->select('tbl_user_educations', '*', ["id_user" => $applicant_id, "archived" => 0]);

        return $this->view->render($response, 'applications/show.twig', [
            'application' => $application,
            'notes' => $notes,
            'skills' => $skills,
            'experiences' => $experiences,
            'educations' => $educations,
            'role' => $user['role'],
            'user' => $user
        ]);
    }

    // Menampilkan form edit lamaran
    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $application = $this->db->get('tbl_applications', '*', ['id' => $args['id']]);
        if (!$application) {
            $response->getBody()->write("Aplikasi tidak ditemukan.");
            return $response->withStatus(404);
        }
        $statuses = ['new' => 'Baru', 'viewed' => 'Dilihat', 'sent_to_client' => 'Dikirim ke Klien', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'];
        return $this->view->render($response, 'applications/edit.twig', ['application' => $application, 'statuses' => $statuses]);
    }

    // [RECRUITER] Edit lamaran
    public function editRecruiter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        if ($user['role'] !== 'recruiter' || !isset($user['id_company'])) {
            return $response->withHeader('Location', '/login')->withStatus(403);
        }

        $applicationId = (int)$args['id'];
        
        // Verifikasi bahwa aplikasi ini milik perusahaan recruiter
        $application = $this->db->get('tbl_applications', [
            "[>]tbl_jobs" => ["id_job" => "id"],
            "[>]tbl_users" => ["id_user" => "id"]
        ], [
            "tbl_applications.id", "tbl_applications.status",
            "tbl_jobs.title(job_title)", 
            "tbl_users.full_name(user_name)"
        ], [
            'tbl_applications.id' => $applicationId,
            'tbl_jobs.id_company' => $user['id_company']
        ]);

        if (!$application) {
            // Bisa tambahkan flash message untuk notifikasi
            return $response->withHeader('Location', '/recruiter/applications')->withStatus(404);
        }

        $statuses = [
            'new' => 'Baru', 
            'viewed' => 'Dilihat', 
            'sent_to_client' => 'Dikirim ke Klien', 
            'accepted' => 'Diterima', 
            'rejected' => 'Ditolak'
        ];

        return $this->view->render($response, 'applications/recruiter/edit.twig', [
            'application' => $application, 
            'statuses' => $statuses
        ]);
    }

    // Memperbarui status lamaran
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $user = $this->getUser();
        $this->db->update('tbl_applications', ['status' => $data['status'], 'update_id' => $user['id']], ['id' => $args['id']]);
        $redirectPath = '/' . $user['role'] . '/applications';
        return $response->withHeader('Location', $redirectPath)->withStatus(302);
    }

    // [RECRUITER] Update lamaran
    public function updateRecruiter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        if ($user['role'] !== 'recruiter' || !isset($user['id_company'])) {
            return $response->withHeader('Location', '/login')->withStatus(403);
        }

        $applicationId = (int)$args['id'];
        $data = $request->getParsedBody();
        
        // Verifikasi sekali lagi bahwa aplikasi ini milik perusahaan recruiter
        $applicationExists = $this->db->has('tbl_applications', [
            "[>]tbl_jobs" => ["id_job" => "id"]
        ], [
            'tbl_applications.id' => $applicationId,
            'tbl_jobs.id_company' => $user['id_company']
        ]);

        if (!$applicationExists) {
            // Bisa tambahkan flash message untuk notifikasi
            return $response->withHeader('Location', '/recruiter/applications')->withStatus(404);
        }

        // Lakukan update
        $this->db->update('tbl_applications', [
            'status' => $data['status'], 
            'update_id' => $user['id']
        ], ['id' => $applicationId]);

        // Redirect kembali ke halaman daftar lamaran
        return $response->withHeader('Location', '/recruiter/applications')->withStatus(302);
    }

    // [SEEKER] Melamar pekerjaan
    public function apply(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        $id_job = (int) $args['id_job'];
        $files = $request->getUploadedFiles();

        if (!isset($files['cover_letter']) || $files['cover_letter']->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write("Surat lamaran wajib diunggah.");
            return $response->withStatus(400);
        }

        $file = $files['cover_letter'];
        $ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'pdf') {
            $response->getBody()->write("File harus berupa PDF.");
            return $response->withStatus(400);
        }

        $filename = 'cover_' . uniqid() . '.pdf';
        $uploadPath = __DIR__ . '/../../../public/uploads/cover_letters/';
        if (!is_dir($uploadPath))
            mkdir($uploadPath, 0777, true);
        $file->moveTo($uploadPath . $filename);

        $this->db->insert('tbl_applications', [
            'id_job' => $id_job,
            'id_user' => $user['id'],
            'status' => 'new',
            'cover_letter' => $filename,
            'create_id' => $user['id'],
            'update_id' => $user['id']
        ]);

        return $response->withHeader('Location', '/seeker/applications')->withStatus(302);
    }

    // [SEEKER] Melihat daftar lamaran sendiri
    public function myApplications(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->getUser();
        return $this->view->render($response, 'applications/seeker/index.twig', [
            'role' => 'seeker',
            'user' => $user
        ]);
    }

    public function createApplicationForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $jobId = (int)$args['id_job'];
        
        // Get job details with company information
        $job = $this->db->get('tbl_jobs', [
            "[>]tbl_companies" => ["id_company" => "id"],
            "[>]tbl_job_categories" => ["id_category" => "id"],
            "[>]tbl_locations" => ["id_location" => "id"]
        ], [
            'tbl_jobs.id',
            'tbl_jobs.title',
            'tbl_jobs.description',
            'tbl_jobs.requirements',
            'tbl_jobs.job_type',
            'tbl_jobs.salary_min',
            'tbl_jobs.salary_max',
            'tbl_companies.name(company_name)',
            'tbl_job_categories.name(category_name)',
            'tbl_locations.city',
            'tbl_locations.province'
        ], [
            'tbl_jobs.id' => $jobId,
            'tbl_jobs.status' => 'open',
            'tbl_jobs.archived' => 0
        ]);

        if (!$job) {
            return $response->withHeader('Location', '/seeker/find-job')->withStatus(404);
        }

        // Check if user already applied
        $hasApplied = $this->db->has('tbl_applications', [
            'id_user' => $user['id'],
            'id_job' => $jobId,
            'archived' => 0
        ]);

        if ($hasApplied) {
            // Redirect with error message (you can implement flash messages)
            return $response->withHeader('Location', '/seeker/find-job/detail/' . $jobId)->withStatus(302);
        }

        return $this->view->render($response, 'applications/seeker/create.twig', [
            'job' => $job,
            'user' => $user
        ]);
    }

    public function submitApplication(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->getUser();
        if ($user['role'] !== 'seeker') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $jobId = (int)$args['id_job'];
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        // Validate job exists and is open
        $job = $this->db->get('tbl_jobs', ['id', 'status'], [
            'id' => $jobId,
            'status' => 'open',
            'archived' => 0
        ]);

        if (!$job) {
            return $response->withHeader('Location', '/seeker/find-job')->withStatus(404);
        }

        // Check if already applied
        $hasApplied = $this->db->has('tbl_applications', [
            'id_user' => $user['id'],
            'id_job' => $jobId,
            'archived' => 0
        ]);

        if ($hasApplied) {
            return $response->withHeader('Location', '/seeker/applications')->withStatus(302);
        }

        $coverLetterFile = null;
        
        // Handle cover letter file upload
        if (isset($files['cover_letter']) && $files['cover_letter']->getError() === UPLOAD_ERR_OK) {
            $file = $files['cover_letter'];
            $ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            
            if (strtolower($ext) === 'pdf') {
                $filename = 'cover_' . uniqid() . '.pdf';
                $uploadPath = __DIR__ . '/../../../public/uploads/cover_letters/';
                
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
                
                $file->moveTo($uploadPath . $filename);
                $coverLetterFile = $filename;
            }
        }

        // Insert application
        $applicationData = [
            'id_job' => $jobId,
            'id_user' => $user['id'],
            'status' => 'new',
            'cover_letter' => $data['cover_letter_text'] ?? null,
            'application_date' => date('Y-m-d H:i:s'),
            'create_id' => $user['id'],
            'update_id' => $user['id']
        ];

        if ($coverLetterFile) {
            $applicationData['cover_letter'] = $coverLetterFile;
        }

        $result = $this->db->insert('tbl_applications', $applicationData);

        if ($result) {
            return $response->withHeader('Location', '/seeker/applications')->withStatus(302);
        } else {
            // Handle error - redirect back to form
            return $response->withHeader('Location', '/seeker/applications/create/' . $jobId)->withStatus(302);
        }
    }
}
