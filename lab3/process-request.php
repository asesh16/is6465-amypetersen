<?php

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Please submit the tutoring request form using POST.");
}

$courseInput = $_POST["course"] ?? "";
$topicInput = $_POST["topic"] ?? "";
$preferredDateInput = $_POST["preferreddate"] ?? "";
$preferredTimeInput = $_POST["preferredtime"] ?? "";

$course = is_string($courseInput) ? trim($courseInput) : "";
$topic = is_string($topicInput) ? trim($topicInput) : "";
$preferredDate = is_string($preferredDateInput) ? $preferredDateInput : "";
$preferredTime = is_string($preferredTimeInput) ? $preferredTimeInput : "";

$errors = [];
$allowedCourses = ["IS 6465", "IS 6495", "IS 6570"];
$allowedTimes = ["Morning", "Afternoon", "Evening"];

if (!in_array($course, $allowedCourses, true)) {
    $errors[] = "Select a valid course.";
}

if (!in_array($preferredTime, $allowedTimes, true)) {
    $errors[] = "Select a valid preferred time.";
}

$topicLength = preg_match_all('/./us', $topic);
if ($topic === "" || $topicLength === false || $topicLength > 120) {
    $errors[] = "Enter a topic of 1 to 120 characters.";
}

$preferredDateTime = DateTimeImmutable::createFromFormat("!Y-m-d", $preferredDate);
$validDate = $preferredDateTime !== false
    && $preferredDateTime->format("Y-m-d") === $preferredDate;
$today = new DateTimeImmutable("today");

if (!$validDate) {
    $errors[] = "Enter a valid preferred date.";
} elseif ($preferredDateTime < $today) {
    $errors[] = "Preferred date cannot be in the past.";
}

$escape = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
};
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $errors ? "Request errors" : "Request received"; ?></title>
</head>

<body>
    <main>
        <?php if ($errors): ?>
            <h1>There were problems with your request</h1>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><a href="request-form.html">Return to the request form</a></p>
        <?php else: ?>
            <h1>Request received</h1>
            <p>Course: <?php echo $escape($course); ?></p>
            <p>Topic: <?php echo $escape($topic); ?></p>
            <p>Preferred date: <?php echo $escape($preferredDate); ?></p>
            <p>Preferred time: <?php echo $escape($preferredTime); ?></p>
            <p>Status: Pending review</p>
        <?php endif; ?>
    </main>
</body>

</html>
