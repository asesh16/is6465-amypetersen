<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // command line only (the lab server runs without the router)
// UM-01 through UM-10 service-level integration tests.
// Expected results come from the User Management Design Document acceptance tests and
// business rules, not from the implementation. In-memory SQLite only.
//   php tests/user-management-tests.php
require_once dirname(__DIR__) . '/services/userService.php';
require_once dirname(__DIR__) . '/services/requestService.php';

$passed = 0;
$failed = 0;
function check(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS: $label" . PHP_EOL; }
    else { $failed++; echo "FAIL: $label" . PHP_EOL; }
}
// Runs $fn and passes only if it throws $class (optionally with a message fragment).
function expectThrows(string $class, callable $fn, string $label, string $fragment = ''): void {
    try {
        $fn();
        check(false, $label . ' (no exception)');
    } catch (Throwable $e) {
        check($e instanceof $class && ($fragment === '' || stripos($e->getMessage(), $fragment) !== false),
            $label . ($e instanceof $class ? '' : ' (got ' . get_class($e) . ': ' . $e->getMessage() . ')'));
    }
}
function userRow($pdo, $id) {
    $s = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
    $s->execute([$id]);
    return $s->fetch();
}
function userCount($pdo) { return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); }

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/setup-database.sql'));
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/upgrade-to-user-management.sql'));
} catch (Throwable $e) {
    echo 'SETUP FAILED: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

function reg($overrides = []) {
    return array_merge(['full_name' => 'Alex Morgan', 'email' => 'alex@example.edu', 'password' => 'CorrectHorse1',
        'confirm_password' => 'CorrectHorse1', 'role' => 'Student'], $overrides);
}

// ======================= UM-01 Create User Account =======================
$alexId = registerUser($pdo, reg());
$alex = userRow($pdo, $alexId);
check($alex && $alex['status'] === 'Active' && $alex['role'] === 'Student', 'UM-01-T1 Valid Student registration creates Active user');
check($alex['password_hash'] !== 'CorrectHorse1' && password_verify('CorrectHorse1', $alex['password_hash']), 'UM-01-T2 Password stored only as a verifiable hash');
check(!array_key_exists('confirm_password', $alex), 'UM-01-T3 Confirmation password not stored');
$taylorId = registerUser($pdo, reg(['full_name' => 'Taylor Lee', 'email' => 'Taylor@Example.edu', 'role' => 'Tutor']));
$taylor = userRow($pdo, $taylorId);
check($taylor['role'] === 'Tutor' && $taylor['status'] === 'Active', 'UM-01-T4 Valid Tutor registration creates Active user');
check($taylor['email'] === 'taylor@example.edu', 'UM-01-T5 Email normalized to lowercase');
$alexStudent = findStudentByUserId($pdo, $alexId);
check($alexStudent && $alexStudent['student_email'] === 'alex@example.edu', 'UM-01-T5a Student registration creates linked students row (users + students)');
check(findStudentByUserId($pdo, $taylorId) === false, 'UM-01-T5b Tutor registration creates no students row');
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg(['email' => 'eleven@example.edu', 'password' => 'Elevenchars', 'confirm_password' => 'Elevenchars'])); }, 'UM-01-T5c 11-character password rejected (12 minimum)', 'at least 12');

$n = userCount($pdo);
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg()); }, 'UM-01-T6 Duplicate email rejected', 'already exists');
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg(['email' => 'ALEX@example.edu'])); }, 'UM-01-T7 Duplicate email rejected regardless of case');
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg(['email' => 'boss@example.edu', 'role' => 'Administrator'])); }, 'UM-01-T8 Public registration cannot assign Administrator', 'Student or Tutor');
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg(['email' => 'x@example.edu', 'confirm_password' => 'Different1'])); }, 'UM-01-T9 Password mismatch rejected', 'do not match');
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg(['email' => 'y@example.edu', 'password' => 'short', 'confirm_password' => 'short'])); }, 'UM-01-T10 Short password rejected');
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg(['email' => 'not-an-email'])); }, 'UM-01-T11 Invalid email rejected');
expectThrows('UserValidationException', function () use ($pdo) { registerUser($pdo, reg(['email' => 'z@example.edu', 'full_name' => '   '])); }, 'UM-01-T12 Missing name rejected');
check(userCount($pdo) === $n, 'UM-01-T13 Failed registrations created no accounts');
$quoteId = registerUser($pdo, reg(['full_name' => "Pat O'Brien <script>", 'email' => 'pat@example.edu']));
check(userRow($pdo, $quoteId)['full_name'] === "Pat O'Brien <script>", 'UM-01-T14 Quotes and special characters stored exactly (encoded on output)');

