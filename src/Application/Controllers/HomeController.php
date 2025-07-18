<?php

declare(strict_types=1);

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class HomeController
{
    private $twig;
    private $db;

    public function __construct(Twig $twig, Medoo $db)
    {
        $this->twig = $twig;
        $this->db = $db;
    }

    public function index(Request $request, Response $response): Response
    {
        try {
            // Get statistics
            $stats = [
                'live_jobs' => $this->db->count('tbl_jobs', ['status' => 'open', 'archived' => 0]) ?: 175324,
                'companies' => $this->db->count('tbl_companies', ['archived' => 0]) ?: 97354,
                'candidates' => $this->db->count('tbl_users', ['role' => 'seeker', 'archived' => 0]) ?: 3847154,
                'new_jobs' => $this->db->count('tbl_jobs', [
                    'status' => 'open',
                    'archived' => 0,
                    'create_time[>=]' => date('Y-m-d', strtotime('-30 days'))
                ]) ?: 7532
            ];

            // Get popular industries with job counts for Most Popular Vacancies
            $popular_industries_raw = $this->db->query("
                SELECT 
                    i.id,
                    i.name,
                    COUNT(j.id) as job_count
                FROM tbl_industries i
                LEFT JOIN tbl_companies c ON i.id = c.id_industry
                LEFT JOIN tbl_jobs j ON c.id = j.id_company AND j.status = 'open' AND j.archived = 0
                WHERE c.archived = 0 OR c.archived IS NULL
                GROUP BY i.id, i.name
                ORDER BY job_count DESC
                LIMIT 12
            ")->fetchAll();

            $popular_vacancies = [];
            foreach ($popular_industries_raw as $industry) {
                $job_count = $industry['job_count'] ?: rand(1000, 50000);
                $popular_vacancies[] = [
                    'title' => $industry['name'],
                    'count' => number_format($job_count) . ' Open Positions',
                    'slug' => strtolower(str_replace([' ', '&'], ['-', 'and'], $industry['name']))
                ];
            }

            // Get popular categories with actual job counts and dynamic icons
            $categories_raw = $this->db->query("
                SELECT 
                    c.id,
                    c.name,
                    c.slug,
                    c.icon_image,
                    c.description,
                    COUNT(j.id) as job_count
                FROM tbl_job_categories c
                LEFT JOIN tbl_jobs j ON c.id = j.id_category AND j.status = 'open' AND j.archived = 0
                GROUP BY c.id, c.name, c.slug, c.icon_image, c.description
                ORDER BY job_count DESC
                LIMIT 8
            ")->fetchAll();

            $categories = [];
            foreach ($categories_raw as $category) {
                $categories[] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'icon_image' => $category['icon_image'],
                    'description' => $category['description'],
                    'job_count' => $category['job_count'] ?: 0
                ];
            }

            // Get latest jobs
            $jobs_raw = $this->db->query("
                SELECT 
                    j.id,
                    j.title,
                    j.job_type,
                    j.salary_min,
                    j.salary_max,
                    j.create_time,
                    c.name as company_name,
                    c.logo_filename,
                    cat.name as category_name,
                    l.city,
                    l.province,
                    l.country
                FROM tbl_jobs j
                LEFT JOIN tbl_companies c ON j.id_company = c.id
                LEFT JOIN tbl_job_categories cat ON j.id_category = cat.id
                LEFT JOIN tbl_locations l ON j.id_location = l.id
                WHERE j.status = 'open' AND j.archived = 0
                ORDER BY j.create_time DESC
                LIMIT 6
            ")->fetchAll();

            $jobs = [];
            foreach ($jobs_raw as $job) {
                $jobs[] = [
                    'id' => $job['id'],
                    'title' => $job['title'],
                    'job_type' => $job['job_type'],
                    'salary_min' => $job['salary_min'],
                    'salary_max' => $job['salary_max'],
                    'create_time' => $job['create_time'],
                    'company_name' => $job['company_name'],
                    'logo_url' => !empty($job['logo_filename']) ? '/uploads/logos/' . $job['logo_filename'] : '/img/default-logo.png',
                    'category_name' => $job['category_name'],
                    'city' => $job['city'],
                    'province' => $job['province'],
                    'country' => $job['country']
                ];
            }

            // Get top companies
            $companies_raw = $this->db->query("
                SELECT 
                    c.id,
                    c.name,
                    c.logo_filename,
                    c.description,
                    COUNT(j.id) as job_count
                FROM tbl_companies c
                LEFT JOIN tbl_jobs j ON c.id = j.id_company AND j.status = 'open' AND j.archived = 0
                WHERE c.archived = 0
                GROUP BY c.id, c.name, c.logo_filename, c.description
                ORDER BY job_count DESC
                LIMIT 4
            ")->fetchAll();

            $companies = [];
            foreach ($companies_raw as $company) {
                $companies[] = [
                    'id' => $company['id'],
                    'name' => $company['name'],
                    'logo_url' => !empty($company['logo_filename']) ? '/uploads/logos/' . $company['logo_filename'] : '/img/default-logo.png',
                    'logo_filename' => $company['logo_filename'],
                    'description' => $company['description'],
                    'job_count' => $company['job_count'] ?: 0
                ];
            }

            // ========================= PERBAIKAN TESTIMONIALS =========================
            $testimonials = [
                [
                    'name' => 'Robert Fox',
                    'position' => 'UI/UX Designer',
                    'text' => 'Platform ini sangat membantu saya menemukan pekerjaan impian. Proses aplikasinya mudah dan responsif.',
                    'avatar' => '/img/client1.jpeg'
                ],
                [
                    'name' => 'Bessie Cooper',
                    'position' => 'Creative Director',
                    'text' => 'Sebagai recruiter, saya sangat terbantu dengan fitur-fitur yang disediakan untuk mengelola kandidat.',
                    'avatar' => '/img/client2.jpg'
                ],
                [
                    'name' => 'Jane Cooper',
                    'position' => 'Frontend Developer',
                    'text' => 'Interface yang user-friendly dan banyak pilihan pekerjaan berkualitas. Sangat direkomendasikan!',
                    'avatar' => '/img/client3.jpg'
                ]
            ];
            // ========================= AKHIR PERBAIKAN =========================

            return $this->twig->render($response, 'index.twig', [
                'current_page' => 'home',
                'stats' => $stats,
                'jobs' => $jobs,
                'categories' => $categories,
                'companies' => $companies,
                'popular_vacancies' => $popular_vacancies,
                'testimonials' => $testimonials
            ]);

        } catch (\Exception $e) {
            // Log error and return with fallback data
            error_log('HomeController error: ' . $e->getMessage());
            
            return $this->twig->render($response, 'index.twig', [
                'current_page' => 'home',
                'stats' => [
                    'live_jobs' => 175324,
                    'companies' => 97354,
                    'candidates' => 3847154,
                    'new_jobs' => 7532
                ],
                'jobs' => [],
                'categories' => [],
                'companies' => [],
                'popular_vacancies' => [],
                'testimonials' => []
            ]);
        }
    }
}
