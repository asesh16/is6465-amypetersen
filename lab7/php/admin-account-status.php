<?php
// UM-07 Activate or Deactivate Account (WF-06) - Administrator only
// GET shows the current status and asks for confirmation; POST applies the change.
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
        $newStatus = (string)($_POST['status'] ?? '');
        updateUserStatus($pdo, (int)$admin['user_id'], $targetId, $newStatus);
        setFlash('Account status changed to ' . $newStatus . '.');
        redirectTo('admin-user-details.php?user_id=' . $targetId);
    } catch (AuthorizationException $e) {
        denyAccess($e->getMessage());
    } catch (UserValidationException $e) {
        http_response_code(422);
        $errors = $e->getErrors();
    } catch (PolicyException $e) {
        http_response_code(409);
        $errors = [$e->getMessage()];
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = ['Unable to change the account status right now.'];
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
$proposed = $target['status'] === 'Active' ? 'Inactive' : 'Active';

$pageTitle = 'Account status';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Account status</h1>
    <?= renderErrors($errors) ?>
    <form class="app-form" method="post" action="admin-account-status.php">
        <?= csrfField() ?>
        <input type="hidden" name="user_id" value="<?= (int)$target['user_id'] ?>">
        <input type="hidden" name="status" value="<?= escapeHtml($proposed) ?>">
        <p><strong>User:</strong> <?= escapeHtml($target['full_name']) ?></p>
        <p><strong>Current status:</strong> <?= escapeHtml($target['status']) ?></p>
        <p><strong>New status:</strong> <?= escapeHtml($proposed) ?></p>
        <?php if ($proposed === 'Inactive'): ?>
            <div class="alert alert-warning">This user will be signed out on their next page load and will not be able to log in until reactivated.</div>
        <?php endif; ?>
        <a class="btn btn-secondary me-2" href="admin-user-details.php?user_id=<?= (int)$target['user_id'] ?>">Cancel</a>
        <button type="submit">Confirm</button>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
