<?php
if (!defined('APP')) {
    exit;
}
date_default_timezone_set('PRC');
if (!isset($_SESSION))
    session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
// header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header('Access-Control-Allow-Methods:*');
header("Access-Control-Allow-Headers: X_Requested_With,userid,username,token,Content-Type,Authorization,X-Requested-With, origin, accept, host, date, cookie, cookie2");
// header("Access-Control-Allow-Headers: a, b, token, Content-Type");
header('P3P: CP="CAO PSA OUR"'); // Makes IE to support cookies

define('FRAMEWORK_PATH', realpath(__DIR__));
define('FRAMEWORK_LIB_PATH', FRAMEWORK_PATH . '/libs');
define('ROOT', realpath(FRAMEWORK_PATH . '/../'));
define('APP_PATH', ROOT . '/app');
define('TEMPLATE_PATH', ROOT . '/template');
define('PUBLIC_PATH', ROOT . '/public');
// 设置模板引擎参数
define('THINK_TEMPLATE', [
    'layout_on' => false,
    // 模板文件目录
    'view_path' => rtrim(TEMPLATE_PATH, '/') . '/',
    'view_suffix' => '.html', // 模板文件后缀
    'tpl_cache' => false, // 是否开启模板编译缓存,设为false则每次都会重新编译
    // 模板编译缓存目录（可写）
    'cache_path' => ROOT . '/runtime/',
    'driver' => '\\think\\Template'
]);

if (file_exists($autoloadFile = realpath(ROOT . '/vendor/autoload.php'))) {
    include_once $autoloadFile;
    if (defined('DEBUG') && DEBUG) {
        if (class_exists("Vipkwd\Utils\Debugger", true)) {
            if (method_exists(\Vipkwd\Utils\Debugger::class, 'default')) {
                \Vipkwd\Utils\Debugger::default(THINK_TEMPLATE['cache_path']);
            }
        }
    }
}

unset($autoloadFile);
// 尝试加载应用函数库
// include_once (FRAMEWORK_PATH . '/exception.php');
// 加载框架函数库
include_once (FRAMEWORK_PATH . '/functions.php');

if (file_exists(APP_PATH . '/functions.php')) {
    @include (APP_PATH . '/functions.php');
}
include FRAMEWORK_PATH . '/framework.php';
$vkFramework = new VKFramework(false);