<?php

class fuiou_plugin
{
	static public $info = [
		'name'        => 'fuiou', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '富友支付(前置商户)', //支付插件显示名称
		'author'      => '富友', //支付插件作者
		'link'        => 'https://www.fuiou.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户密钥',
				'type' => 'input',
				'note' => '',
			],
			'appurl' => [
				'name' => '订单号前缀',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		return ['type'=>'jump','url'=>'/pay/'.$order['typename'].'/'.TRADE_NO.'/'];
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

        $typename = $order['typename'];
        return self::$typename();
	}

	//通用下单
	static private function addOrder($pay_type){
		global $siteurl, $channel, $order, $ordername, $clientip, $conf;

		$apiurl = 'https://aipay.fuioupay.com/aggregatePay/preCreate';
		$param = [
			'version' => '1.0',
			'mchnt_cd' => $channel['appid'],
			'random_str' => random(32),
			'order_type' => $pay_type,
			'order_amt' => strval($order['realmoney']*100),
			'mchnt_order_no' => $channel['appurl'].TRADE_NO,
			'txn_begin_ts' => date('YmdHis'),
			'goods_des' => $ordername,
			'term_id' => rand(10000000,99999999).'',
			'term_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];

		$param_ord = ['mchnt_cd', 'order_type', 'order_amt', 'mchnt_order_no', 'txn_begin_ts', 'goods_des', 'term_id', 'term_ip', 'notify_url', 'random_str', 'version'];
		$signStr = '';
		foreach($param_ord as $key){
			$signStr .= $param[$key] . '|';
		}
		$signStr .= $channel['appkey'];
		$param['sign'] = md5($signStr);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiurl, $param) {
			$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);

			$result = json_decode($data, true);

			if(isset($result['result_code']) && $result['result_code']=='000000'){
				$code_url = $result['qr_code'];
			}else{
				throw new Exception($result['result_msg']?$result['result_msg']:'返回数据解析失败');
			}
			return $code_url;
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		try{
			$code_url = self::addOrder('ALIPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//微信扫码支付
	static public function wxpay(){
		global $siteurl, $device, $mdevice;
		try{
			$code_url = self::addOrder('WXXS');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if($mdevice == 'wechat' || strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::addOrder('UNIONPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'银联云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		//file_put_contents('logs.txt', $json);
		$arr = json_decode($json,true);

		$param_ord = ['mchnt_cd', 'mchnt_order_no', 'settle_order_amt', 'order_amt', 'txn_fin_ts', 'reserved_fy_settle_dt', 'random_str'];
		$signStr = '';
		foreach($param_ord as $key){
			$signStr .= $arr[$key] . '|';
		}
		$signStr .= $channel['appkey'];
		$sign = md5($signStr);

        if ($sign === $arr['sign']) {
			$out_trade_no = substr($arr['mchnt_order_no'],strlen($channel['appurl']));
			$trade_no = $arr['transaction_id'];
			$money = $arr['order_amt'];
			if($out_trade_no == TRADE_NO){
				processNotify($order, $trade_no);
			}
			return ['type'=>'html','data'=>'1'];
        }else{
			return ['type'=>'html','data'=>'0'];
		}

	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$apiurl = 'https://aipay.fuioupay.com/aggregatePay/commonRefund';

		if($order['type'] == 1) $pay_type = 'ALIPAY';
		else if($order['type'] == 2) $pay_type = 'WECHAT';
		else if($order['type'] == 4) $pay_type = 'UNIONPAY';

		$param = [
			'version' => '1.0',
			'mchnt_cd' => $channel['appid'],
			'term_id' => rand(10000000,99999999).'',
			'random_str' => random(32),
			'mchnt_order_no' => $channel['appurl'].$order['trade_no'],
			'refund_order_no' => 'REF'.$order['trade_no'],
			'order_type' => $pay_type,
			'total_amt' => strval($order['realmoney']*100),
			'refund_amt' => strval($order['refundmoney']*100),
		];

		$param_ord = ['mchnt_cd', 'order_type', 'mchnt_order_no', 'refund_order_no', 'total_amt', 'refund_amt', 'term_id', 'random_str', 'version'];
		$signStr = '';
		foreach($param_ord as $key){
			$signStr .= $param[$key] . '|';
		}
		$signStr .= $channel['appkey'];
		$param['sign'] = md5($signStr);

		$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);
		$result = json_decode($data, true);

		if($result["result_code"]=='000000'){
			$result = ['code'=>0, 'trade_no'=>$result['mchnt_order_no'], 'refund_fee'=>$result['reserved_refund_amt']];
		}else{
			$result = ['code'=>-1, 'msg'=>$result["result_msg"]];
		}
		return $result;
	}

}