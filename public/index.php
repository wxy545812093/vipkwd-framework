<?php
define('DEBUG', 1);
define('VIPKWD_EXCEPTION', 1);
define('APP', 'DDXX-OA');
// !defined('WEBROOT') && 
define('WEBROOT', realpath(dirname(__FILE__) . '/../'));
// !defined('PUBLIC_ROOT') && 
define('PUBLIC_ROOT', WEBROOT . '/public');
include '../framework/run.php';
// \Vipkwd\SDK\OAuth\Storage\Session::start();
// \Vipkwd\SDK\OAuth\Storage\Session::name($_CONFIG['session_name']);
$vkFramework->flushVar("OAuthAction", \Vipkwd\SDK\OAuth\Action::class);
$vkFramework->flushVar("OAuthInstance", new \Vipkwd\SDK\OAuth\OAuth([
    'client_id' => cdrConfiGet('oauth.client_id', ''),
    'client_secret' => cdrConfiGet('oauth.client_secret', ''),
    'redirect_uri' => cdrConfiGet('oauth.redirect_uri', ''),
    'scope' => cdrConfiGet('oauth.scope', 'basic'),
    'state' => isset($_GET['state']) ? urlencode(trim($_GET['state'])) : cdrConfiGet('oauth.state', ''),
]));

if (isset($_GET['logout'])) {
    //退出指定Token, 防止多开页面时的(登录与退出)互斥
    if ($_GET['logout'] == $vkFramework->OAuthAction::getAccessToken()) {
        $vkFramework->OAuthAction::deleteOAuthUser();
        $vkFramework->OAuthAction::deleteOAuthToken();
    }
}

$vkFramework->start();