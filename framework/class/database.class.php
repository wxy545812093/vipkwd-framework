<?php
class Database extends Common{

	private $_database;
	private $_table;
	private $_limit;
	private $_page;
	private $_where = '1=1';
	private $_field = '*';
	private $_offset = 0;
	private $_pdoTransaction = false;

	public function __construct($dbname = null){
		parent::__construct();
		$driver = $this->app->get('db_driver');
		$config = $this->app->get('db_config');
		if(is_string($dbname) && $dbname ){
			$config = array_merge($config,[
				'database_name' => $dbname
			]);
		}
		
		$this->_database = new $driver($config);
		//$this->fresh();
	}

	public function table($tbname){
		$this->_offset = 0;
		$this->_table = $tbname;
		return $this;
	}

	public function fresh(){
		$this->_field = '*';
		$this->_page = 1;
		$this->_limit = 10;
		$this->_where = '1=1';
		$this->_offset = 0;
		return $this;
	}

	public function field($field = '*'){
		if($field != '*' && !is_array($field)){
			$field = explode(',', str_replace(' ','',$field));
			if(empty($field)){
				$field = '*';
			}
		}
		$this->_field = $field;
		unset($field);
		return $this;
	}

	public function where(array $where){
		$this->_where = $where;
		return $this;
	}

	public function page($page = 1, $limit = 10, $offset = 0){
		$this->_page = intval($page) > 1 ? intval($page) : 1;
		$this->_limit = intval($limit) > 2 ? intval($limit) : 2;
		$this->_offset = intval($offset) ? intval($offset) : 0;
		return $this;
	}

	public function order($order){
		$order = explode(',', $order);
		$_order_ = [];
		foreach($order as $v){
			$v = trim($v);
			$vv = explode(' ',preg_replace('/\ \ +/',' ', $v) );
			if(count($vv) == 1){
				$vv[] = 'asc';
			}
			$_order_[ $vv[0] ] = strtolower($vv[1]) == 'asc' ? 'ASC' : 'DESC';
			unset($v, $vv);
		}
		if(!empty($_order_)){
			$this->whereValidater();
			$this->_where['ORDER'] = $_order_;
		}
		unset($order, $_order_);
		return $this;
	}

	private function whereValidater(){
		if(!is_array($this->_where)){
			$this->_where = [];
		}
	}
	public function group(string $group){
		$group = str_replace(' ','', $group);
		$this->whereValidater();
		$this->_where['GROUP'] = explode(',', $group);
		// if(count($this->_where['GROUP']) == 1){
			$this->_where['GROUP'] = $this->_where['GROUP'][0];
		// }
		return $this;
	}

	public function having(array $having){
		$this->_where['HAVING'] = $having;
		return $this;
	}

	public function fullText($keyword, array $columns){
		if($keyword){
			$this->whereValidater();
			$this->_where['MATCH'] = [
				"columns" => $columns,
				"keyword" => $keyword,

				// [optional] Search mode
				"mode" => "natural"
			];
		}
		return $this;
	}

	public function get(){
		return $this->_database->get($this->_table, $this->_field, $this->_where);
	}

	public function random(){
		return $this->_database->rand($this->_table, $this->_field, $this->_where);
	}

	public function select(){
		if($this->_page && $this->_limit){
			if(!is_array($where)) $where = [];
			$this->whereValidater();
			$this->_where['LIMIT'] = $this->_calcPageLimit();
		}

		return $this->_database->select(
			$this->_table,
			$this->_field,
			$this->_where
		);
	}
	public function getAll(){
		return $this->_database->select(
			$this->_table,
			$this->_field,
			$this->_where
		);
	}
	public function update(array $data){
		$res = $this->_database->update($this->_table, $data, $this->_where);
		return $res->rowCount();
	}

	public function delete($id = 0){
		$data = $this->_database->delete($this->_table, $this->_where);
		return $data->rowCount();
	}

	public function insert(array $data){
		$this->_database->insert($this->_table, $data);
		return $this->_database->id();
	}

	public function insertAll(array $data){
		$data = $this->_database->insert($this->_table, $data);
		return $data->rowCount();
	}

	public function getInsertId(){
		return $this->_database->id();
	}

	// $data = ["fieldname1" => [ "old_value" => "new_value" ]]
	public function replace(array $data){
		$data = $this->_database->replace($this->_table, $data, $this->_where);
		return $data->rowCount();
	}

	public function max(string $field){
		return $this->_database->max($this->_table, $field, $this->_where);
	}

	public function min(string $field){
		return $this->_database->min($this->_table, $field, $this->_where);
	}
	public function avg(string $field){
		return $this->_database->avg($this->_table, $field, $this->_where);
	}
	public function count(){
		return $this->_database->count($this->_table, $this->_where);
	}
	public function sum(string $field){
		return $this->_database->sum($this->_table, $field, $this->_where);
	}
	public function has(){
		return $this->_database->has($this->_table, $this->_where);
	}

	//??????????????????
	public function action($callback){
		if(is_callable($callback)){
			return $this->_database->action($callback($this));
		}
		return null;
	}

	/**
	* ???????????????
	*
	* @param string $struct ????????????
	* @param string $engine ????????????
	*
	**/
	public function createTable(array $struct, array $engine){
		return $this->_database->create($this->_table, $struct, $engine);
	}

	public function drop($confirm = false){
		return $confirm ? $this->_database->drop($this->_table) : null;
	}

	public function log(){
		return $this->_database->log();
	}
	public function last(){
		return $this->_database->last();
	}
	public function query(string $sql){
		return $this->_database->query($sql)->fetchAll();
	}

	public function begin(){
		$this->_pdoTransaction = true;
		$this->_database->pdo->beginTransaction();
	}

	public function commit(){
		if($this->_pdoTransaction === true){
			return $this->_database->pdo->commit();
		}
		exit('??????db::begin ????????????');
	}
	public function rollBack(){
		if($this->_pdoTransaction === true){
			return $this->_database->pdo->rollBack();
		}
		echo("??????db::begin ????????????\r\n");
		exit("??????db::commit ????????????");
	}


	/*
	* ??????????????????
	*/
	private function _calcPageLimit(){
		$count = $this->count();
		$pages = ceil($count / $this->_limit);
		$this->_page = $this->_page > $pages ? $pages : $this->_page;
		$this->_page = $this->_page < 1 ? 1 : $this->_page;
		$offsets = ($this->_page - 1) * $this->_limit;

		if( $this->_offset != 0 && $count && $count >= abs($this->_offset * 1) ){
			$offsets += $this->_offset;
			$this->_limit += $this->_offset * 1;
		}

		return [$offsets, $this->_limit];
	}


}