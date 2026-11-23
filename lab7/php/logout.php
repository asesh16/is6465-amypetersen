<?php
// UM-10 Log Out
// POST only with a CSRF token, so another site cannot log a user out with a link or image.
require_once __DIR__ . '/../includes/auth.php';
requirePost();

if (!currentUser()) {
    // Failure path: session already expired. Nothing to invalidate; send to login.
    if (empty($_SESSION['flash'])) setFlash('You are already logged out.', 'info');
    redirectTo('login.php');
}

requireValidCsrf();
restartFreshSession();   // destroys session data, expires the cookie, issues a new anonymous ID
setFlash('You have been logged out.', 'info');
redirectTo('login.php');
