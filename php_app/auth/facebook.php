<?php
/**
 * Uthenga — Facebook OAuth 2.0 Initiator
 * Redirects the user to Facebook's authorization screen.
 */
require_once dirname(__DIR__) . '/config.php';

// If already logged in, redirect away
if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

if (FACEBOOK_APP_ID === '' || FACEBOOK_APP_SECRET === '') {
    redirect(BASE_URL . 'login.php');
}

// CSRF state token stored in session to protect the callback
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state']        = $state;
$_SESSION['oauth_redirect_back'] = $_GET['redirect'] ?? '';

$params = http_build_query([
    'client_id'     => FACEBOOK_APP_ID,
    'redirect_uri'  => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'email,public_profile',
    'state'         => $state,
]);

redirect('https://www.facebook.com/v18.0/dialog/oauth?' . $params);
