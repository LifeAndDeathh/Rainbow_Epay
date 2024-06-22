<?php

class stripe_plugin
{
	static public $info = [
		'name'        => 'stripe', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => 'Stripe', //支付插件显示名称
		'author'      => 'Stripe', //支付插件作者
		'link'        => 'https://stripe.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank','paypal'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => 'API密钥',
				'type' => 'textarea',
				'note' => 'sk_live_开头的密钥',
			],
			'appkey' => [
				'name' => 'Webhook密钥',
				'type' => 'textarea',
				'note' => 'whsec_开头的密钥',
			],
		],
		'select' => null,
		'note' => '需设置WebHook地址：[siteurl]pay/webhook/[channel]/ ，侦听的事件: checkout.session.completed、checkout.session.async_payment_succeeded', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $conf;

		require_once PAY_ROOT.'inc/StripeClient.php';

		if($order['typename']=='alipay'){
			$payment_method='alipay';
		}elseif($order['typename']=='wxpay'){
			$payment_method='wechat_pay';
		}elseif($order['typename']=='paypal'){
			$payment_method='paypal';
		}else{
			$payment_method='';
		}

		try{
			$stripe = new Stripe\StripeClient($channel['appid']);
			//$price = currency_convert('CNY', 'HKD', $order['realmoney']);
			$amount = intval(round($order['realmoney'] * 100));
			$data = [
				'success_url'         => $siteurl.'pay/return/'.TRADE_NO.'/',
				'cancel_url'          => $siteurl.'pay/error/'.TRADE_NO.'/',
				'client_reference_id' => TRADE_NO,
				'line_items' => [[
					'price_data' => [
						'currency'     => 'CNY',
						'product_data' => [
							'name' => $ordername
						],
						'unit_amount'  => $amount 
					],
					'quantity'   => 1
				]],
				'mode'                => 'payment'
			];
			if($payment_method)$data['payment_method_types'] = [$payment_method];
			if($payment_method == 'wechat_pay'){
				$data['payment_method_options']['wechat_pay']['client'] = 'web';
			}
			$result = $stripe->request('post', '/v1/checkout/sessions', $data);
			$jump_url = $result['url'];
			return ['type'=>'jump','url'=>$jump_url];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	//异步回调
	static public function webhook(){
		global $channel, $order, $DB;

		require_once PAY_ROOT.'inc/Webhook.php';

		$payload = file_get_contents('php://input');
		$data = json_decode($payload, true);
		if(!$data){
			http_response_code(400);
			return ['type'=>'html','data'=>'no data'];
		}

		$out_trade_no = daddslashes($data['data']['object']['client_reference_id']);
		$order = $DB->getRow("SELECT * FROM pre_order WHERE trade_no='$out_trade_no' limit 1");
		if(!$order){
			http_response_code(400);
			return ['type'=>'html','data'=>'no order'];
		}

		$channel = \lib\Channel::get($order['channel']);
		if(!$channel){
			http_response_code(400);
			return ['type'=>'html','data'=>'no channel'];
		}

		$endpoint_secret = $channel['appkey'];
		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
		$event = null;

		try {
			$event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
		} catch(Exception $e) {
			http_response_code(400);
			return ['type'=>'html','data'=>$e->getMessage()];
		}

		switch($event['type']){
			case 'checkout.session.completed':
				$session = $event['data']['object'];
				if ($session['payment_status'] == 'paid') {
					processNotify($order, $session['payment_intent']);
				}
				break;
			case 'checkout.session.async_payment_succeeded':
				$session = $event['data']['object'];
				processNotify($order, $session['payment_intent']);
				break;
		}
		return ['type'=>'html','data'=>'success'];
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//同步回调
	static public function error(){
		return ['type'=>'page','page'=>'error'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require_once PAY_ROOT.'inc/StripeClient.php';

		try{
			$stripe = new Stripe\StripeClient($channel['appid']);
			$amount = intval(round($order['refundmoney'] * 100));
			$data = [
				'payment_intent' => $order['api_trade_no'],
				'amount' => $amount,
			];
			$result = $stripe->request('post', '/v1/refunds', $data);
			return ['code'=>0, 'trade_no'=>$result['payment_intent'], 'refund_fee'=>$result['amount']/100];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

}