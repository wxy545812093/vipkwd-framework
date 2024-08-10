<?php
// use Vipkwd\Utils\Tools;
class Index extends FwCommon
{
    private $options = null;
    public $noAuth = ["index"];

    public function __construct($options = null)
    {
        parent::__construct();
    }

    /**
     * 自定义index方法的验证器
     */
    public function indexValidater()
    {
        return true;
    }
    public function index()
    {
        echo 'It works!';
        // $this->assign("key", "value");
        // $this->display("index", ['oauth_login_url' => $this->framework->OAuthInstance ? $this->framework->OAuthInstance::$sdk->getLoginUrl('code') : null]);
        //echo !$this->framework->getVar('debug') ? '' : '<br/>[ @'. $this->framework->getVar('module').'->'.__CLASS__.'::'.__FUNCTION__.'() ]';
    }
}