# Lab 7 Peer Tutoring Hub: User Management (UM-01 to UM-10)

Lab 7 extends the Lab 6 Peer Tutoring Hub with user accounts, login and logout, profile management, administrator account management, and protected tutoring request workflows. The Lab 6 three-tier structure is unchanged:

```
Browser -> php/ page (HTML + guards) -> services/ (validation, rules, transactions) -> repositories/ (SQL) -> SQLite
```

Requires PHP 8 with the PDO SQLite and mbstring extensions. Verified on PHP 8.3.6 in the build sandbox; rerun in Codespaces for your evidence.

## Run in GitHub Codespaces (matches the Lab 7 instructions)

```
cd lab7/database
sqlite3 tutoring.db < setup-database.sql                 # Lab 6 structure
sqlite3 tutoring.db < upgrade-to-user-management.sql     # adds users + students.user_id
php create-admin.php "Test Administrator" admin@example.com "YourTestPassword"
cd ..
php -S 0.0.0.0:8000
```

Run the upgrade script once. Running it a second time fails with "duplicate column name: user_id", which is harmless.
No sqlite3? Use `php database/init-database.php` instead; it is safe to rerun.
The optional `php -S 0.0.0.0:8000 php/router.php` also blocks browser access to `/database`, `/config`, `/includes`, `/repositories` and `/services`. The test files refuse to run from a browser either way.

## Run the tests

All suites use disposable databases and never touch `database/tutoring.db`.

| Command | What it covers |
|---|---|
| `php tests/validation-tests.php` | Lab 4 validation helpers (10 checks, HTML output) |
| `php tests/user-management-tests.php` | UM-01 to UM-10 service rules and authorization (78 checks) |
| `php tests/integration-tests.php` | Lab 6 UC01/UC02 regression + Lab 7 request access rules (34 checks) |
| `php tests/http-smoke-tests.php` | Extra: end to end over HTTP for sessions, cookies, CSRF, roles, logout (54 checks) |
| `php tests/user-management-tests.php --prove-harness` | Controlled negative test; must report 1 FAIL and exit 1 |

## Who can do what (Authentication Reading Handout, section 13)

| Action | Student | Tutor | Administrator |
|---|---|---|---|
| Register (UM-01) | Yes | Yes | No (CLI `create-admin.php` only) |
| View/edit own profile, change password | Yes | Yes | Yes |
| Submit tutoring request | Yes | No | No |
| View tutoring requests | Own only | No | All |
| Edit or cancel an Open request | Own only | No | Any |
| Manage users and roles | No | No | Yes, not on own account |

A student who opens another student's request gets **404 Not Found**, because the ownership check is part of the SQL query (`s.user_id = ?`, handout section 14).

## Files added or changed

**New**
- `repositories/userPDO.php`: USER table SQL
- `services/userService.php`: UM-01 to UM-10 validation, business rules, authorization re-checks
- `includes/auth.php`: secure session start, login/logout lifecycle, timeouts, CSRF tokens, `requireLogin()` / `requireRole()` guards, flash messages
- `includes/html.php`: `escapeHtml()` (moved out of requestService.php so all pages share it)
- `php/register.php` (UM-01), `php/login.php` (UM-09), `php/logout.php` (UM-10), `php/dashboard.php`
- `php/profile.php` (UM-02), `php/edit-profile.php` (UM-04), `php/change-password.php` (UM-05)
- `php/admin-users.php`, `php/admin-user-details.php` (UM-03), `php/admin-update-role.php` (UM-06), `php/admin-account-status.php` (UM-07), `php/admin-delete-user.php` (UM-08)
- `database/upgrade-to-user-management.sql`: creates `users`, adds `students.user_id` (UNIQUE, FK to users)
- `database/init-database.php`, `database/create-admin.php`
- `tests/user-management-tests.php`, `tests/http-smoke-tests.php`
- `TRACEABILITY.md`: requirements traceability audit

**Changed**
- `database/setup-database.sql`: still the Lab 6 structure; the seed request insert is now safe to rerun
- `config/db.php`: optional `PTH_DB_PATH` override so HTTP tests can use a throwaway database
- `repositories/studentPDO.php`, `repositories/requestPDO.php`: new functions only, existing ones unchanged (`listRequests` gained an optional 4th parameter with a backward-compatible default)
- `services/requestService.php`: adds role and ownership functions. The Lab 6 functions are unchanged.
- All Lab 6 request pages: login, role, ownership and CSRF checks added. Lab 4 controls (course dropdown, topic checkboxes, date, time radios, reason textarea) are preserved. Student name/email fields remain but are read only and filled from the account.
- `includes/nav.php`: role-aware links and a POST logout button. `includes/header.php`: per-page title.
- `php/router.php`: now also blocks `/config`, `/includes`, `/repositories`, `/services`, `/tests`
- `index.php`: goes to the dashboard (which sends anonymous users to login)
- `tests/integration-tests.php`: Lab 6 checks kept, made relative to the seed rows (3 of them failed in Lab 6 for that reason), plus 15 Lab 7 checks
- `css/styles.css`: `.app-form`, `.inline-form`, `.detail-card` styles for the new screens

## Security controls implemented

- **Password hashing:** `password_hash()` with `PASSWORD_DEFAULT`, `password_verify()`, automatic rehash on login. Confirmation and current passwords are never stored.
- **Login:** the same generic "Invalid email or password" message for an unknown email, an inactive account or a wrong password (handout section 7). A dummy hash check for unknown emails evens out timing. Hashes are upgraded with `password_needs_rehash`.
- **Sessions:** strict mode, cookies only, HttpOnly, SameSite=Lax, Secure when served over HTTPS (including Codespaces forwarding). ID regenerated at login and after a password change. The session stores only user_id, display_name, role and activity times, refreshed from SQLite on every request. 20 minute idle timeout (handout section 10) and 8 hour absolute timeout.
- **Logout:** POST with CSRF token. Clears session data, expires the cookie, destroys the server session. The old cookie cannot be replayed (tested).
- **Authorization:** checked on the server for every page and again inside the service layer. The user is reloaded from the database on every request, so deactivation and role changes take effect immediately.
- **CSRF:** a per-session token checked with `hash_equals()` on every POST, including login and logout.
- **Other:** prepared statements everywhere, LIKE wildcards escaped in search, output encoded with `escapeHtml()`, `X-Frame-Options`, `nosniff`, `Cache-Control: no-store`.

## Design decisions (aligned with the Authentication Reading Handout)

1. **USER to STUDENTS link:** `students.user_id` (UNIQUE, FK), per handout section 2. A Student registration creates the linked `students` row in the same transaction. A Lab 6 student row with the same email and no account yet is linked instead of duplicated. Administrator and Tutor accounts have no `students` row.
2. **Deletion policy (UM-08):** an account with any related tutoring requests cannot be deleted; deactivate it instead. Nothing cascades.
3. **Password rule:** minimum 12 characters (per the Lab 7 instructions), maximum 72 bytes, confirmation must match, and a new password must differ from the current one.
4. **Proposed rules implemented:** unique email, admin-only role and status changes, inactive users cannot log in, the last active administrator is protected, and admins cannot change their own role or status or delete themselves.

## Known limitations (classroom build, not for public deployment)

- No login rate limiting or account lockout
- No email verification. Registering with the email of an unlinked Lab 6 student row (for example the seeded Maria Lopez) claims that row's request history.
- Changing a password does not end the user's other active sessions
- No password reset ("forgot password")
- No audit log of admin actions
- Bootstrap CSS/JS load from a CDN without Subresource Integrity hashes
- Use fictional data only
