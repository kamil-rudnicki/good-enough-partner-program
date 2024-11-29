# easy-partner-program

Data model
- Partners
    - partner_id
    - name
    - percentage
    - link_code
    - email
- Visits
    - partner_id
    - visitor_id
    - visited_at
- Leads
    - lead_id
    - visitor_id
    - email
    - external_id
- Transactions
    - transaction_id
    - total_amount_in_cents
    - is_paid
    - commision_in_cents
    - partner_id
    - lead_id

- Panel for partners written in PHP and bootstrap. Login using email and code send to email everytime. Show their unique partner link. Table with all visits. Table with all leads. Table with all transactions. Button "Request payout" that will send compose an email to us. Ability to create a new partner account if not available.
- Javascript file for tracking visitors.
- Rest API to create a new lead with parameters like email, external_id, visitor_id.
- Script that will everyday get new transactions from Chartmogul API and populate transactions table.
