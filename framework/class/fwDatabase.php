<?php
class FwDatabase
{
    private $_DB;
    private $_table;
    private $_limit;
    private $_page;
    private $_where = '1=1';
    private $_field = '*';
    private $_offset = 0;
    private $_pdoTransaction = false;
    private $_fieldExcept = [];
    private $_pk = 'id';
    private $_queryResult = null;
    private static $_instance = [];

    /**
     * 根据数据库配置参数:单例化
     */
    public function __construct(string $dbname = null)
    {
        global $vkFramework;
        //parent::__construct();
        $driver = $vkFramework->config->database['driver'];//config('database.driver')
        $config = $vkFramework->config->database[ $vkFramework->config->database['env'] ];//config('database.' . config('database.env'));

        if ($dbname) {
            $config = array_merge($config, [
                'database_name' => $dbname
            ]);
        }
        ksort($config);
        $instanceKey = md5(json_encode($config));
        if (empty(static::$_instance) || !isset(static::$_instance[$instanceKey])) {
            static::$_instance[$instanceKey] = new $driver($config);
        }

        $this->_DB = static::$_instance[$instanceKey];
        //$this->fresh();
    }

    /**
     * 指定操作数据表
     * 
     * @param string $name
     */
    public function table($name): FwDatabase
    {
        $this->_offset = 0;
        $this->_table = $name;
        return $this;
    }

    /**
     * 重置/刷新前序查询状态
     *      重置：字段、分页数、条件限定、查询偏移量等
     * 
     * @return FwDatabase
     */
    public function fresh(): FwDatabase
    {
        $this->_field = '*';
        $this->_page = 1;
        $this->_limit = 10;
        $this->_where = '1=1';
        $this->_offset = 0;
        $this->_fieldExcept = [];
        $this->_queryResult = null;
        return $this;
    }
    /**
     * 开启debug
     * 
     * @return FwDatabase
     */
    public function debug($t = true): FwDatabase
    {
        if ($t) {
            $this->_DB->debug();
            $this->_debug = 1;
        }
        return $this;
    }

    /**
     * 查询字段
     * 
     * @param string|array $field string时英文逗号分割
     */
    public function field($field = '*'): FwDatabase
    {
        if ($field != '*' && !is_array($field)) {
            $field = explode(',', $field);
            if (empty($field)) {
                $field = '*';
            }
        }
        if (is_array($field)) {
            $field = array_map(function ($key) {
                return trim($key);
            }, $field);
        }
        $this->_field = $field;
        unset($field);
        return $this;
    }

    /**
     * 筛选条件
     * 
     * @param array|string $where where array or field key
     */
    public function where($where, $value = null): FwDatabase
    {
        if (is_string($where)) {
            $where = [
                "$where" => $value
            ];
        }
        $this->_where = $where;
        return $this;
    }

    /**
     * 分页边界设定
     * 
     * @param number $page 开始页码
     * @param number $limit 单页查询记录条数
     * @param number $offset 查询偏移量
     */
    public function page($page = 1, $limit = 10, $offset = 0): FwDatabase
    {
        $this->_page = intval($page) > 1 ? intval($page) : 1;
        $this->_limit = intval($limit) > 2 ? intval($limit) : 2;
        $this->_offset = intval($offset) ? intval($offset) : 0;
        return $this;
    }

    /**
     * 字段排序
     * 
     * @param string $order
     */
    public function order(string $order): FwDatabase
    {
        $order = explode(',', $order);
        $_order_ = [];
        foreach ($order as $v) {
            $v = trim($v);
            $vv = explode(' ', preg_replace('/\ \ +/', ' ', $v));
            if (count($vv) == 1) {
                $vv[] = 'asc';
            }
            $_order_[$vv[0]] = strtolower($vv[1]) == 'asc' ? 'ASC' : 'DESC';
            unset($v, $vv);
        }
        if (!empty($_order_)) {
            $this->whereValidater();
            $this->_where['ORDER'] = $_order_;
        }
        unset($order, $_order_);
        return $this;
    }

    /**
     * group分组
     * 
     * @param string $group
     */
    public function group(string $group): FwDatabase
    {
        $group = str_replace(' ', '', $group);
        $this->whereValidater();
        $this->_where['GROUP'] = explode(',', $group);
        // if(count($this->_where['GROUP']) == 1){
        $this->_where['GROUP'] = $this->_where['GROUP'][0];
        // }
        return $this;
    }

    /**
     * having过滤
     * 
     * @param array $having
     */
    public function having(array $having): FwDatabase
    {
        $this->_where['HAVING'] = $having;
        return $this;
    }

    /**
     * 在指定全文 查找列 和 查找关键字
     * 
     * @param string $keyword
     * @param string|array $columns
     */
    public function fullText(string $keyword, $columns): FwDatabase
    {
        if ($keyword) {
            $this->whereValidater();
            if (!is_array($columns)) {
                $columns = str_replace(' ', "", $columns);
                $columns = explode(',', $columns);
            }
            $this->_where['MATCH'] = [
                "columns" => $columns,
                "keyword" => $keyword,

                // [optional] Search mode
                "mode" => "natural"
            ];
        }
        return $this;
    }

