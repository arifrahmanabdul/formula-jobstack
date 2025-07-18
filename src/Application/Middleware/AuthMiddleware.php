<?php
namespace App\Application\Middleware;

class AuthMiddleware {
    public function __invoke($request, $handler) {
        if (!isset($_SESSION['user'])) {
            return (new \Slim\Psr7\Response())->withHeader('Location', '/login')->withStatus(302);
        }
        return $handler->handle($request);
    }
}
