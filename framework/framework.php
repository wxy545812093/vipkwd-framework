<?php

use flight\Engine;
use \think\Template;
use \Exception as Except;
use Vipkwd\Utils\System\File as VKFile;

class VKFramework
{
    private static $injectFn = ["assign", "display", "tplExists", 'error'];
    private static $started;
    private $engine = null;
    private $autoloadList = [];
    private $config = [];
    private $classMapList = [];

    private $_varData = [];

    public function __construct(bool $autoStartApp = true)
    {
        $this->configParse();

        $this->engine = new Engine;
        // 注册Think template
        $this->engine->register('view', THINK_TEMPLATE['driver'], [THINK_TEMPLATE], function (Template $template) {});

        $this->_setFlightVar();
        $this->_relationPathInvoke();
        $this->_reflection();
        if ($autoStartApp)
            $this->start();
    }

    public function __get(string $key)
    {
        if (in_array($key, ['autoloadList', 'engine', 'config', 'classMapList'])) {
            return isset($this->$key) ? $this->$key : null;
        }
        if ($this->flushVarHas($key))
            return $this->_varData[$key];
        return null;
    }

    public function flushVarHas($key)
    {
        return isset($this->_varData[$key]);
    }

    public function flushVar($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->_varData[$k] = $v;
            }
            return true;
        }
        $this->_varData[$key] = $value;
    }

    public function getVar($key = null)
    {
        return $this->engine->get($key);
    }

    public function start()
    {
        if (!static::$started)
            $this->engine->start();
        static::$started = true;
    }

    public function mapDisplay($template, $data = [])
    {
        $template = str_replace(['\\', '/'], '.', $template);
        //定位模块子模板目录
        $template = explode('.', $template);
        if (count($template) < 3) {
            array_unshift($template, $this->engine->get('module'));
        }
        $template = implode('/', $template);

        // 注册所有模板变量添加data节点（注意：think模板引擎无data节点，此操作仅仅是保持与flight模板引擎变量结果对齐)
        //think标准:  viewer->assign(['a'=> 1])  模板使用{$a}
        //flight标准:  viewer->set('a', 1)  模板使用：{$data['a']}

        // 使tp模板兼容flight-view变量语法方案：
        // (!empty($data)) && $this->engine->view()->assign(['data'=>$data]);

        !empty($data) && $this->engine->view()->assign($data);

        $global_vars = [];
        //解析全局模板变量
        $GLOBAL_TEMPLATE_VARS = $this->engine->get('GLOBAL_TEMPLATE_VARS');
        if (is_array($GLOBAL_TEMPLATE_VARS)) {
            foreach ($GLOBAL_TEMPLATE_VARS as $k => $v) {
                $global_vars[$k] = $v;
            }
            unset($k, $v);
        }
        $this->engine->view()->assign($global_vars);
        $this->engine->view()->fetch($template);
        unset($template, $data, $global_vars, $GLOBAL_TEMPLATE_VARS);
    }

    public function mapTplExists($template, string $suffix = null)
    {
        $template = str_replace('..', '{@--SPACE--#}', "$template");
        $template = str_replace('.', '/', $template);
        $template = str_replace("{@--SPACE--#}", '..', $template);
        $template = trim(str_replace('//', '/', $template), '/');
        $suffix = $suffix ?? trim(THINK_TEMPLATE['view_suffix'], '.');
        return file_exists(TEMPLATE_PATH . "/{$template}.{$suffix}");
    }

    public function mapError($exception, $whoops = null)
    {
        if ($this->engine->get('debug') && $whoops) {
            return defined('VIPKWD_EXCEPTION') ? handleException($exception) : $whoops->handleException($exception);
        }
        // echo "系统错误";
    }

    private function _listen(array $config = [])
    {
        //全局类侦测(自动注册)
        // $this->engine->moduleInvoke(FRAMEWORK_PATH . '/class');
        $this->_invokeModule(FRAMEWORK_PATH . '/class');

        //创建Home 默认应用
        // $this->engine->moduleCheck();
        $this->_checkModule();

        // $this->engine->map('notFound', function () {
        //     $this->engine->fwCommon()->error(404);
        // });
        // $this->engine->route('/403', function () {
        //     $this->engine->fwCommon()->error(403);
        // });

        //监听路由配置
        $this->_invokeRoute();
        // dump($this);
    }

    /**
     * 
     * @throws \Exception
     * @return void|null
     */
    private function _invokeModule(string $class)
    {
        if (!is_dir($class) && !is_file($class)) {
            return;
        }
        $class = is_file($class) ? [$class] : glob(rtrim($class, '/') . "/*.php");
        foreach ($class as $file) {
            $file = realpath($file);
            try {
                $this->_moduleInvoker($file);
            } catch (Except $e) {
                throw new Except($e->getMessage(), $e->getCode());
            }
        }
    }

    private function _moduleInvoker($classFile)
    {
        try {
            $pathinfo = pathinfo($classFile);
            // $claSs = str_replace('.php', '', $pathinfo['basename']);
            $claSs = $pathinfo['filename'];
            $ucfirst_class = ucfirst($claSs);
            $name = lcfirst($claSs);
            $d = include ($classFile);
            if (!class_exists($ucfirst_class, false)) {
                throw new Except("Unable to load <{$ucfirst_class}> class in {$classFile}");
                // trigger_error("Unable to load class: {$ucfirst_class}({$pathinfo['basename']})", E_USER_WARNING);
            }

            $this->classMapList[$name] = [
                "name" => $name,
                "class" => $ucfirst_class,// . "::class",
                "params" => [$this],
                "callback" => function ($fn) {},
                // "instance" => (new \ReflectionClass($ucfirst_class))->newInstance($this)
            ];
            $this->engine->register($name, $ucfirst_class);
        } catch (Except $e) {
            throw new Except($e->getMessage(), $e->getCode());
        }
        $this->autoloadList[] = $classFile;
    }

    private function _checkModule($module = 'home')
    {
        if (!file_exists(APP_PATH . '/router.php')) {
            file_put_contents(APP_PATH . '/router.php', file_get_contents(FRAMEWORK_PATH . '/tpl/router.tpl.php'));
        }
        $module_path = APP_PATH . '/' . $module;
        !is_dir($module_path . '/controller') && mkdir($module_path . '/controller', 0777, true);
        !is_dir(TEMPLATE_PATH . '/' . $module) && mkdir(TEMPLATE_PATH . '/' . $module, 0777, true);

        $globs = glob($module_path . '/controller/*.php');
        // !file_exists($module_path . '/controller/index.php')
        if (count($globs) == 0) {
            file_put_contents($module_path . '/controller/index.php', file_get_contents(FRAMEWORK_PATH . '/tpl/controller.tpl.php'));
            $routePath = APP_PATH . '/router.php';
            $routeList = include ($routePath);
            $router = ($module != 'home') ? "/$module" : "/";
            if (!array_key_exists($router, $routeList)) {
                $routeList[$router] = [
                    'action' => $module . '.index.index'
                ];
                $routeList = var_export($routeList, true);
                $routeList = preg_replace("/[\s]/", ' ', $routeList);
                $routeList = preg_replace("/\ \ +/", '', $routeList);
                $routeList = preg_replace("/array\ \(/", '[', $routeList);
                $routeList = preg_replace("/\)/", ']', $routeList);
                $routeList = preg_replace("/'\//", "\n\n    '/", $routeList);

                $routeList = preg_replace("/\d+\ ?=>\ ?/", '', $routeList);
                $routeList = preg_replace("/(,\])/", ']', $routeList);
                $routeList = str_replace('array(', '[', $routeList);
                $routeList = preg_replace("/=\>\[/", '=> [', $routeList);
                $routeList = str_replace(", ]", ",\n]", $routeList);

                $routeContent = '<?php' . PHP_EOL;
                $routeContent .= 'if(!defined(\'APP\')){exit;}' . PHP_EOL;
                $routeContent .= 'return ' . $routeList . ';';
                $routeContent = preg_replace("@\=(\ +)?\>(\r\n)(\ +)?array@i", '=> array', $routeContent);
                file_put_contents($routePath, $routeContent);
            }
        }
    }

    private function _invokeRoute()
    {
        $routers = [];
        //全局路由
        if (file_exists(ROOT . '/router.php')) {
            $routers = @include (ROOT . '/router.php');
        }
        //应用路由
        if (file_exists(APP_PATH . '/router.php')) {
            $routers = array_merge($routers, @include (APP_PATH . '/router.php'));
        }
        foreach ($routers as $pattern => $options) {
            // $this->engine->route("GET|POST /login", function () {
            //     $this->engine->user()->login();
            // });
            $pattern = explode(' ', $pattern);

            //默认支持8种http请求方式(以下标追加的方式填补 路由表中 没有配置method的情况)
            $pattern[] = '';//'GET|POST|OPTIONS|DELETE|PUT|HEAD|TRACE|CONNECT';
            $pattern[1] = trim(str_replace(' ', '', $pattern[1]));

            // 检测action 是否存在快捷配置
            if (is_string($options)) {
                $options = ['action' => $options];
            }
            //domain 检测
            $options = $this->_routeDomainInvoke($options);
            // devdump($this->autoloadList); devdump($options,1);
            $router = trim($pattern[1] . ' ' . trim($pattern[0]));
            try {
                $route = $this->engine->route($router, function () use ($options, $pattern, $router) {

                    $route_arguments = func_get_args();
                    // dump($route_arguments, $options, $pattern, $router);exit;

                    if (isset($options['action'])) {

                        $args = func_get_args();
                        if (!isset($options['args']) || !is_array($options['args'])) {
                            $options['args'] = array();
                        }

                        if (!in_array('route', $options['args'])) {
                            //$options['args'][] = 'route';
                            array_pop($args);
                        }

                        // TODO 用foreach替代combine, 防止combine时各数组因子键值对个数不对齐的bug
                        // $params = array_combine($options['args'], $args);
                        $params = [];
                        foreach ($options['args'] as $idx => $field) {
                            $params[$field] = isset($args[$idx]) ? $args[$idx] : null;
                        }

                        $action = explode('.', $options['action']);
                        if (count($action) == 2) {
                            array_unshift($action, 'home');
                        } elseif (count($action) == 1) {
                            array_unshift($action, 'index');
                            array_unshift($action, 'home');
                        } elseif (count($action) == 0) {
                            $action = ["home", "index", "index"];
                        }

                        $module = lcfirst($action[0]);
                        $controller = lcfirst($action[1]);
                        $action = lcfirst($action[2]);

                        //绑定模块名到模板
                        $this->engine->set('map', $options['action']);
                        $this->engine->set('module', $module);
                        $this->engine->set('controller', $controller);
                        $this->engine->set('action', $action);
                        $this->engine->set('route', $pattern[0]);
                        $this->engine->set('methods', explode('|', $pattern[1]));
                        $this->_checkModule($module);

                        //项目模块侦测
                        // try{
                        if (file_exists(APP_PATH . "/{$module}/functions.php")) {
                            include_once (APP_PATH . "/{$module}/functions.php");
                        }
                        //自动加载并注册项目模块类
                        $this->_invokeModule(APP_PATH . "/{$module}/controller");

                        if (isset($options['before']) && is_callable($options['before'])) {
                            // $result = call_user_func_array($options['before'], array_merge($params, ['app'=> $this->engine]));
                            // if(is_array($result)) $params = $result;
                            call_user_func_array($options['before'], [array_merge($params, ['app' => &$this->engine])]);
                        }

                        try {
                            $ref = new \ReflectionClass($this->classMapList[$controller]['class']);
                            $ref->newInstance(...[])->$action($params);

                            // call_user_func_array([$this->engine->$controller(), $action], [$params]);
                        } catch (Except $exception) {
                            $message = $exception->getMessage();
                            if (strripos($message, 'must be a mapped method.')) {
                                $message = "Maybe... {APP_PATH}/{$module}/{$controller}:class is not exists !";
                                // $message = ($controller . " {$module} constroller is not exists!");
                            }

                            // \Vipkwd\Utils\Dev::dump(debug_backtrace(),1);
                            throw new Except($message, $exception->getCode());
                        }

                        // if($this->engine->request()->method == 'OPTIONS'){
                        // 	send_http_status(204);die;
                        // }

                        if (isset($options['after']) && is_callable($options['after'])) {
                            call_user_func_array($options['after'], [array_merge($params, ['app' => &$this->engine])]);
                        }
                        unset($options, $params, $classList, $action, $controller);

                    } else {
                        $this->engine->fwCommon()->error(404);
                    }

                }, true, isset($options['alias']) ? $options['alias'] : '');

            } catch (Except $e) {

                throw new Except($e->getMessage(), $e->getCode());
            }
        }

    }

    private function _routeDomainInvoke(array $options)
    {
        if (!array_key_exists("action", $options)) {
            $host = explode(":", $_SERVER['HTTP_HOST'])[0];
            $port = $_SERVER['SERVER_PORT'];
            foreach ($options as $router_domain => $domain_option) {
                //路由端口补全检测
                if (strrpos($router_domain, ":") === false) {
                    $router_domain .= ":80,{$router_domain}:443";
                }
                // dump([$router_domain, $host . ":" . $port]);

                //与路由表 授权域名不匹配
                if (!in_array("{$host}:{$port}", explode(',', $router_domain))) {
                    // dump($_SERVER,1);
                    // 拒绝请求
                    unset($router_domain, $domain_option);
                    continue;
                }
                // 域名匹配命中
                $options = $domain_option;
                if (is_string($options)) {
                    $options = ['action' => $options];
                }
                unset($router_domain, $domain_option);
                //停止其它路由匹配
                break;
            }
            unset($host, $port);
        }
        return $options;
    }

    private function _relationPathInvoke()
    {
        !is_dir(APP_PATH) && mkdir(APP_PATH, 0666, true);
        !is_dir(TEMPLATE_PATH) && mkdir(TEMPLATE_PATH, 0666, true);

        //win路径分隔符换成unix路径分隔符(否则win下mkdir时可能报 path路径无效)
        $runtimePath = str_replace('\\', '/', realpath(THINK_TEMPLATE['cache_path']));
        if (!is_dir($runtimePath)) {
            @mkdir($runtimePath, 0666, true);
        }
    }

    private function _setFlightVar()
    {
        $this->engine->set('flight.views.path', TEMPLATE_PATH);
        $this->engine->set('flight.log_errors', true);
        //模板全局变量
        $this->engine->set('GLOBAL_TEMPLATE_VARS', $this->config->app['global_vars']['template_vars']);
        $this->engine->set('debug', $this->config->app['debug']);
        $this->engine->set('root', $this->config->app['root']);
        $this->engine->set('flight.v2.output_buffering', true);
        // devdump($this->engine,1);
    }
    private function _reflection()
    {
        $methods = (new \ReflectionClass($this))->getMethods(/*\ReflectionMethod::IS_STATIC + */ \ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $obj) {
            if (substr($obj->getName(), 0, 3) != 'map') {
                continue;
            }
            $name = lcfirst(substr($obj->getName(), 3));
            $ref = new \ReflectionMethod($this, $obj->getName());
            // echo $name;
            // devdump($obj->getName(), 1);

            $this->engine->map($name, function () use ($ref) {
                $ref->invokeArgs($this, func_get_args());
                unset($ref);
            });
            unset($name, $ref, $obj);
        }
        unset($methods);
        $this->_listen();
    }

    private function configParse()
    {
        $config = [];
        $files = VKFile::getFiles(FRAMEWORK_PATH . '/config');
        foreach ($files as $file) {
            $key = substr($file['name'], 0, 0 - 1 - strlen(VKFile::getExtension($file['name'])));
            $config[$key] = @include_once ($file['path']);
            unset($key, $file);
        }
        unset($files);
        if (file_exists(APP_PATH . '/config.php')) {
            $appConfig = include (APP_PATH . '/config.php');
            if (is_array($appConfig)) {
                $config = $this->_deepMergeArray($appConfig, $config);
            }
            unset($appConfig);
        }
        $this->config = (object) $config;
    }

    private function _deepMergeArray($arr, $conf)
    {
        foreach ($arr as $k => $v) {
            if (!isset($conf[$k])) {
                $conf[$k] = $v;
                continue;
            }
            if (!is_array($v)) {
                $conf[$k] = $v;
                continue;
            }
            if (is_array($v)) {
                $conf[$k] = $this->_deepMergeArray($v, $conf[$k]);
            }
        }
        return $conf;
    }
}