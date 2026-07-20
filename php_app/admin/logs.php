<?php
require_once __DIR__ . '/../config.php';
redirect(BASE_URL . 'admin/audit-logs.php?' . $_SERVER['QUERY_STRING']);
