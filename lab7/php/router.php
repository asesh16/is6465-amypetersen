<?php
// Router for the PHP built-in server (php -S 0.0.0.0:8000 php/router.php).
// Lab 7: also blocks direct browser access to internal folders (config, includes,
// repositories, services, tests) in addition to the database folder and data files.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';
if(preg_match('~^/(?:database|config|includes|repositories|services|tests)(?:/|$)~i',$path)||preg_match('~\.(?:db|sqlite|sql|md)$~i',$path)){http_response_code(404);exit('Not found');}
if($path==='/' )$path='/index.php';
$base=realpath(dirname(__DIR__));
$full=realpath($base.$path);
if($full===false||substr($full,0,strlen($base.DIRECTORY_SEPARATOR))!==$base.DIRECTORY_SEPARATOR||!is_file($full)){http_response_code(404);exit('Not found');}
return false;
