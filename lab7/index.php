<?php
// Lab 7: the dashboard requires login and sends anonymous visitors to the login page.
header('Location: php/dashboard.php', true, 302);
exit;
