<?php

namespace App\Services;

use Doctrine\DBAL\Connection;

class ChartMogulService {
    private string $apiKey;
    private Connection $db;

    public function __construct(Connection $db) {
        $this->apiKey = $_ENV['CHARTMOGUL_API_KEY'];
        $this->db = $db;
    }

    public function syncTransactions(): void {
        // Get yesterday's date
        $date = date('Y-m-d', strtotime('-1 day'));
        
        // Fetch transactions from ChartMogul API
        $transactions = $this->fetchTransactionsFromChartMogul($date);
        
        foreach ($transactions as $transaction) {
            // Find lead by email
            $lead = $this->db->fetchAssociative(
                'SELECT l.*, v.partner_id FROM leads l
                JOIN visits v ON v.visitor_id = l.visitor_id
                WHERE l.email = ?',
                [$transaction['customer_email']]
            );
            
            if (!$lead) {
                continue;
            }
            
            // Get partner's commission percentage
            $partner = $this->db->fetchAssociative(
                'SELECT percentage FROM partners WHERE partner_id = ?',
                [$lead['partner_id']]
            );
            
            if (!$partner) {
                continue;
            }
            
            // Calculate commission
            $commissionInCents = (int)($transaction['amount_in_cents'] * ($partner['percentage'] / 100));
            
            // Create transaction record
            $this->db->insert('transactions', [
                'transaction_id' => $transaction['id'],
                'total_amount_in_cents' => $transaction['amount_in_cents'],
                'is_paid' => false,
                'commission_in_cents' => $commissionInCents,
                'partner_id' => $lead['partner_id'],
                'lead_id' => $lead['lead_id']
            ]);
        }
    }

    private function fetchTransactionsFromChartMogul(string $date): array {
        $url = "https://api.chartmogul.com/v1/transactions?start_date={$date}&end_date={$date}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode !== 200) {
            throw new \RuntimeException('Failed to fetch transactions from ChartMogul');
        }
        
        return json_decode($response, true)['entries'] ?? [];
    }
} 