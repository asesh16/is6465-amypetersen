<?php
// UM-03 step 4: show permitted account details, with links to UM-06, UM-07 and UM-08.
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);

$targetId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$targetId || $targetId < 1) { http_response_code(400); exit('Invalid user ID'); }

try {
    $target = getUserForAdministrator(openDatabase(), (int)$admin['user_id'], $targetId);
} catch (AuthorizationException $e) {
    denyAccess($e->getMessage());
} catch (PolicyException $e) {
    http_response_code(404);
    exit(escapeHtml($e->getMessage()));
}
$isSelf = (int)$target['user_id'] === (int)$admin['user_id'];

$pageTitle = 'Account details';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Account: <?= escapeHtml($target['full_name']) ?></h1>
    <?= renderFlash() ?>
    <div class="detail-card">
        <dl class="row">
            <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= escapeHtml($target['full_name']) ?></dd>
            <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= escapeHtml($target['email']) ?></dd>
            <dt class="col-sm-4">Role</dt><dd class="col-sm-8"><?= escapeHtml($target['role']) ?></dd>
            <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= escapeHtml($target['status']) ?></dd>
            <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?= escapeHtml($target['created_at']) ?></dd>
            <dt class="col-sm-4">Related tutoring requests</dt><dd class="col-sm-8"><?= (int)$target['related_request_count'] ?></dd>
        </dl>
        <?php if ($isSelf): ?>
            <div class="alert alert-secondary">This is your own account. Administrators cannot change their own role or status or delete themselves. Use My Profile for name, email and password.</div>
        <?php else: ?>
            <a class="btn btn-primary" href="admin-update-role.php?user_id=<?= (int)$target['user_id'] ?>">Update Role</a>
            <a class="btn btn-outline-primary" href="admin-account-status.php?user_id=<?= (int)$target['user_id'] ?>"><?= $target['status'] === 'Active' ? 'Deactivate' : 'Activate' ?> Account</a>
            <a class="btn btn-outline-danger" href="admin-delete-user.php?user_id=<?= (int)$target['user_id'] ?>">Delete Account</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="admin-users.php">Back to users</a>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
