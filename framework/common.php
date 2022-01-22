<?php
if(!defined('APP')){exit;}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
// header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header('Access-Control-Allow-Methods:*');
header("Access-Control-Allow-Headers: userid,username,token,Content-Type,Authorization,X-Requested-With, origin, accept, host, date, cookie, cookie2");
// header("Access-Control-Allow-Headers: a, b, token, Content-Type");
header('P3P: CP="CAO PSA OUR"'); // Makes IE to support cookies
session_start();

define('FRAMEWORK_PATH', realpath(__DIR__));
define('FRAMEWORK_LIB_PATH', FRAMEWORK_PATH .'/libs');
define('ROOT',  realpath(FRAMEWORK_PATH.'/../'));
define('APP_PATH', ROOT.'/app');
define('TEMPLATE_PATH', APP_PATH.'/views');

define('PUBLIC_PATH', ROOT.'/public');

// 设置模板引擎参数
define('THINK_TEMPLATE', [
    // 模板文件目录
    'view_path'   => rtrim(TEMPLATE_PATH,'/').'/',
    // 模板编译缓存目录（可写）
    'cache_path'  => ROOT.'/runtime/',
    'driver' => '\\think\\Template'
]);
include_once(ROOT.'/vendor/autoload.php');
include_once(FRAMEWORK_PATH.'/exception.php');
include_once(FRAMEWORK_PATH.'/functions.php');
$flight = new flight\Engine();
$flight->set('flight.views.path', TEMPLATE_PATH);
$flight->set('flight.log_errors', true);

// $flight->set('debug', 0);
// $flight->set('root', ROOT);
// $flight->set('db_driver', '\\Medoo\\Medoo');
// $flight->set('db_config', [
//     'database_type' => 'mysql',
//     'database_name' => 'ddxx_kaowu',
//     'server' => '127.0.0.1',
//     'username' => 'root',
//     'password' => 'root',//'adminiis!!__',
//     'port' =>3306,
// 		'prefix' => '',
// 		'charset' => 'utf8mb4',
// 		'collation' => 'utf8mb4_general_ci',
// 		"logging" => true,
// 		'option' => [
// 			PDO::ATTR_CASE => PDO::CASE_NATURAL
// 		],
// ]);
$flight->map('error', function($exception)use(&$whoops, &$flight){
  if($flight->get('debug')){
    return $whoops->handleException($exception);
  }
  echo "系统错误";
});

//模板全局变量
$flight->set('GLOBAL_TEMPLATE_VARS', config('app.global_vars.template_vars'));
$flight->set('debug', config('app.debug'));
$flight->set('root', config('app.root'));
$flight->set('db_driver', config('app.db_driver'));
$flight->set('db_config', config('database.'. config('app.db_connection')));

!is_dir(APP_PATH) && mkdir(APP_PATH, 0777, true);
!is_dir(TEMPLATE_PATH) && mkdir(TEMPLATE_PATH, 0777, true);

