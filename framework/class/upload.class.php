<?php
class Upload extends Common{

	private $mimeType = null;

	public function __construct($options = null){
		parent::__construct();
		//$this->options = $options;
		$this->mimeType = [
		      "3gp" => "video/3gpp",
		      "apk" => "application/vnd.android.package-archive",
		      "asf" => "video/x-ms-asf",
		      "avi" => "video/x-msvideo",
		      "bin" => "application/octet-stream",
		      "bmp" => "image/bmp",
		      "c" => "text/plain",
		      "class" => "application/octet-stream",
		      "conf" => "text/plain",
		      "cpp" => "text/plain",
		      "doc" => "application/msword",
		      "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
		      "xls" => "application/vnd.ms-excel",
		      "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
		      "exe" => "application/octet-stream",
		      "gif" => "image/gif",
		      "gtar" => "application/x-gtar",
		      "gz" => "application/x-gzip",
		      "h" => "text/plain",
		      "htm" => "text/html",
		      "html" => "text/html",
		      "jar" => "application/java-archive",
		      "java" => "text/plain",
		      // "jpeg" => "image/jpeg",
		      "jpg" => "image/jpeg",
		      "js" => "application/x-javascript",
		      "log" => "text/plain",
		      "m3u" => "audio/x-mpegurl",
		      "m4a" => "audio/mp4a-latm",
		      "m4b" => "audio/mp4a-latm",
		      "m4p" => "audio/mp4a-latm",
		      "m4u" => "video/vnd.mpegurl",
		      "m4v" => "video/x-m4v",
		      "mov" => "video/quicktime",
		      "mp2" => "audio/x-mpeg",
		      "mp3" => "audio/x-mpeg",
		      "mp4" => "video/mp4",
		      "mpc" => "application/vnd.mpohun.certificate",
		      "mpe" => "video/mpeg",
		      "mpeg" => "video/mpeg",
		      "mpg" => "video/mpeg",
		      "mpg4" => "video/mp4",
		      "mpga" => "audio/mpeg",
		      "msg" => "application/vnd.ms-outlook",
		      "ogg" => "audio/ogg",
		      "pdf" => "application/pdf",
		      "png" => "image/png",
		      "pps" => "application/vnd.ms-powerpoint",
		      "ppt" => "application/vnd.ms-powerpoint",
		      "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
		      "prop" => "text/plain",
		      "rc" => "text/plain",
		      "rmvb" => "audio/x-pn-realaudio",
		      "rtf" => "application/rtf",
		      "sh" => "text/plain",
		      "tar" => "application/x-tar",
		      "tgz" => "application/x-compressed",
		      "txt" => "text/plain",
		      "wav" => "audio/x-wav",
		      "wma" => "audio/x-ms-wma",
		      "wmv" => "audio/x-ms-wmv",
		      "wps" => "application/vnd.ms-works",
		      "xml" => "text/plain",
		      "z" => "application/x-compress",
		      "zip" => "application/x-zip-compressed"
		];
	}

