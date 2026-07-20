<?php
/**
 * Uthenga — Facebook OAuth 2.0 Callback Handler
 * Called by Facebook after the user grants or denies access.
 * Exchanges the auth code for tokens, fetches profile, then creates/updates
 * the user account and establishes a session.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';

// Validate State (CSRF protection)
$state        = $_GET['state']            ?? '';
$sessionState = $_SESSION['oauth_state']  ?? '';
$code         = $_GET['code']             ?? '';
$oauthError   = $_GET['error']            ?? '';

// Consume the state immediately
unset($_SESSION['oauth_state']);

if ($oauthError) {
    redirect(BASE_URL . 'login.php?oauth_error=' . urlencode($oauthError));
}

if (FACEBOOK_APP_ID === '' || FACEBOOK_APP_SECRET === '') {
    redirect(BASE_URL . 'login.php');
}

if (empty($code) || empty($state) || !hash_equals($sessionState, $state)) {
    redirect(BASE_URL . 'login.php?oauth_error=invalid_state');
}

// Exchange Authorization Code for Access Token
$ch = curl_init('https://graph.facebook.com/v18.0/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'redirect_uri'  => FACEBOOK_REDIRECT_URI,
    ]),
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

// Fetch Facebook User Profile
$ch = curl_init('https://graph.facebook.com/v18.0/me?' . http_build_query([
    'fields'       => 'id,name,email,picture.type(large)',
    'access_token' => $accessToken,
]));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$profileResponse = curl_exec($ch);
$httpCode        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($profileResponse === false || $httpCode !== 200) {
    redirect(BASE_URL . 'login.php?oauth_error=profile_fetch_failed');
}

$profile       = json_decode($profileResponse, true);
$facebookId    = $profile['id']    ?? '';
$facebookEmail = strtolower(trim($profile['email'] ?? ''));
$facebookName  = $profile['name']   ?? 'Facebook User';
$facebookAvatar= $profile['picture']['data']['url'] ?? '';

if (empty($facebookId)) {
    redirect(BASE_URL . 'login.php?oauth_error=missing_profile_data');
}

// If email is empty (e.g. registered with phone number only), use a fallback email
if (empty($facebookEmail)) {
    $facebookEmail = $facebookId . '@facebook.com';
}

// Find or Create User
$existingUser = dbQueryOne('SELECT * FROM users WHERE email = ?', [$facebookEmail]);

if ($existingUser) {
    // Block admins from using public OAuth
    if (in_array($existingUser['role'], ADMIN_ROLES, true)) {
        redirect(BASE_URL . 'login.php?oauth_error=admin_not_allowed');
    }
    // Block suspended accounts
    if (!$existingUser['is_approved']) {
        redirect(BASE_URL . 'login.php?suspended=1');
    }

    // Backfill avatar if missing
    if (!empty($facebookAvatar) && empty($existingUser['avatar'])) {
        dbExecute('UPDATE users SET avatar = ? WHERE id = ?', [$facebookAvatar, $existingUser['id']]);
    }

    $userId      = $existingUser['id'];
    $userName    = $existingUser['name'];
    $userRole    = $existingUser['role'];
    $userBalance = $existingUser['balance'];
    $userAvatar  = $existingUser['avatar'] ?: $facebookAvatar;

} else {
    // Create new customer — generateId produces a string like "U-ABCD1234"
    $userId      = generateId('U');
    $userRole    = ROLE_CUSTOMER;
    $userName    = $facebookName;
    $userBalance = 0;
    $userAvatar  = $facebookAvatar;

    dbExecute(
        'INSERT INTO users (id, name, email, password_hash, role, avatar, is_approved, joined_date)
         VALUES (?, ?, ?, ?, ?, ?, 1, CURDATE())',
        [$userId, $facebookName, $facebookEmail, '', ROLE_CUSTOMER, $facebookAvatar]
    );

    dbExecute(
        'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
        [$userId, $facebookName, ROLE_CUSTOMER, 'Facebook OAuth Registration',
         "New customer account via Facebook OAuth: $facebookEmail"]
    );
}

// Upsert social_accounts Link
try {
    $link = dbQueryOne(
        'SELECT id FROM social_accounts WHERE provider = ? AND provider_user_id = ?',
        ['facebook', $facebookId]
    );
    if (!$link) {
        dbExecute(
            'INSERT INTO social_accounts (user_id, provider, provider_user_id, provider_email)
             VALUES (?, ?, ?, ?)',
            [$userId, 'facebook', $facebookId, $facebookEmail]
        );
    }
} catch (Exception $e) {
    error_log('Uthenga OAuth: social_accounts insert skipped: ' . $e->getMessage());
}

// Create Session
session_regenerate_id(true);
$_SESSION['user_id']      = $userId;
$_SESSION['user_name']    = $userName;
$_SESSION['user_role']    = $userRole;
$_SESSION['user_email']   = $facebookEmail;
$_SESSION['user_balance'] = $userBalance;
$_SESSION['user_avatar']  = $userAvatar;

try {
    dbExecute(
        'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
        [$userId, $userName, $userRole, 'Facebook OAuth Login',
         'Signed in via Facebook OAuth from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')]
    );
} catch (Exception $e) { /* non-fatal */ }

// Redirect
$redirectBack = $_SESSION['oauth_redirect_back'] ?? '';
unset($_SESSION['oauth_redirect_back']);

if (in_array($userRole, VENDOR_ROLES, true)) {
    redirect($redirectBack ?: BASE_URL . 'vendor/dashboard.php');
} else {
    redirect($redirectBack ?: BASE_URL . 'dashboard.php');
}
