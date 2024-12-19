<?php

namespace App\Models;

use Doctrine\DBAL\Connection;

class Partner {
    private Connection $db;

    public function __construct(Connection $db) {
        $this->db = $db;
    }

    public function create(string $name, string $email): array {
        $linkCode = $this->generateLinkCode();
        
        $this->db->insert('partners', [
            'name' => $name,
            'email' => $email,
            'link_code' => $linkCode,
            'percentage' => 30
        ]);

        return $this->findByEmail($email) ?? [];
    }

    public function findByEmail(string $email): ?array {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM partners WHERE email = ?',
            [$email]
        );

        return $result ?: null;
    }

    public function findByLinkCode(string $linkCode): ?array {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM partners WHERE link_code = ?',
            [$linkCode]
        );

        return $result ?: null;
    }

    private function generateLinkCode(): string {
        return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    }

    public function getDashboardData(int $partnerId): array {
        $visits = $this->db->fetchOne(
            'SELECT COUNT(*) FROM visits WHERE partner_id = ?',
            [$partnerId]
        );

        $partnerData = $this->db->fetchAssociative(
            'SELECT p.link_code, p.email, COUNT(l.lead_id) as lead_count 
            FROM partners p 
            LEFT JOIN leads l ON l.partner_id = p.link_code 
            WHERE p.partner_id = ? 
            GROUP BY p.partner_id, p.link_code, p.email',
            [$partnerId]
        );

        $transactions = $this->db->fetchAllAssociative(
            'SELECT * FROM transactions 
            WHERE partner_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10',
            [$partnerId]
        );

        $totalCommission = $this->db->fetchOne(
            'SELECT COALESCE(SUM(commission_in_cents), 0) 
            FROM transactions 
            WHERE partner_id = ?',
            [$partnerId]
        );

        return [
            'email' => $partnerData['email'],
            'totalVisits' => (int)$visits,
            'totalLeads' => (int)$partnerData['lead_count'],
            'totalCommission' => (int)$totalCommission,
            'transactions' => $transactions,
            'partnerLink' => [
                'code' => $partnerData['link_code'],
                'fullUrl' => $_ENV['PARTNER_LINK_URL'] . '?partner=' . $partnerData['link_code'],
                'testUrl' => 'http://localhost:8000?partner=' . $partnerData['link_code']
            ]
        ];
    }
} 