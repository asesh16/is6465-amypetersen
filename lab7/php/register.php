<?php
// UM-01 Create User Account (WF-01 Registration)
require_once __DIR__ . '/../includes/auth.php';
requireGuest();

$errors = [];
$values = ['full_name' => '', 'email' => '', 'role' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $values['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $values['email'] = trim((string)($_POST['email'] ?? ''));
    $values['role'] = (string)($_POST['role'] ?? '');
    try {
        registerUser(openDatabase(), $_POST);
        setFlash('Your account was created and is active. You can log in now.');
        redirectTo('login.php');
    } catch (UserValidationException $e) {
        http_response_code(422);
        $errors = $e->getErrors();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = ['Unable to create the account right now. Please try again.'];
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Create an account</h1>
    <?= renderFlash() ?>
    <?= renderErrors($errors) ?>
    <form class="app-form" method="post" action="register.php">
        <?= csrfField() ?>
        <div class="form-field">
            <label for="full_name">Full Name</label>
            <input id="full_name" name="full_name" maxlength="120" required autocomplete="name" value="<?= escapeHtml($values['full_name']) ?>">
        </div>
        <div class="form-field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" required autocomplete="email" value="<?= escapeHtml($values['email']) ?>">
        </div>
        <div class="form-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password">
            <small class="text-muted">At least <?= PASSWORD_MIN_LENGTH ?> characters.</small>
        </div>
        <div class="form-field">
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
        </div>
        <fieldset class="form-field">
            <legend>Account type</legend>
            <?php foreach (selfRegistrationRoles() as $r): ?>
                <label class="choice me-3">
                    <input type="radio" name="role" value="<?= escapeHtml($r) ?>" <?= $values['role'] === $r ? 'checked' : '' ?> required>
                    <span><?= escapeHtml($r) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <button type="submit">Create Account</button>
        <p class="mt-3 mb-0">Already registered? <a href="login.php">Log in</a></p>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
