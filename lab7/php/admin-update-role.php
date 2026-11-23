<?php
// UM-06 Update User Role (WF-05) - Administrator only
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
        updateUserRole($pdo, (int)$admin['user_id'], $targetId, (string)($_POST['role'] ?? ''));
        setFlash('Role updated.');
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
        $errors = ['Unable to update the role right now.'];
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

$pageTitle = 'Update role';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Update role</h1>
    <?= renderErrors($errors) ?>
    <form class="app-form" method="post" action="admin-update-role.php">
        <?= csrfField() ?>
        <input type="hidden" name="user_id" value="<?= (int)$target['user_id'] ?>">
        <p><strong>User:</strong> <?= escapeHtml($target['full_name']) ?></p>
        <p><strong>Current role:</strong> <?= escapeHtml($target['role']) ?></p>
        <div class="form-field">
            <label for="role">New role</label>
            <select id="role" name="role" required>
                <?php foreach (userRoles() as $r): ?>
                    <option value="<?= escapeHtml($r) ?>" <?= $target['role'] === $r ? 'selected' : '' ?>><?= escapeHtml($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a class="btn btn-secondary me-2" href="admin-user-details.php?user_id=<?= (int)$target['user_id'] ?>">Cancel</a>
        <button type="submit">Save Role</button>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
