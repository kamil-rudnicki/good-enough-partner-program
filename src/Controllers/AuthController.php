<?php

namespace App\Controllers;

use App\Models\Partner;
use App\Services\EmailService;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    private Partner $partnerModel;
    private EmailService $emailService;
    private string $jwtSecret;

    public function __construct(Partner $partnerModel, EmailService $emailService) {
        $this->partnerModel = $partnerModel;
        $this->emailService = $emailService;
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    }

    public function requestCode(Request $request, Response $response): Response {
        $input = (string) $request->getBody();
        error_log('Raw input: ' . $input);
        
        $data = json_decode($input, true);
        error_log('Parsed data: ' . print_r($data, true));

        if (!isset($data['email'])) {
            return $this->jsonResponse($response, [
                'error' => 'Email is required',
                'debug' => [
                    'received_data' => $data
                ]
            ], 400);
        }

        $email = $data['email'];
        $isRegistering = $data['isRegistering'] ?? false;

        $partner = $this->partnerModel->findByEmail($email);

        if ($isRegistering && $partner) {
            return $this->jsonResponse($response, ['error' => 'Email already registered'], 400);
        }

        if (!$isRegistering && !$partner) {
            return $this->jsonResponse($response, ['error' => 'Partner not found'], 404);
        }

        $code = $this->generateAuthCode();
        $_SESSION['auth_code'] = [
            'code' => $code,
            'email' => $email,
            'expires' => time() + 300
        ];

        try {
            $this->emailService->sendAuthCode($email, $code);
            return $this->jsonResponse($response, ['message' => 'Authentication code sent']);
        } catch (\Exception $e) {
            error_log('Failed to send email: ' . $e->getMessage());
            return $this->jsonResponse($response, ['error' => 'Failed to send authentication code'], 500);
        }
    }

    public function verifyCode(Request $request, Response $response): Response {
        $input = (string) $request->getBody();
        $data = json_decode($input, true);

        if (!isset($data['email']) || !isset($data['code'])) {
            return $this->jsonResponse($response, ['error' => 'Email and code are required'], 400);
        }

        $email = $data['email'];
        $code = $data['code'];

        if (!isset($_SESSION['auth_code']) ||
            $_SESSION['auth_code']['email'] !== $email ||
            $_SESSION['auth_code']['code'] !== $code ||
            $_SESSION['auth_code']['expires'] < time()) {
            return $this->jsonResponse($response, ['error' => 'Invalid or expired code'], 400);
        }

        $partner = $this->partnerModel->findByEmail($email);
        if (!$partner) {
            $partner = $this->partnerModel->create('New Partner', $email);
        }

        $token = JWT::encode([
            'partner_id' => $partner['partner_id'],
            'email' => $partner['email'],
            'exp' => time() + 86400
        ], $this->jwtSecret, 'HS256');

        unset($_SESSION['auth_code']);

        return $this->jsonResponse($response, ['token' => $token]);
    }

    private function generateAuthCode(): string {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
} 