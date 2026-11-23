<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // command line only (the lab server runs without the router)
// End-to-end HTTP tests for sessions, CSRF, role based access and logout.
// Starts the PHP built-in server against a DISPOSABLE database file (PTH_DB_PATH),
// so database/tutoring.db is never touched.
//   php tests/http-smoke-tests.php
require_once dirname(__DIR__) . '/services/userService.php';

$passed = 0;
$failed = 0;
function check(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS: $label" . PHP_EOL; }
    else { $failed++; echo "FAIL: $label" . PHP_EOL; }
}

$root = dirname(__DIR__);
$port = 18000 + random_int(0, 999);
$base = "http://127.0.0.1:$port";
$dbFile = tempnam(sys_get_temp_dir(), 'pth_test_') . '.db';

// ---------- disposable database with one bootstrapped administrator ----------
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(file_get_contents($root . '/database/setup-database.sql'));
    $pdo->exec(file_get_contents($root . '/database/upgrade-to-user-management.sql'));
    bootstrapAdministrator($pdo, 'Admin One', 'admin@example.edu', 'AdminPass1234');
} catch (Throwable $e) {
    echo 'SETUP FAILED: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ---------- start the server ----------
$env = array_merge(getenv(), ['PTH_DB_PATH' => $dbFile]);
$cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", 'php/router.php'];
$server = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', getenv('PTH_SERVER_LOG') ?: '/dev/null', 'a']], $pipes, $root, $env);
if (!is_resource($server)) { echo 'SETUP FAILED: could not start server' . PHP_EOL; exit(1); }
register_shutdown_function(function () use ($server, $dbFile) {
    proc_terminate($server);
    proc_close($server);
    @unlink($dbFile);
});
$up = false;
for ($i = 0; $i < 50 && !$up; $i++) { usleep(100000); $up = @fsockopen('127.0.0.1', $port) !== false; }
if (!$up) { echo 'SETUP FAILED: server did not start' . PHP_EOL; exit(1); }

// ---------- tiny HTTP client with a per-browser cookie jar ----------
function http($method, $path, array &$jar, array $form = []) {
    global $base;
    $headers = [];
    if ($jar) {
        $pairs = [];
        foreach ($jar as $k => $v) $pairs[] = "$k=$v";
        $headers[] = 'Cookie: ' . implode('; ', $pairs);
    }
    $opts = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'header' => ''];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $opts['content'] = http_build_query($form);
    }
    $opts['header'] = implode("\r\n", $headers);
    $body = @file_get_contents($base . $path, false, stream_context_create(['http' => $opts]));
    $resHeaders = $http_response_header ?? [];
    preg_match('~HTTP/\S+ (\d+)~', $resHeaders[0] ?? '', $m);
    $res = ['status' => (int)($m[1] ?? 0), 'body' => (string)$body, 'location' => '', 'cookies' => []];
    foreach ($resHeaders as $h) {
        if (stripos($h, 'Location:') === 0) $res['location'] = trim(substr($h, 9));
        if (stripos($h, 'Set-Cookie:') === 0) {
            $raw = trim(substr($h, 11));
            $res['cookies'][] = $raw;
            [$pair] = explode(';', $raw, 2);
            [$name, $value] = array_map('trim', explode('=', $pair, 2));
            if ($value === '' || $value === 'deleted' || stripos($raw, 'expires=Thu, 01 Jan 1970') !== false || preg_match('~Max-Age=0~i', $raw)) {
                unset($jar[$name]);
            } else {
                $jar[$name] = $value;
            }
        }
    }
    return $res;
}
function csrfFrom($html) {
    return preg_match('~name="csrf_token" value="([a-f0-9]{64})"~', $html, $m) ? $m[1] : '';
}
function loginAs(array &$jar, $email, $password) {
    $page = http('GET', '/php/login.php', $jar);
    return http('POST', '/php/login.php', $jar, ['csrf_token' => csrfFrom($page['body']), 'email' => $email, 'password' => $password]);
}
function registerAs(array &$jar, $name, $email, $password, $role) {
    $page = http('GET', '/php/register.php', $jar);
    return http('POST', '/php/register.php', $jar, ['csrf_token' => csrfFrom($page['body']), 'full_name' => $name,
        'email' => $email, 'password' => $password, 'confirm_password' => $password, 'role' => $role]);
}
function userByEmail($pdo, $email) {
    $s = $pdo->prepare('SELECT * FROM users WHERE email = ?'); $s->execute([$email]); return $s->fetch();
}

