<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class RecruiterDashboardController
{
    private Twig $view;
    private Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view =  $view;
        $this->db = $db;
    }

    /**
     * Menampilkan halaman dasbor untuk recruiter dengan statistik dinamis.
     */
    public function index(Request $request, Response $response): Response
    {
        // Mendapatkan data user dari session
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'recruiter' || !isset($user['id_company'])) {
            // Jika tidak ada user, bukan recruiter, atau tidak terhubung ke perusahaan,
            // render dengan data default atau redirect.
            return $this->view->render($response, 'dashboard/recruiter.twig', [
                'totalLowongan' => 0,
                'totalLamaran' => 0
            ]);
        }

        $companyId = $user['id_company'];

        // 1. Menghitung total lowongan yang dimiliki perusahaan
        $totalLowongan = $this->db->count('tbl_jobs', [
            'id_company' => $companyId,
            'archived' => 0
        ]);

        // 2. Menghitung total lamaran untuk semua lowongan dari perusahaan tersebut
        // Pertama, dapatkan semua ID lowongan dari perusahaan ini
        $jobIds = $this->db->select('tbl_jobs', 'id', [
            'id_company' => $companyId
        ]);

        $totalLamaran = 0;
        if (!empty($jobIds)) {
            // Hitung jumlah lamaran yang masuk ke lowongan-lowongan tersebut
            $totalLamaran = $this->db->count('tbl_applications', [
                'id_job' => $jobIds,
                'archived' => 0
            ]);
        }
        
        // Render view dengan data statistik
        return $this->view->render($response, 'dashboard/recruiter.twig', [
            'totalLowongan' => $totalLowongan ?: 0,
            'totalLamaran' => $totalLamaran ?: 0
        ]);
    }
}