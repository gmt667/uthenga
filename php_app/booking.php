<?php
/**
 * Uthenga — Customer Booking Portal Redirect
 * Backwards compatibility wrapper redirecting to the customer dashboard
 */
require_once __DIR__ . '/config.php';
redirect(BASE_URL . 'dashboard.php');
?>
