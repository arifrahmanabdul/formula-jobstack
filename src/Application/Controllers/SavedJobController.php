<?php

namespace App\Application\Controllers;

use Medoo\Medoo;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SavedJobController
{
    protected Twig $view;
    protected Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    // === ADMIN ===
    public function index(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        $role = $user['role'];

        if ($role === 'admin') {
            $savedJobs = $this->db->select("tbl_saved_jobs", [
                "[>]tbl_users" => ["id_user" => "id"],
                "[>]tbl_jobs"  => ["id_job" => "id"],
            ], [
                "tbl_saved_jobs.id_user",
                "tbl_saved_jobs.id_job",
                "tbl_saved_jobs.saved_at",
                "tbl_users.full_name",
                "tbl_jobs.title(job_title)"
            ]);

            return $this->view->render($response, 'saved_jobs/admin/index.twig', compact('savedJobs'));
        } elseif ($role === 'seeker') {
            $savedJobs = $this->db->select("tbl_saved_jobs", [
                "[>]tbl_jobs"  => ["id_job" => "id"],
            ], [
                "tbl_saved_jobs.id_job",
                "tbl_saved_jobs.saved_at",
                "tbl_jobs.title(job_title)"
            ], [
                "tbl_saved_jobs.id_user" => $user['id']
            ]);

            return $this->view->render($response, 'saved_jobs/seeker/index.twig', compact('savedJobs'));
        }

        return $response->withHeader('Location', '/')->withStatus(403);
    }

    // === ADMIN ONLY ===
    public function create(Request $request, Response $response): Response
    {
        $users = $this->db->select("tbl_users", ["id", "full_name"]);
        $jobs = $this->db->select("tbl_jobs", ["id", "title"]);
        return $this->view->render($response, 'saved_jobs/admin/create.twig', compact('users', 'jobs'));
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        $data = $request->getParsedBody();

        if ($user['role'] === 'admin') {
            $this->db->insert("tbl_saved_jobs", [
                "id_user" => $data["id_user"],
                "id_job" => $data["id_job"],
                "saved_at" => date("Y-m-d H:i:s")
            ]);

            return $response->withHeader('Location', '/admin/saved-jobs')->withStatus(302);
        }

        // === SEEKER: Simpan dari job list
        if ($user['role'] === 'seeker') {
            $this->db->insert("tbl_saved_jobs", [
                "id_user" => $user["id"],
                "id_job" => $data["id_job"],
                "saved_at" => date("Y-m-d H:i:s")
            ]);

            return $response->withHeader('Location', '/seeker/saved-jobs')->withStatus(302);
        }

        return $response->withStatus(403);
    }

    public function delete(Request $request, Response $response, $args): Response
    {
        $user = $_SESSION['user'];

        if ($user['role'] === 'admin') {
            $this->db->delete("tbl_saved_jobs", [
                "id_user" => $args['id_user'],
                "id_job" => $args['id_job']
            ]);

            return $response->withHeader('Location', '/admin/saved-jobs')->withStatus(302);
        }

        if ($user['role'] === 'seeker') {
            $this->db->delete("tbl_saved_jobs", [
                "id_user" => $user['id'],
                "id_job" => $args['id_job']
            ]);

            return $response->withHeader('Location', '/seeker/saved-jobs')->withStatus(302);
        }

        return $response->withStatus(403);
    }
}
