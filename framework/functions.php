<?php
if(!defined('APP')){exit;}

function checkModule($module = 'home'){

	if(!file_exists(APP_PATH.'/router.php')){
		file_put_contents(APP_PATH.'/router.php', file_get_contents(FRAMEWORK_PATH.'/views/router.php'));
	}
    if(is_object($module) && $module instanceof flight\Engine)
        $module = $module->get('module');

	$module_path = APP_PATH.'/'.$module;
    
    !is_dir($module_path.'/controller') && mkdir($module_path.'/controller', 0777, true);
    !is_dir(realpath($module_path.'/../views/').'/'.$module) && mkdir(realpath($module_path.'/../views/').'/'.$module, 0777, true);

	if(!file_exists($module_path.'/controller/index.class.php')){
		file_put_contents($module_path.'/controller/index.class.php', file_get_contents(FRAMEWORK_PATH.'/views/controller.php'));
		$routePath = APP_PATH.'/router.php';
		$routeList = include($routePath);
        $router = ($module != 'home') ? "/$module" : "/";
		if(!array_key_exists($router , $routeList)){
			$routeList[ $router ] = [
				'action' => $module.'.index.index'
			];
			$routeList = var_export($routeList, true);
			$routeContent='<?php'.PHP_EOL;
			$routeContent.='if(!defined(\'APP\')){exit;}'.PHP_EOL;
			$routeContent.='return '.$routeList.';';
			$routeContent = preg_replace("@\=(\ +)?\>(\r\n)(\ +)?array@i",'=> array', $routeContent);
			file_put_contents($routePath, $routeContent);
		}
	}
}

/**
* 输出常见HTTP状态信息
* @param integer $code 状态码
*/
function send_http_status($code) {
	header("HTTP/1.1 $code".get_http_status($code));
}
function get_http_status($code) {
	$status = array(
		200 => 'OK',
		204 => 'OK',
		301 => 'Moved Permanently',
		302 => 'Moved Temporarily ',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		500 => 'Internal Server Error',
		503 => 'Service Unavailable',
	);
	return isset($status[$code]) ? (' ' . $status[$code]) : '';
}

function registerFlightModel($classFile, &$app){
    try{
        $pathinfo = pathinfo($classFile);
        $claSs = str_replace('.class.php', '', $pathinfo['basename']);
        $ucfirst_class = ucfirst($claSs);
        //$lcfirst_class = lcfirst($claSs);
        include_once($classFile);
        if (!class_exists($ucfirst_class, false)) {
            throw new Exception("Unable to load &lt;{$ucfirst_class}&gt; class in {$classFile}");
            // trigger_error("Unable to load class: {$ucfirst_class}({$pathinfo['basename']})", E_USER_WARNING);
        }
        $app->register(lcfirst($claSs), $ucfirst_class, [$app], function(){});
    }catch(\Exception $e){
        throw new Exception($e->getMessage(), $e->getCode());
    }
}

//原始大图地址，缩略图宽度，高度，缩略图地址
function imageThumb($big_img, $width, $height, $small_img) {
    $imgage = getimagesize($big_img); //得到原始大图片
    switch ($imgage[2]) { // 图像类型判断
        case 1:
            $im = imagecreatefromgif($big_img);
        break;
        case 2:
            $im = imagecreatefromjpeg($big_img);
        break;
        case 3:
            $im = imagecreatefrompng($big_img);
        break;
    }
    $src_W = $imgage[0]; //获取大图片宽度
    $src_H = $imgage[1]; //获取大图片高度
    $tn = imagecreatetruecolor($width, $height); //创建缩略图
    imagecopyresampled($tn, $im, 0, 0, 0, 0, $width, $height, $src_W, $src_H); //复制图像并改变大小
    imagejpeg($tn, $small_img); //输出图像
}
function json($data = [], $code = 0, $msg =''){
    global $app;
    return $app->json([
        'code' => $code,
        'msg' => $msg,
        'data' => $data,
    ]);
}

function loadLibs($class, $args = null, $ext = '.class.php', $responseSameNameObject = true, $ucfirst = true ){

    $pathList = explode('.', str_replace(['/',' '],['.', ''], trim($class)) );

    //第三方库使用了多级目录
    if(count($pathList) > 1){
        $pa = [];
        foreach($pathList as $dir){
            if($dir && $dir != '/' && $dir != '.'){
                $pa[] = $ucfirst ? ucfirst($dir) : $dir;
            }
        }
        $pathList = $pa;
    }else{
        $pathList = [$ucfirst ? ucfirst($class) : $class];
    }
    $classFile = LIBS_PATH . '/'.implode('/', $pathList). '.'. ltrim($ext, '.');

    if(file_exists($classFile)){
        //unset($filename);
        include_once($classFile);

        if($responseSameNameObject){
            $class = ucfirst( array_pop($pathList) );
            if (!class_exists($class, false)) {
                throw new Exception("Unable to load lib: $class", E_USER_WARNING);
                return ;
            }
            if($args !== null){
                unset($pathList, $classFile);
                return new $class($args);
            }
            unset($pathList, $classFile);
            return new $class();
        }
        unset($pathList, $classFile);
        return;
    }
    throw new Exception("Failed to open ({$classFile}): No such file or directory", E_USER_WARNING);
    unset($pathList, $classFile);
    return null;
}

function filehash($file){
    return hash_file('md5', $file);
}

function dump($data, $exit=0){
	echo '<pre>';
	print_r($data);
	echo '</pre>';
	$exit && exit;
}

function request($args){
	return $args ? htmlspecialchars(addslashes(trim($args))) : $args;
}

if(!function_exists('authcode') ){
    // 参数解释
    // $string： 明文 或 密文
    // $operation：DECODE表示解密,其它表示加密
    // $key： 密匙
    // $expiry：密文有效期 秒
    function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙  
        $ckey_length = 6;

        $operation = strtoupper($operation);

        // 密匙
        $key = md5($key ? $key : 'm*d+{>2s.~#%9=93$s5');

        // 密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));
        // 密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));
        // 密匙c用于变化生成的密文
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
        // 参与运算的密匙
        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);
        // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
        // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        if($operation =='DECODE' ){
            $string = str_replace('---','/', $string);
            $string = str_replace('-','+', $string);
            $string = base64_decode(substr($string, $ckey_length));
        }else{
            $string = sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        }
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        // 产生密匙簿
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        // 核心加解密部分
        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if($operation == 'DECODE') {
            // substr($result, 0, 10) == 0 验证数据有效性
            // substr($result, 0, 10) - time() > 0 验证数据有效性
            // substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
            // 验证数据有效性，请看未加密明文的格式
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
            // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
            $string = $keyc.str_replace('=', '', base64_encode($result));
            return str_replace(['+', '/'], ['-','---'], $string);
        }
    }
}
/**
 * 提升数据列为键
 *
 * @param array $arr
 * @param string $column
 * @param boolean $cover <true> 遇相同column时，是否覆盖
 * @return array
 */
function columnUptoKey(array $arr, string $column, bool $cover = true):array{
    $data = [];
    foreach($arr as $item){
        if(isset($item[$column])){
            if($cover || ($cover !== true && !isset($data[ $item[$column] ]) )){
                $data[ $item[$column] ] = $item;
            }
        }
        unset($item);
    }
    unset($arr, $column);
    return $data;
}

function config(string $key){
    return \Vipkwd\Utils\Tools::config($key, FRAMEWORK_PATH.'/config');
}