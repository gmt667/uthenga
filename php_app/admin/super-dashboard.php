<?php
/** Legacy Super Admin dashboard bookmark: canonical Overview is shared. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin([ROLE_SUPER_ADMIN]);
redirect(BASE_URL . 'admin/dashboard.php');
