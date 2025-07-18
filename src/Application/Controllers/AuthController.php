<?php

namespace App\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Medoo\Medoo;

class AuthController
{
    private Twig $view;
    private Medoo $db;

    public function __construct(Twig $view, Medoo $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Email dan password wajib diisi.'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            return $this->view->render($response, 'auth/login.twig', [
                'error' => 'Email dan password wajib diisi.'
            ]);
        }

        // Find user
        $user = $this->db->get("tbl_users", "*", [
            "email" => $data['email'],
            "archived" => 0
        ]);

        if ($user && password_verify($data['password'], $user['password'])) {
            $_SESSION['user'] = $user;

            // Determine redirect URL based on role
            $redirectUrl = match ($user['role']) {
                'admin' => '/dashboard/admin',
                'recruiter' => '/dashboard/recruiter',
                default => '/dashboard/seeker'
            };

            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Login berhasil!',
                    'redirect' => $redirectUrl
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            return $response->withHeader('Location', $redirectUrl)->withStatus(302);
        }

        $errorMessage = 'Email atau password salah.';
        
        if ($isAjax) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $errorMessage
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $this->view->render($response, 'auth/login.twig', [
            'error' => $errorMessage
        ]);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        // Validate input
        $errors = $this->validateRegistrationData($data);
        
        if (!empty($errors)) {
            $errorMessage = implode(' ', $errors);
            
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => $errors
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            return $this->view->render($response, 'auth/register.twig', [
                'error' => $errorMessage,
                'old_data' => $data
            ]);
        }

        // Check if email already exists
        $emailExists = $this->db->has("tbl_users", ["email" => $data['email']]);
        if ($emailExists) {
            $errorMessage = 'Email sudah digunakan.';
            
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $errorMessage
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            return $this->view->render($response, 'auth/register.twig', [
                'error' => $errorMessage,
                'old_data' => $data
            ]);
        }

        try {
            // Insert new user
            $userId = $this->db->insert("tbl_users", [
                "full_name" => trim($data['full_name']),
                "email" => strtolower(trim($data['email'])),
                "password" => password_hash($data['password'], PASSWORD_DEFAULT),
                "role" => $data['role'] ?? 'seeker',
                "create_time" => date("Y-m-d H:i:s"),
                "update_time" => date("Y-m-d H:i:s")
            ]);

            if ($userId) {
                if ($isAjax) {
                    $response->getBody()->write(json_encode([
                        'success' => true,
                        'message' => 'Registrasi berhasil! Silakan login dengan akun Anda.'
                    ]));
                    return $response->withHeader('Content-Type', 'application/json');
                }

                return $response->withHeader('Location', '/login?registered=1')->withStatus(302);
            } else {
                throw new \Exception('Gagal menyimpan data pengguna');
            }
            
        } catch (\Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            
            $errorMessage = 'Terjadi kesalahan saat registrasi. Silakan coba lagi.';
            
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $errorMessage
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            return $this->view->render($response, 'auth/register.twig', [
                'error' => $errorMessage,
                'old_data' => $data
            ]);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    public function profile(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        return $this->view->render($response, 'profile/index.twig', [
            'user' => $user
        ]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        
        // Update user data
        $updateData = [
            'full_name' => trim($data['full_name']),
            'phone' => trim($data['phone'] ?? ''),
            'update_time' => date("Y-m-d H:i:s")
        ];

        // Update password if provided
        if (!empty($data['new_password'])) {
            if (empty($data['current_password']) || !password_verify($data['current_password'], $user['password'])) {
                return $this->view->render($response, 'profile/index.twig', [
                    'user' => $user,
                    'error' => 'Password saat ini tidak benar.'
                ]);
            }
            
            if ($data['new_password'] !== $data['confirm_password']) {
                return $this->view->render($response, 'profile/index.twig', [
                    'user' => $user,
                    'error' => 'Konfirmasi password baru tidak sama.'
                ]);
            }
            
            $updateData['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }

        try {
            $this->db->update("tbl_users", $updateData, ["id" => $user['id']]);
            
            // Update session data
            $_SESSION['user'] = array_merge($user, $updateData);
            
            return $this->view->render($response, 'profile/index.twig', [
                'user' => $_SESSION['user'],
                'success' => 'Profil berhasil diperbarui.'
            ]);
            
        } catch (\Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            
            return $this->view->render($response, 'profile/index.twig', [
                'user' => $user,
                'error' => 'Terjadi kesalahan saat memperbarui profil.'
            ]);
        }
    }

    public function seekerProfile(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'seeker') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Get fresh user data from database
        $userData = $this->db->get("tbl_users", "*", [
            "id" => $user['id'],
            "archived" => 0
        ]);

        if (!$userData) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        return $this->view->render($response, 'profile/index.twig', [
            'user' => $userData,
            'pageTitle' => 'Edit Profil'
        ]);
    }

    public function updateSeekerProfile(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || $user['role'] !== 'seeker') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        // Validate input
        $errors = [];
        
        if (empty(trim($data['full_name'] ?? ''))) {
            $errors[] = 'Nama lengkap wajib diisi.';
        }

        if (empty(trim($data['email'] ?? ''))) {
            $errors[] = 'Email wajib diisi.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }

        // Check if email already exists for other users
        if (!empty($data['email'])) {
            $emailExists = $this->db->has("tbl_users", [
                "AND" => [
                    "email" => $data['email'],
                    "id[!]" => $user['id']
                ]
            ]);
        
            if ($emailExists) {
                $errors[] = 'Email sudah digunakan oleh pengguna lain.';
            }
        }

        // Validate phone format if provided
        if (!empty($data['phone']) && !preg_match('/^[+]?[0-9\s\-()]{10,}$/', $data['phone'])) {
            $errors[] = 'Format nomor telepon tidak valid.';
        }

        if (!empty($errors)) {
            $errorMessage = implode(' ', $errors);
        
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $errorMessage
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
        
            return $this->view->render($response, 'profile/index.twig', [
                'user' => $user,
                'error' => $errorMessage,
                'old_data' => $data
            ]);
        }

        try {
            // Prepare update data
            $updateData = [
                'full_name' => trim($data['full_name']),
                'email' => strtolower(trim($data['email'])),
                'phone' => trim($data['phone'] ?? ''),
                'update_time' => date("Y-m-d H:i:s"),
                'update_id' => $user['id']
            ];

            // Handle password update if provided
            if (!empty($data['new_password'])) {
                if (empty($data['current_password'])) {
                    $errorMessage = 'Password saat ini wajib diisi untuk mengubah password.';
                } elseif (!password_verify($data['current_password'], $user['password'])) {
                    $errorMessage = 'Password saat ini tidak benar.';
                } elseif (strlen($data['new_password']) < 6) {
                    $errorMessage = 'Password baru minimal 6 karakter.';
                } elseif ($data['new_password'] !== $data['confirm_password']) {
                    $errorMessage = 'Konfirmasi password baru tidak sama.';
                }
            
                if (isset($errorMessage)) {
                    if ($isAjax) {
                        $response->getBody()->write(json_encode([
                            'success' => false,
                            'message' => $errorMessage
                        ]));
                        return $response->withHeader('Content-Type', 'application/json');
                    }
                
                    return $this->view->render($response, 'profile/index.twig', [
                        'user' => $user,
                        'error' => $errorMessage,
                        'old_data' => $data
                    ]);
                }
            
                $updateData['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
            }

            // Update user data
            $result = $this->db->update("tbl_users", $updateData, ["id" => $user['id']]);
        
            if ($this->db->id() || $result) {
                // Get updated user data
                $updatedUser = $this->db->get("tbl_users", "*", ["id" => $user['id']]);
                $_SESSION['user'] = $updatedUser;
            
                $successMessage = 'Profil berhasil diperbarui.';
            
                if ($isAjax) {
                    $response->getBody()->write(json_encode([
                        'success' => true,
                        'message' => $successMessage
                    ]));
                    return $response->withHeader('Content-Type', 'application/json');
                }
            
                return $this->view->render($response, 'profile/index.twig', [
                    'user' => $updatedUser,
                    'success' => $successMessage
                ]);
            } else {
                throw new \Exception('Tidak ada perubahan data atau gagal menyimpan');
            }
        
        } catch (\Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
        
            $errorMessage = 'Terjadi kesalahan saat memperbarui profil. Silakan coba lagi.';
        
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $errorMessage
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
        
            return $this->view->render($response, 'profile/index.twig', [
                'user' => $user,
                'error' => $errorMessage,
                'old_data' => $data
            ]);
        }
    }

    private function validateRegistrationData(array $data): array
    {
        $errors = [];

        // Required fields
        if (empty(trim($data['full_name'] ?? ''))) {
            $errors[] = 'Nama lengkap wajib diisi.';
        }

        if (empty(trim($data['email'] ?? ''))) {
            $errors[] = 'Email wajib diisi.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }

        if (empty($data['password'] ?? '')) {
            $errors[] = 'Password wajib diisi.';
        } elseif (strlen($data['password']) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        }

        if (empty($data['confirm_password'] ?? '')) {
            $errors[] = 'Konfirmasi password wajib diisi.';
        } elseif ($data['password'] !== $data['confirm_password']) {
            $errors[] = 'Konfirmasi password tidak sama.';
        }

        if (empty($data['role']) || !in_array($data['role'], ['seeker', 'recruiter'])) {
            $errors[] = 'Role tidak valid.';
        }

        if (empty($data['terms'])) {
            $errors[] = 'Anda harus menyetujui syarat dan ketentuan.';
        }

        return $errors;
    }
}