// Administrator bootstrap (outside public registration)
$adminId = bootstrapAdministrator($pdo, 'Admin One', 'admin1@example.edu', 'AdminPass123');
check(userRow($pdo, $adminId)['role'] === 'Administrator', 'UM-SETUP Administrator created through CLI bootstrap only');
check(findStudentByUserId($pdo, $adminId) === false, 'UM-SETUP Administrator has no students row');

// ======================= UM-09 Log In =======================
$session = authenticateUser($pdo, 'alex@example.edu', 'CorrectHorse1');
check((int)$session['user_id'] === $alexId && !isset($session['password_hash']), 'UM-09-T1 Valid login returns account without hash');
check((int)authenticateUser($pdo, '  ALEX@example.edu ', 'CorrectHorse1')['user_id'] === $alexId, 'UM-09-T2 Login email is case-insensitive');
expectThrows('UserValidationException', function () use ($pdo) { authenticateUser($pdo, 'alex@example.edu', 'WrongPass1'); }, 'UM-09-T3 Wrong password rejected', 'Invalid email or password');
expectThrows('UserValidationException', function () use ($pdo) { authenticateUser($pdo, 'nobody@example.edu', 'CorrectHorse1'); }, 'UM-09-T4 Unknown email gives the same generic message', 'Invalid email or password');

// ======================= UM-02 View Own Account =======================
$own = getOwnAccount($pdo, $alexId);
check($own['email'] === 'alex@example.edu' && $own['role'] === 'Student' && $own['status'] === 'Active', 'UM-02-T1 Own profile shows name, email, role and status');
check(!isset($own['password_hash']), 'UM-02-T2 Profile data excludes password hash');
expectThrows('AuthorizationException', function () use ($pdo) { getOwnAccount($pdo, 9999); }, 'UM-02-T3 Missing or expired session account denied');

// ======================= UM-04 Update Own Account =======================
// Give Alex a request history so the STUDENTS link can be checked.
$reqInput = ['course' => 'IS 6465', 'reason' => 'Help me with sessions in PHP.', 'topics' => ['Study plan'],
    'preferreddate' => (new DateTimeImmutable('+5 days'))->format('Y-m-d'), 'preferredtime' => 'Evening'];
$alexReq = submitRequestForUser($pdo, findUserById($pdo, $alexId), $reqInput);
$updated = updateOwnAccount($pdo, $alexId, ['full_name' => 'Alex M. Morgan', 'email' => 'alex.morgan@example.edu', 'role' => 'Administrator', 'status' => 'Inactive']);
check($updated['full_name'] === 'Alex M. Morgan' && $updated['email'] === 'alex.morgan@example.edu', 'UM-04-T1 Name and email update succeeds');
check($updated['role'] === 'Student' && $updated['status'] === 'Active', 'UM-04-T2 Role and status modification through profile denied');
$req = findRequest($pdo, $alexReq);
check($req['student_email'] === 'alex.morgan@example.edu' && $req['student_name'] === 'Alex M. Morgan' && (int)$req['student_user_id'] === $alexId, 'UM-04-T3 Linked STUDENTS row follows the name/email change');
check(canModifyRequest(findUserById($pdo, $alexId), $req), 'UM-04-T4 Ownership of existing requests kept after email change');
$snapshot = userRow($pdo, $alexId);
expectThrows('UserValidationException', function () use ($pdo, $alexId) { updateOwnAccount($pdo, $alexId, ['full_name' => 'Alex', 'email' => 'taylor@example.edu']); }, 'UM-04-T5 Duplicate email rejected', 'already in use');
expectThrows('UserValidationException', function () use ($pdo, $alexId) { updateOwnAccount($pdo, $alexId, ['full_name' => 'Alex', 'email' => 'maria.lopez@example.com']); }, 'UM-04-T6 Email owned by another student record rejected');
expectThrows('UserValidationException', function () use ($pdo, $alexId) { updateOwnAccount($pdo, $alexId, ['full_name' => 'Alex', 'email' => 'bad@']); }, 'UM-04-T7 Invalid email rejected');
check(userRow($pdo, $alexId) === $snapshot, 'UM-04-T8 Rejected updates changed nothing');

