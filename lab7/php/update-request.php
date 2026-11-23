<?php
// UC01 save a revision. Lab 7: login, CSRF token and ownership are checked before any update.
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../services/requestService.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required');}
$user=requireRole(['Student','Administrator']);requireValidCsrf();
$id=filter_input(INPUT_POST,'request_id',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(400);exit('Invalid ID');}
$pdo=openDatabase();$r=findRequestForUser($pdo,$user,$id);if(!$r){http_response_code(404);exit('Not found');}
try{$ok=reviseRequestForUser($pdo,$user,$id,$_POST);if(!$ok){http_response_code(409);exit('Request not found or not Open');}setFlash('Request updated.');header('Location: request-details.php?request_id='.$id,true,303);exit;}
catch(InvalidArgumentException $e){http_response_code(422);exit(escapeHtml($e->getMessage()));}
catch(Throwable $e){error_log($e->getMessage());http_response_code(500);exit('Unable to update request');}
