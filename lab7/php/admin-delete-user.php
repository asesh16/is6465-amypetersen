<?php
// UM-08 Delete User Account (WF-07) - Administrator only
// Interim policy: accounts with related tutoring requests are never deleted (deactivate instead).
// The POST handler rechecks authorization and relationships even if the form was tampered with.
require_once __DIR__ . '/../includes/auth.php';
$admin = requireRole(['Administrator']);
$pdo = openDatabase();

$method = $_SERVER['REQUEST_METHOD'];
$targetId = filter_input($method === 'POST' ? INPUT_POST : INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$targetId || $targetId < 1) { http_response_code(400); exit('Invalid user ID'); }

$errors = [];
if ($method === 'POST') {
    requireValidCsrf();
    try {
        deleteUserAccount($pdo, (int)$admin['user_id'], $targetId);
        setFlash('The account was deleted.');
        redirectTo('admin-users.php');
    } catch (AuthorizationException $e) {
        denyAccess($e->getMessage());
    } catch (PolicyException $e) {
        http_response_code(409);
        $errors = [$e->getMessage()];
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = ['Unable to delete the account right now.'];
    }
}

try {
    $target = getUserForAdministrator($pdo, (int)$admin['user_id'], $targetId);
} catch (AuthorizationException $e) {
    denyAccess($e->getMessage());
} catch (PolicyException $e) {
    http_response_code(404);
    exit(escapeHtml($e->getMessage()));
}
$related = (int)$target['related_request_count'];

$pageTitle = 'Delete account';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Delete account</h1>
    <?= renderErrors($errors) ?>
    <div class="detail-card">
        <p class="lead">Delete account for <strong><?= escapeHtml($target['full_name']) ?></strong> (<?= escapeHtml($target['email']) ?>)?</p>
        <?php if ($related > 0): ?>
            <div class="alert alert-warning">
                This account has <?= $related ?> related tutoring request(s). Under the current retention policy, accounts with
                tutoring records cannot be deleted and dependent records are never removed. Deactivate the account instead.
            </div>
            <a class="btn btn-outline-primary" href="admin-account-status.php?user_id=<?= (int)$target['user_id'] ?>">Go to account status</a>
        <?php else: ?>
            <div class="alert alert-danger">
                Related tutoring requests were checked: none found. This permanently removes the account and cannot be undone.
            </div>
            <form class="inline-form" method="post" action="admin-delete-user.php">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" value="<?= (int)$target['user_id'] ?>">
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-secondary" href="admin-user-details.php?user_id=<?= (int)$target['user_id'] ?>">Cancel</a>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
