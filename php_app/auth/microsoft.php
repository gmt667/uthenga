<?php
/**
 * Uthenga - Microsoft OAuth 2.0 Initiator
 * Redirects the user to Microsoft's authorization screen.
 */
require_once dirname(__DIR__) . '/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

if (MICROSOFT_CLIENT_ID === '' || MICROSOFT_CLIENT_SECRET === '') {
    redirect(BASE_URL . 'login.php');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_redirect_back'] = $_GET['redirect'] ?? '';

$params = http_build_query([
    'client_id'     => MICROSOFT_CLIENT_ID,
    'redirect_uri'  => MICROSOFT_REDIRECT_URI,
    'response_type' => 'code',
    'response_mode' => 'query',
    'scope'         => 'openid email profile User.Read',
    'state'         => $state,
    'prompt'        => 'select_account',
]);

redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . $params);
