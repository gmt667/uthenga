<?php
/**
 * Uthenga — Real-Time Auth Field Checking API
 * Handles live inline checks for email existence, role detection, and account status.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/restoration_helpers.php';
require_once __DIR__ . '/../../includes/auth_check.php';

try {
    $email = strtolower(trim((string)($_GET['email'] ?? $_POST['email'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid email format'
        ]);
        exit;
    }

    $user = uthenga_auth_find_user_by_email($email);

    if (!$user) {
        echo json_encode([
            'success' => true,
            'exists'  => false,
            'message' => 'No account found with this email address.'
        ]);
        exit;
    }

    echo json_encode([
        'success'     => true,
        'exists'      => true,
        'role'        => (string)$user['role'],
        'is_approved' => (bool)$user['is_approved'],
        'name'        => (string)$user['name'],
        'message'     => 'Account found'
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