// ======================= Anonymous access =======================
$anon = [];
$r = http('GET', '/php/requests.php', $anon);
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-01 Protected page redirects anonymous user to login');
$r = http('GET', '/php/admin-users.php', $anon);
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-02 Admin page redirects anonymous user to login');
foreach (['/services/userService.php', '/includes/auth.php', '/tests/user-management-tests.php', '/database/tutoring.db', '/config/db.php'] as $p) {
    $r = http('GET', $p, $anon);
    check($r['status'] === 404, "H-03 Router blocks internal path $p");
}

// ======================= Session cookie and CSRF =======================
$a = [];
$page = http('GET', '/php/login.php', $a);
$cookieHeader = implode(' | ', $page['cookies']);
check($page['status'] === 200 && isset($a['PTH_SESSION']), 'H-04 Login page starts a session');
check(stripos($cookieHeader, 'HttpOnly') !== false && stripos($cookieHeader, 'SameSite=Lax') !== false, 'H-05 Session cookie is HttpOnly and SameSite=Lax');
$r = http('POST', '/php/login.php', $a, ['email' => 'admin@example.edu', 'password' => 'AdminPass1234']);
check($r['status'] === 403 && !isset($a['x']), 'H-06 Login POST without CSRF token rejected');
$r = http('POST', '/php/login.php', $a, ['csrf_token' => str_repeat('0', 64), 'email' => 'admin@example.edu', 'password' => 'AdminPass1234']);
check($r['status'] === 403, 'H-07 Login POST with forged CSRF token rejected');
$forged = ['PTH_SESSION' => 'attackerchosenid1234567890'];
http('GET', '/php/login.php', $forged);
check(($forged['PTH_SESSION'] ?? '') !== 'attackerchosenid1234567890', 'H-08 Strict mode rejects an attacker-chosen session ID');

// ======================= UM-01 via HTTP =======================
$s = [];
$r = registerAs($s, 'Mallory', 'mallory@example.edu', 'StudentPass123', 'Administrator');
check($r['status'] === 422 && userByEmail($pdo, 'mallory@example.edu') === false, 'H-09 Registration as Administrator denied, nothing stored');
$r = registerAs($s, 'Alex <b>Morgan</b>', 'alex@example.edu', 'StudentPass123', 'Student');
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-10 Student registration redirects to login with confirmation');
check(strpos(http('GET', '/php/login.php', $s)['body'], 'account was created') !== false, 'H-11 Confirmation message displayed');
registerAs($s, 'Casey Park', 'casey@example.edu', 'StudentPass456', 'Student');
registerAs($s, 'Taylor Lee', 'taylor@example.edu', 'TutorPass789', 'Tutor');

// ======================= UM-09 Log In =======================
$alex = [];
http('GET', '/php/login.php', $alex);
$preLoginId = $alex['PTH_SESSION'] ?? '';
$r = loginAs($alex, 'alex@example.edu', 'WrongPassword');
check($r['status'] === 401 && strpos($r['body'], 'Invalid email or password') !== false, 'H-12 Wrong password rejected with generic message');
$r = loginAs($alex, 'alex@example.edu', 'StudentPass123');
check($r['status'] === 303 && $r['location'] === 'dashboard.php', 'H-13 Valid login redirects to dashboard');
check(($alex['PTH_SESSION'] ?? '') !== '' && $alex['PTH_SESSION'] !== $preLoginId, 'H-14 Session ID regenerated at login');
$dash = http('GET', '/php/dashboard.php', $alex);
check($dash['status'] === 200 && strpos($dash['body'], 'Alex &lt;b&gt;Morgan&lt;/b&gt;') !== false, 'H-15 Dashboard shows user with HTML-encoded name');

// ======================= Role based access =======================
check(http('GET', '/php/admin-users.php', $alex)['status'] === 403, 'H-16 Student denied Manage Users');
check(http('GET', '/php/admin-update-role.php?user_id=1', $alex)['status'] === 403, 'H-17 Student denied Update Role page');

