<?php
class Index extends Common{

	private $options = null;

	static $xxxxxxx=100;
	public function __construct($options = null){
		parent::__construct();
	}

	public function index(){
		echo 'It works!';
		echo !$this->app->get('debug') ? '' : '<br/>[ @'. $this->app->get('module').'->'.__CLASS__.'::'.__FUNCTION__.'() ]';
	}
}