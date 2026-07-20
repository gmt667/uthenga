<?php
/**
 * Uthenga — Google OAuth 2.0 Initiator
 * Redirects the user to Google's consent/authorization screen.
 */
require_once dirname(__DIR__) . '/config.php';

// If already logged in, redirect away
if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    redirect(BASE_URL . 'login.php');
}

// CSRF state token stored in session to protect the callback
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state']        = $state;
$_SESSION['oauth_redirect_back'] = $_GET['redirect'] ?? '';

$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
