<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // command line only (the lab server runs without the router)
// UC01 / UC02 integration and regression tests (Lab 6 suite, kept for Lab 7).
// Runs against an in-memory SQLite database. Never touches database/tutoring.db.
//   php tests/integration-tests.php
//
// Lab 7 fix: setup-database.sql seeds one student and one Open IS 6465 request, so
// count assertions are now measured relative to that seeded baseline. The Lab 6 version
// asserted absolute counts and failed 3 checks for that reason alone.
require_once dirname(__DIR__).'/services/requestService.php';
require_once dirname(__DIR__).'/services/userService.php';

$passed=0;$failed=0;
function check($ok, $label){global $passed,$failed;if($ok){$passed++;echo 'PASS: '.$label.PHP_EOL;}else{$failed++;echo 'FAIL: '.$label.PHP_EOL;}}
function countRows($pdo,$table){return (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}

try{
 $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');
 $pdo->exec(file_get_contents(dirname(__DIR__).'/database/setup-database.sql'));
 $pdo->exec(file_get_contents(dirname(__DIR__).'/database/upgrade-to-user-management.sql'));
}catch(Throwable $e){echo 'SETUP FAILED: '.$e->getMessage().PHP_EOL;exit(1);}

$baseStudents=countRows($pdo,'students');$baseRequests=countRows($pdo,'tutoring_requests');
$baseOpen6465=count(listRequests($pdo,'IS 6465','Open'));

// ---------------- Lab 6 UC01 / UC02 regression checks ----------------
$sample=['student_name'=>'Jordan Lee','student_email'=>'jordan@example.edu','course'=>'IS 6465','reason'=>'Please help with PHP form validation.','topics'=>['Exam review','Study plan'],'preferreddate'=>(new DateTimeImmutable('+10 days'))->format('Y-m-d'),'preferredtime'=>'Morning'];
$id=submitRequest($pdo,$sample);$r=findRequest($pdo,$id);check($r!==false&&$r['status']==='Open','UC01 create and Open');
check(decodeTopic($r['topic'])['topics']===['Exam review','Study plan'],'Lab 4 checkbox values preserved');
$id2=submitRequest($pdo,$sample);check(countRows($pdo,'students')===$baseStudents+1,'Duplicate email reuses student');
check(count(listRequests($pdo,'IS 6465','Open'))===$baseOpen6465+2,'UC02 course and status filter');
$bad=$sample;$bad['reason']='short';try{submitRequest($pdo,$bad);check(false,'Invalid reason rejected');}catch(InvalidArgumentException $e){check(true,'Invalid reason rejected');}
check(countRows($pdo,'tutoring_requests')===$baseRequests+2,'Invalid submission writes nothing');
$edit=$sample;$edit['reason']='Updated request reason for the exam.';check(reviseRequest($pdo,$id,$edit),'Edit Open request');
check(decodeTopic(findRequest($pdo,$id)['topic'])['reason']===$edit['reason'],'Updated reason persisted');
check(cancelOpenRequest($pdo,$id),'Cancel Open request');check(findRequest($pdo,$id)['status']==='Cancelled','Cancelled row retained');
check(!cancelOpenRequest($pdo,$id),'Second cancellation rejected');check(findRequest($pdo,$id2)['status']==='Open','Unrelated request unchanged');
try{$pdo->exec("INSERT INTO tutoring_requests(student_id,course,topic,preferred_date,preferred_time) VALUES(999,'IS 6465','x','2030-01-01','Morning')");check(false,'FK enforced');}catch(PDOException $e){check(true,'FK enforced');}
check(count(listRequests($pdo,'IS 6570',''))===0,'UC02 empty filter result');

// ---------------- Lab 7 protected request workflow checks (handout section 13) ----------------
function mk($pdo,$name,$email,$role){return findUserById($pdo,registerUser($pdo,['full_name'=>$name,'email'=>$email,'password'=>'StrongPass1234','confirm_password'=>'StrongPass1234','role'=>$role]));}
$jordan=mk($pdo,'Jordan Lee','jordan@example.edu','Student');
$casey=mk($pdo,'Casey Park','casey@example.edu','Student');
$tutor=mk($pdo,'Taylor Lee','taylor@example.edu','Tutor');
$adminId=bootstrapAdministrator($pdo,'Admin One','admin@example.edu','AdminPass1234');$admin=findUserById($pdo,$adminId);

check((int)findStudentByEmail($pdo,'jordan@example.edu')['user_id']===(int)$jordan['user_id'],'L7-R01 Registration links the existing student row through students.user_id');
check(count(listRequestsForUser($pdo,$jordan))===2,'L7-R02 Student sees own requests');
check(count(listRequestsForUser($pdo,$casey))===0,'L7-R03 Student sees no other student requests');
check(count(listRequestsForUser($pdo,$admin))===countRows($pdo,'tutoring_requests'),'L7-R04 Administrator sees all requests');
try{listRequestsForUser($pdo,$tutor);check(false,'L7-R05 Tutor denied request list');}catch(RuntimeException $e){check(true,'L7-R05 Tutor denied request list');}
check(findRequestForUser($pdo,$jordan,$id2)!==false,'L7-R06 Owner can load own request');
check(findRequestForUser($pdo,$casey,$id2)===false,'L7-R07 Other student gets not found (ownership in SQL)');
check(findRequestForUser($pdo,$tutor,$id2)===false,'L7-R08 Tutor cannot load requests');
check(findRequestForUser($pdo,$admin,$id2)!==false,'L7-R09 Administrator can load any request');
check(canCreateRequest($casey)&&!canCreateRequest($tutor)&&!canCreateRequest($admin),'L7-R10 Only Students may create requests');

$spoof=$sample;$spoof['student_name']='Someone Else';$spoof['student_email']='jordan@example.edu';
$caseyReq=submitRequestForUser($pdo,$casey,$spoof);$cr=findRequest($pdo,$caseyReq);
check((int)$cr['student_user_id']===(int)$casey['user_id']&&$cr['student_name']==='Casey Park','L7-R11 Posted name/email ignored; identity comes from account');
try{submitRequestForUser($pdo,$tutor,$sample);check(false,'L7-R12 Tutor submission rejected');}catch(RuntimeException $e){check(true,'L7-R12 Tutor submission rejected');}
try{submitRequestForUser($pdo,$admin,$sample);check(false,'L7-R13 Administrator submission rejected');}catch(RuntimeException $e){check(true,'L7-R13 Administrator submission rejected');}

$before=findRequest($pdo,$id2);
try{reviseRequestForUser($pdo,$casey,$id2,$edit);check(false,'L7-R14 Non-owner edit rejected');}catch(RuntimeException $e){check(true,'L7-R14 Non-owner edit rejected');}
try{cancelRequestForUser($pdo,$casey,$id2);check(false,'L7-R15 Non-owner cancel rejected');}catch(RuntimeException $e){check(true,'L7-R15 Non-owner cancel rejected');}
check(findRequest($pdo,$id2)===$before,'L7-R16 Rejected edit/cancel changed nothing');
$adminEdit=$sample;$adminEdit['reason']='Administrator corrected this request.';
check(reviseRequestForUser($pdo,$admin,$caseyReq,$adminEdit),'L7-R17 Administrator can edit any Open request');
check(cancelRequestForUser($pdo,$jordan,$id2)&&findRequest($pdo,$id2)['status']==='Cancelled','L7-R18 Owner cancel works');
$quoted=$sample;$quoted['reason']='Help with "quotes" & <tags> in O\'Brien\'s form.';
$qid=submitRequestForUser($pdo,$casey,$quoted);
check(decodeTopic(findRequest($pdo,$qid)['topic'])['reason']===$quoted['reason'],'L7-R19 Quoted and special characters stored exactly');
check(escapeHtml('<b>"x"</b>')==='&lt;b&gt;&quot;x&quot;&lt;/b&gt;','L7-R20 escapeHtml still encodes output');

echo "Results: $passed passed, $failed failed".PHP_EOL;
exit($failed>0?1:0);
