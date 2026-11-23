<?php
// User Management service layer (UM-01 through UM-10).
// Validation, business rules and transaction coordination live here.
// SQL lives in the repositories; HTML lives in the php/ pages.
require_once dirname(__DIR__) . '/includes/html.php';
require_once dirname(__DIR__) . '/repositories/userPDO.php';
require_once dirname(__DIR__) . '/repositories/studentPDO.php';
require_once dirname(__DIR__) . '/repositories/requestPDO.php';

// ---------------------------------------------------------------------------
// Exceptions. Each one maps to a different page response:
//   UserValidationException -> 422, redisplay the form with corrections
//   AuthorizationException  -> 403, access denied
//   PolicyException         -> 409, prohibited change or blocked action explained
// ---------------------------------------------------------------------------
class UserValidationException extends InvalidArgumentException {
    private $errors;
    public function __construct(array $errors) {
        $this->errors = $errors;
        parent::__construct(implode(' ', $errors));
    }
    public function getErrors() { return $this->errors; }
}
class AuthorizationException extends RuntimeException {}
class PolicyException extends RuntimeException {}

// ---------------------------------------------------------------------------
// Business rule constants
// ---------------------------------------------------------------------------
function userRoles() { return ['Student', 'Tutor', 'Administrator']; }
function selfRegistrationRoles() { return ['Student', 'Tutor']; }   // Approved: public registration cannot grant Administrator
function userStatuses() { return ['Active', 'Inactive']; }

// Password rule. The design document lists exact requirements as an OPEN decision; the Lab 7
// instructions call for passwords of at least 12 characters. 72 byte maximum because bcrypt
// (PASSWORD_DEFAULT) ignores bytes past 72.
const PASSWORD_MIN_LENGTH = 12;
const PASSWORD_MAX_BYTES = 72;

// A fixed hash used to keep login timing similar when an email does not exist.
const DUMMY_PASSWORD_HASH = '$2y$10$d6vmt3Qr0O9sNeHwxHm.yO6l2g1Hn/GHY4AG/e/.IJyofo3WoVHoS';

// ---------------------------------------------------------------------------
// Field validation helpers
// ---------------------------------------------------------------------------
function normalizeEmail($email) {
    return strtolower(trim((string)$email));
}

function validateFullName($name) {
    $length = mb_strlen($name);
    if ($length < 1 || $length > 120) {
        return 'Full name is required (120 characters maximum).';
    }
    return '';
}

function validateUserEmail($email) {
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    return '';
}