    /**
     * 屏蔽结果中指定字段
     * 
     * @param string|array $fields 多字段英文逗号隔开
     * 
     */
    public function except($fields = []): FwDatabase
    {
        if (is_string($fields)) {
            $fields = str_replace([' ', ',,'], ['', ','], $fields);
            ($fields != '') && $fields = explode(',', $fields);
        }
        if (!empty($fields))
            $this->_fieldExcept = $fields;
        return $this;
    }

    /**
     * 指定主键 字段
     * 
     * @param string $pkField
     * 
     */
    public function pk(string $pkField = 'id'): FwDatabase
    {
        $this->_pk = $pkField;
        return $this;
    }

    /**
     * 插入数据(insert 别名)
     * 
     * @param array $data
     * 
     * @return number|string
     */
    public function add(array $data)
    {
        return $this->insert($data);
    }

    /**
     * 插入数据
     * 
     * @param array $data
     * 
     * @return number|string
     */
    public function insert(array $data)
    {
        $this->_DB->insert($this->_table, $data);
        return $this->getInsertId();
    }

    /**
     * 批量插入数据(insertAll 别名)
     * 
     * @param array $data
     * 
     * @return number
     */
    public function addAll(array $data)
    {
        return $this->insertAll($data);
    }

    /**
     * 批量插入数据
     * 
     * @param array $data
     * 
     * @return number
     */
    public function insertAll(array $data)
    {
        $data = $this->_DB->insert($this->_table, $data);
        return $data ? $data->rowCount() : 0;
    }

    /**
     * 删除数据
     * 
     * @param string|integer $id
     * 
     * @return number
     */
    public function delete($id = 0)
    {
        ($id > 0) && $this->where([$this->_pk => $id]);
        $data = $this->_DB->delete($this->_table, $this->_where);
        return $data->rowCount();
    }

    /**
     * 替换或新增
     * 
     * @param array $data  =["fieldname1" => [ "old_value" => "new_value" ]]
     * 
     * @return number
     */
    public function replace(array $data)
    {
        $data = $this->_DB->replace($this->_table, $data, $this->_where);
        return $data ? $data->rowCount() : 0;
    }

    /**
     * 更新数据
     * 
     * @param array $data
     * 
     * @return number
     */
    public function update(array $data)
    {
        $res = $this->_DB->update($this->_table, $data, $this->_where);
        return $res->rowCount();
    }

    /**
     * 获取指定字段
     * 
     * @return mixed
     */
    public function value(string $field)
    {
        $data = $this->get();
        if (!empty($data) && isset($data)) {
            $field = str_replace(' ', '', $field);
            $field = explode(',', $field);
            $result = [];
            foreach ($field as $key) {

                //不存在的字段响应 null
                // $result[$key] = array_key_exists($key, $data) ? $data[$key] : null;

                //不存在的字段不响应(切忌不可用isset, 当db字段值 === `null` 时,isset检测结果是 `false`,这无法达到预期)
                if (array_key_exists($key, $data)) {
                    $result[$key] = $data[$key];
                }
            }
            unset($data);
            if (count($field) == 1) {
                return count($result) > 0 ? $result[$key] : null;
            }
            unset($field, $key);
            return $result;
        }
        return null;
    }

    /**
     * 按条件拉取一条记录
     * 
     * @return array
     */
    public function get()
    {
        $this->_queryResult = $this->_DB->get($this->_table, $this->_field, $this->_where);
        $this->_fieldExcepter();
        return $this->_queryResult;
    }

    /**
     * 按条件随机拉取一条数据
     * 
     * @return array
     */
    public function random()
    {
        $this->_queryResult = $this->_DB->rand(
            $this->_table,
            $this->_field,
            $this->_where
        );
        $this->_fieldExcepter();
        return $this->_queryResult;
    }

    /**
     * 按条件获取列表
     * 
     * @return array
     */
    public function select()
    {
        $this->whereValidater();
        if ($this->_page && $this->_limit) {
            $this->_where['LIMIT'] = $this->_calcPageLimit();
        }

        $this->_queryResult = $this->_DB->select(
            $this->_table,
            $this->_field,
            $this->_where
        );
        $this->_fieldExcepter();
        return $this->_queryResult;
    }

    /**
     * 获取表全部数据
     * 
     * @return array
     */
    public function getAll()
    {
        $this->_queryResult = $this->_DB->select(
            $this->_table,
            $this->_field,
            $this->_where
        );
        $this->_fieldExcepter();
        return $this->_queryResult;
    }

    /**
     * 按条件检测是否存在数据
     * 
     * @return boolean
     */
    public function has()
    {
        return $this->_DB->has($this->_table, $this->_where);
    }

    /**
     * 统计记录数量
     * 
     * @return number|bool
     */
    public function count()
    {
        return $this->_DB->count($this->_table, $this->_where);
    }

