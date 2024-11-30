<?php

namespace App\Controllers;

use App\Models\Partner;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiController {
    private Connection $db;
    private Partner $partnerModel;

    public function __construct(Connection $db, Partner $partnerModel) {
        $this->db = $db;
        $this->partnerModel = $partnerModel;
    }

    public function trackVisit(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $partnerId = $data['partner_id'] ?? null;
        $visitorId = $data['visitor_id'] ?? null;

        if (!$partnerId || !$visitorId) {
            return $this->jsonResponse($response, ['error' => 'Missing required parameters'], 400);
        }

        $partner = $this->partnerModel->findByLinkCode($partnerId);
        if (!$partner) {
            return $this->jsonResponse($response, ['error' => 'Invalid partner code'], 404);
        }

        $this->db->insert('visits', [
            'partner_id' => $partner['partner_id'],
            'visitor_id' => $visitorId,
            'visited_at' => date('Y-m-d H:i:s')
        ]);

        return $this->jsonResponse($response, ['message' => 'Visit tracked successfully']);
    }

    public function createLead(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $visitorId = $data['visitor_id'] ?? null;
        $externalId = $data['external_id'] ?? null;

        if (!$email || !$visitorId) {
            return $this->jsonResponse($response, ['error' => 'Missing required parameters'], 400);
        }

        try {
            $this->db->insert('leads', [
                'visitor_id' => $visitorId,
                'email' => $email,
                'external_id' => $externalId
            ]);

            return $this->jsonResponse($response, ['message' => 'Lead created successfully']);
        } catch (\Exception $e) {
            // Handle duplicate email error
            if (strpos($e->getMessage(), 'unique constraint') !== false) {
                return $this->jsonResponse($response, ['error' => 'Email already exists'], 409);
            }
            throw $e;
        }
    }

    public function getDashboard(Request $request, Response $response): Response {
        $partnerId = $request->getAttribute('partner_id');
        $data = $this->partnerModel->getDashboardData($partnerId);
        
        $linkCode = $this->db->fetchOne('SELECT link_code FROM partners WHERE partner_id = ?', [$partnerId]);
        $data['partnerLink'] = [
            'code' => $linkCode,
            'fullUrl' => $_ENV['PARTNER_LINK_URL'] . '?partner=' . $linkCode,
            'testUrl' => 'http://localhost:8000?partner=' . $linkCode
        ];

        return $this->jsonResponse($response, $data);
    }

    public function requestPayout(Request $request, Response $response): Response {
        $partnerId = $request->getAttribute('partner_id');
        $partner = $this->db->fetchAssociative('SELECT * FROM partners WHERE partner_id = ?', [$partnerId]);
        
        // In a real application, you would:
        // 1. Calculate unpaid commissions
        // 2. Create a payout request record
        // 3. Send email to admin
        // For now, we'll just return success
        
        return $this->jsonResponse($response, ['message' => 'Payout request submitted successfully']);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
} 