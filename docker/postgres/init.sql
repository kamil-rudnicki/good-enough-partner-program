-- Create tables
CREATE TABLE partners (
    partner_id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    percentage INTEGER NOT NULL DEFAULT 30,
    link_code VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE visits (
    visit_id SERIAL PRIMARY KEY,
    partner_id INTEGER REFERENCES partners(partner_id),
    visitor_id VARCHAR(255) NOT NULL,
    visited_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE leads (
    lead_id SERIAL PRIMARY KEY,
    visitor_id VARCHAR(255),
    partner_id VARCHAR(255),
    email VARCHAR(255) NOT NULL UNIQUE,
    external_id VARCHAR(255) UNIQUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    transaction_id SERIAL PRIMARY KEY,
    total_amount_in_cents INTEGER NOT NULL,
    is_paid BOOLEAN DEFAULT FALSE,
    commission_in_cents INTEGER NOT NULL,
    partner_id INTEGER REFERENCES partners(partner_id),
    lead_id INTEGER REFERENCES leads(lead_id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX idx_visits_visitor_id ON visits(visitor_id);
CREATE INDEX idx_leads_visitor_id ON leads(visitor_id);
CREATE INDEX idx_leads_email ON leads(email);
CREATE INDEX idx_transactions_partner_id ON transactions(partner_id);
CREATE INDEX idx_transactions_lead_id ON transactions(lead_id); 