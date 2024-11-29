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

        $leads = $this->db->fetchOne(
            'SELECT COUNT(*) FROM leads l
            JOIN visits v ON v.visitor_id = l.visitor_id
            WHERE v.partner_id = ?',
            [$partnerId]
        );

        $transactions = $this->db->fetchAllAssociative(
            'SELECT * FROM transactions WHERE partner_id = ? ORDER BY created_at DESC LIMIT 10',
            [$partnerId]
        );

        $totalCommission = $this->db->fetchOne(
            'SELECT COALESCE(SUM(commission_in_cents), 0) FROM transactions WHERE partner_id = ?',
            [$partnerId]
        );

        return [
            'totalVisits' => $visits,
            'totalLeads' => $leads,
            'totalCommission' => $totalCommission,
            'transactions' => $transactions
        ];
    }
} 