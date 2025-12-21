# UG Shipping API + App Notes

## Stack
- PHP 8+, MySQL/MariaDB
- HTML/CSS/Vanilla JS (Fetch)

## Base URL
`BASE_URL` and `PUBLIC_URL` are computed in `config/config.php` from host + app root.
Use relative paths or `BASE_URL` in PHP. In JS, use `window.APP_BASE`.

## Auth
- Internal auth: `/api/auth/login.php`, `/api/auth/logout.php` (session based)
- Portal auth: not implemented yet (hooks exist in `app/customer_auth.php`)

## Roles
Seeded roles (see `config/schema.sql`):
- Admin
- Owner
- Main Branch
- Sub Branch
- Warehouse

## Core APIs (implemented)

### Auth
- `POST /api/auth/login.php`
- `POST /api/auth/logout.php`

### Shipments
- `GET /api/shipments/list.php`
- `GET /api/shipments/view.php`
- `POST /api/shipments/create.php`
- `PATCH /api/shipments/update.php`
- `POST /api/shipments/status.php`
- `POST /api/shipments/delete.php`

### Orders
- `GET /api/orders/list.php`
- `POST /api/orders/create.php`
- `PATCH /api/orders/update.php`
- `POST /api/orders/reassign_customer.php`
- `POST /api/orders/delete.php`

### Receiving
- `POST /api/receiving/scan.php`
- `GET /api/receiving/unmatched.php`

### Collections
- `GET /api/collections/list.php`

### Invoices
- `GET /api/invoices/list.php`
- `POST /api/invoices/create.php`
- `PATCH /api/invoices/update.php`
- `GET /api/invoices/view.php`
- `GET /api/invoices/print.php`
- `POST /api/invoices/delete.php`

### Transactions
- `GET /api/transactions/list.php`
- `POST /api/transactions/create.php`
- `POST /api/transactions/allocate.php`
- `POST /api/transactions/delete.php`

### Attachments
- `POST /api/attachments/upload.php`
- `GET /api/attachments/list.php`
- `GET /api/attachments/download.php`
- `PATCH /api/attachments/update.php`
- `POST /api/attachments/delete.php`

### Branches
- `GET /api/branches/list.php`
- `POST /api/branches/create.php`
- `PATCH /api/branches/update.php`
- `POST /api/branches/delete.php`

### Users
- `GET /api/users/list.php`
- `POST /api/users/create.php`
- `PATCH /api/users/update.php`
- `POST /api/users/delete.php`

### Roles
- `GET /api/roles/list.php`
- `POST /api/roles/create.php`
- `PATCH /api/roles/update.php`
- `POST /api/roles/delete.php` (hard delete; no `deleted_at` column)

### Customers
- `GET /api/customers/list.php`
- `GET /api/customers/view.php`
- `POST /api/customers/create.php`
- `PATCH /api/customers/update.php`
- `POST /api/customers/delete.php`

### Countries
- `GET /api/countries/list.php`
- `POST /api/countries/create.php`
- `PATCH /api/countries/update.php`
- `POST /api/countries/delete.php` (hard delete; no `deleted_at` column)

### Payment Methods
- `GET /api/payment_methods/list.php`
- `POST /api/payment_methods/create.php`
- `PATCH /api/payment_methods/update.php`
- `POST /api/payment_methods/delete.php` (hard delete; no `deleted_at` column)

### Shopping Orders
- `GET /api/shopping_orders/list.php`
- `POST /api/shopping_orders/create.php`
- `PATCH /api/shopping_orders/update.php`
- `POST /api/shopping_orders/delete.php`

## Soft Delete Notes
Entities with `deleted_at` are soft deleted. Tables without `deleted_at` (roles, countries, payment_methods) are hard deleted.

## UI Pages
- Login: `/index.php`
- Internal pages: `/views/internal/*.php` (dashboard + sections)
- Portal placeholder: `/views/portal/home.php`

## Next Build Targets
- Portal auth + portal pages
- API guards by role matrix for all endpoints
- Dashboard widgets wired to API data

