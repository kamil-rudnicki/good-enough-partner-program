<?php

namespace App\Services;
require __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use ChartMogul\Customer;
use ChartMogul\Configuration;
use ChartMogul\CustomerInvoices;

class ChartMogulService {
    private string $apiKey;
    private Connection $db;
    private Configuration $chartMogulConfig;
    private \ChartMogul\Http\Client $chartMogulClient;
    private string $leadsDataSourceUuid;

    public function __construct(Connection $db) {
        $this->apiKey = $_ENV['CHARTMOGUL_API_KEY'];
        $this->db = $db;
        $this->chartMogulConfig = new Configuration(
            $_ENV['CHARTMOGUL_ACCOUNT_TOKEN'],
            $_ENV['CHARTMOGUL_API_KEY']
        );
        $this->chartMogulClient = new \ChartMogul\Http\Client($this->chartMogulConfig);
        $this->leadsDataSourceUuid = $_ENV['CHARTMOGUL_LEADS_DATA_SOURCE_UUID'];
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
            throw new \RuntimeException(sprintf(
            'Failed to fetch transactions from ChartMogul. Status code: %d. Response: %s',
            $statusCode,
            $response
            ));
        }
        
        return json_decode($response, true)['entries'] ?? [];
    }

    public function findCustomerByExternalId(string $externalId): ?Customer
    {
        try {
            $customer = Customer::findByExternalId([
                "data_source_uuid" => $this->leadsDataSourceUuid,
                "external_id" => $externalId
            ], $this->chartMogulClient);

            return $customer;
        } catch (\ChartMogul\Exceptions\ChartMogulException $e) {
            // Handle exceptions (e.g., customer not found, API errors)
            error_log("Error finding customer: " . $e->getMessage());
            return null;
        }
    }

    public function fetchLeadsFromDatabase(): array
    {
        // Fetch leads from the database
        $query = 'select lead_id, external_id, p.link_code, p.partner_id from leads l join public.partners p on l.partner_id = p.link_code order by l.created_at desc;';
        return $this->db->fetchAllAssociative($query);
    }

    public function getCustomerInvoices(string $customerUuid): array
    {
        $invoices = [];
        $page = 1;

        do {
            $ci = CustomerInvoices::all([
                'customer_uuid' => $customerUuid,
                'page' => $page,
                'per_page' => 200
            ], $this->chartMogulClient);

            if (!empty($ci->invoices)) {
                $invoices = array_merge($invoices, $ci->invoices->toArray());
            }

            $page++;
        } while (!empty($ci->invoices->toArray()));

        return $invoices;
    }

    public function writeTransactionsToDatabase(array $transactions, int $partnerId, int $leadId): void
    {
        foreach ($transactions as $transaction) {
            $this->db->executeStatement(
                'INSERT INTO transactions (transaction_id, total_amount_in_cents, is_paid, commission_in_cents, partner_id, lead_id)
                 VALUES (:transaction_id, :total_amount_in_cents, :is_paid, :commission_in_cents, :partner_id, :lead_id)
                 ON CONFLICT (transaction_id) DO UPDATE SET
                    total_amount_in_cents = EXCLUDED.total_amount_in_cents,
                    partner_id = EXCLUDED.partner_id,
                    lead_id = EXCLUDED.lead_id',
                [
                    'transaction_id' => $transaction['hash'],
                    'total_amount_in_cents' => $transaction['amount_in_cents'],
                    'is_paid' => null,
                    'commission_in_cents' => 0,
                    'partner_id' => $partnerId,
                    'lead_id' => $leadId,
                ]
            );
        }
    }

    public function updateTransactionsData(): void
    {
        $leads = $this->fetchLeadsFromDatabase();
        foreach ($leads as $lead) {
            $LeadID = $lead['lead_id'];
            $externalId = $lead['external_id'];
            $PartnerID = $lead['partner_id'];

            echo "Processing Lead ID: $LeadID, External ID: $externalId, Partner ID: $PartnerID" . PHP_EOL;

            $customer = $this->findCustomerByExternalId($externalId);
            if (!$customer) {
                echo "Customer not found for External ID: $externalId" . PHP_EOL;
                continue;
            }

            $CustomerID = $customer->id;
            $CustomerUUID = $customer->uuid;
            echo "Customer ID: $CustomerID, UUID: $CustomerUUID" . PHP_EOL;

            // Fetch invoices and transactions
            $invoices = $this->getCustomerInvoices($CustomerUUID);

            // Extract transactions from invoices
            $transactions = [];
            foreach ($invoices as $invoice) {
                foreach ($invoice->transactions as $transaction) {
                    $transactions[] = [
                        'transaction_id' => $transaction->uuid,
                        'type' => $transaction->type,
                        'date' => $transaction->date,
                        'result' => $transaction->result,
                        'external_id' => $transaction->external_id,
                        'amount_in_cents' => $transaction->amount_in_cents,
                        'hash' => crc32($transaction->external_id),
                    ];
                }
            }

            // Write transactions to the database
            $this->writeTransactionsToDatabase($transactions, $PartnerID, $LeadID);
        }
    }
} 

