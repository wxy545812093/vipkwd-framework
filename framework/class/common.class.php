<?php
class Common{

	protected $app;
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

	public function __construct($flight = null){
		if(is_null($flight)){
			global $flight;
		}
		$this->app 		= $flight;
		$this->request 	= $flight->request();
		$this->isAjax 	= $this->request->ajax === true;
		$this->isPost 	= $this->request->method === 'POST';
		$this->isGet  	= $this->request->method === 'GET';

		$this->data 	= $this->request->data;
		$this->query 	= $this->request->query;
		$this->cookies 	= $this->request->cookies;
		$this->files 	= $this->request->files;
		$this->headers 	= $this->request->headers;
		//$this->server 	= $_SERVER;

		$this->tokenValidater();
	}


	public function error($code, array $data=[]){
		$code > 0 && send_http_status($code);
		switch ($code) {
			case '403':
			case '404': // 显示自定义的页面
				$this->app->hasTemplate($code) 
					? $this->display($code, $data)
					: include (FRAMEWORK_PATH."/views/{$code}.php");
				break;
			default:
				# code...
				break;
		}
	}
	protected function tokenValidater() {
		$this->username = $this->headers->username ?? "";
		$this->token = $this->headers->token ?? "";
		$this->userid = $this->headers->userid ?? "";
		return true;
	}

	protected function json($data = [], $code = 0, $msg = 'ok'){
		header("Content-Type: application/json; charset=utf-8");
		// header("Access-Control-Allow-Origin: *");
		// header("Access-Control-Allow-Credentials: true");
		// // header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
		// header('Access-Control-Allow-Methods:*'); 
		// header("Access-Control-Allow-Headers: userid,username,token,Content-Type,Authorization,X-Requested-With, origin, accept, host, date, cookie, cookie2");
		// // header("Access-Control-Allow-Headers: a, b, token, Content-Type");
		// header('P3P: CP="CAO PSA OUR"'); // Makes IE to support cookies
		if(is_array($data) && isset($data['code'])){
			return $this->app->json($data);
		}
		return $this->app->json([
			'code' 	=> $code,
			'msg' 	=> $msg,
			'data' 	=> $data ? $data : []
		]);
	}

	protected function display($template, $data = []){
		return $this->app->render($template, $data);
	}
}