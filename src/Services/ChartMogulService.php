<?php

namespace App\Services;

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

    public function syncTransactions(): void
    {
        $leads = $this->fetchLeadsFromDatabase();
        echo "Found " . count($leads) . " leads to process." . PHP_EOL;
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
