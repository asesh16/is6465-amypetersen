<?php

require_once 'lab2B.php';

//expression
$x = -5;
$y = 3 *(abs(2 * $x) + 4);
echo $y;
echo '<br>';

//boolean
var_dump(2 > 1); //true
var_dump(2 == 3); //false
var_dump("5" == 5); //true
var_dump("5" === 5); //false
echo '<br>';

//unary operators
$c = 5;
$d = -$c; //unary minus
$e = +$c; 
$f = ++$c; //unary plus 
echo $d;
echo '<br>';
echo $e;
echo '<br>';
echo $f;
echo '<br>';

//precedence
$a = 2 + 3 * 4;
$b = (2 + 3) * 4;
echo $a;
echo '<br>';
echo $b;
echo '<br>';

//associativity
$level = $score = $time = 0;
echo $level;
echo '<br>';
echo $score;
echo '<br>';
echo $time;
echo '<br>';

//equality and assignment
$a = 5;
$b = 5;
var_dump($a == $b); //true
var_dump($a === $b); //false
$c = 10;
$d = 20; 
var_dump($c == $d); //false
var_dump($c === $d); //false
echo '<br>'; 

//logical operators
// && = AND = both conditions true 
// || = OR = at least one condition is true 
// != NOT = condition is false
// xor = exclusive OR = exactly one condition is true 
$a = 1;
$b =0;
$c = 1;
var_dump($a && $b); //false
var_dump($a || $b); //true
var_dump(!$a); //false
var_dump(!$b); //true
echo '<br>';

//conditionals 
// if, if else, if elseif else, switch (match exact values), ternary (return one of 2 short values)
//if
$gpa = 3.5;

if ($gpa >= 3.7) {
    echo "student gets a scholarship";
}
else{
    echo "student doesnt get scholarship";
}
echo '<br>';

//else if
$gpa = 3.6;

if ($gpa >= 3.7) {
    echo "student gets a scholarship";
} elseif ($gpa >= 3.5){
    echo "student gets a partial scholarship";
} 
else{
    echo "student doesnt get scholarship";
}
echo '<br>';


//switch
$status = "pending";
switch ($status) {
    case "approved":
    echo "access granted";
    break;
    case "pending":
    echo "review in progress";
    break;
    default:
    echo "access denied";
    break;
}
echo '<br>';

//looping 
//while, do while, and for 
//while
$fuel = 10;
while($fuel >1) {
    echo "there is enough fuel <br>";
    $fuel--;
}
echo '<br>';

//for 
for ($count = 1; $count <= 12; $count++){
echo $count . " times 12 is " .
($count * 12) . "<br>";
}



?>