<?php
// UM-03 View and Search User Accounts (WF-03 Manage Users) - Administrator only
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);

$errors = [];
$rows = [];
[$criteriaErrors, $criteria] = validateUserSearch($_GET);
try {
    $rows = searchUsers(openDatabase(), (int)$admin['user_id'], $_GET);
} catch (UserValidationException $e) {
    http_response_code(400);
    $errors = $e->getErrors();
} catch (AuthorizationException $e) {
    denyAccess($e->getMessage());
}

$pageTitle = 'Manage users';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Manage users</h1>
    <?= renderFlash() ?>
    <?= renderErrors($errors) ?>
    <form method="get" action="admin-users.php" class="row g-2 my-3">
        <div class="col-md-5">
            <label class="visually-hidden" for="q">Search</label>
            <input class="form-control" id="q" name="q" maxlength="100" placeholder="Search name or email" value="<?= escapeHtml($criteria['q']) ?>">
        </div>
        <div class="col-md-3">
            <label class="visually-hidden" for="role">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">All roles</option>
                <?php foreach (userRoles() as $r): ?>
                    <option value="<?= escapeHtml($r) ?>" <?= $criteria['role'] === $r ? 'selected' : '' ?>><?= escapeHtml($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="visually-hidden" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (userStatuses() as $s): ?>
                    <option value="<?= escapeHtml($s) ?>" <?= $criteria['status'] === $s ? 'selected' : '' ?>><?= escapeHtml($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    </form>

    <?php if (!$errors && !$rows): ?>
        <div class="alert alert-info">No accounts match your search.</div>
    <?php elseif ($rows): ?>
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td><?= escapeHtml($u['full_name']) ?></td>
                    <td><?= escapeHtml($u['email']) ?></td>
                    <td><?= escapeHtml($u['role']) ?></td>
                    <td><?= escapeHtml($u['status']) ?></td>
                    <td><a href="admin-user-details.php?user_id=<?= (int)$u['user_id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
