<?php
/**
 * Uthenga - Microsoft OAuth 2.0 Callback Handler
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';

$state        = $_GET['state'] ?? '';
$sessionState = $_SESSION['oauth_state'] ?? '';
$code         = $_GET['code'] ?? '';
$oauthError   = $_GET['error'] ?? '';

unset($_SESSION['oauth_state']);

if ($oauthError) {
    redirect(BASE_URL . 'login.php?oauth_error=' . urlencode($oauthError));
}

if (MICROSOFT_CLIENT_ID === '' || MICROSOFT_CLIENT_SECRET === '') {
    redirect(BASE_URL . 'login.php');
}

if (empty($code) || empty($state) || !hash_equals($sessionState, $state)) {
    redirect(BASE_URL . 'login.php?oauth_error=invalid_state');
}

$tokenEndpoint = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
$ch = curl_init($tokenEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => MICROSOFT_CLIENT_ID,
        'client_secret' => MICROSOFT_CLIENT_SECRET,
        'code'          => $code,
        'redirect_uri'  => MICROSOFT_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
        'scope'         => 'openid email profile User.Read',
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$tokenResponse = curl_exec($ch);
$httpCode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenResponse === false || $httpCode !== 200) {
    redirect(BASE_URL . 'login.php?oauth_error=token_exchange_failed');
}

$tokenData   = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? '';

if (empty($accessToken)) {
    redirect(BASE_URL . 'login.php?oauth_error=no_access_token');
}

$profileEndpoint = 'https://graph.microsoft.com/v1.0/me?' . http_build_query([
    '$select' => 'id,displayName,mail,userPrincipalName,givenName,surname',
]);
$ch = curl_init($profileEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$profileResponse = curl_exec($ch);
$httpCode        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($profileResponse === false || $httpCode !== 200) {
    redirect(BASE_URL . 'login.php?oauth_error=profile_fetch_failed');
}

$profile          = json_decode($profileResponse, true) ?: [];
$microsoftId      = trim((string)($profile['id'] ?? ''));
$microsoftEmail   = strtolower(trim((string)($profile['mail'] ?? $profile['userPrincipalName'] ?? '')));
$microsoftName    = trim((string)($profile['displayName'] ?? 'Microsoft User'));

if ($microsoftEmail === '') {
    $microsoftEmail = $microsoftId !== '' ? $microsoftId . '@microsoft.local' : '';
}

if ($microsoftId === '' || $microsoftEmail === '') {
    redirect(BASE_URL . 'login.php?oauth_error=missing_profile_data');
}

$registerRole = strtolower(trim((string)($_SESSION['oauth_register_role'] ?? 'customer')));
if (!in_array($registerRole, ['customer', 'vendor'], true)) {
    $registerRole = 'customer';
}
$userIsApproved = 1;
$existingUser = dbQueryOne('SELECT * FROM users WHERE email = ?', [$microsoftEmail]);

if ($existingUser) {
    if (in_array($existingUser['role'], ADMIN_ROLES, true)) {
        redirect(BASE_URL . 'login.php?oauth_error=admin_not_allowed');
    }
    if ($registerRole === 'vendor' && $existingUser['role'] !== ROLE_VENDOR) {
        dbExecute('UPDATE users SET role = ?, is_approved = 0 WHERE id = ?', [ROLE_VENDOR, $existingUser['id']]);
        $existingUser['role'] = ROLE_VENDOR;
        $existingUser['is_approved'] = 0;
    }
    if (!$existingUser['is_approved']) {
        if (($existingUser['role'] ?? '') === ROLE_VENDOR) {
            $userId      = $existingUser['id'];
            $userName    = $existingUser['name'];
            $userRole    = ROLE_VENDOR;
            $userBalance = $existingUser['balance'];
            $userAvatar  = $existingUser['avatar'] ?? '';
            $userIsApproved = 0;
        } else {
            redirect(BASE_URL . 'login.php?suspended=1');
        }
    }

    $userId      = $existingUser['id'];
    $userName    = $existingUser['name'];
    $userRole    = $existingUser['role'];
    $userBalance = $existingUser['balance'];
    $userAvatar  = $existingUser['avatar'] ?? '';
} else {
    $userId      = generateId('U');
    $userName    = $microsoftName;
    $userRole    = $registerRole === 'vendor' ? ROLE_VENDOR : ROLE_CUSTOMER;
    $userBalance = 0;
    $userAvatar  = '';
    $isApproved  = $userRole === ROLE_VENDOR ? 0 : 1;
    $userIsApproved = $isApproved;
    $hasJoinedDate = uthenga_column_exists('users', 'joined_date');

    if ($hasJoinedDate) {
        dbExecute(
            'INSERT INTO users (id, name, email, password_hash, role, avatar, is_approved, joined_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())',
            [$userId, $microsoftName, $microsoftEmail, '', $userRole, null, $isApproved]
        );
    } else {
        dbExecute(
            'INSERT INTO users (id, name, email, password_hash, role, avatar, is_approved)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $microsoftName, $microsoftEmail, '', $userRole, null, $isApproved]
        );
    }

    dbExecute(
        'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
        [$userId, $microsoftName, $userRole, 'Microsoft OAuth Registration',
         "New " . strtolower($userRole) . " account via Microsoft OAuth: $microsoftEmail"]
    );

    if ($userRole === ROLE_VENDOR) {
        try {
            dbExecute(
                'INSERT INTO vendor_profiles (vendor_id, business_name, phone, address, city, category, description, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $microsoftName, null, null, null, 'Other', 'Vendor account created via Microsoft OAuth', 'pending']
            );
        } catch (Throwable $e) {
            error_log('Uthenga OAuth: vendor_profiles insert skipped: ' . $e->getMessage());
        }
    }
}

try {
    $link = dbQueryOne(
        'SELECT id FROM social_accounts WHERE provider = ? AND provider_user_id = ?',
        ['microsoft', $microsoftId]
    );
    if (!$link) {
        dbExecute(
            'INSERT INTO social_accounts (user_id, provider, provider_user_id, provider_email)
             VALUES (?, ?, ?, ?)',
            [$userId, 'microsoft', $microsoftId, $microsoftEmail]
        );
    }
} catch (Exception $e) {
    error_log('Uthenga OAuth: social_accounts insert skipped: ' . $e->getMessage());
}

session_regenerate_id(true);
$_SESSION['user_id']      = $userId;
$_SESSION['user_name']    = $userName;
$_SESSION['user_role']    = $userRole;
$_SESSION['user_email']   = $microsoftEmail;
$_SESSION['user_balance'] = $userBalance;
$_SESSION['user_avatar']  = $userAvatar;

try {
    dbExecute(
        'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
        [$userId, $userName, $userRole, 'Microsoft OAuth Login',
         'Signed in via Microsoft OAuth from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')]
    );
} catch (Exception $e) {
    // non-fatal
}

$redirectBack = $_SESSION['oauth_redirect_back'] ?? '';
unset($_SESSION['oauth_redirect_back']);
unset($_SESSION['oauth_register_role']);

if (in_array($userRole, VENDOR_ROLES, true)) {
    redirect($redirectBack ?: ($userIsApproved ? BASE_URL . 'vendor/dashboard.php' : BASE_URL . 'vendor/pending.php'));
}

redirect($redirectBack ?: BASE_URL . 'dashboard.php');
