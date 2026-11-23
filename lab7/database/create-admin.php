<?php
// Creates the first Administrator account from the command line.
// Public registration can never create an Administrator, so this is the only way to
// bootstrap one. After that, administrators can promote other accounts (UM-06).
// From the lab7/database folder (Lab 7 Part 3):
//   php create-admin.php "Test Administrator" admin@example.com "TestPassword123"
// If the password argument is left off, the script prompts for it instead, which keeps
// it out of shell history.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/services/userService.php';

if ($argc < 3) {
    fwrite(STDERR, 'Usage: php create-admin.php "Full Name" email@example.com ["Password"]' . PHP_EOL);
    exit(1);
}

function promptHidden($label) {
    echo $label;
    $tty = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($tty) shell_exec('stty -echo');
    $value = rtrim((string)fgets(STDIN), "\r\n");
    if ($tty) { shell_exec('stty echo'); echo PHP_EOL; }
    return $value;
}

if ($argc >= 4) {
    $password = $argv[3];
} else {
    $password = promptHidden('Password (min ' . PASSWORD_MIN_LENGTH . ' characters): ');
    $confirm = promptHidden('Confirm password: ');
    if ($password !== $confirm) {
        fwrite(STDERR, 'Passwords do not match.' . PHP_EOL);
        exit(1);
    }
}

try {
    $id = bootstrapAdministrator(openDatabase(), $argv[1], $argv[2], $password);
    echo 'Administrator account created: ' . normalizeEmail($argv[2]) . ' (role: Administrator, user_id ' . $id . ').' . PHP_EOL;
    exit(0);
} catch (UserValidationException $e) {
    fwrite(STDERR, 'Not created: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Not created: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
