<?php
require_once dirname(__DIR__).'/includes/validation.php';
require_once dirname(__DIR__).'/includes/html.php';   // escapeHtml() now lives here (Lab 7)
require_once dirname(__DIR__).'/repositories/studentPDO.php';
require_once dirname(__DIR__).'/repositories/requestPDO.php';
// Week 4 form fields map to the Week 6 schema without adding database columns.
// topic contains JSON {reason,topics}; preferred_time stores Morning/Afternoon/Evening.
function decodeTopic($topic) {
    $v=json_decode($topic,true);
    return is_array($v)&&isset($v['reason'])&&is_array($v['topics']??null) ? $v : ['reason'=>$topic,'topics'=>[]];
}
function validateSubmission($input, $requireStudent = true) {
    $errors=[];$courses=['IS 6465','IS 6495','IS 6570'];
    $name=trim((string)($input['student_name']??''));$email=trim((string)($input['student_email']??''));
    $course=trim((string)($input['course']??''));$reason=trim((string)($input['reason']??''));
    $date=(string)($input['preferreddate']??'');$time=(string)($input['preferredtime']??'');
    $topics=$input['topics']??[];
    if(!is_array($topics)){$errors[]='Topics must be a list.';$topics=[];}
    if($requireStudent && $name==='')$errors[]='Student name is required.';
    if($requireStudent && !filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Valid student email required.';
    if(!in_array($course,$courses,true))$errors[]='Select a valid course.';
    if($msg=validatePreferredTime($time))$errors[]=$msg;
    if($msg=validateTopics($topics))$errors[]=$msg;
    if($msg=validateReason($reason))$errors[]=$msg;
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$dt || $dt->format('Y-m-d')!==$date || $dt < new DateTimeImmutable('today'))$errors[]='Choose a valid date today or later.';
    $payload=['student_name'=>$name,'student_email'=>$email,'course'=>$course,'topic'=>json_encode(['reason'=>$reason,'topics'=>array_values($topics)]),'preferred_date'=>$date,'preferred_time'=>$time];
    return [$errors,$payload];
}
function submitRequest($pdo, $input) {
    [$errors,$data]=validateSubmission($input);
    if($errors)throw new InvalidArgumentException(implode(' ',$errors));
    $pdo->beginTransaction();
    try {
        insertStudentIfAbsent($pdo,$data['student_name'],$data['student_email']);
        $student=findStudentByEmail($pdo,$data['student_email']);
        if(!$student)throw new RuntimeException('Student lookup failed');
        $id=insertTutoringRequest($pdo,(int)$student['student_id'],$data);
        $pdo->commit();return $id;
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function reviseRequest($pdo, $id, $input) {
    [$errors,$data]=validateSubmission($input,false);
    if($errors)throw new InvalidArgumentException(implode(' ',$errors));
    return updateOpenRequest($pdo,$id,$data);
}

// ---------------------------------------------------------------------------
// Lab 7: protected tutoring request workflows (Authentication Reading Handout, section 13)
//   Student       : submit requests; view, edit and cancel only their OWN requests
//   Tutor         : no request workflow in this Lab 7 version
//   Administrator : view all requests; edit or cancel any Open request; does not submit
// Ownership is students.user_id = the signed-in user's user_id.
// ---------------------------------------------------------------------------
function canCreateRequest($user) {
    return $user && $user['status'] === 'Active' && $user['role'] === 'Student';
}
function canUseRequestPages($user) {
    return $user && $user['status'] === 'Active' && in_array($user['role'], ['Student', 'Administrator'], true);
}
function ownsRequest($user, $request) {
    return $user && $request && $user['role'] === 'Student'
        && $request['student_user_id'] !== null && (int)$request['student_user_id'] === (int)$user['user_id'];
}
function canViewRequest($user, $request) {
    if (!canUseRequestPages($user) || !$request) return false;
    return $user['role'] === 'Administrator' || ownsRequest($user, $request);
}
function canModifyRequest($user, $request) {
    return canViewRequest($user, $request);   // same rule; the Open-status check happens in the UPDATE
}
// Loads a request the user may access. Students get the ownership condition inside the SQL
// (handout section 14), so another student's request comes back as not found.
function findRequestForUser($pdo, $user, $id) {
    if (!canUseRequestPages($user)) return false;
    if ($user['role'] === 'Administrator') return findRequest($pdo, $id);
    return findRequestForStudentUser($pdo, $id, (int)$user['user_id']);
}
// The student's identity always comes from the signed-in account, never from the posted
// student_name / student_email fields, so a request cannot be filed for someone else.
// Creates a missing linked STUDENTS row in the same transaction (handout section 19).
function submitRequestForUser($pdo, $user, $input) {
    if (!canCreateRequest($user)) throw new RuntimeException('Only student accounts can request tutoring.');
    $input['student_name'] = $user['full_name'];
    $input['student_email'] = $user['email'];
    [$errors, $data] = validateSubmission($input);
    if ($errors) throw new InvalidArgumentException(implode(' ', $errors));
    $pdo->beginTransaction();
    try {
        $student = findStudentByUserId($pdo, (int)$user['user_id']);
        $studentId = $student ? (int)$student['student_id']
            : insertStudentForUser($pdo, (int)$user['user_id'], $user['full_name'], $user['email']);
        $id = insertTutoringRequest($pdo, $studentId, $data);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
// The list a user may see. Students are limited to their own user_id; Tutors are denied.
function listRequestsForUser($pdo, $user, $course = '', $status = '') {
    if (!canUseRequestPages($user)) throw new RuntimeException('Tutors do not have a request workflow in this version.');
    if ($user['role'] === 'Student') return listRequests($pdo, $course, $status, (int)$user['user_id']);
    return listRequests($pdo, $course, $status);
}
function reviseRequestForUser($pdo, $user, $id, $input) {
    if (!findRequestForUser($pdo, $user, $id)) throw new RuntimeException('Request not found.');
    return reviseRequest($pdo, $id, $input);
}
function cancelRequestForUser($pdo, $user, $id) {
    if (!findRequestForUser($pdo, $user, $id)) throw new RuntimeException('Request not found.');
    return cancelOpenRequest($pdo, $id);
}
