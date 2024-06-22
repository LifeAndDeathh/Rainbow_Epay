<?php

namespace WeChatPay\V3;

/**
 * 基础支付服务类
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/index.shtml
 */
class PaymentService extends BaseService
{
    public function __construct($config)
    {
        parent::__construct($config);
    }


    /**
     * NATIVE支付
     * @param $params 下单参数
     * @return mixed {"code_url":"二维码链接"}
     */
    public function nativePay($params){
        $path = '/v3/pay/transactions/native';
        $publicParams = [
            'appid' => $this->appId,
            'mchid' => $this->mchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * JSAPI支付
     * @param $params 下单参数
     * @return array Jsapi支付json数据
     */
    public function jsapiPay($params){
        $path = '/v3/pay/transactions/jsapi';
        $publicParams = [
            'appid' => $this->appId,
            'mchid' => $this->mchId,
        ];
        $params = array_merge($publicParams, $params);
        $result = $this->execute('POST', $path, $params);
        return $this->getJsApiParameters($result['prepay_id']);
    }

    /**
     * 获取JSAPI支付的参数
     * @param $prepay_id 预支付交易会话标识
     * @return array json数据
     */
    private function getJsApiParameters($prepay_id)
    {
        $params = [
            'appId' => $this->appId,
            'timeStamp' => time().'',
            'nonceStr' => $this->getNonceStr(),
            'package' => 'prepay_id=' . $prepay_id,
        ];
        $params['paySign'] = $this->makeSign([$params['appId'], $params['timeStamp'], $params['nonceStr'], $params['package']]);
        $params['signType'] = 'RSA';
        return $params;
    }

    /**
     * H5支付
     * @param $params 下单参数
     * @return mixed {"h5_url":"支付跳转链接"}
     */
    public function h5Pay($params){
        $path = '/v3/pay/transactions/h5';
        $publicParams = [
            'appid' => $this->appId,
            'mchid' => $this->mchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * APP支付
     * @param $params 下单参数
     * @return array APP支付json数据
     */
    public function appPay($params){
        $path = '/v3/pay/transactions/app';
        $publicParams = [
            'appid' => $this->appId,
            'mchid' => $this->mchId,
        ];
        $params = array_merge($publicParams, $params);
        $result = $this->execute('POST', $path, $params);
        return $this->getAppParameters($result['prepay_id']);
    }

    /**
     * 获取APP支付的参数
     * @param $prepay_id 预支付交易会话标识
     * @return array
     */
    private function getAppParameters($prepay_id)
    {
        $params = [
            'appid' => $this->appId,
            'partnerid' => $this->mchId,
            'prepayid' => $prepay_id,
            'package' => 'Sign=WXPay',
            'noncestr' => $this->getNonceStr(),
            'timestamp' => time().'',
        ];
        $params['sign'] = $this->makeSign([$params['appid'], $params['timestamp'], $params['noncestr'], $params['prepayid']]);
        return $params;
    }

    /**
     * 查询订单，微信订单号、商户订单号至少填一个
     * @param $transaction_id 微信订单号
     * @param $out_trade_no 商户订单号
     * @return mixed
     */
    public function orderQuery($transaction_id = null, $out_trade_no = null){
        if(!empty($transaction_id)){
            $path = '/v3/pay/transactions/id/'.$transaction_id;
        }elseif(!empty($out_trade_no)){
            $path = '/v3/pay/transactions/out-trade-no/'.$out_trade_no;
        }else{
            throw new \Exception('微信支付订单号和商户订单号不能同时为空');
        }
        
        $params = [
            'mchid' => $this->mchId,
        ];
        return $this->execute('GET', $path, $params);
    }

    /**
     * 判断订单是否已完成
     * @param $transaction_id 微信订单号
     * @return bool
     */
    public function orderQueryResult($transaction_id)
    {
        try {
            $data = $this->orderQuery($transaction_id);
            return $data['trade_state'] == 'SUCCESS' || $data['trade_state'] == 'REFUND';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 关闭订单
     * @param $out_trade_no 商户订单号
     * @return mixed
     */
    public function closeOrder($out_trade_no){
        $path = '/v3/pay/transactions/out-trade-no/'.$out_trade_no.'/close';
        $params = [
            'mchid' => $this->mchId,
        ];
        return $this->execute('POST', $path, $params);
    }

    /**
     * 申请退款
     * @param $params
     * @return mixed
     */
    public function refund($params){
        $path = '/v3/refund/domestic/refunds';
        return $this->execute('POST', $path, $params);
    }

    /**
     * 查询退款
     * @param $params
     * @return mixed
     */
    public function refundQuery($out_refund_no){
        $path = '/v3/refund/domestic/refunds/'.$out_refund_no;
        return $this->execute('GET', $path, []);
    }

    /**
     * 申请交易账单
     * @param $params
     * @return mixed
     */
    public function tradeBill($params){
        $path = '/v3/bill/tradebill';
        return $this->execute('GET', $path, $params);
    }

    /**
     * 申请资金账单
     * @param $params
     * @return mixed
     */
    public function fundflowBill($params){
        $path = '/v3/bill/fundflowbill';
        return $this->execute('GET', $path, $params);
    }

    /**
     * 支付通知处理
     * @return array 支付成功通知参数
     */
    public function notify()
    {
        $data = parent::notify();
        if (!$data || !isset($data['transaction_id']) && !isset($data['combine_out_trade_no'])) {
            throw new \Exception('缺少订单号参数');
        }
        if (!isset($data['combine_out_trade_no']) && !$this->orderQueryResult($data['transaction_id'])) {
            throw new \Exception('订单未完成');
        }
        return $data;
    }


    /**
     * 合单Native支付
     * @param $params 下单参数
     * @return mixed {"code_url":"二维码链接"}
     */
    public function combineNativePay($params){
        $path = '/v3/combine-transactions/native';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
        }
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * 合单JSAPI支付
     * @param $params 下单参数
     * @return array Jsapi支付json数据
     */
    public function combineJsapiPay($params){
        $path = '/v3/combine-transactions/jsapi';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
        }
        $params = array_merge($publicParams, $params);
        $result = $this->execute('POST', $path, $params);
        return $this->getJsApiParameters($result['prepay_id']);
    }

    /**
     * 合单H5支付
     * @param $params 下单参数
     * @return mixed {"h5_url":"支付跳转链接"}
     */
    public function combineH5Pay($params){
        $path = '/v3/combine-transactions/h5';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
        }
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * 合单APP支付
     * @param $params 下单参数
     * @return mixed {"prepay_id":"预支付交易会话标识"}
     */
    public function combineAppPay($params){
        $path = '/v3/combine-transactions/app';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        foreach($params['sub_orders'] as &$order){
            $order['mchid'] = $this->mchId;
        }
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * 合单查询订单
     * @param $combine_out_trade_no 合单商户订单号
     * @return mixed
     */
    public function combineQueryOrder($combine_out_trade_no){
        $path = '/v3/combine-transactions/out-trade-no/'.$combine_out_trade_no;
        
        return $this->execute('GET', $path, []);
    }

    /**
     * 合单关闭订单
     * @param $combine_out_trade_no 合单商户订单号
     * @param $out_trade_no_list 子单订单号列表
     * @return mixed
     */
    public function combineCloseOrder($combine_out_trade_no, $out_trade_no_list){
        $path = '/v3/combine-transactions/out-trade-no/'.$combine_out_trade_no.'/close';
        $sub_orders = [];
        foreach($out_trade_no_list as $out_trade_no){
            $sub_orders[] = [
                'mchid' => $this->mchId,
                'out_trade_no' => $out_trade_no,
            ];
        }
        $params = [
            'combine_appid' => $this->appId,
            'sub_orders' => $sub_orders
        ];
        return $this->execute('POST', $path, $params);
    }
}