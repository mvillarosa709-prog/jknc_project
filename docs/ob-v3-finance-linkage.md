# O.B. V3 finance linkage

`GET /activities/official-business-trip-tickets/finance-ready` is the finance-only source of completed client Trip Tickets. It reads the canonical ticket, master O.B., personnel, stop, financial treatment, travel-credit ledger entry, cost, and evidence records. It supports `company_id` and `per_page` filters and returns a Trip Ticket PDF URL as the supporting record. Server-side authorization requires O.B. finance access.

The master `deal_id` and `project_id` are included for downstream matching. Travel-credit deductions already post to the company travel-credit ledger per ticket. Billing treatment distinguishes travel credit, billable, retainer, and non-billable work; additional costs retain their own classifications.

The current Finance module has expense records and Deal Proposal invoice files, but no shared invoice-line, statement-of-account, or retainer-utilization entity to receive a Trip Ticket. Expense reimbursement records are not linked to Trip Tickets. Posting into those workflows remains an external integration dependency; this feed does not claim that an invoice, statement, retainer entry, or reimbursement has been created.