// ======================= UM-02 / UM-04 =======================
$profile = http('GET', '/php/profile.php?user_id=1', $alex);
check($profile['status'] === 200 && strpos($profile['body'], 'alex@example.edu') !== false && strpos($profile['body'], 'admin@example.edu') === false, 'H-18 Profile shows only own account even with a user_id in the URL');
$edit = http('GET', '/php/edit-profile.php', $alex);
$r = http('POST', '/php/edit-profile.php', $alex, ['full_name' => 'Alex Morgan', 'email' => 'alex@example.edu']);
check($r['status'] === 403, 'H-19 Profile update without CSRF token rejected');
$r = http('POST', '/php/edit-profile.php', $alex, ['csrf_token' => csrfFrom($edit['body']), 'full_name' => 'Alex Morgan', 'email' => 'alex@example.edu', 'role' => 'Administrator', 'status' => 'Active']);
$row = userByEmail($pdo, 'alex@example.edu');
check($r['status'] === 303 && $row['full_name'] === 'Alex Morgan' && $row['role'] === 'Student', 'H-20 Name updated; injected role field ignored');

// ======================= Protected request workflow =======================
$form = http('GET', '/php/request-form.php', $alex);
check($form['status'] === 200 && strpos($form['body'], 'value="alex@example.edu"') !== false, 'H-21 Request form pre-fills email from the account');
$r = http('POST', '/php/process-request.php', $alex, ['csrf_token' => csrfFrom($form['body']), 'form_name' => 'tutoring_request',
    'student_name' => 'Spoofed', 'student_email' => 'casey@example.edu', 'course' => 'IS 6465', 'reason' => 'Need help with PHP sessions.',
    'topics' => ['Study plan'], 'preferreddate' => (new DateTimeImmutable('+3 days'))->format('Y-m-d'), 'preferredtime' => 'Morning']);
preg_match('~request_id=(\d+)~', $r['location'], $m);
$alexRequest = (int)($m[1] ?? 0);
$owner = $pdo->query('SELECT s.student_email FROM tutoring_requests r JOIN students s ON r.student_id = s.student_id WHERE r.request_id = ' . $alexRequest)->fetchColumn();
check($r['status'] === 303 && $alexRequest > 0 && $owner === 'alex@example.edu', 'H-22 Request saved under the signed-in student, spoofed email ignored');
$r = http('POST', '/php/process-request.php', $alex, ['form_name' => 'tutoring_request', 'course' => 'IS 6465']);
check($r['status'] === 403, 'H-23 Request submission without CSRF token rejected');

$casey = [];
loginAs($casey, 'casey@example.edu', 'StudentPass456');
check(http('GET', "/php/request-details.php?request_id=$alexRequest", $casey)['status'] === 404, "H-24 Other student gets 404 for someone else's request");
$caseyPage = http('GET', '/php/dashboard.php', $casey);
$r = http('POST', '/php/delete-request.php', $casey, ['csrf_token' => csrfFrom($caseyPage['body']), 'request_id' => $alexRequest]);
$status = $pdo->query("SELECT status FROM tutoring_requests WHERE request_id = $alexRequest")->fetchColumn();
check($r['status'] === 404 && $status === 'Open', "H-25 Other student cannot cancel someone else's request");
$caseyList = http('GET', '/php/requests.php', $casey);
check(strpos($caseyList['body'], 'Need help with PHP sessions.') === false, 'H-26 Student request list excludes other students');

$taylor = [];
loginAs($taylor, 'taylor@example.edu', 'TutorPass789');
check(http('GET', "/php/request-details.php?request_id=$alexRequest", $taylor)['status'] === 403, 'H-27 Tutor denied request details');
check(http('GET', '/php/requests.php', $taylor)['status'] === 403, 'H-27a Tutor denied request list');
check(http('GET', '/php/request-form.php', $taylor)['status'] === 403, 'H-28 Tutor cannot open the student request form');
check(http('GET', "/php/edit-request.php?request_id=$alexRequest", $taylor)['status'] === 403, 'H-29 Tutor cannot edit a student request');

// ======================= UM-05 Change Password =======================
$cp = http('GET', '/php/change-password.php', $casey);
$r = http('POST', '/php/change-password.php', $casey, ['csrf_token' => csrfFrom($cp['body']), 'current_password' => 'StudentPass456', 'new_password' => 'NewPassword456', 'confirm_password' => 'NewPassword456']);
check($r['status'] === 303, 'H-30 Password change accepted');
$fresh = [];
check(loginAs($fresh, 'casey@example.edu', 'StudentPass456')['status'] === 401, 'H-31 Old password no longer works');
check(loginAs($fresh, 'casey@example.edu', 'NewPassword456')['status'] === 303, 'H-32 New password works');

