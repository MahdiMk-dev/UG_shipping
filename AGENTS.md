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
- Users can change their own password with the current password; Admin/Owner can reset other users without the current password.
- Read-only scoping:
  - Sub-branch users are scoped to `branch_id` in list endpoints.
  - Warehouse users are scoped to `origin_country_id` in shipment/order workflows.
- Portal login uses `customer_accounts` (shared username/password/phone) linked to multiple customer profiles.
- Roles create/update/delete are Owner-only; role list is shared for selection in internal forms.
- Warehouse constraints:
  - Shipments must match warehouse country.
  - Warehouse can only edit/create orders when shipment status is `active`.
  - Warehouse can view customer accounts filtered to their country (summary profiles); customer selection is search-only in order creation.
- Warehouse has no access to Supplier profiles.
  - Warehouse cannot view or edit shipment pricing (rates, costs) or shipment income totals.
- Customer edits are Admin-only (profile code edits + customer info edits).
- Main Branch access:
  - Can manage shipments, orders, customers, and receiving.
  - Cannot access branches, users, roles, or staff screens/APIs.
- Reports access:
  - Admin/Owner can generate all reports.
  - Sub Branch can only generate reports scoped to their own branch.

## Core Entities + Statuses
- Shipments: `active`, `departed`, `airport`, `arrived`, `partially_distributed`, `distributed`.
- Orders fulfillment: `in_shipment`, `main_branch`, `pending_receipt`, `received_subbranch`, `with_delivery`, `picked_up`,
  `closed`, `returned`, `canceled`.
- Orders notification: `pending`, `notified`.
- Shopping orders: `pending`, `distributed`, `received_subbranch`, `closed`, `canceled`.
- Staff: `active`, `inactive`.
- Staff expenses: `salary_adjustment`, `advance`, `bonus`.
- Supplier profiles: `shipper`, `consignee`.
- Supplier transactions: `receipt`, `refund`, `adjustment` (status: `active`, `canceled`).
- Customer transactions status: `active`, `canceled`.
- Soft deletes: most tables use `deleted_at`; `roles`, `countries`, `payment_methods` are hard delete.

## Workflows
Shipment lifecycle:
- When shipment status changes to `arrived`, orders stay in `in_shipment` until main branch receiving scans them.
- When shipment status changes to `active` or `airport`, orders in `main_branch`, `pending_receipt`, `received_subbranch`,
  `with_delivery`, `picked_up`
  move back to `in_shipment`.
- Shipments require `type_of_goods` on create/update.
- `type_of_goods` values come from `goods_types` and are managed in Company settings (Admin/Owner).

Distribution:
- Distribution requires shipment status `arrived` or `partially_distributed`.
- Only orders with `fulfillment_status = main_branch` and `sub_branch_id > 0` move to `pending_receipt`.
- Orders already in `pending_receipt`/`received_subbranch`/`with_delivery`/`picked_up` are not touched.
- Orders missing branch (`sub_branch_id` NULL/0) stay in `main_branch`.
- Shipment status is set to `distributed` only when there are zero orders remaining in `in_shipment` or `main_branch`;
  otherwise it becomes `partially_distributed`.
- Repeated distribute only affects still-eligible orders; previously distributed orders do not re-appear in pending receipts.

Receiving:
- Pending receipts are orders in `pending_receipt`.
- Scans match `shipment_id + tracking_number` (and branch if provided).
- Matched scans move orders to `received_subbranch`.
- All scans are logged in `branch_receiving_scans` with `matched`/`unmatched`.
- Unmatched scans are de-duplicated per branch/shipment/tracking; Admin/Owner/Main Branch can return them to `in_shipment`.
- Returning to main branch reverses customer/branch balances for `received_subbranch`/`with_delivery`/`picked_up` orders
  and resolves the scan.
- Main branch receiving uses orders in `in_shipment` for shipments with status `arrived` or `partially_distributed`.
- Main branch scans move orders to `main_branch`.

Orders:
- Order creation allows customers without a `sub_branch_id`.
- Order creation and reassignment require the customer profile country to match the shipment `origin_country_id`.
- Order creation defaults `rate` from shipment `default_rate` when omitted.
- Order unit type is derived from weight type (`actual` = `kg`, `volumetric` = `cbm`).
- `update_shipment_totals()` recalculates shipment weight/volume from orders.
- Invoiced orders are locked against rate/weight edits; shipment default-rate changes are blocked once any order is invoiced.

Customer profiles:
- Profiles are grouped under `customer_accounts` by shared phone/username.
- Each account can only have one profile per `profile_country_id`.
- Profiles in the same account share one balance and one sub-branch assignment; profile codes stay per profile.
- Profile edits are Admin-only and limited to code; customer info edits (name/phone/address/note/sub-branch) are Admin-only.
- Adding a profile for an existing account requires only code + country; name/phone/portal come from the account.

