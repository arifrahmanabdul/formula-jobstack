<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class SeekerDashboardController
{
    private Twig $view;
    private Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view =  $view;
        $this->db = $db;
    }

    /**
     * Menampilkan halaman dasbor untuk seeker dengan statistik dinamis.
     */
    public function index(Request $request, Response $response): Response
    {
        // Mendapatkan data user dari session
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'seeker') {
            // Jika tidak ada user atau bukan seeker, render dengan data default atau redirect.
            return $this->view->render($response, 'dashboard/seeker.twig', [
                'totalLamaran' => 0
            ]);
        }

        // Menghitung total lamaran yang telah dikirim oleh user
        $totalLamaran = $this->db->count('tbl_applications', [
            'id_user' => $user['id'],
            'archived' => 0
        ]);
        
        // Render view dengan data statistik
        return $this->view->render($response, 'dashboard/seeker.twig', [
            'totalLamaran' => $totalLamaran ?: 0
        ]);
    }
}