<?php
/**
 * Uthenga — Admin Booking Portal Redirect
 * Backwards compatibility wrapper redirecting to admin/bookings.php
 */
require_once __DIR__ . '/config.php';
redirect(BASE_URL . 'admin/bookings.php');
?>
