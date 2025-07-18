<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class UserSkillController
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
        $role = $user['role'];

        if ($role === 'seeker') {
            $userSkills = $this->db->select("tbl_user_skills", [
                "[>]tbl_skills" => ["id_skill" => "id"]
            ], [
                "tbl_user_skills.id_skill",
                "tbl_user_skills.pdf",
                "tbl_skills.name(skill_name)"
            ], [
                "id_user" => $user['id']
            ]);

            return $this->view->render($response, 'user_skills/seeker/index.twig', compact('userSkills'));
        }

        // admin & recruiter
        $userSkills = $this->db->select("tbl_user_skills", [
            "[>]tbl_users" => ["id_user" => "id"],
            "[>]tbl_skills" => ["id_skill" => "id"]
        ], [
            "tbl_users.full_name",
            "tbl_skills.name(skill_name)"
        ]);

        $template = $role === 'admin' ? 'user_skills/admin/index.twig' : 'user_skills/recruiter/index.twig';

        return $this->view->render($response, $template, compact('userSkills'));
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];

        if ($user['role'] !== 'seeker') return $response->withStatus(403);

        $skills = $this->db->select("tbl_skills", ["id", "name"]);
        return $this->view->render($response, 'user_skills/seeker/create.twig', compact('skills'));
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'seeker') return $response->withStatus(403);

        $data = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        $pdfName = null;

        if (isset($uploadedFiles['pdf'])) {
            $pdfFile = $uploadedFiles['pdf'];

            if ($pdfFile->getError() === UPLOAD_ERR_OK) {
                $extension = pathinfo($pdfFile->getClientFilename(), PATHINFO_EXTENSION);
                $fileSize = $pdfFile->getSize();

                // Validasi tipe dan ukuran file
                if (strtolower($extension) === 'pdf' && $fileSize <= 5 * 1024 * 1024) {
                    $pdfName = uniqid('cert_') . '.' . $extension;
                    $uploadDir = __DIR__ . '/../../../public/uploads/certificates';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $pdfFile->moveTo($uploadDir . DIRECTORY_SEPARATOR . $pdfName);
                } else {
                    // Tampilkan pesan kesalahan jika tidak valid
                    $response->getBody()->write("File harus berupa PDF dan maksimal 5MB.");
                    return $response->withStatus(400);
                }
            }
        }
        $exists = $this->db->has('tbl_user_skills', [
            'id_user' => $user['id'],
            'id_skill' => $data['id_skill']
        ]);

        if ($exists) {
            $response->getBody()->write("Skill ini sudah ditambahkan sebelumnya.");
            return $response->withStatus(400);
        }

        $this->db->insert("tbl_user_skills", [
            "id_user" => $user['id'],
            "id_skill" => $data['id_skill'],
            "pdf" => $pdfName
        ]);

        return $response->withHeader('Location', '/seeker/user-skills')->withStatus(302);
    }



    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'];
        if ($user['role'] !== 'seeker') return $response->withStatus(403);

        // Ambil nama file PDF dari database
        $skill = $this->db->get("tbl_user_skills", ["pdf"], [
            "id_user" => $user['id'],
            "id_skill" => $args['id_skill']
        ]);

        if ($skill && $skill['pdf']) {
            $filePath = __DIR__ . '/../../../public/uploads/certificates/' . $skill['pdf'];
            if (file_exists($filePath)) {
                unlink($filePath); // Hapus file dari server
            }
        }

        // Hapus data dari tabel
        $this->db->delete("tbl_user_skills", [
            "id_user" => $user['id'],
            "id_skill" => $args['id_skill']
        ]);

        return $response->withHeader('Location', '/seeker/user-skills')->withStatus(302);
    }
}
