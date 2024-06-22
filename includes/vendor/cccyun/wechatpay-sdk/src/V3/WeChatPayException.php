<?php

namespace WeChatPay\V3;

/**
 * 微信支付响应内容异常
 */
class WeChatPayException extends \Exception
{
    private $res = [];
    private $httpCode;

    /**
     * @param array $res
     * @param string $code
     */
    public function __construct($res, $httpCode)
    {
        $this->res = $res;
        $this->httpCode = $httpCode;
        if(is_array($res)){
            $message = '['.$res['code'].']'.$res['message'].(isset($res['detail']['issue'])?'('.$res['detail']['issue'].')':'');
        }else{
            $message = '返回数据解析失败(http_code='.$httpCode.')';
        }
        parent::__construct($message);
    }

    public function getResponse()
    {
        return $this->res;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }
}