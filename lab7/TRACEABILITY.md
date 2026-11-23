# Lab 7 Requirements Traceability Audit: User Management

## 1. Scope and inspected materials

- **Requirements baseline:** User Management CRUD Design Package v1.0 (UM-01 to UM-10, WF-01 to WF-08, USER data dictionary, business rules, acceptance tests)
- **Guidance:** Application Builder, Test Harness Generator, Traceability Auditor skill documents
- **Code:** the complete Lab 7 package, `database/setup-database.sql`, `database/upgrade-to-user-management.sql`
- **Security baseline:** IS 6465 Authentication Reading Handout v1.2 (16 Nov 2026) and the Lab 7 assignment page
- **Evidence:** executed on PHP 8.3.6 CLI and the PHP built-in server in the build sandbox, 18 Nov 2026. These runs are not yet repeated in GitHub Codespaces.

| Suite | Result |
|---|---|
| `tests/integration-tests.php` | 34 passed, 0 failed |
| `tests/user-management-tests.php` | 78 passed, 0 failed |
| `tests/http-smoke-tests.php` | 54 passed, 0 failed |
| `tests/user-management-tests.php --prove-harness` | 78 passed, 1 failed, exit 1 (harness detects failure as expected) |
| `tests/validation-tests.php` | 10 PASS, 0 FAIL |
| `php -l` on all PHP files | No syntax errors |

**Status labels:** Verified = executed test evidence supports it. Implemented, unverified = code exists, no executed test. Partial = some behavior missing.

## 2. Overall assessment

All ten use cases are implemented and supported by executed tests. The three-tier boundaries hold: SQL appears only in `repositories/`, rules and transactions only in `services/`, and HTML only in `php/` pages. The USER/STUDENTS link and request access rules now follow the reading handout. The deletion policy is still an open decision in the design document.

## 3. Forward traceability matrix

| ID | Requirement | Implementation evidence | Test evidence | Status | Gap or action |
|---|---|---|---|---|---|
| UM-01 | Student/Tutor self-registration creates an Active account; validated input, unique email, matching passwords, allowed role, hash stored | `php/register.php` → `registerUser()` (transaction), `validateRegistration()`, `validateNewPassword()`, `selfRegistrationRoles()` → `insertUser()` + `insertStudentForUser()`/`linkStudentToUser()`; `users` CHECK constraints | UM-01-T1 to T14, T5a to T5c, H-09 to H-11 | Verified | None |
| UM-02 | View own name, email, role, status; expired session denied | `php/profile.php` (no ID parameter) → `getOwnAccount()` → `findUserById()` (no hash column); `requireLogin()`, `enforceSessionTimeouts()` | UM-02-T1 to T3, H-18, H-01 | Verified | Idle/absolute timeouts were only checked ad hoc on the command line; no automated test |
| UM-03 | Admin lists, searches, filters and opens accounts; unauthorized denied; empty result handled | `php/admin-users.php`, `php/admin-user-details.php` → `searchUsers()`, `validateUserSearch()`, `getUserForAdministrator()` → `searchUserAccounts()` (escaped LIKE) | UM-03-T1 to T10, H-02, H-16, H-34, H-35 | Verified | None |
| UM-04 | Edit own name/email; ownership and fields validated; role/status not editable; duplicate rejected | `php/edit-profile.php` → `updateOwnAccount()` (reads only full_name/email) → `updateUserProfile()`, `updateStudentIdentityByUserId()` in one transaction | UM-04-T1 to T8, H-19, H-20 | Verified | None |
| UM-05 | Verify current password, validate new one, store new hash | `php/change-password.php` → `changePassword()` → `findUserCredentialsById()`, `updateUserPasswordHash()`; `session_regenerate_id()` | UM-05-T1 to T6, H-30 to H-32 | Verified | Other sessions stay active (limitation) |
| UM-06 | Admin changes role; unauthorized or prohibited change denied | `php/admin-update-role.php` → `updateUserRole()` (re-reads actor, self-change block, last-admin rule, transaction) | UM-06-T1 to T8, H-17, H-41 | Verified | The last-admin branch can't be reached on its own because the self-change block runs first; it remains as a second line of defense |
| UM-07 | Admin activates/deactivates with confirmation; invalid transition rejected; inactive user cannot log in | `php/admin-account-status.php` (confirm screen + POST) → `updateUserStatus()`; `authenticateUser()` status check; `currentUser()` signs out inactive users | UM-07-T1 to T10, H-36, H-37, H-38 | Verified | None |
| UM-08 | Warn about related records; recheck on confirm; delete only if policy allows, otherwise explain | `php/admin-delete-user.php` → `deleteUserAccount()` → `countRequestsByUserId()`, `deleteStudentWithoutRequestsByUserId()`, `deleteUserById()` (transaction, no cascade) | UM-08-T1 to T8, T7a, H-39, H-40 | Verified (interim policy) | Permanent deletion and retention policy is an open decision |
| UM-09 | Verify hash and Active status; regenerate session ID; land on dashboard | `php/login.php` → `authenticateUser()` → `establishUserSession()`; `php/dashboard.php` | UM-09-T1 to T4, H-12 to H-15, H-06 to H-08 | Verified | No rate limiting (limitation) |
| UM-10 | Invalidate session and cookie; redirect to login; protected access denied afterwards; already-expired handled | `php/logout.php` (POST + CSRF) → `restartFreshSession()` → `endSession()` | H-42 to H-46, UM-10-T1 | Verified | None |
| UC01/UC02 (regression) | Lab 6 request create, revise, cancel and filter keep working | Lab 6 functions unchanged; new `*ForUser()` wrappers | 14 Lab 6 checks in integration-tests | Verified | None |
| Protected requests (handout section 13) | Student: own requests. Tutor: none. Admin: all, edit/cancel any Open | `requireRole(['Student','Administrator'])` in 6 request pages; `findRequestForUser()` → `findRequestForStudentUser()` (ownership in SQL); `submitRequestForUser()`, `listRequestsForUser()` | L7-R01 to R20, H-21 to H-29, H-35a to c | Verified | None |

