# AGENTS.md

Source of truth for how this repo works. Update this file whenever workflows, permissions, or business rules change.

## Project Overview
UG Shipping is an internal web app + API for managing shipments, orders, receiving, invoicing, payments, and attachments.

## Stack
- PHP 8+, MySQL/MariaDB
- HTML/CSS/Vanilla JS (Fetch)

## Repo Layout
- `api/` JSON endpoints (one file per action).
- `app/` shared helpers (auth, permissions, services, audit, db).
- `config/` runtime config + schema.
- `public/` static assets + uploads.
- `views/` internal app pages and portal placeholder.
- `index.php` login entry.

## Runtime + Config
- `config/config.php` builds `BASE_URL`/`PUBLIC_URL`, reads DB env (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
- `app/db.php` provides a shared PDO connection.
- Upload constraints live in `config/config.php` (`uploads.max_bytes`, `uploads.allowed_mime`).

## Auth + Permissions
- Session auth in `app/auth.php`.
- `require_role()` in `app/permissions.php` gates restricted endpoints.
- Read-only roles: `Sub Branch`, `Warehouse`, `Staff` (note: `Staff` is not seeded in schema).
- Read-only scoping:
  - Sub-branch users are scoped to `branch_id` in list endpoints.
  - Warehouse users are scoped to `origin_country_id` in shipment/order workflows.
- Portal login uses `customer_accounts` (shared username/password/phone) linked to multiple customer profiles.
- Roles management screen/API is Owner-only.
- Warehouse constraints:
  - Shipments must match warehouse country.
  - Warehouse can only edit/create orders when shipment status is `active`.
  - Warehouse can only view customer profiles that match their country.
  - Warehouse can only view partner profiles that match their country.
- Main Branch access:
  - Can manage shipments, orders, customers, and receiving.
  - Cannot access branches, users, roles, or staff screens/APIs.
- Reports access:
  - Admin/Owner can generate all reports.
  - Sub Branch can only generate reports scoped to their own branch.

## Core Entities + Statuses
- Shipments: `active`, `departed`, `airport`, `arrived`, `partially_distributed`, `distributed`.
- Orders fulfillment: `in_shipment`, `main_branch`, `pending_receipt`, `received_subbranch`, `closed`, `returned`, `canceled`.
- Orders notification: `pending`, `notified`.
- Shopping orders: `pending`, `distributed`, `received_subbranch`, `closed`, `canceled`.
- Staff: `active`, `inactive`.
- Staff expenses: `salary_adjustment`, `advance`, `bonus`.
- Partner profiles: `shipper`, `consignee`.
- Partner transactions: `receipt`, `refund`, `adjustment`.
- Soft deletes: most tables use `deleted_at`; `roles`, `countries`, `payment_methods` are hard delete.

## Workflows
Shipment lifecycle:
- When shipment status changes to `arrived`, orders stay in `in_shipment` until main branch receiving scans them.
- When shipment status changes to `active` or `airport`, orders in `main_branch`, `pending_receipt`, `received_subbranch`
  move back to `in_shipment`.

Distribution:
- Distribution requires shipment status `arrived` or `partially_distributed`.
- Only orders with `fulfillment_status = main_branch` and `sub_branch_id > 0` move to `pending_receipt`.
- Orders already in `pending_receipt`/`received_subbranch` are not touched.
- Orders missing branch (`sub_branch_id` NULL/0) stay in `main_branch`.
- Shipment status is set to `distributed` only when there are zero orders remaining in `in_shipment` or `main_branch`;
  otherwise it becomes `partially_distributed`.
- Repeated distribute only affects still-eligible orders; previously distributed orders do not re-appear in pending receipts.

Receiving:
- Pending receipts are orders in `pending_receipt`.
- Scans match `shipment_id + tracking_number` (and branch if provided).
- Matched scans move orders to `received_subbranch`.
- All scans are logged in `branch_receiving_scans` with `matched`/`unmatched`.
- Main branch receiving uses orders in `in_shipment` for shipments with status `arrived` or `partially_distributed`.
- Main branch scans move orders to `main_branch`.

Orders:
- Order creation allows customers without a `sub_branch_id`.
- Order creation and reassignment require the customer profile country to match the shipment `origin_country_id`.
- `update_shipment_totals()` recalculates shipment weight/volume from orders.

Customer profiles:
- Profiles are grouped under `customer_accounts` by shared phone/username.
- Each account can only have one profile per `profile_country_id`.

Invoices + Transactions:
- Invoices track totals and status; allocations link transactions to invoices.
- Orders can be invoiced only when `fulfillment_status = received_subbranch` and all orders belong to the same sub branch.
- Invoiced orders are locked against price/identity changes and delete/reassign actions.
- Sub Branch users can create invoices and payments for customers in their branch.

Balances + Transfers:
- Customer balance decreases when orders are created, and increases when payments are recorded.
- Price updates adjust customer balance by the delta; shipment default-rate sync skips invoiced orders.
- Customer balance activity is logged in `customer_balance_entries` (order charges/reversals and payments).
- Sub-branch balance entries are recorded when orders reach `received_subbranch`, reversed when moved out or deleted.
- Internal branch transfers are tracked in `branch_transfers` and mirrored in `branch_balance_entries`.

Attachments:
- Stored under `public/uploads/YYYY/MM`, MIME whitelist + max size enforced by config.

Staff + Expenses:
- Staff records live in `staff_members`.
- Salary adjustments, advances, and bonuses are logged in `staff_expenses` and treated as expenses for reporting.
- General operational expenses are stored in `general_expenses` with optional `branch_id`.
- Shipment-linked expenses are stored in `general_expenses` with `shipment_id` and are Admin/Owner only.
- Company expense reports include monthly salary payouts; advances in the prior month reduce the next month's salary.

Company settings:
- Company details for printable documents are stored in `company_settings` and managed by Admin/Owner.

Shipper/Consignee profiles:
- Shipments can optionally link to shipper and consignee profiles.
- Partner invoices decrease profile balance; receipts increase balance.
- Partner invoices can optionally link to shipments.

Audit:
- `audit_logs` captures before/after JSON + meta for key actions, with IP and user agent.

## UI
- Login: `index.php`.
- Internal app: `views/internal/*.php` (driven by `public/assets/js/app.js`).
- Portal placeholder: `views/portal/home.php`.

## API Conventions
- JSON responses via `api_json()` with `ok` flag.
- Input accepted as JSON body or form POST (`api_read_input()`).
- Endpoint filenames mirror actions (list/create/update/delete).

## Change Log (keep current)
- 2025-12-21: Shipment distribute only queues orders with a branch; shipment status stays `main_branch` until all orders
  have a branch; repeated distribute does not requeue already distributed orders.
- 2025-12-21: Wired internal attachments page for upload, list, download, and delete with pagination.
- 2025-12-21: Added attachment list search by shipment number or tracking number (with optional type filter).
- 2025-12-26: Added customer accounts with multi-country profiles and portal aggregation.
- 2025-12-26: Added staff management with salary adjustments, advances, bonuses, and expense logging.
- 2025-12-26: Main branch receives arrived shipments via scans; arrived orders stay `in_shipment` until scanned to `main_branch`.
- 2025-12-27: Added shipper/consignee partner profiles with invoices and receipts, plus shipment profile linking.
- 2025-12-27: Added general expense tracking for operational costs.
- 2025-12-27: Partner invoices can optionally link to shipments with searchable selection.
- 2025-12-27: Added company settings for printable invoices/receipts and partner print views.
- 2025-12-27: Added company logo upload/delete with fallback to default icon.
- 2025-12-28: Shipment expenses can be tracked per shipment (Admin/Owner only), and branch is optional for expenses.
- 2025-12-28: Added printable reports for shipment expenses, company expenses, net totals, and transaction in/out.
- 2025-12-29: Added branch order/balance reports, branch transfers, uninvoiced orders list, and balance sync rules.
- 2025-12-30: Company expenses and net reports include monthly salaries with prior-month advance deductions.
- 2025-12-31: Added `partially_distributed` shipment status, distribution stays partial while main-branch/in-shipment orders remain,
  order creation allows missing sub-branch, and packing lists hide qty/rate/price.
