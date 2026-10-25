<?php
$timeSlots = ["Morning", "Afternoon", "Evening"];

$request = [
    "course" => "IS 6465",
    "topic" => "PHP arrays",
    "status" => "Pending review",
];

$requests = [
    [
        "student" => "Amina",
        "course" => "IS 6465",
        "status" => "Pending review",
    ],
    [
        "student" => "Diego",
        "course" => "IS 6495",
        "status" => "Matched",
    ],
];

$courses = ["IS 6465", "IS 6495", "IS 6570"];
$escape = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
};
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP array practice</title>
</head>

<body>
    <main>
        <h1>PHP array practice</h1>

        <section>
            <h2>Indexed array: time slots</h2>
            <p>Index 0: <?php echo $escape($timeSlots[0]); ?></p>
            <p>Index 1: <?php echo $escape($timeSlots[1]); ?></p>
        </section>

        <section>
            <h2>Associative array: tutoring request</h2>
            <p>Topic: <?php echo $escape($request["topic"]); ?></p>
        </section>

        <section>
            <h2>Multidimensional array: tutoring requests</h2>
            <ul>
                <?php foreach ($requests as $tutoringRequest): ?>
                    <li>
                        <?php echo $escape($tutoringRequest["student"]); ?> —
                        <?php echo $escape($tutoringRequest["course"]); ?> —
                        <?php echo $escape($tutoringRequest["status"]); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section>
            <h2>Approved courses</h2>
            <select aria-label="Approved courses">
                <?php foreach ($courses as $course): ?>
                    <?php $safeCourse = $escape($course); ?>
                    <option value="<?php echo $safeCourse; ?>"><?php echo $safeCourse; ?></option>
                <?php endforeach; ?>
            </select>
        </section>
    </main>
</body>

</html>
