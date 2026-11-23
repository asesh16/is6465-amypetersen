<?php
// UM-09 Log In (WF-08)
require_once __DIR__ . '/../includes/auth.php';
requireGuest();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $email = trim((string)($_POST['email'] ?? ''));
    try {
        $user = authenticateUser(openDatabase(), $email, $_POST['password'] ?? '');
        establishUserSession($user);   // regenerates the session ID
        setFlash('Welcome, ' . $user['full_name'] . '.');
        redirectTo('dashboard.php');
    } catch (UserValidationException $e) {
        http_response_code(401);
        $errors = $e->getErrors();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = ['Unable to log in right now. Please try again.'];
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Log in</h1>
    <?= renderFlash() ?>
    <?= renderErrors($errors) ?>
    <form class="app-form" method="post" action="login.php">
        <?= csrfField() ?>
        <div class="form-field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" required autocomplete="username" value="<?= escapeHtml($email) ?>">
        </div>
        <div class="form-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button type="submit">Log In</button>
        <p class="mt-3 mb-0">New here? <a href="register.php">Create an account</a></p>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
