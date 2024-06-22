<?php

class qqpay_plugin
{
	static public $info = [
		'name'        => 'qqpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => 'QQ钱包官方支付', //支付插件显示名称
		'author'      => 'QQ钱包', //支付插件作者
		'link'        => 'https://mp.qpay.tenpay.com/', //支付插件作者链接
		'types'       => ['qqpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['qqpay'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => 'QQ钱包商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => 'QQ钱包API密钥',
				'type' => 'input',
				'note' => '',
			],
			'appurl' => [
				'name' => '操作员账号',
				'type' => 'input',
				'note' => '仅资金下发（如退款、企业付款）时需要',
			],
			'appmchid' => [
				'name' => '操作员密码',
				'type' => 'input',
				'note' => '仅资金下发（如退款、企业付款）时需要',
			],
		],
		'select' => [ //选择已开启的支付方式
			'1' => '扫码支付(包含H5)',
			'2' => '公众号支付',
		],
		'note' => '<p>如需资金下发（如退款、企业付款）功能，请将<a href="https://mp.qpay.tenpay.com/buss/wiki/206/1213" target="_blank" rel="noreferrer">API证书</a>放置于<font color="red">/plugins/qqpay/cert/</font>文件夹（或<font color="red">/plugins/qqpay/cert/商户ID/</font>文件夹），并填写<a href="https://kf.qq.com/faq/170112AZ7Fzm170112VNz6zE.html" target="_blank" rel="noreferrer">操作员账号和密码</a></p>', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $submit2, $conf;

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false && in_array('2',$channel['apptype'])){
			return ['type'=>'jump','url'=>'/pay/jspay/'.TRADE_NO.'/'];
		}else{
			return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

		if($mdevice=='qq' && in_array('2',$channel['apptype'])){
			return ['type'=>'jump','url'=>$siteurl.'pay/jspay/'.TRADE_NO.'/'];
		}else{
			return self::qrcode();
		}
	}

	//扫码支付
	static public function qrcode(){
		global $channel, $order, $ordername, $conf, $clientip;

		$params = [
			'out_trade_no' => TRADE_NO,
			'body' => $ordername,
			'fee_type' => 'CNY',
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'spbill_create_ip' => $clientip,
			'total_fee' => strval($order['realmoney']*100),
		];
		$qqpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \QQPay\PaymentService($qqpay_config);
			$result = $client->nativePay($params);
			$code_url = $result['code_url'];
			//$code_url = 'https://myun.tenpay.com/mqq/pay/qrcode.html?_wv=1027&_bid=2183&t='.$result['prepay_id'];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$e->getMessage()];
		}

		if(checkmobile()==true && !isset($_GET['qrcode'])){
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false){
				return ['type'=>'jump','url'=>$code_url];
			}
			return ['type'=>'qrcode','page'=>'qqpay_wap','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'qqpay_qrcode','url'=>$code_url];
		}
	}

	//JS支付
	static public function jspay(){
		global $channel, $order, $ordername, $conf, $clientip;

		$params = [
			'out_trade_no' => TRADE_NO,
			'body' => $ordername,
			'fee_type' => 'CNY',
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'spbill_create_ip' => $clientip,
			'total_fee' => strval($order['realmoney']*100),
		];
		$qqpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \QQPay\PaymentService($qqpay_config);
			$result = $client->jsapiPay($params);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$e->getMessage()];
		}
		
		return ['type'=>'page','page'=>'qqpay_jspay','data'=>$result];
	}

	//聚合收款码接口
	static public function jsapi($type,$money,$name,$openid){
		global $siteurl, $channel, $conf, $clientip;

		$params = [
			'out_trade_no' => TRADE_NO,
			'body' => $name,
			'fee_type' => 'CNY',
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'spbill_create_ip' => $clientip,
			'total_fee' => strval($money*100),
		];
		$qqpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \QQPay\PaymentService($qqpay_config);
			$result = $client->jsapiPay($params);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$e->getMessage()];
		}
		$paydata = json_encode($result);
		return $paydata;
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$isSuccess = true;
		$qqpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \QQPay\PaymentService($qqpay_config);
			$data = $client->notify();
			if($data['out_trade_no'] == TRADE_NO && $data['total_fee']==strval($order['realmoney']*100)){
				processNotify($order, $data['transaction_id'], $data['openid']);
			}
		}catch(Exception $e){
			$isSuccess = false;
			$errmsg = $e->getMessage();
		}

		$client->replyNotify($isSuccess, $errmsg);
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$params = [
			'transaction_id' => $order['api_trade_no'],
			'out_refund_no' => $order['trade_no'],
			'refund_fee' => strval($order['refundmoney']*100),
		];
		$qqpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \QQPay\PaymentService($qqpay_config);
			$result = $client->refund($params);
			$result = ['code'=>0, 'trade_no'=>$result['transaction_id'], 'refund_fee'=>$result['refund_fee']];
		} catch(Exception $e) {
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}

	//转账
	static public function transfer($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$money = strval($bizParam['money'] * 100);
		$qqpay_config = require(PLUGIN_ROOT.'qqpay/inc/config.php');
		try{
			$client = new \QQPay\TransferService($qqpay_config);
			$result = $client->transfer($bizParam['out_biz_no'], $bizParam['payee_account'], $bizParam['payee_real_name'], $money, $bizParam['transfer_desc']);
			return ['code'=>0, 'status'=>1, 'orderid'=>$result['transaction_id'], 'paydate'=>date('Y-m-d H:i:s')];
		}catch(\QQPay\QQPayException $e){
			$result = $e->getResponse();
			return ['code'=>-1, 'errcode'=>$result['err_code'], 'msg'=>$e->getMessage()];
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$qqpay_config = require(PLUGIN_ROOT.'qqpay/inc/config.php');
		try{
			$client = new \QQPay\TransferService($qqpay_config);
			$result = $client->transferQuery($bizParam['out_biz_no']);
			if($result['status'] == 'SUCCESS'){
				$status = 1;
			}elseif($result['status'] == 'REFUND'){
				$status = 2;
			}else{
				$status = 0;
			}
			return ['code'=>0, 'status'=>$status, 'amount'=>round($result['total_fee']/100, 2), 'paydate'=>$result['transfer_time'], 'errmsg'=>''];
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}
}