function validateNewPassword($password, $confirm) {
    $errors = [];
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (strlen($password) > PASSWORD_MAX_BYTES) {
        $errors[] = 'Password must be ' . PASSWORD_MAX_BYTES . ' bytes or fewer.';
    }
    if (strpos($password, "\0") !== false) {
        $errors[] = 'Password contains an invalid character.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    return $errors;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Re-reads the acting user from the database so authorization is based on
// current role and status, not on whatever the session remembered.
function requireActiveAdministrator($pdo, $actorId) {
    $actor = findUserById($pdo, (int)$actorId);
    if (!$actor || $actor['status'] !== 'Active' || $actor['role'] !== 'Administrator') {
        throw new AuthorizationException('Administrator access is required.');
    }
    return $actor;
}

function requireTargetUser($pdo, $targetId) {
    $target = findUserById($pdo, (int)$targetId);
    if (!$target) {
        throw new PolicyException('User account not found.');
    }
    return $target;
}

// ---------------------------------------------------------------------------
// UM-01 Create User Account
// ---------------------------------------------------------------------------
function validateRegistration($input) {
    $errors = [];
    $name = trim((string)($input['full_name'] ?? ''));
    $email = normalizeEmail($input['email'] ?? '');
    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['confirm_password'] ?? '');
    $role = (string)($input['role'] ?? '');

    if ($msg = validateFullName($name)) $errors[] = $msg;
    if ($msg = validateUserEmail($email)) $errors[] = $msg;
    $errors = array_merge($errors, validateNewPassword($password, $confirm));
    if (!in_array($role, selfRegistrationRoles(), true)) {
        $errors[] = 'Choose Student or Tutor as the account type.';
    }
    return [$errors, ['full_name' => $name, 'email' => $email, 'password' => $password, 'role' => $role]];
}

function registerUser($pdo, $input) {
    [$errors, $data] = validateRegistration($input);
    if (!$errors && findUserByEmail($pdo, $data['email'])) {
        $errors[] = 'An account with that email already exists.';
    }
    if ($errors) {
        throw new UserValidationException($errors);
    }
    $pdo->beginTransaction();
    try {
        // Only the hash is stored. confirm_password is never stored.
        $userId = insertUser($pdo, $data['full_name'], $data['email'], hashPassword($data['password']), $data['role'], 'Active');
        if ($data['role'] === 'Student') {
            // A Student account gets a linked STUDENTS profile row (students.user_id).
            // A Lab 6 student row with the same email and no account yet is linked instead of duplicated.
            $existing = findStudentByEmail($pdo, $data['email']);
            if ($existing && $existing['user_id'] === null) {
                linkStudentToUser($pdo, (int)$existing['student_id'], $userId, $data['full_name']);
            } elseif ($existing) {
                throw new UserValidationException(['An account with that email already exists.']);
            } else {
                insertStudentForUser($pdo, $userId, $data['full_name'], $data['email']);
            }
        }
        $pdo->commit();
        return $userId;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // UNIQUE constraint backstop if two registrations race for the same email.
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            throw new UserValidationException(['An account with that email already exists.']);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Command line bootstrap only (database/create-admin.php). Not reachable from any web page,
// because public registration must never create an Administrator.
function bootstrapAdministrator($pdo, $fullName, $email, $password) {
    $name = trim((string)$fullName);
    $email = normalizeEmail($email);
    $errors = [];
    if ($msg = validateFullName($name)) $errors[] = $msg;
    if ($msg = validateUserEmail($email)) $errors[] = $msg;
    $errors = array_merge($errors, validateNewPassword((string)$password, (string)$password));
    if (!$errors && findUserByEmail($pdo, $email)) $errors[] = 'An account with that email already exists.';
    if ($errors) throw new UserValidationException($errors);
    return insertUser($pdo, $name, $email, hashPassword($password), 'Administrator', 'Active');
}

// ---------------------------------------------------------------------------
// UM-09 Log In
// Returns the account (without hash) on success. Session work happens in includes/auth.php.
// ---------------------------------------------------------------------------
function authenticateUser($pdo, $email, $password) {
    $email = normalizeEmail($email);
    $password = (string)$password;
    $account = ($email === '') ? false : findUserCredentialsByEmail($pdo, $email);

    if (!$account) {
        password_verify($password, DUMMY_PASSWORD_HASH);   // similar timing whether or not the email exists
        throw new UserValidationException(['Invalid email or password.']);
    }
    if (!password_verify($password, $account['password_hash'])) {
        throw new UserValidationException(['Invalid email or password.']);
    }
    if ($account['status'] !== 'Active') {
        // Handout section 7: same generic message for unknown, inactive or wrong-password logins.
        throw new UserValidationException(['Invalid email or password.']);
    }
    // Upgrade the stored hash automatically if PHP's default algorithm or cost changes.
    if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
        updateUserPasswordHash($pdo, (int)$account['user_id'], hashPassword($password));
    }
    unset($account['password_hash']);
    return $account;
}

// ---------------------------------------------------------------------------
// UM-02 View Own Account
// ---------------------------------------------------------------------------
function getOwnAccount($pdo, $userId) {
    $user = findUserById($pdo, (int)$userId);
    if (!$user) {
        throw new AuthorizationException('Your session is no longer valid. Please log in again.');
    }
    return $user;
}

// ---------------------------------------------------------------------------
// UM-04 Update Own Account
// Only full_name and email are read from input. role and status keys are ignored,
// so a tampered form cannot change them.
// ---------------------------------------------------------------------------
function updateOwnAccount($pdo, $userId, $input) {
    $current = getOwnAccount($pdo, $userId);
    $name = trim((string)($input['full_name'] ?? ''));
    $email = normalizeEmail($input['email'] ?? '');

    $errors = [];
    if ($msg = validateFullName($name)) $errors[] = $msg;
    if ($msg = validateUserEmail($email)) $errors[] = $msg;
    if (!$errors && $email !== $current['email']) {
        // Duplicate in USERS, or an email already used by a student profile that is not this user's.
        $studentWithEmail = findStudentByEmail($pdo, $email);
        $takenByStudent = $studentWithEmail && (int)$studentWithEmail['user_id'] !== (int)$userId;
        if (emailUsedByAnotherUser($pdo, $email, (int)$userId) || $takenByStudent) {
            $errors[] = 'That email is already in use.';
        }
    }
    if ($errors) throw new UserValidationException($errors);

    $pdo->beginTransaction();
    try {
        updateUserProfile($pdo, (int)$userId, $name, $email);
        // Keep the linked STUDENTS profile row (students.user_id) in step.
        updateStudentIdentityByUserId($pdo, (int)$userId, $name, $email);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return findUserById($pdo, (int)$userId);
}

// ---------------------------------------------------------------------------
// UM-05 Change Password
// ---------------------------------------------------------------------------
function changePassword($pdo, $userId, $input) {
    $current = (string)($input['current_password'] ?? '');
    $new = (string)($input['new_password'] ?? '');
    $confirm = (string)($input['confirm_password'] ?? '');

    $account = findUserCredentialsById($pdo, (int)$userId);
    if (!$account) {
        throw new AuthorizationException('Your session is no longer valid. Please log in again.');
    }
    if (!password_verify($current, $account['password_hash'])) {
        throw new UserValidationException(['Current password is incorrect.']);
    }
    $errors = validateNewPassword($new, $confirm);
    if (!$errors && $new === $current) {
        $errors[] = 'New password must be different from the current password.';
    }
    if ($errors) throw new UserValidationException($errors);

    return updateUserPasswordHash($pdo, (int)$userId, hashPassword($new));
}

// ---------------------------------------------------------------------------
// UM-03 View and Search User Accounts (Administrator)
// ---------------------------------------------------------------------------
function validateUserSearch($input) {
    $term = trim((string)($input['q'] ?? ''));
    $role = (string)($input['role'] ?? '');
    $status = (string)($input['status'] ?? '');
    $errors = [];
    if (mb_strlen($term) > 100) $errors[] = 'Search text must be 100 characters or fewer.';
    if ($role !== '' && !in_array($role, userRoles(), true)) $errors[] = 'Choose a valid role filter.';
    if ($status !== '' && !in_array($status, userStatuses(), true)) $errors[] = 'Choose a valid status filter.';
    return [$errors, ['q' => $term, 'role' => $role, 'status' => $status]];
}

function searchUsers($pdo, $actorId, $input) {
    requireActiveAdministrator($pdo, $actorId);
    [$errors, $criteria] = validateUserSearch($input);
    if ($errors) throw new UserValidationException($errors);
    return searchUserAccounts($pdo, $criteria['q'], $criteria['role'], $criteria['status']);
}

function getUserForAdministrator($pdo, $actorId, $targetId) {
    requireActiveAdministrator($pdo, $actorId);
    $target = requireTargetUser($pdo, $targetId);
    $target['related_request_count'] = countRequestsByUserId($pdo, (int)$target['user_id']);
    return $target;
}

// ---------------------------------------------------------------------------
// UM-06 Update User Role (Administrator)
// ---------------------------------------------------------------------------
function updateUserRole($pdo, $actorId, $targetId, $newRole) {
    $pdo->beginTransaction();
    try {
        $actor = requireActiveAdministrator($pdo, $actorId);
        $target = requireTargetUser($pdo, $targetId);
        if (!in_array($newRole, userRoles(), true)) {
            throw new UserValidationException(['Choose a valid role.']);
        }
        if ((int)$target['user_id'] === (int)$actor['user_id']) {
            throw new PolicyException('Administrators cannot change their own role.');
        }
        if ($target['role'] === $newRole) {
            throw new PolicyException('The account already has the ' . $newRole . ' role.');
        }
        if ($target['role'] === 'Administrator' && $target['status'] === 'Active' && countActiveAdministrators($pdo) <= 1) {
            throw new PolicyException('The last active administrator cannot be removed.');
        }
        updateUserRoleById($pdo, (int)$target['user_id'], $newRole);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return true;
}

// ---------------------------------------------------------------------------
// UM-07 Activate or Deactivate Account (Administrator)
// ---------------------------------------------------------------------------
function updateUserStatus($pdo, $actorId, $targetId, $newStatus) {
    $pdo->beginTransaction();
    try {
        $actor = requireActiveAdministrator($pdo, $actorId);
        $target = requireTargetUser($pdo, $targetId);
        if (!in_array($newStatus, userStatuses(), true)) {
            throw new UserValidationException(['Choose a valid status.']);
        }
        if ($target['status'] === $newStatus) {
            throw new PolicyException('Invalid transition: the account is already ' . $newStatus . '.');
        }
        if ((int)$target['user_id'] === (int)$actor['user_id']) {
            throw new PolicyException('Administrators cannot change their own account status.');
        }
        if ($newStatus === 'Inactive' && $target['role'] === 'Administrator' && countActiveAdministrators($pdo) <= 1) {
            throw new PolicyException('The last active administrator cannot be deactivated.');
        }
        updateUserStatusById($pdo, (int)$target['user_id'], $newStatus);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return true;
}

// ---------------------------------------------------------------------------
// UM-08 Delete User Account (Administrator)
// INTERIM POLICY (permanent deletion policy is an OPEN decision in the design document):
//   * An account with ANY related tutoring requests cannot be deleted. Deactivate it instead.
//   * Dependent records are never removed silently and are never cascaded.
//   * An account with no requests is deleted along with its empty linked STUDENTS row, if one exists.
// ---------------------------------------------------------------------------
function deleteUserAccount($pdo, $actorId, $targetId) {
    $pdo->beginTransaction();
    try {
        $actor = requireActiveAdministrator($pdo, $actorId);
        $target = requireTargetUser($pdo, $targetId);
        if ((int)$target['user_id'] === (int)$actor['user_id']) {
            throw new PolicyException('Administrators cannot delete their own account.');
        }
        if ($target['role'] === 'Administrator' && $target['status'] === 'Active' && countActiveAdministrators($pdo) <= 1) {
            throw new PolicyException('The last active administrator cannot be deleted.');
        }
        $related = countRequestsByUserId($pdo, (int)$target['user_id']);
        if ($related > 0) {
            throw new PolicyException('Deletion blocked: this account has ' . $related . ' related tutoring request(s). Under the current retention policy, deactivate the account instead.');
        }
        deleteStudentWithoutRequestsByUserId($pdo, (int)$target['user_id']);
        deleteUserById($pdo, (int)$target['user_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return true;
}
