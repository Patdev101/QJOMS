# QJOMS — Quotation and Job Order Management System

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | React 19, Inertia.js 3 (no separate API; Laravel controllers send data straight to React pages) |
| Build tool | Vite 8 |
| Database | Microsoft SQL Server Express (`sqlsrv` driver) |
| Styling | Hand-written CSS, "Pine & Sand" theme (`public/css/style.css`), Plus Jakarta Sans font |
| Testing | PHPUnit 12 |

---

QJOMS is an ERP module for **sales and order management**. It covers the quote-to-cash process: staff prepare a quotation from standard service and item prices, the customer approves it, it becomes a job order, and the job order is tracked through to billing.

```
Quotation  →  Customer approval  →  Job Order  →  Billing
```

## Features

### Available now
- **Dashboard**: quotations waiting for customer approval, job orders in progress, work ready for billing, and the value approved this month.
- **New quotation**:
  - Pick a customer; their address, contact details and **discount %** fill in automatically.
  - Add service and item lines with a searchable code picker. Description, UOM and price come from the tariff.
  - Totals, discount and grand total update as you type. A staff-only box shows cost, profit and margin, and is never printed.
  - Keyboard friendly: Enter moves to the next field and adds a new line at the end.
  - Saving opens the new quotation.
- **Quotations list**: filter by All / For approval / Approved, search by number, customer or project, click a row to open it.
- **Quotation details**: progress steps, customer info, lines, totals and the staff-only box.
- **Print**: a clean printable quotation (or job order) without cost or profit, with "Prepared by" and "Customer approval" signature lines.
- **Revise**: edit a quotation that's still waiting for approval (same quotation, no new number).
- **Approve → Job order**: once the customer agrees, approve it (with a confirm pop-up). It becomes job order `JO-YYYY-0001` and the quotation is locked.
- **Job orders**: list with filters (In progress / Ready for billing / Billed) and search; detail page with clickable status steps Open → Ongoing → Completed → Billed (Billed asks first).

- **Delete quotation**: only while it's still waiting for approval (asks first).
- **Customer profiles** (Master data): add, edit, search and delete customers, each with a default discount %. A customer with quotations can't be deleted.
- **Service and item tariffs** (Master data): code, description, UOM, unit, cost and price, with a margin column and a live profit/margin preview. Tariffs can be set inactive to hide them from new quotations.

### Not built yet
- Login and user roles
- Billing / invoices

## Business rules
- Each customer has a default **discount in %**. It fills in on new quotations, and staff can change it on a quotation before approval.
- Each tariff has a **cost** (internal) and a **selling price**. Cost and profit are shown to staff only.
- Prices are copied onto each quotation line, so changing a tariff later doesn't change old quotations.
- The server recalculates all totals when saving; the numbers on screen are only a preview.
- Quotation numbers are `QT-YYYY-0001` and job order numbers `JO-YYYY-0001`; both restart every year.
- Only quotations waiting for approval can be revised or deleted; approved ones are locked.
- Deleting a tariff is safe: quotations keep their own copy of each line.

## Getting started

### Requirements
- PHP 8.3+ with the `pdo_sqlsrv` extension
- Composer
- Node.js 20+ and npm
- SQL Server (Express is fine)

### Setup
```bash
composer install
npm install
copy .env.example .env        # then set the database values below
php artisan key:generate
```

Database settings in `.env`:
```env
DB_CONNECTION=sqlsrv
DB_HOST=.\SQLEXPRESS
DB_DATABASE=quotation_system
DB_TRUST_SERVER_CERTIFICATE=true
```

Create the database in SQL Server, then:
```bash
php artisan migrate --seed                  # tables + sample customers and tariffs
php artisan db:seed --class=DemoSeeder      # optional: sample quotations and job orders
```

### Run
```bash
npm run build                    # build the React pages (or: npm run dev while editing)
php artisan serve --port=8092
```
Open http://localhost:8092

### Tests
```bash
php artisan test
```

## Project structure

| Path | What it holds |
|---|---|
| `resources/js/Pages/` | React pages: `Dashboard.jsx`, `Quotations/` (Index, Show, Create — also used for Revise), `JobOrders/` (Index, Show), `Customers/` (Index, Form), `Tariffs/` (Index, Form) |
| `resources/js/Layouts/AppLayout.jsx` | Top menu with the Master data drop-down, phone menu, messages |
| `resources/js/Components/` | Icons, money formatting, confirm pop-up, pager, shared quotation details |
| `resources/views/` | `app.blade.php` (React shell) and `quotations/print.blade.php` (printable quotation / job order) |
| `app/Http/Controllers/` | Dashboard, quotations, job orders, customers, tariffs |
| `app/Models/` | Customer, Tariff, Quotation, QuotationLine, JobOrder |
| `database/migrations/` | All tables |
| `public/css/style.css` | The whole look; colors are set at the top in `:root` |