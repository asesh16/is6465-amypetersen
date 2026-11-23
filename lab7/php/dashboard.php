<?php
// Landing page after UM-09 Log In. Links are role based; each target page enforces its own access.
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Dashboard</h1>
    <?= renderFlash() ?>
    <div class="detail-card">
        <p class="lead">Signed in as <strong><?= escapeHtml($user['full_name']) ?></strong> (<?= escapeHtml($user['role']) ?>).</p>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($user['role'] === 'Student'): ?>
                <a class="btn btn-primary" href="request-form.php">Request a tutoring session</a>
                <a class="btn btn-outline-primary" href="requests.php">View my requests</a>
            <?php elseif ($user['role'] === 'Administrator'): ?>
                <a class="btn btn-primary" href="requests.php">View all tutoring requests</a>
            <?php else: ?>
                <p class="mb-0 me-3 align-self-center">Tutor request features arrive in a future lab.</p>
            <?php endif; ?>
            <?php if ($user['role'] === 'Administrator'): ?>
                <a class="btn btn-outline-primary" href="admin-users.php">Manage users</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="profile.php">My profile</a>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
