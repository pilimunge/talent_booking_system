# Talent Booking Management System

A full-stack web application connecting customers with talented service
providers (MCs, comedians, musicians, DJs, content creators, influencers,
event hosts, motivational speakers, dancers, photographers, videographers)
for event bookings.

Built with **plain PHP (PDO) + MySQL + Bootstrap 5 + vanilla JavaScript** —
no framework, no Composer dependencies. Designed to run directly on
XAMPP/WAMP after importing the database.

---

## 1. Requirements

- XAMPP or WAMP with **PHP 8.0+** and **MySQL 5.7+ / MariaDB 10.3+**
- A modern browser

No Composer, no `npm install`, no build step. Bootstrap, Bootstrap Icons,
and Chart.js are loaded from CDN in `includes/header.php` /
`admin/dashboard.php`.

---

## 2. Installation Steps

### Step 1 — Copy the project folder

Extract this project into your XAMPP/WAMP web root, e.g.:

```
C:\xampp\htdocs\talent_booking_system      (Windows/XAMPP)
/Applications/XAMPP/htdocs/talent_booking_system   (Mac/XAMPP)
/opt/lampp/htdocs/talent_booking_system    (Linux/XAMPP)
```

The folder name matters only if it differs from `talent_booking_system` —
see Step 3 if you rename it.

### Step 2 — Import the database

1. Start Apache and MySQL in your XAMPP/WAMP control panel.
2. Open **phpMyAdmin** (usually `http://localhost/phpmyadmin`).
3. Click **Import** → **Choose File** → select
   `database/talent_booking_system.sql` → click **Go**.
   - This creates the `talent_booking_system` database, all 12 tables,
     foreign keys, and sample data automatically. You do **not** need to
     create the database manually first — the SQL file does it with
     `CREATE DATABASE IF NOT EXISTS`.

### Step 3 — Configure the app (only if needed)

Open `includes/config.php` and check these values match your setup:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'talent_booking_system');
define('DB_USER', 'root');
define('DB_PASS', '');   // default XAMPP root password is empty
define('APP_URL', 'http://localhost/talent_booking_system'); // update if your folder name differs
```

If your MySQL root user has a password (common on WAMP), set `DB_PASS`
accordingly. If you extracted the project under a different folder name,
update `APP_URL` to match.

### Step 4 — Fix demo account passwords (important)

The SQL file ships with a placeholder bcrypt hash for all demo accounts.
It is very likely correct, but bcrypt hashes are salted/random, so to
**guarantee** the documented passwords work on your exact PHP build:

1. Visit: `http://localhost/talent_booking_system/database/generate_password_hashes.php`
2. Copy the `UPDATE users SET password = '...' WHERE email = '...';`
   statements it prints.
3. Paste and run them in phpMyAdmin's **SQL** tab (select the
   `talent_booking_system` database first).
4. **Delete `database/generate_password_hashes.php`** afterward — it
   should never remain accessible in a live deployment. (Note:
   `database/.htaccess` already blocks direct browser access to this
   whole folder as a safety net, but the file's contents are still
   sensitive and should not be left on disk long-term.)

### Step 5 — Set folder permissions (Linux/Mac only)

```bash
chmod -R 755 uploads/
```

(Not needed on Windows/XAMPP.)

### Step 6 — Open the app

Visit `http://localhost/talent_booking_system/` in your browser.

---

## 3. Default Accounts

| Role     | Email                        | Password       |
|----------|-------------------------------|----------------|
| Admin    | admin@talentbooking.com       | Admin@123      |
| Customer | asha@example.com              | Customer@123   |
| Customer | baraka@example.com            | Customer@123   |
| Provider | djkombo@example.com           | Provider@123   |
| Provider | mcfahari@example.com          | Provider@123   |
| Provider | chapa@example.com (pending approval) | Provider@123 |

All demo passwords work **after completing Step 4** above.

---

## 4. Folder Structure

```
/talent_booking_system
│
├── assets/
│   ├── css/style.css        Custom styles layered on Bootstrap 5
│   ├── js/script.js         Star ratings, image previews, confirm dialogs
│   └── images/               (empty, reserved for static site images)
│
├── includes/                 Shared PHP includes (blocked from direct HTTP access)
│   ├── config.php            DB credentials & app constants — EDIT THIS
│   ├── db.php                PDO connection (prepared statements enforced)
│   ├── functions.php         Helpers: e(), csrf, notifications, uploads, badges
│   ├── auth.php               Session guard, require_role(), remember-me
│   ├── header.php             Shared <head> + navbar (role-aware)
│   └── footer.php             Shared footer + Bootstrap JS

├── auth/                      Public authentication pages
│   ├── register.php, login.php, logout.php
│   └── forgot_password.php, reset_password.php
│
├── customer/                  Customer-only pages (require_role('customer'))
│   ├── dashboard.php, search.php, view_provider.php
│   ├── book_provider.php, bookings.php, booking_view.php
│   ├── payment.php, review.php, receipt.php
│   ├── profile.php, notifications.php
│   └── _sidebar.php           (internal partial, not a page)
│
├── provider/                  Provider-only pages (require_role('provider'))
│   ├── dashboard.php, profile.php, gallery.php
│   ├── bookings.php, booking_view.php
│   ├── earnings.php, reviews.php, availability.php, notifications.php
│   └── _sidebar.php, _require_profile.php  (internal partials)
│
├── admin/                     Admin-only pages (require_role('admin'))
│   ├── dashboard.php          Stats + Chart.js graphs
│   ├── users.php               Activate/Deactivate/Suspend
│   ├── providers.php, provider_view.php   Approve/Reject/Suspend
│   ├── categories.php          Full CRUD
│   ├── bookings.php, payments.php, reviews.php   Platform oversight
│   ├── reports.php             Daily/Weekly/Monthly/Annual + top talent
│   ├── settings.php            Site name/logo/contact
│   ├── notifications.php       Own notifications + broadcast tool
│   └── _sidebar.php
│
├── uploads/                    User-uploaded images (PHP execution disabled here)
│   ├── profile_images/
│   ├── cover_images/
│   └── gallery/
│
├── database/
│   ├── talent_booking_system.sql       Full schema + seed data
│   └── generate_password_hashes.php    Run once, then delete (see Step 4)
│
└── index.php                   Public landing page
```

