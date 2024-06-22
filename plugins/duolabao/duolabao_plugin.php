<?php

class duolabao_plugin
{
	static public $info = [
		'name'        => 'duolabao', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '哆啦宝支付', //支付插件显示名称
		'author'      => '哆啦宝', //支付插件作者
		'link'        => 'http://www.duolabao.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','qqpay','bank','jdpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '店铺编号',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '公钥',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '私钥',
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

	//通用创建订单
	static public function addOrder(){
		global $channel, $order, $ordername, $conf, $clientip;

		require PAY_ROOT.'inc/PayApp.class.php';
		$pay_config = include PAY_ROOT.'inc/config.php';
		$client = new PayApp($pay_config);
		
		$param = [
			'customerNum' => $pay_config['customerNum'],
			'shopNum'     => $pay_config['shopNum'],
			'requestNum'  => TRADE_NO,
			'amount'      => sprintf('%.2f' , $order['realmoney']),
			'source'      => 'API',
			'callbackUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'completeUrl' => $siteurl . 'pay/return/' . TRADE_NO . '/',
		];

		$result = $client->submit('/v1/customer/order/payurl/create', $param);
		return $result['url'];
	}

	//支付宝扫码支付
	static public function alipay(){
		try{
			$code_url = self::addOrder();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//微信扫码支付
	static public function wxpay(){
		try{
			$code_url = self::addOrder();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
			return ['type'=>'jump','url'=>$code_url];
		}elseif (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::addOrder();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$ex->getMessage()];
		}

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false){
			return ['type'=>'jump','url'=>$code_url];
		} elseif(checkmobile() && !isset($_GET['qrcode'])){
			return ['type'=>'qrcode','page'=>'qqpay_wap','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'qqpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::addOrder();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		if (checkmobile()==true) {
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
		}
	}

	//京东支付
	static public function jdpay(){
		try{
			$code_url = self::addOrder();
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'京东支付下单失败！'.$ex->getMessage()];
		}

		if (checkmobile()==true) {
			return ['type'=>'jump','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'jdpay_qrcode','url'=>$code_url];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		//@file_put_contents('./query.txt' , json_encode($_GET));
		require PAY_ROOT.'inc/PayApp.class.php';
		$pay_config = include PAY_ROOT.'inc/config.php';
		$client = new PayApp($pay_config);
		
		if ($client->verifyNotify()) {
			$trade_no = daddslashes($_GET['requestNum']); //流水号
			$api_trade_no = daddslashes($_GET['orderNum']); //订单编号
			$orderAmount = $_GET['orderAmount']; //订单金额
			if ($_GET['status'] == 'SUCCESS') {
				if ($trade_no == TRADE_NO && round($order['realmoney'],2) == round($orderAmount,2)) {
					processNotify($order, $api_trade_no);
				}
				return ['type'=>'html','data'=>'success'];
			}else{
				return ['type'=>'html','data'=>'fail'];
			}
		} else {
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require PAY_ROOT.'inc/PayApp.class.php';
		$pay_config = include PAY_ROOT.'inc/config.php';
		$client = new PayApp($pay_config);

		$param = [
			'customerNum' => $pay_config['customerNum'],
			'shopNum'     => $pay_config['shopNum'],
			'requestNum'  => $order['trade_no'],
			'refundPartAmount' => $order['refundmoney']
		];
		try{
			$result = $client->submit('/v1/customer/order/refund/part', $param);
			return ['code'=>0, 'trade_no'=>$result['orderNum'], 'refund_fee'=>$result['refundAmount']];
		}catch(Exception $ex){
			return ['code'=>-1,'msg'=>$ex->getMessage()];
		}
	}
}