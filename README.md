# Good Enough Partner Program System

A complete partner program management system, featuring partner registration, visit tracking, lead management, and commission calculations.

## Features

- Partner registration and authentication using email codes
- Partner dashboard with statistics and earnings
- JavaScript tracking for partner referrals
- Lead tracking and management
- Integration with ChartMogul for transaction sync
- Automatic commission calculations
- Payout request system

## Database Schema

PostgreSQL database with the following tables:

- Partners
  - partner_id (PK)
  - name
  - percentage (default 30%)
  - link_code
  - email
- Visits
  - partner_id (FK)
  - visitor_id
  - visited_at
- Leads
  - lead_id (PK)
  - visitor_id
  - email
  - external_id
- Transactions
  - transaction_id (PK)
  - total_amount_in_cents
  - is_paid
  - commission_in_cents
  - partner_id (FK)
  - lead_id (FK)

## Setup

1. Clone the repository
2. Copy `.env.example` to `.env` and configure your environment variables:
   ```bash
   cp .env.example .env
   ```

3. Build and start the Docker containers:
   ```bash
   docker compose up -d
   ```

4. Install PHP dependencies:
   ```bash
   docker compose exec php composer install
   ```

5. The application will be available at:
   - Partner Panel: http://localhost:8080
   - Tracking Script: http://localhost:8080/integration.j4fn2k.js

## Usage

### Partner Registration/Login
1. Visit http://localhost:8080
2. Enter your email address
3. Check your email for the authentication code
4. Enter the code to access your dashboard

### Tracking Integration
Add the following script to your website:
```html
<script src="http://localhost:8080/integration.j4fn2k.js"></script>
```

### Creating Leads
Send a POST request to create a lead:
```bash
curl -X POST http://localhost:8080/api/leads \
  -H "Content-Type: application/json" \
  -d '{"email": "customer@example.com", "visitor_id": "v_123", "external_id": "cust_123"}'
```

### Transaction Sync
Transactions are automatically synced from ChartMogul daily at 1 AM. You can also run the sync manually:
```bash
docker-compose exec php php scripts/sync_transactions.php
```

## Development

The project uses:
- PHP 8.1 with Slim Framework
- PostgreSQL database
- Nginx web server
- Docker for containerization
- Bootstrap for the frontend
- JWT for authentication

## Security Notes

1. Always use HTTPS in production
2. Configure proper CORS settings for the API
3. Set strong JWT secrets
4. Use proper email service credentials
5. Secure the ChartMogul API key