	/*
	* param $options array
					可选项 如果save_path、save_name 参数不存在，则返回临时文件path，否则返回$save_path + $save_name 组成的正式文件path;
	*               save_path string
	*               save_name string
					必须项
	*               exts string   'jpg|png'
	*               size int  必须项
	*/
	public function upload(array $options, $multiple = false){

	  $file = $this->request->files->file;
		
		if(!is_array($file)) return null;
		
		// return $file;
		
	    if( $file['error'] != "0"){
	        //文件上传错误
	        $this->json([
	            'code' => 102,
	            'msg' => $file['error']
	        ]);exit;
	    }
		// return $options;
		
	    if(isset($options['size']) && intval($options['size']) > 0){
		    if(intval($file['size']) > ($options['size'] * 1) ){
		        $this->json([
		            'code' => 104,
		            'msg' => '资源内容过大（'.$this->formatFileSize($file['size']).'）',
		            'maxsize' => $this->formatFileSize($options['size'])
		        ]);exit;
		    }
	    }

	    if(isset($options['exts']) && $options['exts']){
	    	$options['exts'] = explode('|', str_replace(',','|',$options['exts']));
	    	$mimeTypes = $this->getAllowMimeList($options['exts']);
	    	if(!empty($options['exts'])){
			    if(!in_array($file['type'], $mimeTypes)){
			        // 请上传图片
			        $this->json([
			            'code' => 103,
			            'msg' => "拒绝上传[".array_pop(explode('.', $file['name']))."]类资源"
			        ]);exit;
			    }
	    	}
	    }
		// return $file;
	    // return $file;

	    $fileExt = $this->getFileExt($file['type']);
	    $runtimeUploadPath = ROOT .'/runtime/upload';
	    if(!is_dir($runtimeUploadPath)){
	    	mkdir($runtimeUploadPath, 0777, true);
	    }
	    $save_tmp_path = $runtimeUploadPath.'/'.md5(time().mt_rand(100,999)).'.'.$fileExt;
	    @move_uploaded_file($file["tmp_name"], $save_tmp_path);

	    if(file_exists($save_tmp_path)){
	    	$_subtype = '';
			if(isset($options['sub_dir']) ){
				
				if( $options['sub_dir']){
					$_subtype = trim( str_replace('\\', '/', $options['sub_dir']), '/');

				}else if( stripos($file['type'],'image/') === 0 ){
				
					$_subtype= 'images';
				}else{
				
					$_subtype= $fileExt;
				}
			}

	    	if( isset($options['save_path']) ){
	    		//检测save_path是否为相对路劲
	    		$_tmp_save_path = str_ireplace(PUBLIC_PATH, '', $options['save_path']);
	    		
	    		if($_tmp_save_path == $options['save_path']){
	    			//说明指定的save_path是相对路劲
	    			$path = (PUBLIC_PATH . '/'.trim($options['save_path'],'/').'/');
	    		}else{
	    			$path = $options['save_path'];
	    		}
	    		//定义子目录
	    		if($_subtype){
					$path .= ( $_subtype.'/');
				}

	    		if(!is_dir($path)){
	    			mkdir($path, 0777, true);
	    		}

	    		//$options['save_path'] = $path;
				
	    		if(isset($options['save_name'])){
	    			if($options['save_name'] === 'random'){
	    				$options['save_name'] = md5( range('A','Z'). time() . mt_rand());

	    			}elseif(isset($options['save_name']['callback'])){
	    				if(is_callable($options['save_name']['callback'], false, $function)){
	    					$options['save_name'] = $function($options['save_name']['args'] ?? [], $file);
	    				}else{
	    					unset($options['save_name']);
	    				}
	    			}else{
	    				unset($options['save_name']);
	    			}
	    		}

	    		if(!isset($options['save_name'])){
	    			//保持原来的文件名
						$options['save_name'] = substr($file['name'], 0, strripos($file['name'], ".{$fileExt}"));
	    		}

	    		// return $options;
	    		$filename = $options['save_name'].'.'.$fileExt;
		    	$filepath = $path . $filename;
		        @rename($save_tmp_path, $filepath);
		        if(file_exists($filepath)){
		        	$filepath = str_replace('\\','/', str_replace(PUBLIC_PATH,'', realpath($filepath)));
		        	if($multiple){
		        		if($_subtype == 'images' && isset($options['thumb'])){
		        			if(isset($options['thumb']['callback']) && is_callable($options['thumb']['callback'], false, $function)){
			        			$thumb = str_replace('/images/','/thumb/', PUBLIC_PATH.$filepath);
			        			if(!is_dir(dirname($thumb))){
			        				mkdir(dirname($thumb), 0777, true);
			        			}
			        			if(!file_exists($thumb) || (isset($options['thumb']['cover']) && $options['thumb']['cover']) ){
				        			$function(PUBLIC_PATH.$filepath, $options['thumb']['width']??100, $options['thumb']['height']??100 , $thumb);
			        			}
				        		$thumb = str_replace(PUBLIC_PATH, '', $thumb);
		        			}
		        		}
		        		return [
		        			'path' => $filepath,
		        			'name' => $filename,
		        			'thumb' => isset($thumb)? $thumb : false,
		        			'size' => $file['size'],
		        			'ext'  => $fileExt,
		        			'hash' => hash_file('md5',PUBLIC_PATH.$filepath),
		        			'mime' => $file['type'],
		        			'stype' => $_subtype, 
		        			'ctime' => time(),
		        		];
		        	}
		        	return $filepath;
		        }
	    	}
	    	if($multiple){
	    		return [
	    			'path' => realpath($save_tmp_path),
	    			'size' => $file['size'],
	    			'ext'  => $fileExt,
		        	'hash' => hash_file('md5',realpath($save_tmp_path)),
		        	'mime' => $file['type'],
		        	'stype' => $_subtype,
		        	'ctime' => time()
	    		];
	    	}
	    	//临时文件不在前段展示，所以不需要美化路径
	    	return realpath($save_tmp_path);
	    }
	    return false;
	}

	private function getFileExt($mime){
		$mimeTypes = array_values($this->mimeType);
		$ext = '';
		foreach($mimeTypes as $k => $type){
			if($mime == $type){
				$exts = array_keys($this->mimeType);
				$ext = $exts[$k];
				unset($exts);
				break;
			}
		}
		unset($mimeTypes);
		return $ext;
	}

	// 获取允许上传的mime列表
	private function getAllowMimeList(array $exts){
		$mimeTypes = [];
		foreach($exts as $ext){
			$ext = trim($ext);
			if( $ext !='' && isset($this->mimeType[$ext]) ) {
				$mimeTypes[] = $this->mimeType[$ext];
			}
		}
		return $mimeTypes;
	}



	static function formatFileSize($byte){
	    $KB = 1024;
	    $MB = 1024 * $KB;
	    $GB = 1024 * $MB;
	    $TB = 1024 * $GB;
	    if ($byte < $KB) {
	        return $byte . "B";
	    } elseif ($byte < $MB) {
	        return round($byte / $KB, 2) . "KB";
	    } elseif ($byte < $GB) {
	        return round($byte / $MB, 2) . "MB";
	    } elseif ($byte < $TB) {
	        return round($byte / $GB, 2) . "GB";
	    } else {
	        return round($byte / $TB, 2) . "TB";
	    }
	}

}