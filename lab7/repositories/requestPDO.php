<?php
function insertTutoringRequest($pdo, $studentId, $data) {
    $stmt=$pdo->prepare('INSERT INTO tutoring_requests(student_id,course,topic,preferred_date,preferred_time) VALUES(?,?,?,?,?)');
    $stmt->execute([$studentId,$data['course'],$data['topic'],$data['preferred_date'],$data['preferred_time']]);
    return (int)$pdo->lastInsertId();
}
// Lab 7: also returns s.user_id AS student_user_id for ownership checks.
function findRequest($pdo, $id) {
    $stmt=$pdo->prepare('SELECT r.*,s.student_name,s.student_email,s.user_id AS student_user_id FROM tutoring_requests r JOIN students s ON r.student_id=s.student_id WHERE r.request_id=?');
    $stmt->execute([$id]);return $stmt->fetch();
}
// Lab 7 (handout section 14): ownership condition inside the query.
// Returns the request only if it belongs to the given student account.
function findRequestForStudentUser($pdo, $id, $userId) {
    $stmt=$pdo->prepare('SELECT r.*,s.student_name,s.student_email,s.user_id AS student_user_id FROM tutoring_requests r JOIN students s ON r.student_id=s.student_id WHERE r.request_id=? AND s.user_id=?');
    $stmt->execute([$id,$userId]);return $stmt->fetch();
}
// Lab 7: optional $userId limits the list to one student account's requests.
// The default null keeps the Lab 6 behavior (all requests) for existing callers.
function listRequests($pdo, $course = '', $status = '', $userId = null) {
    $sql='SELECT r.*,s.student_name FROM tutoring_requests r JOIN students s ON r.student_id=s.student_id WHERE 1=1';$args=[];
    if($course!==''){$sql.=' AND r.course = ?';$args[]=$course;}
    if($status!==''){$sql.=' AND r.status = ?';$args[]=$status;}
    if($userId!==null){$sql.=' AND s.user_id = ?';$args[]=(int)$userId;}
    $sql.=' ORDER BY r.request_id DESC';$stmt=$pdo->prepare($sql);$stmt->execute($args);return $stmt->fetchAll();
}
function updateOpenRequest($pdo, $id, $data) {
    $stmt=$pdo->prepare("UPDATE tutoring_requests SET course=?,topic=?,preferred_date=?,preferred_time=? WHERE request_id=? AND status='Open'");
    $stmt->execute([$data['course'],$data['topic'],$data['preferred_date'],$data['preferred_time'],$id]);return $stmt->rowCount()===1;
}
function cancelOpenRequest($pdo, $id) {
    $stmt=$pdo->prepare("UPDATE tutoring_requests SET status='Cancelled' WHERE request_id=? AND status='Open'");
    $stmt->execute([$id]);return $stmt->rowCount()===1;
}
// Lab 7: related-record check for UM-08 (Delete User Account).
function countRequestsByUserId($pdo, $userId) {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM tutoring_requests r JOIN students s ON r.student_id=s.student_id WHERE s.user_id=?');
    $stmt->execute([$userId]);return (int)$stmt->fetchColumn();
}
