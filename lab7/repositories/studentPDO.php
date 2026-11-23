<?php
// STUDENTS table SQL. Lab 7: students.user_id links a student profile to its USERS account.
function findStudentByEmail($pdo, $email) {
    $stmt = $pdo->prepare('SELECT student_id, student_name, student_email, user_id FROM students WHERE student_email = ?');
    $stmt->execute([$email]); return $stmt->fetch();
}
function insertStudentIfAbsent($pdo, $name, $email) {
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO students(student_name,student_email) VALUES(?,?)');
    $stmt->execute([$name,$email]);
}

// ---------------- Lab 7 ----------------
function findStudentByUserId($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT student_id, student_name, student_email, user_id FROM students WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// UM-01: a Student registration creates the linked student profile row.
function insertStudentForUser($pdo, $userId, $name, $email) {
    $stmt = $pdo->prepare('INSERT INTO students (student_name, student_email, user_id) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $userId]);
    return (int)$pdo->lastInsertId();
}

// UM-01: attach an existing Lab 6 student row (same email, no account yet) to the new account.
function linkStudentToUser($pdo, $studentId, $userId, $name) {
    $stmt = $pdo->prepare('UPDATE students SET user_id = ?, student_name = ? WHERE student_id = ? AND user_id IS NULL');
    $stmt->execute([$userId, $name, $studentId]);
    return $stmt->rowCount() === 1;
}

// UM-04: keep the student profile in step with the account name and email.
function updateStudentIdentityByUserId($pdo, $userId, $name, $email) {
    $stmt = $pdo->prepare('UPDATE students SET student_name = ?, student_email = ? WHERE user_id = ?');
    $stmt->execute([$name, $email, $userId]);
    return $stmt->rowCount();
}

// UM-08: removes the profile row only when it owns no tutoring requests.
function deleteStudentWithoutRequestsByUserId($pdo, $userId) {
    $stmt = $pdo->prepare('DELETE FROM students WHERE user_id = ? AND NOT EXISTS (SELECT 1 FROM tutoring_requests r WHERE r.student_id = students.student_id)');
    $stmt->execute([$userId]);
    return $stmt->rowCount();
}
