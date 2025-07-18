<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class JobSkillController
{
    protected Twig $view;
    protected Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    // Rekruter hanya melihat skills pada job miliknya
    public function index(Request $request, Response $response, array $args): Response
    {
        $id_job = (int) $args['id_job'];

        $job = $this->db->get("tbl_jobs", "*", ["id" => $id_job]);
        $skills = $this->db->select("tbl_job_skills", [
            "[>]tbl_skills" => ["id_skill" => "id"]
        ], [
            "tbl_job_skills.id_job",
            "tbl_job_skills.id_skill",
            "tbl_skills.name(skill_name)"
        ], ["tbl_job_skills.id_job" => $id_job]);

        return $this->view->render($response, "job_skills/recruiter/index.twig", [
            "job" => $job,
            "jobSkills" => $skills
        ]);
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $id_job = (int) $args['id_job'];
        $job = $this->db->get("tbl_jobs", "*", ["id" => $id_job]);
        $skills = $this->db->select("tbl_skills", ["id", "name"]);

        return $this->view->render($response, "job_skills/recruiter/create.twig", [
            "job" => $job,
            "skills" => $skills
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $this->db->insert("tbl_job_skills", [
            "id_job" => $data['id_job'],
            "id_skill" => $data['id_skill']
        ]);

        return $response->withHeader('Location', '/recruiter/job-skills/' . $data['id_job'])->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->db->delete("tbl_job_skills", [
            "id_job" => $args['id_job'],
            "id_skill" => $args['id_skill']
        ]);

        return $response->withHeader('Location', '/recruiter/job-skills/' . $args['id_job'])->withStatus(302);
    }
}