Invoices + Transactions:
- Invoices track totals and status; allocations link transactions to invoices.
- Orders can be invoiced only when `fulfillment_status = received_subbranch` and all orders belong to the same sub branch.
- Invoice creation requires a delivery method and updates orders to `with_delivery` or `picked_up`.
- Invoiced orders are locked against price/identity changes and delete/reassign actions.
- Customer invoices can be edited (currency + order selection) only when no payments are linked; edits can move orders
  back to `received_subbranch`.
- Sub Branch users can create invoices for customers in their branch; customer payments and refunds are recorded by Main/Sub Branch only.
- Admin/Owner can refund branches or Suppliers (not customers).
- Invoice/receipt cancellations require a reason, keep records via status, and invoices cannot be canceled while active receipts exist.
- Refund receipts for customers and Suppliers require a reason; notes remain optional.
- Transactions require from/to accounts; payment method is derived from the account type, and each transaction creates an
  `account_transfer` entry.

Accounts + Ledger:
- `accounts` are the source of truth for cash/bank/whish balances (owner_type: `admin`, `branch`).
- Each account is tied to a `payment_method_id`; transfers must use matching payment methods.
- All money movements create `account_transfers` + `account_entries` and update account balances
  (negative = outgoing, positive = incoming).
- Accounts can be deleted only when the balance is zero; otherwise deactivate them.
- Admin accounts capture company-level inflows/outflows (branch payments in, staff/Supplier/expenses out).

Balances + Transfers:
- Customer balance increases when orders reach `received_subbranch`/`with_delivery`/`picked_up`,
  and decreases when payments are recorded.
- Customer and Supplier balances treat positive values as unpaid amounts; payments reduce balance (negative entries).
- Price updates adjust customer balance by the delta for `received_subbranch`/`with_delivery`/`picked_up` orders;
  shipment default-rate sync skips invoiced orders and is blocked once any order is invoiced.
- Customer balance activity is logged in `customer_balance_entries` (order charges/reversals and payments).
- Branch balances are derived from `branch_balance_entries` (orders received in sub branches and admin-recorded payments).
- Branch payments to admin are recorded as account transfers from a branch account to an admin account.
- Sub Branch customer payments post to branch accounts; admin records branch-to-admin transfers.
- Refunds/adjustments flow from admin accounts back to branch accounts.
- Sub Branch users can review their branch balance and customers with non-zero balances in Transactions.

Attachments:
- Stored under `public/uploads/YYYY/MM`, MIME whitelist + max size enforced by config.

Staff + Expenses:
- Staff records live in `staff_members`.
- Staff members can optionally link to a `users` login; Admin/Owner can create/update logins from the staff screen.
- Salary adjustments, advances, and bonuses are logged in `staff_expenses` and treated as expenses for reporting.
- General operational expenses are stored in `general_expenses` with optional `branch_id` and default to unpaid.
- Shipment-linked expenses are stored in `general_expenses` with `shipment_id` and are Admin/Owner only; default to unpaid.
- Company expenses are paid later via account transfers; paid expenses lock edits/deletes unless the payment is canceled.
- Points expenses are auto-marked paid without account transfers.
- Company expense reports include monthly salary payouts; advances in the prior month reduce the next month's salary.

Company settings:
- Company details for printable documents are stored in `company_settings` and managed by Admin/Owner.
- Roles list management is available in Company settings (Owner-only).

Shipper/Consignee profiles:
- Shipments can optionally link to shipper and consignee profiles.
- Supplier invoices increase profile balance; receipts decrease balance.
- Supplier invoices can optionally link to shipments.

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
- 2026-01-25: Added net-by-shipment report with paid/unpaid order breakdown.
- 2026-01-25: Company and shipment expenses default to unpaid; payments use account transfers and lock edits/deletes until canceled; points expenses auto-mark paid without transfers.
- 2026-01-24: Customer profile sections use tabbed view, order labels support barcode generation/printing, tracking numbers are unique across all shipments, and orders store package type (bag/box).
- 2026-01-23: Orders sidebar now shows view-only (no create shortcut).
- 2026-01-23: Owner audit log now excludes create actions (edit/delete only).
- 2026-01-22: Account adjustments allow Admin/Owner deposits/withdrawals, account activity references show branch/customer/invoice/Supplier/expense context, customer payments no longer affect branch balances, WhatsApp excludes delivered/picked-up, admins can return sub-branch orders to main branch, points usage logs invoice references and creates a company points expense, branch balances link to branch-to-admin payments.
- 2026-01-21: Customer profile edits are Admin-only (code-only) with a separate Admin customer info edit flow.
- 2026-01-20: Customer refunds are branch-only; add-profile flow now uses code + country without portal/phone inputs.
- 2026-01-19: Clarified customer edit permissions for Admin/Owner/Main Branch and Sub Branch scoping.
- 2026-01-18: Supplier invoices support currency + line-item edits; customer invoices can be edited before payments to adjust currency/orders/points, refund reasons are required, customer view supports multi-order invoice create, and errors surface in a centered modal.
- 2026-01-17: Sub Branch customer payments now post to branch accounts; admin records branch-to-admin transfers; points discounts reduce customer + branch balances.
- 2026-01-16: Added shipment actual departure/arrival dates plus customer gift points with company settings and invoice discounts.
- 2026-01-15: Staff can optionally link to user logins (managed from staff screen), users can change their own passwords,
  and roles can be managed in Company settings (Owner-only).
