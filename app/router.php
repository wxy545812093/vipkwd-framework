<?php
if (!defined('APP')) {
    exit;
}
return [
    '/' => 'home.index.index',
    '/oauth/vk/callback GET' => 'home.oauth.login',
];
