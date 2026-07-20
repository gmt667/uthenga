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

$existingUser = dbQueryOne('SELECT * FROM users WHERE email = ?', [$microsoftEmail]);

if ($existingUser) {
    if (in_array($existingUser['role'], ADMIN_ROLES, true)) {
        redirect(BASE_URL . 'login.php?oauth_error=admin_not_allowed');
    }
    if (!$existingUser['is_approved']) {
        redirect(BASE_URL . 'login.php?suspended=1');
    }

    $userId      = $existingUser['id'];
    $userName    = $existingUser['name'];
    $userRole    = $existingUser['role'];
    $userBalance = $existingUser['balance'];
    $userAvatar  = $existingUser['avatar'] ?? '';
} else {
    $userId      = generateId('U');
    $userName    = $microsoftName;
    $userRole    = ROLE_CUSTOMER;
    $userBalance = 0;
    $userAvatar  = '';

    dbExecute(
        'INSERT INTO users (id, name, email, password_hash, role, avatar, is_approved, joined_date)
         VALUES (?, ?, ?, ?, ?, ?, 1, CURDATE())',
        [$userId, $microsoftName, $microsoftEmail, '', ROLE_CUSTOMER, null]
    );

    dbExecute(
        'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
        [$userId, $microsoftName, ROLE_CUSTOMER, 'Microsoft OAuth Registration',
         "New customer account via Microsoft OAuth: $microsoftEmail"]
    );
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

if (in_array($userRole, VENDOR_ROLES, true)) {
    redirect($redirectBack ?: BASE_URL . 'vendor/dashboard.php');
}

redirect($redirectBack ?: BASE_URL . 'dashboard.php');