// ======================= Administrator workflows =======================
$admin = [];
check(loginAs($admin, 'admin@example.edu', 'AdminPass1234')['status'] === 303, 'H-33 Administrator logs in');
$list = http('GET', '/php/admin-users.php?q=taylor&role=&status=', $admin);
check($list['status'] === 200 && strpos($list['body'], 'taylor@example.edu') !== false && strpos($list['body'], 'casey@example.edu') === false, 'H-34 Admin search returns matching account only');
check(http('GET', '/php/admin-users.php?role=Root', $admin)['status'] === 400, 'H-35 Invalid search filter rejected');
$all = http('GET', '/php/requests.php', $admin);
check($all['status'] === 200 && strpos($all['body'], 'Alex Morgan') !== false && strpos($all['body'], 'Maria Lopez') !== false, 'H-35a Admin sees requests from multiple students');
check(strpos(http('GET', "/php/request-details.php?request_id=$alexRequest", $admin)['body'], 'Edit') !== false, 'H-35b Admin can open and edit any Open request');
check(http('GET', '/php/request-form.php', $admin)['status'] === 403, 'H-35c Admin cannot submit a tutoring request');
$alexId = (int)userByEmail($pdo, 'alex@example.edu')['user_id'];
$statusPage = http('GET', "/php/admin-account-status.php?user_id=$alexId", $admin);
$r = http('POST', '/php/admin-account-status.php', $admin, ['csrf_token' => csrfFrom($statusPage['body']), 'user_id' => $alexId, 'status' => 'Inactive']);
check($r['status'] === 303 && userByEmail($pdo, 'alex@example.edu')['status'] === 'Inactive', 'H-36 Admin deactivates student');
$r = http('GET', '/php/requests.php', $alex);
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-37 Deactivated user is signed out on next request');
$r = loginAs($alex, 'alex@example.edu', 'StudentPass123');
check($r['status'] === 401 && strpos($r['body'], 'Invalid email or password') !== false, 'H-38 Inactive user cannot log in (generic message)');
$delPage = http('GET', "/php/admin-delete-user.php?user_id=$alexId", $admin);
check(strpos($delPage['body'], 'related tutoring request') !== false && strpos($delPage['body'], 'Confirm Delete') === false, 'H-39 Delete page warns and hides Confirm when records exist');
$r = http('POST', '/php/admin-delete-user.php', $admin, ['csrf_token' => csrfFrom($delPage['body']), 'user_id' => $alexId]);
check($r['status'] === 409 && userByEmail($pdo, 'alex@example.edu') !== false, 'H-40 Tampered delete POST still blocked by related records');
$rolePage = http('GET', '/php/admin-update-role.php?user_id=' . (int)userByEmail($pdo, 'admin@example.edu')['user_id'], $admin);
$r = http('POST', '/php/admin-update-role.php', $admin, ['csrf_token' => csrfFrom($rolePage['body']), 'user_id' => (int)userByEmail($pdo, 'admin@example.edu')['user_id'], 'role' => 'Student']);
check($r['status'] === 409 && userByEmail($pdo, 'admin@example.edu')['role'] === 'Administrator', 'H-41 Admin cannot demote self (last admin protected)');

// ======================= UM-10 Log Out =======================
$t = http('GET', '/php/dashboard.php', $taylor);
$oldCookie = $taylor;
$r = http('POST', '/php/logout.php', $taylor, ['csrf_token' => csrfFrom($t['body'])]);
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-42 Logout redirects to login');
$r = http('GET', '/php/requests.php', $taylor);
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-43 Protected access denied after logout');
$r = http('GET', '/php/requests.php', $oldCookie);
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-44 Replaying the pre-logout session cookie is denied');
check(http('GET', '/php/logout.php', $taylor)['status'] === 405, 'H-45 Logout via GET not allowed');
$r = http('POST', '/php/logout.php', $taylor, []);
check($r['status'] === 303 && $r['location'] === 'login.php', 'H-46 Logout with already-expired session handled gracefully');

echo "Results: $passed passed, $failed failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