// ======================= UM-05 Change Password =======================
expectThrows('UserValidationException', function () use ($pdo, $alexId) { changePassword($pdo, $alexId, ['current_password' => 'Nope12345', 'new_password' => 'BrandNewPass123', 'confirm_password' => 'BrandNewPass123']); }, 'UM-05-T1 Incorrect current password rejected', 'incorrect');
expectThrows('UserValidationException', function () use ($pdo, $alexId) { changePassword($pdo, $alexId, ['current_password' => 'CorrectHorse1', 'new_password' => 'BrandNewPass123', 'confirm_password' => 'BrandNewPass124']); }, 'UM-05-T2 New password mismatch rejected', 'do not match');
check(userRow($pdo, $alexId)['password_hash'] === $snapshot['password_hash'], 'UM-05-T3 Failed attempts left hash unchanged');
check(changePassword($pdo, $alexId, ['current_password' => 'CorrectHorse1', 'new_password' => 'BrandNewPass123', 'confirm_password' => 'BrandNewPass123']), 'UM-05-T4 Valid change succeeds');
check((int)authenticateUser($pdo, 'alex.morgan@example.edu', 'BrandNewPass123')['user_id'] === $alexId, 'UM-05-T5 New password works');
expectThrows('UserValidationException', function () use ($pdo) { authenticateUser($pdo, 'alex.morgan@example.edu', 'CorrectHorse1'); }, 'UM-05-T6 Old password fails');

// ======================= UM-03 View and Search User Accounts =======================
$all = searchUsers($pdo, $adminId, []);
check(count($all) === userCount($pdo), 'UM-03-T1 Admin sees all accounts with no filters');
$hits = searchUsers($pdo, $adminId, ['q' => 'taylor']);
check(count($hits) === 1 && $hits[0]['email'] === 'taylor@example.edu', 'UM-03-T2 Admin search returns matching accounts');
$tutors = searchUsers($pdo, $adminId, ['role' => 'Tutor', 'status' => 'Active']);
check(count($tutors) === 1 && $tutors[0]['role'] === 'Tutor', 'UM-03-T3 Role and status filters combine');
check(searchUsers($pdo, $adminId, ['q' => 'zzz-no-match']) === [], 'UM-03-T4 No matches returns empty list');
check(searchUsers($pdo, $adminId, ['q' => '%']) === [], 'UM-03-T5 LIKE wildcard treated literally');
check(!isset($all[0]['password_hash']), 'UM-03-T6 Search results exclude password hashes');
expectThrows('AuthorizationException', function () use ($pdo, $alexId) { searchUsers($pdo, $alexId, []); }, 'UM-03-T7 Student denied user search');
expectThrows('AuthorizationException', function () use ($pdo, $taylorId) { getUserForAdministrator($pdo, $taylorId, 1); }, 'UM-03-T8 Tutor denied account details');
expectThrows('UserValidationException', function () use ($pdo, $adminId) { searchUsers($pdo, $adminId, ['role' => 'Hacker']); }, 'UM-03-T9 Invalid filter value rejected');
$detail = getUserForAdministrator($pdo, $adminId, $alexId);
check((int)$detail['related_request_count'] === 1, 'UM-03-T10 Account details include related request count');

// ======================= UM-06 Update User Role =======================
$taylorBefore = userRow($pdo, $taylorId);
expectThrows('AuthorizationException', function () use ($pdo, $alexId, $taylorId) { updateUserRole($pdo, $alexId, $taylorId, 'Administrator'); }, 'UM-06-T1 Unauthorized role change denied (Student actor)');
expectThrows('AuthorizationException', function () use ($pdo, $taylorId, $alexId) { updateUserRole($pdo, $taylorId, $alexId, 'Tutor'); }, 'UM-06-T2 Unauthorized role change denied (Tutor actor)');
check(userRow($pdo, $taylorId) === $taylorBefore, 'UM-06-T3 Denied change made no update');
expectThrows('UserValidationException', function () use ($pdo, $adminId, $taylorId) { updateUserRole($pdo, $adminId, $taylorId, 'Superuser'); }, 'UM-06-T4 Invalid role value rejected');
expectThrows('PolicyException', function () use ($pdo, $adminId) { updateUserRole($pdo, $adminId, $adminId, 'Student'); }, 'UM-06-T5 Admin cannot change own role');
check(updateUserRole($pdo, $adminId, $taylorId, 'Administrator') && userRow($pdo, $taylorId)['role'] === 'Administrator', 'UM-06-T6 Admin promotes Tutor to Administrator');
check(updateUserRole($pdo, $adminId, $taylorId, 'Tutor') && userRow($pdo, $taylorId)['role'] === 'Tutor', 'UM-06-T7 Admin changes role back to Tutor');
expectThrows('PolicyException', function () use ($pdo, $adminId, $taylorId) { updateUserRole($pdo, $adminId, $taylorId, 'Tutor'); }, 'UM-06-T8 Same-role change reported as no change');

