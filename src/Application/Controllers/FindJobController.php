<?php

namespace App\Application\Controllers;

use Medoo\Medoo;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FindJobController
{
    private Twig $view;
    private Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        
        // Get filter parameters
        $keyword = $queryParams['keyword'] ?? '';
        $location = $queryParams['location'] ?? '';
        $category = $queryParams['category'] ?? '';
        $company = $queryParams['company'] ?? '';  // Tambahkan parameter company
        $jobType = $queryParams['job_type'] ?? '';
        $salaryMin = $queryParams['salary_min'] ?? '';
        $salaryMax = $queryParams['salary_max'] ?? '';
        $sort = $queryParams['sort'] ?? 'latest';
        $page = (int)($queryParams['page'] ?? 1);
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        // Build WHERE conditions
        $whereConditions = [
            "tbl_jobs.archived" => 0,
            "tbl_jobs.status" => "open"
        ];

        if (!empty($keyword)) {
            $whereConditions["OR"] = [
                "tbl_jobs.title[~]" => $keyword,
                "tbl_jobs.description[~]" => $keyword,
                "tbl_companies.name[~]" => $keyword
            ];
        }

        if (!empty($location)) {
            $whereConditions["OR #location"] = [
                "tbl_locations.city[~]" => $location,
                "tbl_locations.province[~]" => $location
            ];
        }

        if (!empty($category)) {
            // Support both category ID and slug
            if (is_numeric($category)) {
                $whereConditions["tbl_jobs.id_category"] = $category;
            } else {
                // If it's a slug, get the category ID first
                $categoryId = $this->db->get('tbl_job_categories', 'id', ['slug' => $category]);
                if ($categoryId) {
                    $whereConditions["tbl_jobs.id_category"] = $categoryId;
                }
            }
        }

        if (!empty($company)) {
            $whereConditions["tbl_jobs.id_company"] = $company;
        }

        if (!empty($jobType)) {
            $whereConditions["tbl_jobs.job_type"] = $jobType;
        }

        if (!empty($salaryMin)) {
            $whereConditions["tbl_jobs.salary_min[>=]"] = $salaryMin;
        }

        if (!empty($salaryMax)) {
            $whereConditions["tbl_jobs.salary_max[<=]"] = $salaryMax;
        }

        // Build ORDER BY
        $orderBy = [];
        switch ($sort) {
            case 'salary_high':
                $orderBy = ["tbl_jobs.salary_max" => "DESC"];
                break;
            case 'salary_low':
                $orderBy = ["tbl_jobs.salary_min" => "ASC"];
                break;
            case 'company':
                $orderBy = ["tbl_companies.name" => "ASC"];
                break;
            case 'latest':
            default:
                $orderBy = ["tbl_jobs.posted_date" => "DESC", "tbl_jobs.create_time" => "DESC"];
                break;
        }

        // Get total count for pagination
        $totalJobs = $this->db->count('tbl_jobs', [
            "[>]tbl_companies" => ["id_company" => "id"],
            "[>]tbl_locations" => ["id_location" => "id"],
            "[>]tbl_job_categories" => ["id_category" => "id"]
        ], "*", $whereConditions);

        // Get jobs with pagination
        $jobs = $this->db->select('tbl_jobs', [
            "[>]tbl_companies" => ["id_company" => "id"],
            "[>]tbl_locations" => ["id_location" => "id"],
            "[>]tbl_job_categories" => ["id_category" => "id"]
        ], [
            "tbl_jobs.id",
            "tbl_jobs.title",
            "tbl_jobs.description",
            "tbl_jobs.salary_min",
            "tbl_jobs.salary_max",
            "tbl_jobs.job_type",
            "tbl_jobs.featured",
            "tbl_jobs.posted_date",
            "tbl_jobs.create_time",
            "tbl_companies.name(company_name)",
            "tbl_companies.logo_filename(company_logo)",
            "tbl_companies.company_size",
            "tbl_locations.city(location_name)",
            "tbl_job_categories.name(category_name)"
        ], array_merge($whereConditions, [
            "ORDER" => $orderBy,
            "LIMIT" => [$offset, $perPage]
        ]));

        // Get filter options
        $categories = $this->db->select('tbl_job_categories', ['id', 'name'], ['ORDER' => ['name' => 'ASC']]);
        $locations = $this->db->select('tbl_locations', ['id', 'city'], ['ORDER' => ['city' => 'ASC']]);
        
        // Calculate pagination
        $totalPages = ceil($totalJobs / $perPage);
        $pagination = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_jobs' => $totalJobs,
            'per_page' => $perPage,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'next_page' => $page < $totalPages ? $page + 1 : null
        ];

        // Process jobs data
        foreach ($jobs as &$job) {
            $job['posted_ago'] = $this->timeAgo($job['posted_date'] ?: $job['create_time']);
            $job['salary_formatted'] = $this->formatSalary($job['salary_min'], $job['salary_max']);
            $job['company_logo_url'] = $job['company_logo'] ? '/uploads/logos/' . $job['company_logo'] : '/img/default-company.png';
        }

        // Active filters for display
        $activeFilters = [];
        if (!empty($keyword)) $activeFilters[] = ['type' => 'keyword', 'value' => $keyword, 'label' => "Keyword: $keyword"];
        if (!empty($location)) $activeFilters[] = ['type' => 'location', 'value' => $location, 'label' => "Location: $location"];
        if (!empty($category)) {
            if (is_numeric($category)) {
                $categoryName = $this->db->get('tbl_job_categories', 'name', ['id' => $category]);
            } else {
                $categoryName = $this->db->get('tbl_job_categories', 'name', ['slug' => $category]);
            }
            $activeFilters[] = ['type' => 'category', 'value' => $category, 'label' => "Category: $categoryName"];
        }
        if (!empty($company)) {
            $companyName = $this->db->get('tbl_companies', 'name', ['id' => $company]);
            $activeFilters[] = ['type' => 'company', 'value' => $company, 'label' => "Company: $companyName"];
        }
        if (!empty($jobType)) $activeFilters[] = ['type' => 'job_type', 'value' => $jobType, 'label' => "Type: $jobType"];

        return $this->view->render($response, 'find_job/index.twig', [
            'jobs' => $jobs,
            'categories' => $categories,
            'locations' => $locations,
            'pagination' => $pagination,
            'activeFilters' => $activeFilters,
            'currentFilters' => [
                'keyword' => $keyword,
                'location' => $location,
                'category' => $category,
                'company' => $company,  // Tambahkan company ke currentFilters
                'job_type' => $jobType,
                'salary_min' => $salaryMin,
                'salary_max' => $salaryMax,
                'sort' => $sort
            ]
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $jobId = $args['id'];

        // Get job details
        $job = $this->db->get('tbl_jobs', [
            "[>]tbl_companies" => ["id_company" => "id"],
            "[>]tbl_locations" => ["id_location" => "id"],
            "[>]tbl_job_categories" => ["id_category" => "id"],
            "[>]tbl_industries" => ["tbl_companies.id_industry" => "id"]
        ], [
            "tbl_jobs.id",
            "tbl_jobs.title",
            "tbl_jobs.description",
            "tbl_jobs.requirements",
            "tbl_jobs.salary_min",
            "tbl_jobs.salary_max",
            "tbl_jobs.job_type",
            "tbl_jobs.featured",
            "tbl_jobs.posted_date",
            "tbl_jobs.deadline",
            "tbl_jobs.create_time",
            "tbl_companies.id(company_id)",
            "tbl_companies.name(company_name)",
            "tbl_companies.description(company_description)",
            "tbl_companies.logo_filename(company_logo)",
            "tbl_companies.website(company_website)",
            "tbl_companies.founded_year",
            "tbl_companies.company_size",
            "tbl_companies.company_type",
            "tbl_locations.city(location_name)",
            "tbl_locations.province",
            "tbl_job_categories.name(category_name)",
            "tbl_industries.name(industry_name)"
        ], [
            "tbl_jobs.id" => $jobId,
            "tbl_jobs.archived" => 0,
            "tbl_jobs.status" => "open"
        ]);

        if (!$job) {
            $response->getBody()->write("Job not found");
            return $response->withStatus(404);
        }

        // Update views count
        $this->db->update('tbl_jobs', [
            'views_count[+]' => 1
        ], ['id' => $jobId]);

        // Get job skills
        $jobSkills = $this->db->select('tbl_job_skills', [
            "[>]tbl_skills" => ["id_skill" => "id"]
        ], [
            "tbl_skills.id",
            "tbl_skills.name"
        ], [
            "tbl_job_skills.id_job" => $jobId
        ]);

        // Get company stats
        $companyStats = [
            'total_jobs' => $this->db->count('tbl_jobs', [
                'id_company' => $job['company_id'],
                'archived' => 0
            ]),
            'total_employees' => $job['company_size'] ?? 'N/A'
        ];

        // Get related jobs (same category, different job)
        $relatedJobs = $this->db->select('tbl_jobs', [
            "[>]tbl_companies" => ["id_company" => "id"],
            "[>]tbl_locations" => ["id_location" => "id"]
        ], [
            "tbl_jobs.id",
            "tbl_jobs.title",
            "tbl_jobs.salary_min",
            "tbl_jobs.salary_max",
            "tbl_jobs.job_type",
            "tbl_jobs.posted_date",
            "tbl_jobs.create_time",
            "tbl_companies.name(company_name)",
            "tbl_companies.logo_filename(company_logo)",
            "tbl_locations.city(location_name)"
        ], [
            "tbl_jobs.id_category" => $job['id_category'] ?? 0,
            "tbl_jobs.id[!]" => $jobId,
            "tbl_jobs.archived" => 0,
            "tbl_jobs.status" => "open",
            "ORDER" => ["tbl_jobs.posted_date" => "DESC"],
            "LIMIT" => 6
        ]);

        // Process data
        $job['posted_ago'] = $this->timeAgo($job['posted_date'] ?: $job['create_time']);
        $job['salary_formatted'] = $this->formatSalary($job['salary_min'], $job['salary_max']);
        $job['company_logo_url'] = $job['company_logo'] ? '/uploads/logos/' . $job['company_logo'] : '/img/default-company.png';
        $job['requirements_list'] = $job['requirements'] ? explode("\n", $job['requirements']) : [];
        $job['description_paragraphs'] = $job['description'] ? explode("\n\n", $job['description']) : [];
        $job['skills'] = $jobSkills;

        foreach ($relatedJobs as &$relatedJob) {
            $relatedJob['posted_ago'] = $this->timeAgo($relatedJob['posted_date'] ?: $relatedJob['create_time']);
            $relatedJob['salary_formatted'] = $this->formatSalary($relatedJob['salary_min'], $relatedJob['salary_max']);
            $relatedJob['company_logo_url'] = $relatedJob['company_logo'] ? '/uploads/logos/' . $relatedJob['company_logo'] : '/img/default-company.png';
        }

        return $this->view->render($response, 'find_job/detail.twig', [
            'job' => $job,
            'companyStats' => $companyStats,
            'relatedJobs' => $relatedJobs
        ]);
    }

    private function timeAgo($date): string
    {
        if (!$date) return 'Baru saja';
        
        $now = new \DateTime();
        $posted = new \DateTime($date);
        $diff = $now->diff($posted);

        if ($diff->days == 0) {
            if ($diff->h == 0) {
                return $diff->i . ' menit yang lalu';
            }
            return $diff->h . ' jam yang lalu';
        } elseif ($diff->days == 1) {
            return '1 hari yang lalu';
        } elseif ($diff->days < 7) {
            return $diff->days . ' hari yang lalu';
        } elseif ($diff->days < 30) {
            $weeks = floor($diff->days / 7);
            return $weeks . ' minggu yang lalu';
        } else {
            $months = floor($diff->days / 30);
            return $months . ' bulan yang lalu';
        }
    }

    private function formatSalary($min, $max): string
    {
        if (!$min && !$max) {
            return 'Gaji dapat dinegosiasi';
        }

        $formatter = function($amount) {
            if ($amount >= 1000000000) {
                return 'Rp ' . number_format($amount / 1000000000, 1, ',', '.') . ' Miliar';
            } elseif ($amount >= 1000000) {
                return 'Rp ' . number_format($amount / 1000000, 1, ',', '.') . ' Juta';
            } elseif ($amount >= 1000) {
                return 'Rp ' . number_format($amount / 1000, 0, ',', '.') . ' Ribu';
            }
            return 'Rp ' . number_format($amount, 0, ',', '.');
        };

        if ($min && $max) {
            return $formatter($min) . ' - ' . $formatter($max);
        } elseif ($min) {
            return 'Mulai dari ' . $formatter($min);
        } else {
            return 'Hingga ' . $formatter($max);
        }
    }

    public function seekerIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Metode ini hanya merender template utama.
        // Semua pengambilan data akan dilakukan oleh JavaScript melalui endpoint /admin/jobs/data
        return $this->view->render($response, 'find_job/seeker/index.twig');
    }

    public function seekerDetail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $jobId = (int)$args['id'];
        $job = $this->db->get('tbl_jobs', [
            "[>]tbl_companies" => ["id_company" => "id"],
            "[>]tbl_locations" => ["id_location" => "id"],
            "[>]tbl_job_categories" => ["id_category" => "id"]
        ], [
            "tbl_jobs.id", "tbl_jobs.title", "tbl_jobs.description", "tbl_jobs.requirements",
            "tbl_jobs.salary_min", "tbl_jobs.salary_max", "tbl_jobs.job_type",
            "tbl_companies.name(company_name)", "tbl_companies.logo_filename(company_logo)",
            "tbl_locations.city(location_name)", "tbl_job_categories.name(category_name)"
        ], [
            "tbl_jobs.id" => $jobId,
            "tbl_jobs.archived" => 0,
            "tbl_jobs.status" => "open"
        ]);

        if (!$job) {
            // Jika pekerjaan tidak ditemukan, tampilkan halaman detail dengan pesan error
            return $this->view->render($response, 'find_job/seeker/detail.twig', [
                'job' => null,
                'error' => 'Pekerjaan tidak ditemukan atau sudah ditutup.'
            ])->withStatus(404);
        }
        
        // Memformat data agar mudah ditampilkan di view
        $job['requirements_list'] = $job['requirements'] ? array_filter(array_map('trim', explode("\n", $job['requirements']))) : [];
        $job['description_html'] = nl2br(htmlspecialchars($job['description']));

        return $this->view->render($response, 'find_job/seeker/detail.twig', [
            'job' => $job
        ]);
    }

    public function getSeekerJobsData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $limit = (int)($queryParams['limit'] ?? 9);
        $search = trim($queryParams['search'] ?? '');
        $offset = ($page - 1) * $limit;

        // Build WHERE conditions for active jobs only
        $whereConditions = [
            "tbl_jobs.archived" => 0,
            "tbl_jobs.status" => "open"
        ];

        if (!empty($search)) {
            $whereConditions["OR"] = [
                "tbl_jobs.title[~]" => $search,
                "tbl_jobs.description[~]" => $search,
                "tbl_companies.name[~]" => $search
            ];
        }

        try {
            // Get total count
            $totalJobs = $this->db->count('tbl_jobs', [
                "[>]tbl_companies" => ["id_company" => "id"],
                "[>]tbl_locations" => ["id_location" => "id"],
                "[>]tbl_job_categories" => ["id_category" => "id"]
            ], "*", $whereConditions);

            // Get jobs with pagination
            $jobs = $this->db->select('tbl_jobs', [
                "[>]tbl_companies" => ["id_company" => "id"],
                "[>]tbl_locations" => ["id_location" => "id"],
                "[>]tbl_job_categories" => ["id_category" => "id"]
            ], [
                "tbl_jobs.id",
                "tbl_jobs.title",
                "tbl_jobs.description",
                "tbl_jobs.salary_min",
                "tbl_jobs.salary_max",
                "tbl_jobs.job_type",
                "tbl_jobs.featured",
                "tbl_jobs.posted_date",
                "tbl_jobs.create_time",
                "tbl_companies.name(company_name)",
                "tbl_companies.logo_filename(company_logo)",
                "tbl_companies.company_size",
                "tbl_locations.city(location_name)",
                "tbl_job_categories.name(category_name)"
            ], array_merge($whereConditions, [
                "ORDER" => ["tbl_jobs.posted_date" => "DESC", "tbl_jobs.create_time" => "DESC"],
                "LIMIT" => [$offset, $limit]
            ]));

            // Calculate pagination
            $totalPages = ceil($totalJobs / $limit);
            
            $result = [
                'success' => true,
                'data' => $jobs,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalJobs,
                    'per_page' => $limit,
                    'has_prev' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'message' => 'Gagal memuat data lowongan: ' . $e->getMessage(),
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_records' => 0,
                    'per_page' => $limit
                ]
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
