<?php

/**
 * App\Application\Controllers\FaqController
 * ---------------------------------------------------------------
 * Controller sederhana untuk menampilkan halaman FAQ statis.
 * Tidak ada dependensi repository atau pencarian dinamis.
 */

declare(strict_types=1);

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class FaqController
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    /**
     * GET /faq atau /faqs
     * Merender template Twig `faq.twig` dengan data sederhana.
     */
    public function index(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'faq.twig', [
            'current_page' => 'faq', // menandai menu aktif di navbar
        ]);
    }
}
