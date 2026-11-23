<?php
// USER table SQL. Every submitted value is bound through a prepared statement.
// Functions that return accounts for display never select password_hash.

const USER_PUBLIC_COLUMNS = 'user_id, full_name, email, role, status, created_at';

function insertUser($pdo, $fullName, $email, $passwordHash, $role, $status = 'Active') {
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$fullName, $email, $passwordHash, $role, $status]);
    return (int)$pdo->lastInsertId();
}

function findUserById($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function findUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare('SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Credential lookups include the hash and are used only by login and password change.
function findUserCredentialsByEmail($pdo, $email) {
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, role, status, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function findUserCredentialsById($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, role, status, password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function emailUsedByAnotherUser($pdo, $email, $excludeUserId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?');
    $stmt->execute([$email, $excludeUserId]);
    return (int)$stmt->fetchColumn() > 0;
}

// UM-03 search. LIKE wildcards in the search term are escaped so % and _ are literal.
function searchUserAccounts($pdo, $term = '', $role = '', $status = '') {
    $sql = 'SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users WHERE 1=1';
    $args = [];
    if ($term !== '') {
        $like = '%' . strtr($term, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        $sql .= " AND (full_name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\')";
        $args[] = $like;
        $args[] = $like;
    }
    if ($role !== '') { $sql .= ' AND role = ?'; $args[] = $role; }
    if ($status !== '') { $sql .= ' AND status = ?'; $args[] = $status; }
    $sql .= ' ORDER BY full_name COLLATE NOCASE, user_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function updateUserProfile($pdo, $userId, $fullName, $email) {
    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?');
    $stmt->execute([$fullName, $email, $userId]);
    return $stmt->rowCount() === 1;
}

function updateUserPasswordHash($pdo, $userId, $passwordHash) {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
    $stmt->execute([$passwordHash, $userId]);
    return $stmt->rowCount() === 1;
}

function updateUserRoleById($pdo, $userId, $role) {
    $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
    $stmt->execute([$role, $userId]);
    return $stmt->rowCount() === 1;
}

function updateUserStatusById($pdo, $userId, $status) {
    $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
    $stmt->execute([$status, $userId]);
    return $stmt->rowCount() === 1;
}

function deleteUserById($pdo, $userId) {
    $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->rowCount() === 1;
}

function countActiveAdministrators($pdo) {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Administrator' AND status = 'Active'")->fetchColumn();
}
