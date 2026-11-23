<?php
// UM-05 Change Password (WF-04)
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    try {
        changePassword(openDatabase(), (int)$user['user_id'], $_POST);
        session_regenerate_id(true);   // new session ID after a credential change
        setFlash('Your password was updated.');
        redirectTo('profile.php');
    } catch (UserValidationException $e) {
        http_response_code(422);
        $errors = $e->getErrors();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = ['Unable to update your password right now.'];
    }
}

$pageTitle = 'Change password';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Change password</h1>
    <?= renderErrors($errors) ?>
    <form class="app-form" method="post" action="change-password.php">
        <?= csrfField() ?>
        <div class="form-field">
            <label for="current_password">Current Password</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        </div>
        <div class="form-field">
            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password">
            <small class="text-muted">At least <?= PASSWORD_MIN_LENGTH ?> characters.</small>
        </div>
        <div class="form-field">
            <label for="confirm_password">Confirm New Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
        </div>
        <button type="submit">Update Password</button>
        <a class="btn btn-secondary ms-2" href="profile.php">Cancel</a>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
