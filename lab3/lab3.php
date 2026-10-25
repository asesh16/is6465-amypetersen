<?php
//indexed arrays 
$timeslots = ["Morning", "Afternoon", "Evening"];
echo $timeslots[0]; // Output: Morning
echo $timeslots[1]; // Output: Afternoon
echo $timeslots[2]; // Output: Evening
echo '<br>';

//associative arrays
$request = [
    "course" => "IS 6465",
    "topic" => "PHP Arrays",
    "status" => "Pending Review"
];
echo $request["topic"]; //PHP Arrays
echo '<br>';

//multidimensional arrays
$requests = [
    ["student"=>"Amina", "course"=>"IS 6465", "status"=>"Pending Review"],
    ["student"=>"Diego", "course"=>"IS 6495", "status"=>"Matched"],
];
print_r($requests[0]); 
echo '<br>';

//foreach loop
$courses = ["IS 6465", "IS 6495", "IS 6570"];

foreach ($courses as $course){
    echo "<option value=''.htmlspecialchars($course, ENT_QUOTES, 'UTF-8').''>";
    echo htmlspecialchars($course, ENT_QUOTES, "UTF-8")."</option>";
}
echo '<br>';

//18:55
?>