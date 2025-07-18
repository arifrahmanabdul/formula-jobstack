<?php

namespace App\Application\Controllers;

use Medoo\Medoo;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApplicationNoteController
{
    protected Medoo $db;
    protected Twig $view;

    public function __construct(Medoo $db, Twig $view)
    {
        $this->db = $db;
        $this->view = $view;
    }

    // ========== ADMIN ==========
    public function index(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];

        if ($user['role'] === 'admin') {
            $notes = $this->db->select('tbl_application_notes', '*');
            return $this->view->render($response, 'application_notes/admin/index.twig', compact('notes'));
        }

        if ($user['role'] === 'recruiter') {
            $notes = $this->db->select('tbl_application_notes', '*', [
                'id_recruiter' => $user['id']
            ]);
            return $this->view->render($response, 'application_notes/recruiter/index.twig', compact('notes'));
        }

        return $response->withHeader('Location', '/')->withStatus(403);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            return $response->withHeader('Location', '/admin/application-notes')->withStatus(403);
        }

        $applications = $this->db->select('tbl_applications', [
            '[>]tbl_users' => ['id_user' => 'id'],
            '[>]tbl_jobs' => ['id_job' => 'id']
        ], [
            'tbl_applications.id',
            'tbl_users.full_name',
            'tbl_jobs.title'
        ]);

        return $this->view->render($response, 'application_notes/admin/create.twig', compact('applications'));
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        $data = $request->getParsedBody();

        $this->db->insert('tbl_application_notes', [
            'id_application' => $data['id_application'],
            'id_recruiter'   => $user['id'], // berlaku juga untuk admin
            'note_type'      => $data['note_type'],
            'note_text'      => $data['note_text'],
            'created_at'     => date('Y-m-d H:i:s')
        ]);

        if ($user['role'] === 'admin') {
            return $response->withHeader('Location', '/admin/application-notes')->withStatus(302);
        }

        // Redirect ke halaman show aplikasi
        return $response->withHeader('Location', '/recruiter/applications/show/' . $data['id_application'])->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'];
        $note = $this->db->get('tbl_application_notes', '*', ['id' => $args['id']]);

        if (!$note) {
            return $response->withHeader('Location', '/')->withStatus(404);
        }

        // Validasi hak akses recruiter
        if ($user['role'] === 'recruiter' && $note['id_recruiter'] !== $user['id']) {
            return $response->withHeader('Location', '/recruiter/application-notes')->withStatus(403);
        }

        // Simpan id_application untuk redirect nanti
        $id_application = $note['id_application'];

        // Hapus catatan
        $this->db->delete('tbl_application_notes', ['id' => $args['id']]);

        if ($user['role'] === 'admin') {
            return $response->withHeader('Location', '/admin/application-notes')->withStatus(302);
        }

        // Redirect ke halaman detail aplikasi
        return $response->withHeader('Location', '/recruiter/applications/show/' . $id_application)->withStatus(302);
    }

    public function createForRecruiter(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'];
        $id_application = (int)$args['id_application'];

        if ($user['role'] !== 'recruiter') {
            $response->getBody()->write("Akses ditolak.");
            return $response->withStatus(403);
        }

        // Ambil data lamaran + cek perusahaan
        $application = $this->db->get("tbl_applications (a)", [
            "[>]tbl_jobs (j)"      => ["a.id_job" => "id"],
            "[>]tbl_companies (c)" => ["j.id_company" => "id"],
            "[>]tbl_users (u)"     => ["a.id_user" => "id"]
        ], [
            "a.id AS id",
            "u.full_name",
            "j.title",
            "c.id_recruiter"
        ], [
            "a.id" => $id_application
        ]);

        // Validasi hak akses recruiter
        if (!$application || $application['id_recruiter'] != $user['id']) {
            $response->getBody()->write("Unauthorized: Lamaran tidak milik perusahaan Anda.");
            return $response->withStatus(403);
        }

        return $this->view->render($response, 'application_notes/recruiter/create.twig', [
            'application' => $application,
            'id_application' => $id_application
        ]);
    }



    public function edit(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'];
        $note = $this->db->get('tbl_application_notes', '*', ['id' => $args['id']]);

        if (!$note) {
            return $response->withHeader('Location', '/')->withStatus(404);
        }

        if ($user['role'] === 'recruiter' && $note['id_recruiter'] !== $user['id']) {
            return $response->withHeader('Location', '/recruiter/application-notes')->withStatus(403);
        }

        return $this->view->render($response, 'application_notes/recruiter/edit.twig', compact('note'));
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'];
        $note = $this->db->get('tbl_application_notes', '*', ['id' => $args['id']]);

        if (!$note) {
            return $response->withHeader('Location', '/')->withStatus(404);
        }

        if ($user['role'] === 'recruiter' && $note['id_recruiter'] !== $user['id']) {
            return $response->withHeader('Location', '/recruiter/application-notes')->withStatus(403);
        }

        $data = $request->getParsedBody();
        $this->db->update('tbl_application_notes', [
            'note_type' => $data['note_type'],
            'note_text' => $data['note_text'],
        ], ['id' => $args['id']]);

        if ($user['role'] === 'admin') {
            return $response->withHeader('Location', '/admin/application-notes')->withStatus(302);
        }

        return $response->withHeader('Location', '/recruiter/application-notes')->withStatus(302);
    }
}
