<?php

namespace App\Application\Controllers;

use Medoo\Medoo;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CompanyReviewController
{
    protected Twig $view;
    protected Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            return $response->withHeader('Location', '/')->withStatus(403);
        }

        $reviews = $this->db->select("tbl_company_reviews", [
            "[>]tbl_companies" => ["id_company" => "id"],
            "[>]tbl_users" => ["id_user" => "id"],
        ], [
            "tbl_company_reviews.id",
            "tbl_company_reviews.rating",
            "tbl_company_reviews.title",
            "tbl_company_reviews.review_text",
            "tbl_company_reviews.created_at",
            "tbl_users.full_name(user_name)",
            "tbl_companies.name(company_name)",
        ]);

        return $this->view->render($response, 'company_reviews/admin/index.twig', compact('reviews'));
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            return $response->withHeader('Location', '/')->withStatus(403);
        }

        $users = $this->db->select("tbl_users", ["id", "full_name"]);
        $companies = $this->db->select("tbl_companies", ["id", "name"]);

        return $this->view->render($response, 'company_reviews/admin/create.twig', compact('users', 'companies'));
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            return $response->withHeader('Location', '/')->withStatus(403);
        }

        $data = $request->getParsedBody();

        $this->db->insert("tbl_company_reviews", [
            "id_company" => $data["id_company"],
            "id_user" => $data["id_user"],
            "rating" => $data["rating"],
            "title" => $data["title"],
            "review_text" => $data["review_text"],
            "created_at" => date('Y-m-d H:i:s'),
        ]);

        return $response->withHeader('Location', '/admin/company-reviews')->withStatus(302);
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            return $response->withHeader('Location', '/')->withStatus(403);
        }

        $review = $this->db->get("tbl_company_reviews", "*", ["id" => $args['id']]);
        $users = $this->db->select("tbl_users", ["id", "full_name"]);
        $companies = $this->db->select("tbl_companies", ["id", "name"]);

        return $this->view->render($response, 'company_reviews/admin/edit.twig', compact('review', 'users', 'companies'));
    }

    public function update(Request $request, Response $response, $args): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            return $response->withHeader('Location', '/')->withStatus(403);
        }

        $data = $request->getParsedBody();

        $this->db->update("tbl_company_reviews", [
            "id_company" => $data["id_company"],
            "id_user" => $data["id_user"],
            "rating" => $data["rating"],
            "title" => $data["title"],
            "review_text" => $data["review_text"],
        ], ["id" => $args['id']]);

        return $response->withHeader('Location', '/admin/company-reviews')->withStatus(302);
    }

    public function delete(Request $request, Response $response, $args): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            return $response->withHeader('Location', '/')->withStatus(403);
        }

        $this->db->delete("tbl_company_reviews", ["id" => $args['id']]);
        return $response->withHeader('Location', '/admin/company-reviews')->withStatus(302);
    }
}