- 2026-01-14: Accounts limited to admin + sub-branch owners, sub-branch payments recorded by admin/main, invoicing
  captures delivery mode and sets `with_delivery`/`picked_up`, and shipment default-rate updates are locked once invoiced.
- 2026-01-13: Added Admin/Owner accounts screen for creating, editing, deactivating, and deleting payment accounts.
- 2026-01-12: Moved money flows to account transfers with admin/branch/staff/Supplier accounts and account-linked payments.
- 2026-01-11: Mobile responsiveness improved for toolbar, panels, and scrollable tables.
- 2026-01-10: Dashboard now shows role-specific charts/insights for admin, main branch, sub branch, and warehouse.
- 2026-01-09: Branch list shows balances, branch payments can be recorded with printable receipts, transactions page
  shows branch balance summary, and receiving auto-focuses tracking after scans.
- 2026-01-08: Packing list orders display sub-branch, sub-branch packing lists/media are scoped to their own orders,
  and warehouse customer list hides balances.
- 2026-01-08: Invoice/receipt cancellations now record reasons, keep records (status-based), reverse balances, and
  shipment-linked Supplier invoice cancellations reverse expense totals; reports exclude canceled receipts.
- 2026-01-07: Customer list now shows account summaries (profile count + countries), profile drawer shows country/order count/created date,
  add-profile links prefill account logins, and balances sync across profiles in the same account.
- 2026-01-07: Packing lists show media per order, plus shipment/collection media tables, and attachments support collections.
- 2026-01-07: Shipment cost per unit auto-calculates from shipment expenses divided by weight/volume (kg/cbm).
- 2026-01-06: Added `goods_types` list with company-managed goods dropdown for shipments (seeded defaults).
- 2026-01-05: Shipments require type of goods, shipment rate unit is removed from internal forms, order create always
  defaults rate from shipment, and returning unmatched scans reverses customer balances.
- 2026-01-02: Order create defaults rate to shipment default rate, unit type follows weight type, warehouse access to customers is removed,
- 2026-01-02: Order create defaults rate to shipment default rate, unit type follows weight type, warehouse access to customers is removed,
  duplicate unmatched receiving scans are ignored, and orders list groups by shipment with a dedicated shipment orders view.
- 2026-01-03: Customer balances post on sub-branch receipt and payments reduce balance; portal shows incoming orders and transactions;
  branch balances include admin-recorded customer payments.
- 2025-12-21: Shipment distribute only queues orders with a branch; shipment status stays `main_branch` until all orders
  have a branch; repeated distribute does not requeue already distributed orders.
- 2025-12-21: Wired internal attachments page for upload, list, download, and delete with pagination.
- 2025-12-21: Added attachment list search by shipment number or tracking number (with optional type filter).
- 2025-12-26: Added customer accounts with multi-country profiles and portal aggregation.
- 2025-12-26: Added staff management with salary adjustments, advances, bonuses, and expense logging.
- 2025-12-26: Main branch receives arrived shipments via scans; arrived orders stay `in_shipment` until scanned to `main_branch`.
- 2025-12-27: Added shipper/consignee Supplier profiles with invoices and receipts, plus shipment profile linking.
- 2025-12-27: Added general expense tracking for operational costs.
- 2025-12-27: Supplier invoices can optionally link to shipments with searchable selection.
- 2025-12-27: Added company settings for printable invoices/receipts and Supplier print views.
- 2025-12-27: Added company logo upload/delete with fallback to default icon.
- 2025-12-28: Shipment expenses can be tracked per shipment (Admin/Owner only), and branch is optional for expenses.
- 2025-12-28: Added printable reports for shipment expenses, company expenses, net totals, and transaction in/out.
- 2025-12-29: Added branch order/balance reports, branch transfers, uninvoiced orders list, and balance sync rules.
- 2025-12-30: Company expenses and net reports include monthly salaries with prior-month advance deductions.
- 2025-12-31: Added `partially_distributed` shipment status, distribution stays partial while main-branch/in-shipment orders remain,
  order creation allows missing sub-branch, and packing lists hide qty/rate/price.