// Main function to execute the service
function main()
{
    // Database connection
    $connectionParams = [
        'dbname' => $_ENV['DB_NAME'],
        'user' => $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASSWORD'],
        'host' => $_ENV['DB_HOST'],
        'driver' => 'pdo_pgsql',
    ];
    $dbConnection = DriverManager::getConnection($connectionParams);

    // Initialize ChartMogul service
    $chartMogulService = new ChartMogulService($dbConnection);

    // get external ids of leads
    // select lead_id, external_id from leads order by created_at desc;
    $leads = $dbConnection->fetchAllAssociative('select lead_id, external_id, p.link_code, p.partner_id from leads l join public.partners p on l.partner_id = p.link_code order by l.created_at desc;');
    foreach ($leads as $lead) {
        echo "Lead ID: {$lead['lead_id']}, External ID: {$lead['external_id']}, Partner ID: {$lead['partner_id']}, Link Code: {$lead['link_code']}" . PHP_EOL;
    }

    // // Example external ID to find the customer
    // $LeadID = 20;
    // $externalId = "900311";
    // $PartnerID = 324;

    for ($i = 0; $i < count($leads); $i++) { // for each lead
        $LeadID = $leads[$i]['lead_id'];
        $externalId = $leads[$i]['external_id'];
        $PartnerID = $leads[$i]['partner_id'];
        echo "Processing Lead ID: $LeadID, External ID: $externalId, Partner ID: $PartnerID" . PHP_EOL;

        $customer = $chartMogulService->findCustomerByExternalId($externalId);

        if (!$customer) {
            echo "Customer not found for External ID: $externalId" . PHP_EOL;
            continue;
        }

        $CustomerID = $customer->id;
        $CustomerUUID = $customer->uuid;
        echo "Customer ID: $CustomerID, UUID: $CustomerUUID" . PHP_EOL; 
        # Customer ID: 200766960, UUID: cus_3071a1c2-f3a6-11ef-8e1b-fbecde95bf8e
        $chartMogulConfig = new Configuration(
                $_ENV['CHARTMOGUL_ACCOUNT_TOKEN'],
                $_ENV['CHARTMOGUL_API_KEY']
            );
        $client = new \ChartMogul\Http\Client($chartMogulConfig);

        // Get Customer invoices with paging
        $ci = null;
        $page = 1;
        $invoices = [];

        do {
            echo "Fetching invoices for page $page..." . PHP_EOL;
            $ci = CustomerInvoices::all([
                'customer_uuid' => $CustomerUUID,
                'page' => $page,
                'per_page' => 200
            ], $client);

            if (!empty($ci->invoices)) {
                $invoices = array_merge($invoices, $ci->invoices->toArray());
            }

            $page++;
        } while (!empty($ci->invoices->toArray()));

        // Extract transactions from invoices
        $transactions = [];
        foreach ($invoices as $invoice) {
            foreach ($invoice->transactions as $transaction) {
                $transactions[] = [
                    'transaction_id' => $transaction->uuid,
                    'type' => $transaction->type,
                    'date' => $transaction->date,
                    'result' => $transaction->result,
                    'external_id' => $transaction->external_id,
                    'amount_in_cents' => $transaction->amount_in_cents,
                    'hash' => crc32($transaction->external_id),
                ];
            }
        }

        // Print the transactions
        echo "Transactions:\n";
        foreach ($transactions as $transaction) {
            echo sprintf(
                "ID: %s, Type: %s, Date: %s, Result: %s, External ID: %s, Amount: %d cents, Hash: %d\n",
                $transaction['transaction_id'],
                $transaction['type'],
                $transaction['date'],
                $transaction['result'],
                $transaction['external_id'],
                $transaction['amount_in_cents'],
                $transaction['hash']
            );
        }

        foreach ($transactions as $transaction) {
            $dbConnection->insert('transactions', [
                'transaction_id' => $transaction['hash'],
                'total_amount_in_cents' => $transaction['amount_in_cents'],
                'is_paid' => null,
                'commission_in_cents' => 0,
                'partner_id' => $PartnerID,
                'lead_id' => $LeadID,
            ]);
        }
    }
}

// Call the main function
// main();
