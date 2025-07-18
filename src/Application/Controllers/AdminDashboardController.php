<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class AdminDashboardController
{
    private Twig $view;
    private Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view =  $view;
        $this->db = $db;
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'dashboard/admin.twig');
    }

    public function getStatistics(Request $request, Response $response): Response
    {
        try {
            // Get current date for comparison
            $currentDate = date('Y-m-d');
            $lastMonth = date('Y-m-d', strtotime('-30 days'));

            // Get total jobs - FIXED: Added tbl_ prefix
            $totalJobs = $this->db->count('tbl_jobs');
            $totalJobsLastMonth = $this->db->count('tbl_jobs', [
                'create_time[<]' => $lastMonth
            ]);
            $jobsGrowth = $totalJobsLastMonth > 0 ? 
                (($totalJobs - $totalJobsLastMonth) / $totalJobsLastMonth) * 100 : 
                ($totalJobs > 0 ? 100 : 0);

            // Get total companies - FIXED: Added tbl_ prefix
            $totalCompanies = $this->db->count('tbl_companies');
            $totalCompaniesLastMonth = $this->db->count('tbl_companies', [
                'create_time[<]' => $lastMonth
            ]);
            $companiesGrowth = $totalCompaniesLastMonth > 0 ? 
                (($totalCompanies - $totalCompaniesLastMonth) / $totalCompaniesLastMonth) * 100 : 
                ($totalCompanies > 0 ? 100 : 0);

            // Get total applications - FIXED: Added tbl_ prefix
            $totalApplications = $this->db->count('tbl_applications');
            $totalApplicationsLastMonth = $this->db->count('tbl_applications', [
                'create_time[<]' => $lastMonth
            ]);
            $applicationsGrowth = $totalApplicationsLastMonth > 0 ? 
                (($totalApplications - $totalApplicationsLastMonth) / $totalApplicationsLastMonth) * 100 : 
                ($totalApplications > 0 ? 100 : 0);

            // Get total users (seekers + recruiters) - FIXED: Added tbl_ prefix
            $totalUsers = $this->db->count('tbl_users');
            $totalUsersLastMonth = $this->db->count('tbl_users', [
                'create_time[<]' => $lastMonth
            ]);
            $usersGrowth = $totalUsersLastMonth > 0 ? 
                (($totalUsers - $totalUsersLastMonth) / $totalUsersLastMonth) * 100 : 
                ($totalUsers > 0 ? 100 : 0);

            $data = [
                'total_jobs' => [
                    'count' => $totalJobs,
                    'growth' => round($jobsGrowth, 1),
                    'growth_text' => ($jobsGrowth >= 0 ? '+' : '') . round($jobsGrowth, 1) . '%'
                ],
                'total_companies' => [
                    'count' => $totalCompanies,
                    'growth' => round($companiesGrowth, 1),
                    'growth_text' => ($companiesGrowth >= 0 ? '+' : '') . round($companiesGrowth, 1) . '%'
                ],
                'total_applications' => [
                    'count' => $totalApplications,
                    'growth' => round($applicationsGrowth, 1),
                    'growth_text' => ($applicationsGrowth >= 0 ? '+' : '') . round($applicationsGrowth, 1) . '%'
                ],
                'total_users' => [
                    'count' => $totalUsers,
                    'growth' => round($usersGrowth, 1),
                    'growth_text' => ($usersGrowth >= 0 ? '+' : '') . round($usersGrowth, 1) . '%'
                ]
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $data
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            error_log('Statistics API Error: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to fetch statistics',
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    public function getRecentActivity(Request $request, Response $response): Response
    {
        try {
            $activities = [];

            // Get recent job applications - FIXED: Added tbl_ prefix
            $recentApplications = $this->db->select('tbl_applications', [
                '[>]tbl_jobs' => ['id_job' => 'id'],
                '[>]tbl_users' => ['id_user' => 'id']
            ], [
                'tbl_applications.id',
                'tbl_applications.create_time',
                'tbl_jobs.title(job_title)',
                'tbl_users.full_name(applicant_name)'
            ], [
                'ORDER' => ['tbl_applications.create_time' => 'DESC'],
                'LIMIT' => 5
            ]);

            foreach ($recentApplications as $application) {
                $activities[] = [
                    'title' => 'Lamaran Baru',
                    'description' => $application['applicant_name'] . ' melamar untuk posisi ' . $application['job_title'],
                    'time' => $this->timeAgo($application['create_time']),
                    'icon' => 'fas fa-file-alt',
                    'color' => 'primary'
                ];
            }

            // Get recent job postings - FIXED: Added tbl_ prefix
            $recentJobs = $this->db->select('tbl_jobs', [
                '[>]tbl_companies' => ['id_company' => 'id']
            ], [
                'tbl_jobs.id',
                'tbl_jobs.title',
                'tbl_jobs.create_time',
                'tbl_companies.name(company_name)'
            ], [
                'ORDER' => ['tbl_jobs.create_time' => 'DESC'],
                'LIMIT' => 3
            ]);

            foreach ($recentJobs as $job) {
                $activities[] = [
                    'title' => 'Lowongan Baru',
                    'description' => $job['company_name'] . ' memposting lowongan ' . $job['title'],
                    'time' => $this->timeAgo($job['create_time']),
                    'icon' => 'fas fa-briefcase',
                    'color' => 'success'
                ];
            }

            // Get recent company registrations - FIXED: Added tbl_ prefix
            $recentCompanies = $this->db->select('tbl_companies', [
                'id',
                'name',
                'create_time'
            ], [
                'ORDER' => ['create_time' => 'DESC'],
                'LIMIT' => 2
            ]);

            foreach ($recentCompanies as $company) {
                $activities[] = [
                    'title' => 'Perusahaan Baru',
                    'description' => $company['name'] . ' bergabung dengan platform',
                    'time' => $this->timeAgo($company['create_time']),
                    'icon' => 'fas fa-building',
                    'color' => 'info'
                ];
            }

            // Sort activities by time (most recent first)
            usort($activities, function($a, $b) {
                return strcmp($b['time'], $a['time']);
            });

            // Limit to 10 most recent activities
            $activities = array_slice($activities, 0, 10);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $activities
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            error_log('Recent Activity API Error: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to fetch recent activity',
                'message' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    private function timeAgo($datetime): string
    {
        $time = time() - strtotime($datetime);

        if ($time < 60) {
            return 'Baru saja';
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . ' menit yang lalu';
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . ' jam yang lalu';
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            return $days . ' hari yang lalu';
        } elseif ($time < 31536000) {
            $months = floor($time / 2592000);
            return $months . ' bulan yang lalu';
        } else {
            $years = floor($time / 31536000);
            return $years . ' tahun yang lalu';
        }
    }
}
