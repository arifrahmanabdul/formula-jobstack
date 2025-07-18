<?php

namespace App\Application\Controllers;

use Medoo\Medoo;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class JobAlertController
{
    protected Twig $view;
    protected Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    // GET /admin/job-alerts
    public function index(Request $request, Response $response): Response
    {
        $alerts = $this->db->select("tbl_job_alerts", [
            "[>]tbl_users" => ["id_user" => "id"],
            "[>]tbl_locations" => ["id_location" => "id"],
            "[>]tbl_job_categories" => ["id_category" => "id"]
        ], [
            "tbl_job_alerts.id",
            "tbl_job_alerts.keywords",
            "tbl_job_alerts.created_at",
            "tbl_users.full_name(user_name)",
            "tbl_locations.city(location_city)",
            "tbl_job_categories.name(category_name)"
        ]);

        return $this->view->render($response, 'job_alerts/index.twig', compact('alerts'));
    }

    // GET /admin/job-alerts/create
    public function create(Request $request, Response $response): Response
    {
        $users = $this->db->select("tbl_users", ["id", "full_name"]);
        $locations = $this->db->select("tbl_locations", ["id", "city"]);
        $categories = $this->db->select("tbl_job_categories", ["id", "name"]);

        return $this->view->render($response, 'job_alerts/create.twig', compact('users', 'locations', 'categories'));
    }

    // POST /admin/job-alerts
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $this->db->insert("tbl_job_alerts", [
            "id_user" => $data["id_user"],
            "keywords" => $data["keywords"],
            "id_location" => $data["id_location"] ?: null,
            "id_category" => $data["id_category"] ?: null,
            "created_at" => date('Y-m-d H:i:s')
        ]);

        return $response->withHeader('Location', '/admin/job-alerts')->withStatus(302);
    }

    // GET /admin/job-alerts/edit/{id}
    public function edit(Request $request, Response $response, $args): Response
    {
        $alert = $this->db->get("tbl_job_alerts", "*", ["id" => $args["id"]]);

        $users = $this->db->select("tbl_users", ["id", "full_name"]);
        $locations = $this->db->select("tbl_locations", ["id", "city"]);
        $categories = $this->db->select("tbl_job_categories", ["id", "name"]);

        return $this->view->render($response, 'job_alerts/edit.twig', compact('alert', 'users', 'locations', 'categories'));
    }

    // POST /admin/job-alerts/update/{id}
    public function update(Request $request, Response $response, $args): Response
    {
        $data = $request->getParsedBody();

        $this->db->update("tbl_job_alerts", [
            "id_user" => $data["id_user"],
            "keywords" => $data["keywords"],
            "id_location" => $data["id_location"] ?: null,
            "id_category" => $data["id_category"] ?: null
        ], ["id" => $args["id"]]);

        return $response->withHeader('Location', '/admin/job-alerts')->withStatus(302);
    }

    // GET /admin/job-alerts/delete/{id}
    public function delete(Request $request, Response $response, $args): Response
    {
        $this->db->delete("tbl_job_alerts", ["id" => $args["id"]]);
        return $response->withHeader('Location', '/admin/job-alerts')->withStatus(302);
    }
}
