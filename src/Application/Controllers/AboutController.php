<?php

declare(strict_types=1);

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AboutController
{
    private Twig $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function index(Request $request, Response $response): Response
{
    return $this->twig->render($response, 'about.twig', [
        'current_page' => 'about'  // ini untuk menandai menu active
    ]);
}

}
