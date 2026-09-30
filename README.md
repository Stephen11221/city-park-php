# City Park Management

A PHP and MySQL park management app with registration, login, a dashboard, and pages for all seven schema tables. Requires PHP 7.4 or newer with `pdo_mysql` enabled.

## Run locally

```sh
php -S localhost:8000
```

Open http://localhost:8000/register.php to create your account, or http://localhost:8000/login.php to sign in. Successful registration signs you in automatically. The public landing page is at `/index.php`. The dashboard at `/dashboard.php` requires a valid session.

## Database setup

The local database is `city_park_management`. Connection settings are stored in `database.local.php`, which is ignored by Git. To configure another installation, copy `database.example.php` to `database.local.php` and enter its credentials. Environment variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` override local settings.

Run the setup command once per installation:

```sh
php scripts/setup-database.php
```

This loads `database/schema.sql` and creates the selected database and all seven tables if missing: `users`, `parks`, `facilities`, `bookings`, `payments`, `maintenance`, and `activity_logs`. It preserves existing records and does not alter existing table definitions. Foreign keys match the city park schema, including its cascading deletes and nullable user references.

The users table uses `full_name`, unique `email` (150 characters), optional `phone`, hashed `password`, `role`, `status`, and timestamps. Registration creates active customer accounts using database defaults. Inactive accounts cannot log in or retain access to the protected page. Existing passwords must be PHP-compatible hashes; plaintext passwords are not accepted. Accounts in the earlier `php_playground` database are not automatically copied. Passwords are hashed using PHP's password API. Database operations use PDO prepared statements. Authentication forms and logout use CSRF tokens; signing in regenerates the session ID. Session cookies use HttpOnly and SameSite=Lax, plus Secure when served over HTTPS.

## Files

- `login.php` and `register.php`: account forms, rendered by `auth-page.php`.
- `auth.php`: sessions, CSRF protection, and user lookup.
- `logout.php`: POST-only sign-out.
- `index.php`: public landing page with park listings and sign-in links.
- `dashboard.php`: protected dashboard with database counts and recent bookings.
- `database.php`: shared PDO connection.
- `style.css`: responsive page styles.
- `includes/`: shared page rendering, role access, entity definitions, and validation.

The built-in PHP server is for local development. For deployment, keep credential files outside the public document root.

## Management pages

- `parks.php`: park locations, descriptions, hours, and statuses.
- `facilities.php`: facilities linked to parks, capacities, and booking prices.
- `bookings.php`: customer and facility reservations, times, amounts, and statuses.
- `payments.php`: payment records linked to bookings. This does not process online payments.
- `maintenance.php`: facility reports, priorities, staff assignments, and completion dates.
- `users.php`: accounts, roles, statuses, and password changes.
- `activity_logs.php`: read-only history of authentication and record changes.

Lists support search and pagination. Status filters are available where applicable. Editable tables have add, view, edit, and confirmed delete flows. Deletes are blocked while related operational records remain, avoiding the schema's cascading deletion of those records. Password hashes are never displayed.

Admins can manage all six editable tables and read activity logs. Staff can manage parks, facilities, bookings, payments, and maintenance. Customers can browse parks and facilities, create bookings, view their own bookings and payments, and cancel their pending or approved bookings. Customers cannot set their booking's owner, price, or approval status.

Booking prices are a flat facility price per booking. Capacity zero means no bookable capacity. Pending and approved bookings cannot overlap for the same facility. Booking times must fit the park's opening hours; overnight bookings are not supported. Cancelling a booking does not automatically refund a payment.

An active administrator account, `steven@mail.com`, has been created in this local database with the password supplied during setup. The setup script does not reset accounts or embed this password. Sign in at `/login.php`; change the password through the Users edit form if needed.

Validation performed against the local database: all seven admin pages, creation of all six editable record types, editing and deletion, audit logging, invalid booking rules, CSRF checks, role restrictions, and customer ownership/price checks. Temporary test records were removed. Browser visual checks have not been performed.

## Demo data and real photographs

```sh
php scripts/setup-database.php
php scripts/seed-demo-data.php
```

The demo seed adds 3 parks, 6 facilities, 4 fictional users, 6 linked bookings and test payments, 3 maintenance reports, and 6 seed activity logs. Stable, unique `demo_key` values make reruns leave existing sample records unchanged. Existing records and the administrator account are preserved. Demo users receive random hashed passwords; no shared demo login is enabled.

The migration adds nullable `image_path` columns for parks/facilities and nullable unique `demo_key` identifiers. Existing installations are upgraded by setup or the seed command. The schema file includes these columns for fresh installations.

Photos are actual downloaded stock images stored in `assets/images/`, with sources in [photo credits](assets/images/CREDITS.md). They represent fictional demo places. The landing hero, park/facility cards, table thumbnails, and record details display local images. Update a park or facility's Photo path field to choose an existing JPG, PNG, or WebP file in that directory.

Sample payments do not represent money collected. Demo bookings use dates relative to the first seed run and are not shifted on reruns.

## Food menu, staff, and table reservations

Public pages:

- `/menu.php`: available food and drink items, category filtering, and search.
- `/tables.php`: find tables by visit date, start/end time, and guest count. Selecting a table carries those details through login or registration to the booking form.

Management pages:

- `/menu_items.php`: admins and staff can add, edit, hide, or delete menu items, with prices and optional local photos.
- `/staff.php`: admin-only staff account management. Records live in `users` with the staff role; customers and admins cannot be edited through this filtered page.
- `/dining_tables.php`: admins and staff manage tables, seats, reservation fees, and availability. Tables live in `facilities` with `kind = table` so existing bookings, payments, maintenance, and conflict checks apply.

The public menu is informational; it does not submit food orders. Meals and drinks are separate from the table reservation fee. Customer bookings use the stored fee and start as pending. Pending and approved reservations block overlapping requests for that individual table, while other tables remain bookable. Cancelling a reservation releases the time slot.

Upgrade and add repeat-safe dining samples:

```sh
php scripts/setup-database.php
php scripts/seed-demo-data.php
php scripts/seed-dining.php
```

The dining migration adds `menu_items` and the facility `kind` column. The dining seed adds six menu items and six tables without overwriting existing records. Three menu photographs are stored locally; sources are listed in the photo credits file.

Verified over HTTP against MySQL: public menu filtering, unavailable item visibility, staff role restrictions, table creation, preserved selection after login, customer ownership and fee enforcement, capacity and overlap rejection, and cancellation restoring availability.

## Hiring, dismissal, staff pay, and suppliers

Admins use **Staff → Hire staff** to enter an employee's account details, job title, hire date, daily rate, and monthly rate. On a staff detail page:

- **Dismiss / fire staff** requires confirmation and a reason. It takes effect today, disables login and existing sessions, and retains the account and its related records.
- **Rehire staff** restores login and starts a new hire date today, retaining saved pay rates and previous payment records.
- **Allocate payment** opens the pay allocation form with that employee selected.

`staff_payments.php` supports one day's pay or a full month's salary. Daily allocations use the selected date and saved daily rate. Monthly allocations use the first day of a month and the saved monthly rate. There is no automatic attendance calculation, deduction, tax calculation, or prorating. Amounts are captured when allocated, so changing a staff rate does not change existing allocations for the same period.

Duplicate periods and overlapping daily/monthly allocations for the same employee and month are rejected. Edit an existing unpaid or cancelled allocation instead of creating another for the same period. Marking paid requires a payment date; paid records are locked. Allocations cannot be deleted, and staff with pay history cannot be deleted. Dismissed staff may receive new allocations for periods within their recorded employment dates, including their dismissal month, but not for later periods. These are accounting records, not money transfers.

`suppliers.php` lets admins manage supplier names, contacts, email, phone, address, goods/services supplied, notes, and active status. Payroll and supplier pages are restricted to admins.

Run `php scripts/setup-database.php` to apply the additional employment columns and the `staff_payments` and `suppliers` tables. The migration can also be run directly using `php scripts/migrate-staff-payroll.php`.

Verified against MySQL through HTTP: hire and staff login, daily/monthly allocation amounts, duplicate and overlapping-period rejection, rate snapshots, paid-record locking, supplier create/edit, CSRF checks, dismissal revoking access, preserved payroll, rehire, and admin-only permissions.

## Image uploads

Park, facility, table, and menu forms now support JPG, PNG, and WebP uploads, a preview, replacement, and removal. The current limit is 2 MB and 8 megapixels. PHP GD and Fileinfo validate and re-encode each image; generated filenames are stored under `assets/images/uploads/`. Keep this directory writable by PHP and include it in backups. Existing photos remain if no replacement is selected. Failed record saves remove the newly uploaded file; previously saved files are retained to avoid breaking shared references.

## Cashier dashboard and receipts

- `/cashier.php`: full menu with quantities, takeaway/table checkout, cash change, payment references, and waiter allocation.
- `/sales.php`: saved sales and receipt links; admins can record a confirmed full refund.
- `/receipt.php?id=...`: printable itemized receipt with original prices, cashier, waiter, and table details.
- `/cashier-audit.php`: searchable, read-only sales/refund/assignment history.
- `/accounting.php`: dated cash movement summary, ledger, payment-method breakdown, and CSV export.
- `/expenses.php`: admin-managed operating expenses and supplier payments.

Admins can use the cashier dashboard or create a user with the `cashier` role. Cashiers land on `/cashier.php` after login. Waiters are active, employed staff accounts; their assigned tables appear on their staff dashboard. Table assignment does not alter bookings or claim that a table is free for a reservation.

Checkout recalculates prices from MySQL, uses integer cents, stores price/name snapshots, and uses a unique request key to prevent double-submission sales. Dine-in checkout requires an available table in an open park and an active assigned waiter. Cash requires sufficient tendered money; other payment methods require a reference. This records received funds and does not initiate electronic payments.

By default receipts use `KES` and zero added tax. Set the environment variables `POS_CURRENCY` (three uppercase letters) and `POS_TAX_BPS` (basis points, for example `1600` for 16%) before starting PHP to change those settings. Each sale retains its original currency and tax rate. Price changes do not change saved receipts. Refunds are recorded separately at the date of refund; paid sales are not deleted.

Accounting totals include POS sales, recorded refunds, expenses, and paid staff allocations. They are a cash movement report, not double-entry financial statements or profit calculations. Reservation payments remain separate. Existing expense/payroll records have no currency column and use the configured reporting currency; do not change currency for those records without a data migration. Mixed-currency sales remain listed with their own currency and are not combined in the summary.

Run `php scripts/setup-database.php` to install cashier storage, or `php scripts/migrate-cashier.php` on an existing installation with the earlier tables.


Cashier validation: real HTTP/MySQL checks passed for cashier login and access boundaries, table/waiter assignment and staff dashboard visibility, cash received/change, database-derived prices, repeated-submission protection, stored receipt snapshots after menu price changes, non-cash reference requirements, admin-only full-refund records, CSV accounting entries, and the cashier audit. Test records were removed. Browser print-dialog and visual checks have not been performed.