// ======================= UM-07 Activate or Deactivate Account =======================
expectThrows('AuthorizationException', function () use ($pdo, $taylorId, $alexId) { updateUserStatus($pdo, $taylorId, $alexId, 'Inactive'); }, 'UM-07-T1 Non-admin status change denied');
check(updateUserStatus($pdo, $adminId, $alexId, 'Inactive') && userRow($pdo, $alexId)['status'] === 'Inactive', 'UM-07-T2 Admin deactivates account');
expectThrows('UserValidationException', function () use ($pdo) { authenticateUser($pdo, 'alex.morgan@example.edu', 'BrandNewPass123'); }, 'UM-07-T3 Inactive user cannot log in (generic message)', 'Invalid email or password');
expectThrows('AuthorizationException', function () use ($pdo, $alexId) { searchUsers($pdo, $alexId, []); }, 'UM-07-T4 Inactive account has no admin authority');
expectThrows('PolicyException', function () use ($pdo, $adminId, $alexId) { updateUserStatus($pdo, $adminId, $alexId, 'Inactive'); }, 'UM-07-T5 Invalid transition (already Inactive) rejected', 'Invalid transition');
expectThrows('PolicyException', function () use ($pdo, $adminId) { updateUserStatus($pdo, $adminId, $adminId, 'Inactive'); }, 'UM-07-T6 Admin cannot deactivate self');
check(updateUserStatus($pdo, $adminId, $alexId, 'Active') && (int)authenticateUser($pdo, 'alex.morgan@example.edu', 'BrandNewPass123')['user_id'] === $alexId, 'UM-07-T7 Reactivated user can log in again');
// Last active administrator rule: make a second admin, deactivate the first through the second,
// then the second (now the only active admin) must be protected.
$admin2Id = bootstrapAdministrator($pdo, 'Admin Two', 'admin2@example.edu', 'AdminPass456');
check(updateUserStatus($pdo, $admin2Id, $adminId, 'Inactive'), 'UM-07-T8 Admin can deactivate another admin when one remains');
check(countActiveAdministrators($pdo) === 1, 'UM-07-T9 Exactly one active administrator remains');
expectThrows('AuthorizationException', function () use ($pdo, $adminId, $admin2Id) { updateUserStatus($pdo, $adminId, $admin2Id, 'Inactive'); }, 'UM-07-T10 Deactivated admin loses authority');
updateUserStatus($pdo, $admin2Id, $adminId, 'Active');

// ======================= UM-08 Delete User Account =======================
$jordanId = registerUser($pdo, reg(['full_name' => 'Jordan Kim', 'email' => 'jordan.kim@example.edu']));
$usersBefore = userCount($pdo);
$requestsBefore = (int)$pdo->query('SELECT COUNT(*) FROM tutoring_requests')->fetchColumn();
expectThrows('AuthorizationException', function () use ($pdo, $taylorId, $jordanId) { deleteUserAccount($pdo, $taylorId, $jordanId); }, 'UM-08-T1 Non-admin delete denied');
expectThrows('PolicyException', function () use ($pdo, $adminId, $alexId) { deleteUserAccount($pdo, $adminId, $alexId); }, 'UM-08-T2 Deletion blocked by related tutoring records', 'blocked');
check(userCount($pdo) === $usersBefore && (int)$pdo->query('SELECT COUNT(*) FROM tutoring_requests')->fetchColumn() === $requestsBefore, 'UM-08-T3 Blocked delete removed no users or requests');
check(findRequest($pdo, $alexReq) !== false, 'UM-08-T4 Dependent tutoring request retained');
expectThrows('PolicyException', function () use ($pdo, $adminId) { deleteUserAccount($pdo, $adminId, $adminId); }, 'UM-08-T5 Admin cannot delete self');
expectThrows('PolicyException', function () use ($pdo, $adminId) { deleteUserAccount($pdo, $adminId, 99999); }, 'UM-08-T6 Unknown account reported', 'not found');
check(deleteUserAccount($pdo, $adminId, $jordanId) && findUserById($pdo, $jordanId) === false, 'UM-08-T7 Account with no related records deleted');
check(findStudentByEmail($pdo, 'jordan.kim@example.edu') === false, 'UM-08-T7a Empty linked students row removed with it');
check(userCount($pdo) === $usersBefore - 1, 'UM-08-T8 Exactly one account removed');

// ======================= UM-10 Log Out (service-level part) =======================
// Session invalidation is browser/session behavior; see tests/http-smoke-tests.php for the
// end-to-end "protected access denied after logout" evidence.
updateUserStatus($pdo, $adminId, $quoteId, 'Inactive');
check(findUserById($pdo, $quoteId)['status'] === 'Inactive', 'UM-10-T1 currentUser() source data reflects deactivation immediately');

// Controlled negative test: proves the harness reports a failure when expectations are wrong.
if (in_array('--prove-harness', $argv ?? [], true)) {
    check(false, 'CONTROL Intentional failure to prove the harness detects failures');
}

echo "Results: $passed passed, $failed failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
