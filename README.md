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
