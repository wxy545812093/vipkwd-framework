<?php

use Vipkwd\Utils\{Http, Tools};

class FwCommon
{

    protected $framework;
    protected $engine;
    protected $isAjax;
    protected $isPost;
    protected $isGet;
    protected $request;
    protected $data;
    protected $query;
    protected $cookies;
    protected $files;
    protected $headers;
    protected $username;
    protected $userid;

    protected $tokenValid = false;
    private $_tplEngine;

    public function __construct()
    {

        global $vkFramework;
        $this->framework = $vkFramework;
        // $this->engine = $vkFramework->engine;
        $this->request = Http::request();//$flight->request();
        $this->isAjax = $this->request->ajax === true;
        $this->isPost = $this->request->method === 'POST';
        $this->isGet = $this->request->method === 'GET';

        $this->data = $this->request->data;
        $this->query = $this->request->query;
        $this->cookies = $this->request->cookies;
        $this->files = $this->request->files;
        $this->headers = $this->request->headers ?? [];

        //$this->server 	= $_SERVER;
        $this->_tplEngine = $this->framework->engine->view();
        $this->tokenValidater();
        $this->commonVarsAssign();
    }

    public function index()
    {
        echo "Service is running!";
    }

    public function error($code, array $data = [])
    {
        $code > 0 && send_http_status($code);
        switch ($code) {
            case '403':
            case '404': // 显示自定义的页面
                $this->framework->mapTplExists($code)
                    ? $this->display($code, $data)
                    : include (FRAMEWORK_PATH . "/tpl/{$code}.tpl.php");
                break;
            default:
                # code...
                break;
        }
    }
    protected function tokenValidater()
    {
        $this->username = $this->headers->Username ?? ($this->data->username ?? $this->query->username ?? '');
        $this->token = $this->headers->Token ?? ($this->data->token ?? $this->query->token ?? '');
        $this->userid = $this->headers->Userid ?? ($this->data->userid ?? $this->query->userid ?? '');
        $this->autoken = $this->headers->Autoken ?? ($this->data->autoken ?? $this->query->autoken ?? '');
        $this->sign = $this->headers->Sign ?? ($this->data->sign ?? $this->query->sign ?? '');

        $this->tokenValid = $this->framework->OAuthAction ? $this->framework->OAuthAction::getUserId() > 0 : false;//&& isset($this->data->vversion);

        if (($this->tokenValid !== true) && isset($this->noAuth) && in_array($this->framework->getVar('action'), $this->noAuth)) {
            $this->tokenValid = true;
        }

        $validater = $this->framework->getVar('action') . 'Validater';
        if (method_exists($this, $validater)) {
            $this->tokenValid = call_user_func([$this, $validater], $this->tokenValid);
        }

        if ($this->tokenValid !== true) {
            if ($this->isAjax) {
                $this->json(null, 403, getString('no_access'));
                exit;
            } else {
                $this->framework->engine->redirect(sprintf("/?timeout&state=%s&logout=%s", urlencode($this->request->uri), $this->framework->OAuthAction ? $this->framework->OAuthAction::getAccessToken() : md5(time())));
                exit(getString('no_access'));
            }
        }
    }

    protected function json($data = [], $code = 200, $msg = 'ok')
    {
        header("Content-Type: application/json; charset=utf-8");
        if (is_array($data) && isset($data['code'])) {
            return $this->framework->engine->json($data);
        }
        return $this->framework->engine->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data ? $data : []
        ]);
    }

    protected function display($template, $data = [])
    {
        $this->framework->mapDisplay($template, $data);
    }

    protected function assign($key, $value = null)
    {
        if (is_array($key) && !empty($key)) {
            $this->_tplEngine->assign($key);
        }
        if (is_string($key)) {
            $this->_tplEngine->assign([
                $key => $value
            ]);
        }
    }
    protected function getVar(string $key = '')
    {
        return $this->_tplEngine->get($key);
    }

    protected static function table(string $name)
    {
        return (new FwDatabase(null))->table($name)->fresh();
    }

    /**
     * 跨模块调用方法
     * 
     * invokeModelAction('model@controller/action', $array) 跨模块
     * 
     * invokeModelAction('@controller/action', $array) 当前模块
     * 
     * invokeModelAction('controller/action', $array) 当前模块
     * 
     * @return mixed
     * 
     * @throws \Exception
     */
    protected function invokeModelAction(string $path)
    {
        global $vkFramework;
        $path = trim(str_replace('.', '/', $path), '/');
        $idx = strpos($path, "@");
        if ($idx >= 0) {
            // $model = explode("@", $path);
            // $model = array_shift($model);
            $model = substr($path, 0, $idx);
        } else {
            $model = $vkFramework->engine->get('module') ?: "{miss_model_name}";
        }
        $path = explode('/', substr($path, $idx + 1));
        $classFile = APP_PATH . '/' . $model . '/controller/' . lcfirst($path[0]) . '.php';
        if (file_exists($classFile)) {
            include_once $classFile;
            $path[0] = ucfirst($path[0]);
            $path[] = "notfound";
            list($controller, $action) = $path;
            if (!class_exists($controller, false)) {
                throw new \Exception("Unable to load class: $controller", E_USER_WARNING);
            }
            $class = new $controller([]);
            if (!method_exists($class, $action)) {
                throw new \Exception("Unable to access class method: $controller::$action", E_USER_WARNING);
            }
            $args = func_get_args();
            unset($args[0]);
            return $class->$action(...$args);
            //return call_user_func_array($path,  isset($args[0]) ? array_values($args) : $args );
        }
        throw new \Exception("Failed to open ({$classFile}): No such target file.", E_USER_WARNING);
    }
    private function commonVarsAssign()
    {
        $OAuthAction = $this->framework->OAuthAction;
        $this->assign('path_info', $this->request->path_info);
        $this->assign('domain', $this->request->domain);
        $this->assign('host', $this->request->host);
        // $this->assign('request', get_object_vars($this->request));
        $this->assign('user', $OAuthAction ? $OAuthAction::getUserInfo() : 0);
        $this->assign('uid', $OAuthAction ? $OAuthAction::getUserId() : 0);
        $this->assign('accessToken', $OAuthAction ? $OAuthAction::getAccessToken() : "");
        unset($OAuthAction);
    }
}