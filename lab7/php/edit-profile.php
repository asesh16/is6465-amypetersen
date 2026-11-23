<?php
// UM-04 Update Own Account (WF-02 Edit)
// Only name and email are editable. Role and status are displayed but never read from the form.
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();

$errors = [];
$values = ['full_name' => $user['full_name'], 'email' => $user['email']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $values['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $values['email'] = trim((string)($_POST['email'] ?? ''));
    try {
        updateOwnAccount(openDatabase(), (int)$user['user_id'], $_POST);
        setFlash('Your profile changes were saved.');
        redirectTo('profile.php');
    } catch (UserValidationException $e) {
        http_response_code(422);
        $errors = $e->getErrors();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = ['Unable to save your profile right now.'];
    }
}

$pageTitle = 'Edit profile';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Edit profile</h1>
    <?= renderErrors($errors) ?>
    <form class="app-form" method="post" action="edit-profile.php">
        <?= csrfField() ?>
        <div class="form-field">
            <label for="full_name">Name</label>
            <input id="full_name" name="full_name" maxlength="120" required value="<?= escapeHtml($values['full_name']) ?>">
        </div>
        <div class="form-field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" required value="<?= escapeHtml($values['email']) ?>">
        </div>
        <div class="form-field">
            <label for="role_display">Role (read only)</label>
            <input id="role_display" value="<?= escapeHtml($user['role']) ?>" disabled>
        </div>
        <div class="form-field">
            <label for="status_display">Status (read only)</label>
            <input id="status_display" value="<?= escapeHtml($user['status']) ?>" disabled>
        </div>
        <button type="submit">Save Changes</button>
        <a class="btn btn-secondary ms-2" href="profile.php">Cancel</a>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