---

## 5. Database ERD (Entity-Relationship Overview)

```
 users (1) ────────────< providers (1)          [one user account can have one provider profile]
   │                          │
   │                          ├──< gallery                [provider's portfolio photos]
   │                          ├──< availability            [provider's date-by-date status]
   │                          └──< bookings (as provider)
   │
   ├──< bookings (as customer)
   │        │
   │        ├──1─1─ payments        [each booking has at most one payment]
   │        └──1─1─ reviews          [each booking has at most one review]
   │
   ├──< notifications
   └──< activity_logs

 categories (1) ────────< providers   [each provider belongs to one category]

 password_resets           [standalone, keyed by email — not FK'd to users
                             so a reset request works even before confirming
                             which account, though in practice only existing
                             emails receive a token]

 settings                  [single-row table for site-wide config]
```

**Relationship summary:**

| Table | Relates to | Type |
|---|---|---|
| `providers.user_id` | `users.id` | Many providers rows → One user (in practice 1:1, enforced at app level) |
| `providers.category_id` | `categories.id` | Many providers → One category |
| `bookings.customer_id` | `users.id` | Many bookings → One customer |
| `bookings.provider_id` | `providers.id` | Many bookings → One provider |
| `payments.booking_id` | `bookings.id` | One payment → One booking |
| `reviews.booking_id` | `bookings.id` | One review → One booking (UNIQUE constraint) |
| `reviews.customer_id` / `reviews.provider_id` | `users.id` | Denormalized for fast lookups without joining through bookings |
| `gallery.provider_id` | `providers.id` | Many photos → One provider |
| `availability.provider_id` | `providers.id` | Many date entries → One provider |
| `notifications.user_id` | `users.id` | Many notifications → One user |
| `activity_logs.user_id` | `users.id` | Many log entries → One user (nullable, SET NULL on user delete) |

All foreign keys use `ON DELETE CASCADE` (except `activity_logs`, which
uses `SET NULL` so historical logs survive account deletion).

---

## 6. Booking Workflow (as implemented)

```
pending → accepted → (customer pays) → ongoing → completed
   │           │
   └─ rejected  └─ (customer can still cancel while pending/accepted) → cancelled
```

1. Customer sends a booking request → status `pending`, provider notified.
2. Provider accepts (`accepted`) or rejects (`rejected`) → customer notified.
3. If accepted, customer pays via `customer/payment.php` (manual/mock —
   enters a mobile money transaction reference) → status `ongoing`,
   provider notified of payment.
4. Provider marks the event `completed` after it happens.
5. Customer leaves a rating + review (only once, only after `completed`).
6. Admin can view/monitor every booking, payment, and review from the
   admin panel at any stage.

---

## 7. Security Measures Implemented

- **PDO with `ATTR_EMULATE_PREPARES => false`** — real prepared
  statements everywhere, no string-concatenated SQL.
- **`e()` escaping** on every piece of dynamic output (XSS prevention).
- **CSRF tokens** on every POST form (`csrf_field()` + `verify_csrf()`).
- **`password_hash()` / `password_verify()`** — no plaintext or reversible
  password storage.
- **Role-based access control** via `require_role()` on every protected
  page.
- **MIME-type validation** (not just file extension) on all image
  uploads, with random filenames to prevent path traversal/overwrite.
- **`.htaccess` hardening**: `includes/` and `database/` are blocked from
  direct HTTP access; PHP execution is disabled inside `uploads/` even if
  a malicious file slipped past validation.
- **HttpOnly, SameSite=Lax session cookies**.
- **Logout requires POST** (prevents CSRF-triggered logout via `<img>` tags).

## 8. Known Limitations (by design, for this MVP)

- Payments are **manual/mock** — no live M-Pesa/Airtel Money/Tigo
  Pesa/HaloPesa API integration. The schema (`payment_method` +
  `transaction_reference`) is ready for a real gateway to be wired in
  later without migration changes.
- Password reset emails are **not actually sent** (no SMTP configured) —
  the reset link is displayed on-screen instead, for easy local testing.
  Wire in PHPMailer/SMTP for production use.
- Receipts are browser-print-to-PDF (`window.print()`), not a
  server-generated PDF file.
- `admin/settings.php` updates the `settings` table, but the public
  site chrome (header/footer) still reads from the `APP_NAME` constant —
  see the in-app note on that page for how to wire it up fully.
