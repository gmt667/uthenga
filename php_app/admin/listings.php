<?php
/**
 * Uthenga — Admin Listings Redirect Wrapper
 * Backwards compatibility redirecting to admin/events.php
 */
require_once __DIR__ . '/../config.php';
redirect(BASE_URL . 'admin/events.php');
?>