    /**
     * 获取字段列最大值
     * 
     * @param string $field
     * 
     * @return number|bool
     */
    public function max(string $field)
    {
        return $this->_DB->max($this->_table, $field, $this->_where);
    }

    /**
     * 获取字段列最小值
     * 
     * @param string $field
     * 
     * @return number|bool
     */
    public function min(string $field)
    {
        return $this->_DB->min($this->_table, $field, $this->_where);
    }

    /**
     * 获取字段列平均值
     * 
     * @param string $field
     * 
     * @return number|bool
     */
    public function avg(string $field)
    {
        return $this->_DB->avg($this->_table, $field, $this->_where);
    }

    /**
     * 指定字段列 求和
     * 
     * @param string $field
     * 
     * @return number|bool
     */
    public function sum(string $field)
    {
        return $this->_DB->sum($this->_table, $field, $this->_where);
    }

    /**
     * 获取最后一次操作ID
     * 
     * @return number|null|bool|string|\PDOStatement
     */
    public function getInsertId()
    {
        return $this->_DB->id();
    }

    /**
     * 创建数据库
     *
     * @param array $struct 数据结构
     * @param array|string $engine 引擎配置
     * 
     * @return boolean|\PDOStatement
     *
     **/
    public function createTable(array $struct, $engine = "ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")
    {
        return $this->_DB->create($this->_table, $struct, $engine);
    }

    /**
     * 删表(防止误删 需要2参验证)
     * 
     * @param boolean $handleConfirm<false>
     * @param integer $currentDateLenth8 传此刻的日期(8位数字)
     * 
     * @return boolean|\PDOStatement
     */
    public function drop(bool $handleConfirm = false, int $currentDateLenth8 = 0)
    {
        return ($handleConfirm === true && $currentDateLenth8 === intval(date('Ymd'))) ? $this->_DB->drop($this->_table) : false;
    }

    /**
     * 获取日志
     * 
     * @return array
     */
    public function log()
    {
        return $this->_DB->log();
    }

    /**
     * 获取最后一次执行语句
     * 
     * @return array|string|null
     */
    public function last()
    {
        return $this->_DB->last();
    }

    /**
     * 执行原始SQL语句
     * 
     * @param string $sql
     * 
     * @return null|\PDOStatement
     */
    public function query(string $sql)
    {
        return $this->_DB->query($sql)->fetchAll();
    }

    /**
     * 注册事务 事件执行体
     * 
     * @param callable $callback 匿名|闭包函数
     * 
     * @return mixed|null
     */
    public function action(callable $callback)
    {
        if (is_callable($callback)) {
            return $this->_DB->action($callback($this));
        }
        return null;
    }

    /**
     * 启动事务
     * @return bool
     */
    public function begin()
    {
        $this->_pdoTransaction = true;
        return $this->_DB->pdo->beginTransaction();
    }

    /**
     * 提交事务
     * 
     * @return bool|void
     */
    public function commit()
    {
        if ($this->_pdoTransaction === true) {
            return $this->_DB->pdo->commit();
        }
        exit('请先db::begin 开启事务');
    }

    /**
     * 事务回滚
     * 
     * @return bool|void
     * 
     */
    public function rollBack()
    {
        if ($this->_pdoTransaction === true) {
            return $this->_DB->pdo->rollBack();
        }
        echo ("请先db::begin 开启事务\r\n");
        exit("请先db::commit 提交事务");
    }


    /**
     * 计算分页位置
     *
     * @return array
     */
    private function _calcPageLimit()
    {
        $count = $this->count();
        $pages = ceil($count / $this->_limit);
        $this->_page = $this->_page > $pages ? $pages : $this->_page;
        $this->_page = $this->_page < 1 ? 1 : $this->_page;
        $offsets = ($this->_page - 1) * $this->_limit;

        if ($this->_offset != 0 && $count && $count >= abs($this->_offset * 1)) {
            $offsets += $this->_offset;
            $this->_limit += $this->_offset * 1;
        }

        return [$offsets, $this->_limit];
    }
    /**
     * 
     * @return void
     */
    private function whereValidater()
    {
        if (!is_array($this->_where)) {
            $this->_where = [];
        }
    }

    /**
     * 检测是否需要剔除指定字段
     * 
     * @return void
     */
    private function _fieldExcepter()
    {
        $list = $this->_queryResult;
        if (!empty($this->_fieldExcept)) {
            if (!is_array($list))
                $list = [];
            $isList = isset($list[0]);
            if (!$isList) {
                $list = [$list];
            }
            $intersects = array_intersect(array_keys($list[0]), $this->_fieldExcept);
            if (!empty($intersects)) {
                foreach ($list as &$item) {
                    foreach ($intersects as $field) {
                        if (isset($item[$field])) {
                            unset($item[$field]);
                        }
                    }
                }
            }
            if (!$isList) {
                $list = $list[0];
            }
        }
        $this->_queryResult = $list;
    }
}