## 4. Backward traceability (features not explicitly in the design)

| Feature | Justification | Classification |
|---|---|---|
| `database/create-admin.php` CLI bootstrap | Needed because public registration cannot create Administrators, so the first admin has to come from somewhere | Informational; needs sign-off |
| `database/init-database.php` | Lets the Lab 6 database be upgraded without sqlite3 installed | Informational |
| `php/dashboard.php` | UM-09 step 3 says "Views dashboard" | Justified |
| Admins cannot change their own role/status or delete themselves | Extends the proposed "last active administrator" rule to prevent self-lockout | Minor; needs sign-off |
| Session idle and absolute timeouts | UM-02 failure outcome "expired session" | Justified |
| Security headers, router blocking of internal folders | Security requirement: "session protections" plus defense in depth | Informational |
| `PTH_DB_PATH` environment override | Test isolation (Test Harness Generator: never use the working database) | Informational |
| Idempotent seed insert in setup SQL | Fixes a Lab 6 bug where rerunning the script duplicated the seed request | Minor fix |

## 5. Architecture and database findings

| Severity | Finding | Business impact |
|---|---|---|
| Informational | `users` and `students.user_id` match handout section 2 and are added by `upgrade-to-user-management.sql`, so `setup-database.sql` stays the Lab 6 structure. Existing Lab 6 students start with user_id NULL. | Part 10's LEFT JOIN on `s.user_id` works as written. |
| Minor | Lab 6 `integration-tests.php` failed 3 of 14 checks before any Lab 7 change, because absolute counts did not account for the seed rows. The checks are now measured relative to the seed baseline. The application code was not at fault. | Earlier Lab 6 evidence understated passing behavior. |
| Informational | `escapeHtml()` moved to `includes/html.php`. `requestService.php` requires it, so existing callers are unaffected. | None |
| Informational | No SQL in pages, no HTML in repositories, and transactions are only in services (`updateOwnAccount`, `updateUserRole`, `updateUserStatus`, `deleteUserAccount`, `submitRequest`). | None |

## 6. Security limitations (remaining)

| Severity | Limitation |
|---|---|
| Major | No login rate limiting or lockout, so brute-force attempts are possible |
| Major | No email ownership verification. A registrant can claim an unlinked Lab 6 student row that has the same email |
| Minor | A password change does not end the user's other sessions |
| Minor | No password reset flow and no admin audit log |
| Minor | CDN assets have no Subresource Integrity hashes |

## 7. Test evidence assessment

- All status labels above rest on suites that were actually executed, with the results listed in section 1.
- Tests call the real service and repository functions. The HTTP suite exercises the real pages through the built-in server, with a disposable database.
- Expected values come from the design document: Administrator registration denied, old password fails, inactive user cannot log in, protected access denied after logout, delete blocked by related records.
- Gaps: idle/absolute timeout expiry has no automated test (it needs a clock override); the suites have not yet been rerun in Codespaces.

## 8. Prioritized corrective actions

1. Get stakeholder sign-off on the delete-blocked-if-requests policy (still open in the design document).
2. Rerun all suites in Codespaces and record the output as submission evidence.
3. Before any wider use: add login throttling and email verification.
4. Optional: add a test clock to `enforceSessionTimeouts()` so timeout expiry can be tested automatically.
