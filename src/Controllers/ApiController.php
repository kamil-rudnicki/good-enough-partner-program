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
        $url = $data['url'] ?? null;
        $referrer = $data['referrer'] ?? null;

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
            'visited_at' => date('Y-m-d H:i:s'),
            'url' => $url,
            'referrer' => $referrer
        ]);

        return $this->jsonResponse($response, ['message' => 'Visit tracked successfully']);
    }

    public function createLead(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $visitorId = $data['visitor_id'] ?? null;
        $partnerId = $data['partner_id'] ?? null;
        $externalId = $data['external_id'] ?? null;
        $secret = $data['secret'] ?? null;

        if (!$email || !$visitorId || !$partnerId || !$secret) {
            return $this->jsonResponse($response, ['error' => 'Missing required parameters'], 400);
        }

        // Validate secret
        if ($secret !== $_ENV['API_SECRET']) {
            return $this->jsonResponse($response, ['error' => 'Invalid secret'], 403);
        }

        // Validate partner
        $partner = $this->partnerModel->findByLinkCode($partnerId);
        if (!$partner) {
            return $this->jsonResponse($response, ['error' => 'Invalid partner ID'], 404);
        }

        try {
            $this->db->insert('leads', [
                'visitor_id' => $visitorId,
                'partner_id' => $partnerId,
                'email' => $email,
                'external_id' => $externalId,
                'created_at' => date('Y-m-d H:i:s')
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