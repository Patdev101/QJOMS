# QJOMS — Quotation and Job Order Management System

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | React 19 with Inertia.js 3 (no separate API: Laravel controllers send data straight to React pages) |
| Build tool | Vite 8 |
| Database | Microsoft SQL Server Express (`sqlsrv` driver) |
| Authentication | Laravel session login (built-in `auth` middleware, rate-limited sign-in) |
| Styling | Hand-written CSS, "Pine & Sand" theme (`public/css/style.css`), Plus Jakarta Sans font |
| Printing | Plain HTML print pages (Blade) for quotations, job orders and invoices |
| Testing | PHPUnit 12 (feature tests on an in-memory SQLite database) |

---

## Overview

QJOMS is an ERP module for **sales and order management**. It covers the **quote-to-cash** process of a service company: staff price a job from standard service and item tariffs, the customer approves it, it becomes a job order, the work is tracked, and the customer is invoiced and pays.

```
Quotation  →  Customer approval  →  Job order  →  Invoice  →  Paid
 QT-2026-0001                       JO-2026-0001   INV-2026-0001
```

Each step creates or updates one document, and every document links to the others, so the whole story of a job can be followed from any page.

---

## Contents
1. [Modules](#modules)
2. [How to use it](#how-to-use-it)
3. [Business rules](#business-rules)
4. [Security](#security)
5. [Getting started](#getting-started)
6. [Managing data](#managing-data)
7. [Project structure](#project-structure)
8. [Database](#database)
9. [Roadmap](#roadmap)

---

## Modules

### Dashboard
- Quotations **waiting for customer approval** (oldest first, so nobody forgets to follow up)
- Job orders **in progress**, work **ready for billing**, and the value **approved this month**
- Each card and row opens the matching list or document

### Quotations
- **New quotation**
  - Pick a customer: address, contact details and the customer's **discount %** fill in automatically (the % can be changed before approval, with a *Reset* link back to the customer's default).
  - Each line: choose **Service or Item** → **Category** → the entry (grouped by sub-category, with prices). The lists only show the chosen type.
  - The **Code** fills in by itself, or type a code to fill in everything else. Unknown codes are flagged.
  - Totals, discount and grand total update as you type. A **staff-only** box shows total cost and profit; it is never printed.
  - Keyboard friendly: Enter moves to the next field and adds a new line at the end. Leaving with unsaved changes asks first.
- **List**: filters (All / For approval / Approved), search by number, customer or project.
- **Revise**: edit a quotation still waiting for approval (same number).
- **Approve → Job order**: once the customer agrees (confirm pop-up). The quotation is then locked.
- **Delete**: only while waiting for approval (confirm pop-up).
- **Print**: clean A4 quotation (portrait or landscape) with "Prepared by" and "Customer approval" lines; no cost or profit.

### Job orders
- Created automatically when a quotation is approved (`JO-YYYY-0001`).
- Status: **Open → Ongoing → Completed → Billed**, moved forward with one **Next step** button (Start work → Mark as completed → Create invoice). A confirmed *Back* button corrects mistakes.
- List with filters (In progress / Ready for billing / Billed) and search. Printable job order.

### Invoices
- **Create invoice** on a completed job order (`INV-YYYY-0001`, due date defaults to 30 days, optional notes). The job order becomes **Billed**.
- **Record payment**: date, amount, method (Cash / Check / Bank transfer / GCash) and reference (OR, check or transfer no.).
- Status updates itself: **Unpaid → Partly paid → Paid**, plus **Overdue** after the due date.
- A payment entered by mistake can be removed (recorded in the history).
- List with filters, search and the **total still to collect**. Printable invoice with payments received, balance due and a PAID stamp.

### Master data
- **Customer profiles**: name, address, contact person, contact no., email and default **discount %**.
- **Service tariff / Item tariff**: category, sub-category, code, description, UOM, unit name, **cost** (internal) and **selling price**.
  - Shown as a **folder tree**: categories open into sub-categories, which open into the entries. Category rows summarize UOM, cost, price range and margin, and have an **Add** button that pre-fills the category.
  - Margin column ("cost not set" until a cost is entered) and a live profit/margin preview on the form.
  - Inactive tariffs stay on old quotations but can't be picked on new ones.

### Shared on every document page
Quotation, job order and invoice pages all use the same layout:
1. Header with the document number, status and **Print**
2. A **Quotation → Job order → Invoice → Paid** strip: each step shows its number and status, links to its page, and marks *This page*
3. A **Next step** bar with the one action to take (green "done" message when finished)
4. Customer & project, lines and totals
5. **History**: who did what and when (created, revised, approved, status changes, invoiced, payments, deletions)

---

## How to use it

**Typical job, start to finish**
1. **Master data → Customer profiles**: add the customer (once).
2. **Master data → Service / Item tariff**: add the services and items you charge for (once).
3. **New quotation**: choose the customer, add lines, save. Print it for the customer.
4. Customer wants changes? **Revise**. Customer agrees? **Approve → Job order**.
5. On the job order: **Start work** → **Mark as completed** → **Create invoice**.
6. On the invoice: **Record payment** until it is **Paid**.

**Tips**
- Painting is priced per sqm *per coat*: enter quantity = area × coats (500 sqm × 2 coats = 1,000).
- Type a category word (e.g. "gritblasting") in the tariff search to find a whole group.
- Every list row can be **Ctrl+clicked** to open in a new tab.

---

## Business rules
- Each customer has a default **discount in %**; it fills in on new quotations and can be changed per quotation before approval.
- Each tariff has an internal **cost** and a **selling price**. Cost and profit are shown to staff only and never printed.
- Prices are **copied onto each quotation line**, so changing or deleting a tariff never changes existing quotations.
- The **server recalculates** every total when saving; the numbers on screen are only a preview.
- Numbers are `QT-YYYY-0001`, `JO-YYYY-0001` and `INV-YYYY-0001`; each restarts every year.
- Only quotations **waiting for approval** can be revised or deleted; approved ones are locked.
- A job order becomes **Billed** only by creating its invoice, and an invoiced job order can't move back.
- A payment can't be more than the invoice's remaining balance.
- A customer with quotations can't be deleted.

---

## Security
- **Sign-in required** for every page and action; signed-out visitors are sent to the sign-in page.
- **Password guessing** is limited to 5 attempts a minute; a wrong email and a wrong password give the same message.
- **CSRF protection** on every form (requests without the security token are refused with HTTP 419).
- **SQL injection**: all queries use bound parameters; the few raw SQL fragments contain no user input.
- **XSS**: React and Blade escape all text.
- **Activity history** records the signed-in user for every change.
- `.env` (database settings, app key) is excluded from Git.

**Before going live**
- Change the default admin password (`admin@example.com` / `password` is for development only).
- In `.env` set `APP_ENV=production` and `APP_DEBUG=false`.
- Run under a proper web server (IIS or Apache) with HTTPS instead of `php artisan serve`.

---

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
copy .env.example .env        # then set the values below
php artisan key:generate
```

`.env` settings:
```env
APP_URL=http://localhost:8092
DB_CONNECTION=sqlsrv
DB_HOST=.\SQLEXPRESS
DB_DATABASE=quotation_system
DB_TRUST_SERVER_CERTIFICATE=true
```

Create the `quotation_system` database in SQL Server, then:
```bash
php artisan migrate --seed    # tables + admin account + sample customers and tariffs
```

### Run
```bash
npm run build                 # build the React pages (only after changing files in resources/js)
php artisan serve --port=8092
```
Open http://localhost:8092 and sign in with `admin@example.com` / `password`.

While editing React pages, run `npm run dev` in a second terminal instead of rebuilding each time.

### Tests
```bash
php artisan test
```
Covers the full quote-to-cash flow, billing and payments, history, master data, sign-in and rate limiting. Tests use their own in-memory database and never touch real data.

---

## Managing data

| Command | Result |
|---|---|
| `php artisan migrate` | Apply new database changes, **keeping** existing data |
| `php artisan migrate:fresh` | ⚠️ Delete **everything** and recreate empty tables |
| `php artisan migrate:fresh --seed` | ⚠️ Delete everything, then add the admin account + 3 sample customers + 6 sample tariffs |
| `php artisan db:seed --class=AdminUserSeeder` | Add the admin account (safe to run again) |
| `php artisan db:seed --class=NsrmsTariffSeeder` | Load the NSRMS *Hull, Hold & Tank Preservation* tariff (20 services; safe to run again, keeps costs already entered) |
| `php artisan db:seed --class=DemoSeeder` | Add sample quotations, job orders and an invoice (needs the sample customers) |

**Clear only quotations, job orders and invoices** (keep customers, tariffs and users): run in SSMS on `quotation_system`:
```sql
DELETE FROM activities;
DELETE FROM payments;
DELETE FROM invoices;
DELETE FROM job_orders;
DELETE FROM quotation_lines;
DELETE FROM quotations;
```

**Backup**: in SSMS, right-click `quotation_system` → *Tasks → Back Up…*. Restore with *Databases → Restore Database…*.

---

## Project structure

### Frontend (what the user sees)
| Path | What it holds |
|---|---|
| `resources/js/app.jsx` | Starts React and finds the pages |
| `resources/js/Pages/` | One file per screen: `Dashboard`, `Auth/Login`, `Quotations/` (Index, Show, Create — also used for Revise), `JobOrders/` (Index, Show), `Invoices/` (Index, Show), `Customers/` (Index, Form), `Tariffs/` (Index, Form) |
| `resources/js/Layouts/AppLayout.jsx` | Top menu (with Master data and account menus), phone menu, messages |
| `resources/js/Components/` | `Icon`, `format` (₱ money), `ConfirmDialog`, `Pager`, `QuotationDetails`, `History`, `DocFlow` (document flow strip) |
| `resources/views/app.blade.php` | The HTML page React loads into |
| `resources/views/quotations/print.blade.php` | Printable quotation / job order |
| `resources/views/invoices/print.blade.php` | Printable invoice |
| `public/css/style.css` | The whole look; colors are set at the top in `:root` |

### Backend (logic and data)
| Path | What it holds |
|---|---|
| `routes/web.php` | Which URL goes to which controller; sign-in and the `auth` group |
| `app/Http/Controllers/` | `Auth`, `Dashboard`, `Quotation`, `JobOrder`, `Invoice`, `Payment`, `Customer`, `Tariff` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Data shared with every page (signed-in user, messages, menu counts) |
| `app/Models/` | `User`, `Customer`, `Tariff`, `Quotation`, `QuotationLine`, `JobOrder`, `Invoice`, `Payment`, `Activity` |
| `database/migrations/` | Table definitions |
| `database/seeders/` | `DatabaseSeeder`, `AdminUserSeeder`, `NsrmsTariffSeeder`, `DemoSeeder` |
| `tests/Feature/` | `QuotationFlowTest`, `LoginTest` |

---

## Database

| Table | Holds |
|---|---|
| `users` | Accounts that can sign in |
| `customers` | Customer profiles and default discount % |
| `tariffs` | Services and items: type, category, sub-category, code, description, UOM, unit, cost, price, active |
| `quotations` | Quotation header, copied customer details, totals, status (pending / approved) |
| `quotation_lines` | Lines of a quotation (copied code, description, UOM, qty, cost, price) |
| `job_orders` | One per approved quotation; status (open / ongoing / completed / billed) |
| `invoices` | One per billed job order; dates, amount, notes |
| `payments` | Payments received against an invoice |
| `activities` | History: what happened to a quotation, its job order and invoice, by whom and when |

---

## Roadmap
- **User roles** (Admin / Sales / Manager / Billing) and a screen to manage accounts and passwords
- Hide cost and profit from roles that shouldn't see them
- Cancel / void an approved quotation (kept on record)
- Company header and logo on printouts
- Reports (monthly approvals, profit, top customers) and Excel/PDF export
