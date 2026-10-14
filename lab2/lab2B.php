<?php
//calling a function 
function FormatName($first, $middle, $last)
{
    $first =ucfirst(strtolower($first));
    $middle =ucfirst(strtolower($middle));
    $last =ucfirst(strtolower($last));

    return $first . ' ' . $middle . ' ' . $last;
}
$displayName = FormatName('AMy', 'Mae', 'PeterSen');
echo $displayName;
echo '<br>';


//return an array
function cleanNameParts($first, $middle, $last) {
    return[  //creates an array
        ucfirst(strtolower($first)),
        ucfirst(strtolower($middle)),
        ucfirst(strtolower($last))
    ];
    
}
$nameParts = cleanNameParts('AmY', 'MAE', 'peterSeN');
echo $nameParts[0];
echo '<br>';

//local and global variables 
$first = "Amy";
$last ="Petersen";

function buildFullName()
{
    global $first, $last;  //global key to grab the variables from outside the function
    return "$first $last";
}
echo buildFullName();
echo '<br>';

function buildFullName2($first, $last) //sending global variables into the parameters 
{
    return "$first $last";
}
echo buildFullName2($first, $last);
echo '<br>';


//static variables
function nextTicketNumber()
{
    static $number =0; //static keeps the value between calls 
    $number++;
    return $number;
}
echo nextTicketNumber();
echo nextTicketNumber();
echo '<br>';

//class
class User
{
    public $name;
    public $email;

    public function display()
    {
        return 'User: ' . $this->name;
    }
}

$user = new User();
$user->name = "Amy P";
$user->email = "123@123.com";

echo $user->display();
echo '<br>';

//constructor
class User2
{
    public $name;
    private $passwordHash;

    public function __construct($name, $password)
    {
        $this->name = $name;
        $this->passwordHash = 
            password_hash($password, PASSWORD_DEFAULT);
    }
}

$user= new User2("Amy P", 'temporary-password');
print_r($user);
echo '<br>';

//const doesnt change its value after its assigned
//password_hash() hashes a password
//password_verify() checks a login attempt 



?>