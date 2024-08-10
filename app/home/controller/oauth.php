<?php
use \Exception as Except;

class Oauth extends FwCommon
{
    private $options = null;
    public $noAuth = ['login'];

    public function __construct($options = null)
    {
        parent::__construct();
    }

    public function login()
    {
        if ($this->framework->OAuthInstance && $this->framework->OAuthAction) {
            $response = $this->framework->OAuthInstance::$sdk->authorizeCodeType(true);
            if (is_array($response)) {
                // 将令牌缓存到 SESSION中，方便后续访问
                try {
                    $this->framework->OAuthAction::cacheOAuthToken($response['tokenData'], $response['clientId']);
                    $this->framework->OAuthAction::cacheOAuthUser($response['userInfo'], $response['clientId']);
                } catch (Except $e) {
                    throw new Except($e->getMessage());
                }
            }
            if (substr($this->query->state, 0, 4) == 'http') {
                $href = urldecode($this->query->state);
                $href = preg_replace("/&(amp;)+/", "&", $href);
                header('location: ' . $href);
                exit;
            }
        }
        header('location: /');
        exit;
    }
}