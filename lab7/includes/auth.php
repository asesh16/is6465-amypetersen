<?php
// Authentication, session management, CSRF protection and role based access guards.
// Every php/ page requires this file first, then calls requireLogin() / requireRole()
// as needed before doing any work.
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/html.php';
require_once dirname(__DIR__) . '/services/userService.php';

const SESSION_IDLE_TIMEOUT = 1200;        // 20 minutes without activity ends the session (handout section 10)
const SESSION_ABSOLUTE_TIMEOUT = 28800;   // 8 hours after login ends the session regardless

// ---------------------------------------------------------------------------
// Session start with hardened cookie settings
// ---------------------------------------------------------------------------
function requestIsHttps() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    // GitHub Codespaces forwards HTTPS traffic to the plain HTTP built-in server.
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function sendSecurityHeaders() {
    if (headers_sent()) return;
    header('X-Frame-Options: DENY');                 // no clickjacking via iframes
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');               // authenticated pages are not cached
}

function startSecureSession() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');         // reject session IDs the server did not create
    ini_set('session.use_only_cookies', '1');        // never accept a session ID from the URL
    ini_set('session.use_trans_sid', '0');
    session_name('PTH_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => requestIsHttps(),
        'httponly' => true,                          // JavaScript cannot read the cookie
        'samesite' => 'Lax',
    ]);
    session_start();
    sendSecurityHeaders();
    enforceSessionTimeouts();
}

function enforceSessionTimeouts() {
    if (empty($_SESSION['user_id'])) return;
    $now = time();
    $idle = $now - (int)($_SESSION['last_activity'] ?? 0);
    $age = $now - (int)($_SESSION['login_time'] ?? 0);
    if ($idle > SESSION_IDLE_TIMEOUT || $age > SESSION_ABSOLUTE_TIMEOUT) {
        restartFreshSession();
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Your session expired. Please log in again.'];
        return;
    }
    $_SESSION['last_activity'] = $now;
}

// ---------------------------------------------------------------------------
// UM-09 / UM-10 session lifecycle
// ---------------------------------------------------------------------------
function establishUserSession($user) {
    session_regenerate_id(true);                     // new ID at login prevents session fixation
    $_SESSION = [];
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['display_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function endSession() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
                'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}

// Ends the current session and opens a brand new anonymous one (new ID) so a
// message can still be shown on the next page.
function restartFreshSession() {
    endSession();
    session_id(session_create_id());
    startSecureSession();
}

// Loads the signed-in user fresh from the database on every request, so a role change
// or deactivation takes effect immediately instead of at the next login.
function currentUser() {
    static $loaded = false, $user = null;
    if ($loaded) return $user;
    $loaded = true;
    startSecureSession();
    if (empty($_SESSION['user_id'])) return null;
    $found = findUserById(openDatabase(), (int)$_SESSION['user_id']);
    if (!$found || $found['status'] !== 'Active') {
        restartFreshSession();
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Your session has ended. Please log in again.'];
        return null;
    }
    // Fresh authorization (handout section 9): refresh display name and role from SQLite.
    $_SESSION['display_name'] = $found['full_name'];
    $_SESSION['role'] = $found['role'];
    $user = $found;
    return $user;
}

// ---------------------------------------------------------------------------
// Guards
// ---------------------------------------------------------------------------
function redirectTo($location) {
    header('Location: ' . $location, true, 303);
    exit;
}

function requireLogin() {
    $user = currentUser();
    if (!$user) {
        if (empty($_SESSION['flash'])) {
            $_SESSION['flash'] = ['type' => 'info', 'message' => 'Please log in to continue.'];
        }
        redirectTo('login.php');
    }
    return $user;
}

function requireRole(array $roles) {
    $user = requireLogin();
    if (!in_array($user['role'], $roles, true)) {
        denyAccess('You do not have permission to view this page.');
    }
    return $user;
}

function requireGuest() {
    if (currentUser()) redirectTo('dashboard.php');
}

function denyAccess($message, $code = 403) {
    http_response_code($code);
    $pageTitle = 'Access denied';
    require dirname(__DIR__) . '/includes/header.php';
    require dirname(__DIR__) . '/includes/nav.php';
    echo '<main class="container py-4"><h1>Access denied</h1><div class="alert alert-danger mt-3">'
        . escapeHtml($message) . '</div><a class="btn btn-secondary" href="dashboard.php">Back to dashboard</a></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

function requirePost() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('POST required');
    }
}

// ---------------------------------------------------------------------------
// CSRF protection (synchronizer token pattern)
// ---------------------------------------------------------------------------
function csrfToken() {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . escapeHtml(csrfToken()) . '">';
}

function isValidCsrfToken($submitted) {
    return is_string($submitted) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted);
}

function requireValidCsrf() {
    startSecureSession();
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        denyAccess('Your form expired or could not be verified. Reload the page and try again.');
    }
}

// ---------------------------------------------------------------------------
// One-time flash messages for the Post/Redirect/Get pattern
// ---------------------------------------------------------------------------
function setFlash($message, $type = 'success') {
    startSecureSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function renderFlash() {
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = in_array($f['type'], ['success', 'info', 'warning', 'danger'], true) ? $f['type'] : 'info';
    return '<div class="alert alert-' . $type . ' mt-3" role="alert">' . escapeHtml($f['message']) . '</div>';
}

function renderErrors(array $errors) {
    if (!$errors) return '';
    $html = '<div class="alert alert-danger mt-3" role="alert"><ul class="mb-0">';
    foreach ($errors as $e) $html .= '<li>' . escapeHtml($e) . '</li>';
    return $html . '</ul></div>';
}
