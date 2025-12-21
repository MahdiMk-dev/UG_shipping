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
- Warehouse constraints:
  - Shipments must match warehouse country.
  - Warehouse can only edit/create orders when shipment status is `active`.

## Core Entities + Statuses
- Shipments: `active`, `departed`, `airport`, `arrived`, `distributed`.
- Orders fulfillment: `in_shipment`, `main_branch`, `pending_receipt`, `received_subbranch`, `closed`, `returned`, `canceled`.
- Orders notification: `pending`, `notified`.
- Shopping orders: `pending`, `distributed`, `received_subbranch`, `closed`, `canceled`.
- Soft deletes: most tables use `deleted_at`; `roles`, `countries`, `payment_methods` are hard delete.

## Workflows
Shipment lifecycle:
- When shipment status changes to `arrived`, orders in `in_shipment` move to `main_branch`.
- When shipment status changes to `active` or `airport`, orders in `main_branch`, `pending_receipt`, `received_subbranch`
  move back to `in_shipment`.

Distribution:
- Distribution requires shipment status `arrived`.
- Only orders with `fulfillment_status = main_branch` and `sub_branch_id > 0` move to `pending_receipt`.
- Orders already in `pending_receipt`/`received_subbranch` are not touched.
- Orders missing branch (`sub_branch_id` NULL/0) stay in `main_branch`.
- Shipment status is set to `distributed` only when there are zero `main_branch` orders missing a branch.
- Repeated distribute only affects still-eligible orders; previously distributed orders do not re-appear in pending receipts.

Receiving:
- Pending receipts are orders in `pending_receipt`.
- Scans match `shipment_id + tracking_number` (and branch if provided).
- Matched scans move orders to `received_subbranch`.
- All scans are logged in `branch_receiving_scans` with `matched`/`unmatched`.

Orders:
- Order creation requires the customer to have a `sub_branch_id`.
- `update_shipment_totals()` recalculates shipment weight/volume from orders.

Invoices + Transactions:
- Invoices track totals and status; allocations link transactions to invoices.

Attachments:
- Stored under `public/uploads/YYYY/MM`, MIME whitelist + max size enforced by config.

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
