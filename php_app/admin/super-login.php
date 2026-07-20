<?php
require_once __DIR__ . '/../config.php';

$redirect = uthenga_safe_redirect_url((string) ($_GET['redirect'] ?? ''), '');
redirect(BASE_URL . 'admin/login.php' . ($redirect !== '' ? '?redirect=' . urlencode($redirect) : ''));
