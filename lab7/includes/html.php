<?php
// Shared output encoding helper (moved here from requestService.php so that
// request pages and user pages can both use it without redeclaring it).
if (!function_exists('escapeHtml')) {
    function escapeHtml($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
