<?php
if (!defined('APP')) {
    exit;
}

/**
 * header输出常见HTTP状态信息
 * 
 * @param integer $code 状态码
 * 
 * @return void
 */
function send_http_status(int $code)
{
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
    $text = isset($status[$code]) ? (' ' . $status[$code]) : '';
    header(sprintf("HTTP/1.1 %s%s", $code, $text));
}

/**
 * 创建缩略图
 * 
 * @param string $resFilePath 原始大图地址
 * @param integer $width 缩略图宽度
 * @param integer $height 缩略图高度
 * @param resource|string $smallFilePath 输出的缩略图地址
 * 
 * @return void
 */
function imageThumb(string $resFilePath, int $width, int $height, $smallFilePath)
{
    $imgage = getimagesize($resFilePath); //得到原始大图片
    switch ($imgage[2]) { // 图像类型判断
        case 1:
            $im = imagecreatefromgif($resFilePath);
            break;
        case 2:
            $im = imagecreatefromjpeg($resFilePath);
            break;
        case 3:
            $im = imagecreatefrompng($resFilePath);
            break;
    }
    // $src_W = $imgage[0]; //获取大图片宽度
    // $src_H = $imgage[1]; //获取大图片高度
    $tn = imagecreatetruecolor($width, $height); //创建缩略图
    imagecopyresampled($tn, $im, 0, 0, 0, 0, $width, $height, $imgage[0], $imgage[1]); //复制图像并改变大小
    imagejpeg($tn, $smallFilePath); //输出图像
    unset($tn, $image, $im);
}

/**
 * 导出三方库
 * 
 * @param string $class 类文件名(支持/分隔的子目录) /framework/libs/$class.$ext
 * @param mixed $args
 * @param string $classFileExt ".class.php"
 * @param bool $ucfirst <true>
 * @param string|true $className 类名 默认bool:true 检测与$class同名经首字母大写的类名
 * 
 * @return Iterator
 * 
 * @throws \Exception
 */
function importThirdClass(string $class, $args = null, string $classFileExt = '.class.php', bool $ucfirst = true, $className = true)
{
    $pathList = explode('.', str_replace(['/', ' '], ['.', ''], trim($class)));

    //第三方库使用了多级目录
    if (count($pathList) > 1) {
        $pa = [];
        foreach ($pathList as $dir) {
            if ($dir && $dir != '/' && $dir != '.') {
                $pa[] = $ucfirst ? ucfirst($dir) : $dir;
            }
        }
        $pathList = $pa;
    } else {
        $pathList = [$ucfirst ? ucfirst($class) : $class];
    }
    $classFile = FRAMEWORK_LIB_PATH . '/' . implode('/', $pathList) . '.' . ltrim($classFileExt, '.');

    if (file_exists($classFile)) {
        //unset($filename);
        include_once ($classFile);

        if ($className === true) {
            $className = ucfirst(array_pop($pathList));
            unset($pathList, $classFile);
        }

        if (!is_string($className) || !class_exists($className, false)) {
            throw new \Exception("Unable to load class: $className", E_USER_WARNING);
        }
        return ($args !== null) ? (new $className($args)) : (new $className());
    }
    unset($pathList);
    throw new \Exception("Failed to open ({$classFile}): No such target file.", E_USER_WARNING);
}

if (!function_exists('dump')) {
    function dump($data, $exit = 0)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        $exit && exit;
    }
}

if (!function_exists('authcode')) {
    // 参数解释
    // $string： 明文 或 密文
    // $operation：DECODE表示解密,其它表示加密
    // $key： 密匙
    // $expiry：密文有效期 秒
    function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
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
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
        // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        if ($operation == 'DECODE') {
            $string = str_replace('---', '/', $string);
            $string = str_replace('-', '+', $string);
            $string = base64_decode(substr($string, $ckey_length));
        } else {
            $string = sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        }
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        // 产生密匙簿
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        // 核心加解密部分
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'DECODE') {
            // substr($result, 0, 10) == 0 验证数据有效性
            // substr($result, 0, 10) - time() > 0 验证数据有效性
            // substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
            // 验证数据有效性，请看未加密明文的格式
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
            // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
            $string = $keyc . str_replace('=', '', base64_encode($result));
            return str_replace(['+', '/'], ['-', '---'], $string);
        }
    }
}

if (!function_exists('idxArrayFieldUptoKey')) {
    /**
     * 提升数据列为键
     *
     * @param array $arr
     * @param string $field
     * @param boolean $overrideKey <true> 遇相同column时，是否覆盖
     * @return array
     */
    function idxArrayFieldUptoKey(array $arr, string $field, bool $overrideKey = true): array
    {
        /*
        [
            ["subject" => "2016全中文慢摇【花千骨唱夜色】车载CD-领音车载DJ沫沫MIX[独家]","up_uid"=> 8],
            ["subject" => "色海音乐-DJ朵儿【别用她的感情伤我心DTS-劲舞重低音】顶级嗨碟","up_uid"=> 8]
        ]
        假如提升`up_uid`字段的话，需要执行`$overrideKey`策略
        */
        $data = [];
        foreach ($arr as $item) {
            if (isset($item[$field])) {
                if ($overrideKey || !isset($data[($item[$field])])) {
                    $data[($item[$field])] = $item;
                }
            }
            unset($item);
        }
        unset($arr, $field, $override);
        return $data;
    }
}

if (!function_exists('config')) {
    function config(string $key, string $includePath = FRAMEWORK_PATH . '/config', string $fileSuffix = 'php')
    {
        if (class_exists("\\Vipkwd\\Utils\\Tools", true)) {
            return \Vipkwd\Utils\Tools::config($key, $includePath, $fileSuffix);
        }
        throw new \Exception("Unable to load class: \\Vipkwd\\Utils\\Tools", E_USER_WARNING);
    }
}
if (!function_exists('arrayToObject')) {
    function arrayToObject(array $arr = [], bool $deep = true)
    {
        foreach ($arr as &$v) {
            if (is_array($v) && $deep) {
                $v = arrayToObject($v, $deep);
            }
        }
        return (object) $arr;
    }
}