$flight->map('listen', function(array $config = []) use(&$flight){
  //创建Home 默认应用
  checkModule();
  //win路径分隔符换成unix路径分隔符(否则win下mkdir时可能报 path路径无效)
  $runtimePath = str_replace('\\','/',realpath(THINK_TEMPLATE['cache_path']));
  if(!is_dir($runtimePath)){
    @mkdir($runtimePath, 0666, true);
  }

  //全局类侦测
  $controllers = FRAMEWORK_PATH.'/class/*.class.php';
  $classList = [];
  foreach(glob($controllers) as $k => $classFile){
    //$classList[] = $classFile;
    //全局类自动注册
    registerFlightModel($classFile, $flight);
  }
  //注册Think template
  $flight->register('view', THINK_TEMPLATE['driver'], [THINK_TEMPLATE] , function(\think\Template $template){ });

  $flight->map('render', function($template, $data = [])use(&$flight){
    $template = str_replace(['\\','/'],'.', $template);
    //定位模块子模板目录
    $template = explode('.', $template);
    if(count($template) < 3){
      array_unshift($template, $flight->get('module'));
    }
    $template = implode('/', $template);

    // 注册所有模板变量添加data节点（注意：think模板引擎无data节点，此操作仅仅是保持与flight模板引擎变量结果对齐)
    //think标准:  viewer->assign(['a'=> 1])  模板使用{$a}
    //flight标准:  viewer->set('a', 1)  模板使用：{$data['a']}
    //此处兼容方案：
    // $flight->view()->assign(['data'=>$data]);

    !empty($data) && $flight->view()->assign($data);

    $global_vars = [];
    //解析全局模板变量
    $GLOBAL_TEMPLATE_VARS = $flight->get('GLOBAL_TEMPLATE_VARS');
    if(is_array($GLOBAL_TEMPLATE_VARS)){
      foreach($GLOBAL_TEMPLATE_VARS as $k => $v){
        $global_vars[$k] = $v;
      }
      unset($k, $v);
    }
    $flight->view()->assign($global_vars);
    // echo $template;
    // dump($data,1);
    $flight->view()->fetch($template);
    unset($template, $data, $global_vars, $GLOBAL_TEMPLATE_VARS);
  });

  //监听路由配置
  $routers = @include(APP_PATH.'/router.php');
  foreach($routers as $pattern => $options){
    // $flight->route("GET|POST /login", function() use($flight){
    // 	$flight->user()->login();
    // });
    $pattern = explode(' ', $pattern);
    //默认支持8种http请求方式
    $pattern[] = 'GET|POST|OPTIONS|DELETE|PUT|HEAD|TRACE|CONNECT';
    $pattern[1] = trim($pattern[1]);

    $flight->map('notFound', function() use($flight){
        $flight->common()->error(404);
    });
    $flight->route('/403', function() use($flight){
      $flight->common()->error(403);
    });
    $autoLoad = $classList;

    $flight->route("{$pattern[1]} {$pattern[0]}", function() use(&$flight, $options, $autoLoad){

      //domain 检测
      if(!array_key_exists("action", $options)){
        $host = explode(":", $_SERVER['HTTP_HOST'])[0];
        $port = $_SERVER['SERVER_PORT'];
        foreach($options as $router_domain => $domain_option){
          //路由端口补全检测
          if(strrpos($router_domain,":") === false){
            $router_domain .= ":80";
          }
          // dump([$router_domain, $host .":".$port]);
          //与路由表 授权域名不匹配
          if($router_domain != ($host .":".$port)){
            // dump($_SERVER,1);
            // 拒绝请求
            unset($router_domain, $domain_option);
            continue;
          }
          $options = $domain_option;

          unset($router_domain, $domain_option);
          break;
        }
        unset($host,$port);
      }

      if( isset($options['action'])){
        $args = func_get_args();
        if(!isset($options['args']) || !is_array($options['args'])) {
          $options['args'] = array();
        }

        if( !in_array('route', $options['args'])){
          //$options['args'][] = 'route';
          array_pop($args);
        }

        $params = array_combine($options['args'], $args);

        $action = explode('.', $options['action']);
        if(count($action) == 2){
          array_unshift($action,'home');
        }elseif(count($action) == 1){
          array_unshift($action,'index');
          array_unshift($action,'home');
        }elseif(count($action) == 0){
          $action = ["home","index","index"];
        }
        // dump($action);
        $module = lcfirst($action[0]);
        $controller = lcfirst($action[1]);
        $action = lcfirst($action[2]);

        //绑定模块名到模板
        $flight->set('module', $module);
        $flight->set('controller', $controller);
        $flight->set('action', $action);

        checkModule($flight);

        //项目模块侦测
        foreach(glob(APP_PATH."/{$module}/controller/*.class.php") as $classFile){
          $autoLoad[] = realpath($classFile);
        }
        // dump($autoLoad,1);
        // try{
          //自动加载并注册项目模块类
          foreach($autoLoad as $classFile){
            registerFlightModel($classFile, $flight);
          }
        // }catch(\Exception $ex){
        // 	if($flight->request()->ajax){
        // 		return $flight->json([
        // 			"code" => 500,
        // 			"msg" => $ex->getMessage(),
        // 			"data" => []
        // 		]);
        // 	}else{
        // 		throw new \Exception($ex->getMessage(), $ex->getCode());
        // 	}
        // }

        // dump($flight,1);
        //$params['app'] = $flight;
        // try{
        // 	$flight->$controller();
        // }catch(\Exception $e){
        // 	//throw new Exception($e->getMessage());
        // 	if($flight->request()->ajax){
        // 		return $flight->json([
        // 			"code" => 500,
        // 			"msg" => $e->getMessage(),
        // 			"data" => []
        // 		]);
        // 	}else{
        // 		throw new \Exception($e->getMessage(), $e->getCode());
        // 	}
        // };

        // try{
          if(isset($options['before']) && is_callable($options['before'])){
            // $result = call_user_func_array($options['before'], array_merge($params, ['app'=> $flight]));
            // if(is_array($result)) $params = $result;
            call_user_func_array($options['before'], array_merge($params, ['app'=> &$flight]));
          }
          try{
            $flight->$controller()->$action($params);
          }catch(\Exception|\Error $exception){
            $message = $exception->getMessage();
            if(strripos($message,'must be a mapped method.')){
              $message = ( $controller ." constroller is not exists!");
            }
            throw new $exception($message, $exception->getCode());
          }
          // if($flight->request()->method == 'OPTIONS'){
          // 	send_http_status(204);die;
          // }

          if(isset($options['after']) && is_callable($options['after'])){
            call_user_func_array($options['after'], array_merge($params, ['app'=> &$flight]));
          }
        // }catch(\Exception $e){
        // 	if($flight->request()->ajax){
        // 		return $flight->json([
        // 			"code" => 500,
        // 			"msg" => $e->getMessage(),
        // 			"data" => []
        // 		]);
        // 	}else{
        // 		// dump($e->getTrace());
        // 		throw \Exception($e->getMessage(), $e->getCode());
        // 	}
        // }
        unset($options, $params, $classList, $action, $controller);
      }else{
        $flight->common()->error(404);
      }
    }, true);
  }

  $flight->map("hasTemplate", function($template, string $suffix=null) use(&$flight){
    $template = str_replace('..','{@--SPACE--#}', "$template");
    $template = str_replace('.','/', $template);
    $template = str_replace("{@--SPACE--#}",'..', $template);
    $template = trim(str_replace('//','/',$template),'/');
    $suffix = $suffix ?? trim($flight->view()->getConfig('view_suffix'),'.');
    return file_exists(TEMPLATE_PATH."/{$template}.{$suffix}");
  });
});
$flight->listen();
$flight->start();