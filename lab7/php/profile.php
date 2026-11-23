<?php
// UM-02 View Own Account (WF-02 Profile)
// The page takes no user ID parameter. It always shows the signed-in user, so one
// user cannot view another user's profile by changing the URL.
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();
$account = getOwnAccount(openDatabase(), (int)$user['user_id']);

$pageTitle = 'My profile';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>My profile</h1>
    <?= renderFlash() ?>
    <div class="detail-card">
        <dl class="row mb-4">
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= escapeHtml($account['full_name']) ?></dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= escapeHtml($account['email']) ?></dd>
            <dt class="col-sm-3">Role</dt><dd class="col-sm-9"><?= escapeHtml($account['role']) ?> <span class="text-muted small">(read only)</span></dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= escapeHtml($account['status']) ?> <span class="text-muted small">(read only)</span></dd>
            <dt class="col-sm-3">Member since</dt><dd class="col-sm-9"><?= escapeHtml($account['created_at']) ?></dd>
        </dl>
        <a class="btn btn-primary" href="edit-profile.php">Edit Profile</a>
        <a class="btn btn-outline-secondary" href="change-password.php">Change Password</a>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
