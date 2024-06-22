<?php

class jinyihui_plugin
{
	static public $info = [
		'name'        => 'jinyihui', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '金易汇', //支付插件显示名称
		'author'      => '金易汇', //支付插件作者
		'link'        => 'https://www.svip5.net/', //支付插件作者链接
		'types'       => ['alipay','qqpay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '应用AppID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '应用MD5密钥',
				'type' => 'input',
				'note' => '',
			],
			'appswitch' => [
				'name' => '支付模式',
				'type' => 'select',
				'options' => [0=>'跳转支付（默认）',1=>'API接口支付'],
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	const API_URL = 'https://gateway.svip5.net/';

	static private function make_sign($param, $key){
		ksort($param);
		$signstr = '';
	
		foreach($param as $k => $v){
			if($k != "sign" && $k != "sign_type" && $v!=''){
				$signstr .= $k.'='.$v.'&';
			}
		}
		$signstr = substr($signstr,0,-1);
		$sign = md5($signstr.$key);
		return $sign;
	}

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $conf;

		if($channel['appswitch']==1){
			return ['type'=>'jump','url'=>'/pay/'.$order['typename'].'/'.TRADE_NO.'/'];
		}

		$apiurl = self::API_URL.'submit.php';
		$param = array(
			"pid" => $channel['appid'],
			"type" => $order['typename'],
			"notify_url"	=> $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			"return_url"	=> $siteurl.'pay/return/'.TRADE_NO.'/',
			"out_trade_no"	=> TRADE_NO,
			"name"	=> $ordername,
			"money"	=> $order['realmoney']
		);
		$param['sign'] = self::make_sign($param, $channel['appkey']);
		$param['sign_type'] = 'MD5';

		$html_text = '<form id="dopay" action="'.$apiurl.'" method="post">';
		foreach ($param as $k=>$v) {
			$html_text.= '<input type="hidden" name="'.$k.'" value="'.$v.'"/>';
		}
		$html_text .= '<input type="submit" value="正在跳转"></form><script>document.getElementById("dopay").submit();</script>';
		return ['type'=>'html','data'=>$html_text];
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

		if($channel['appswitch']==1){
			$typename = $order['typename'];
			return self::$typename();
		}else{
			return ['type'=>'jump','url'=>$siteurl.'pay/submit/'.TRADE_NO.'/'];
		}
	}

	//mapi支付
	static private function addOrder($pay_type, $openid = null){
		global $siteurl, $channel, $order, $ordername, $sitename, $conf, $clientip;

		$apiurl = self::API_URL.'mapi.php';
		$param = array(
			"pid" => $channel['appid'],
			"type" => $pay_type,
			"device" => 'pc',
			"clientip" => $clientip,
			"notify_url"	=> $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			"return_url"	=> $siteurl.'pay/return/'.TRADE_NO.'/',
			"out_trade_no"	=> TRADE_NO,
			"name"	=> $ordername,
			"money"	=> $order['realmoney']
		);
		$param['sign'] = self::make_sign($param, $channel['appkey']);
		$param['sign_type'] = 'MD5';

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiurl, $param) {
			$data = get_curl($apiurl, http_build_query($param));

			$result = json_decode($data, true);

			if(isset($result['code']) && $result['code']==1){
				return $result['qrcode'];
			}else{
				throw new Exception($result['msg']?$result['msg']:'返回数据解析失败');
			}
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		try{
			$code_url = self::addOrder('alipay');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//微信扫码支付
	static public function wxpay(){
		try{
			$code_url = self::addOrder('wxpay');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>$ex->getMessage()];
		}

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::addOrder('qqpay');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$ex->getMessage()];
		}

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false){
			return ['type'=>'jump','url'=>$code_url];
		} elseif(checkmobile() && !isset($_GET['qrcode'])){
			return ['type'=>'qrcode','page'=>'qqpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'qqpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::addOrder('bank');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		if(!isset($_GET)) return ['type'=>'html','data'=>'no data'];

		$sign = self::make_sign($_GET, $channel['appkey']);

		if($sign===$_GET["sign"]){
			//商户订单号
			$out_trade_no = $_GET['out_trade_no'];

			//易支付交易号
			$trade_no = $_GET['trade_no'];

			//交易金额
			$money = $_GET['money'];

			if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
				if($out_trade_no == TRADE_NO && round($money,2)==round($order['realmoney'],2)){
					processNotify($order, $trade_no);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//同步回调
	static public function return(){
		global $channel, $order;

		$sign = self::make_sign($_GET, $channel['appkey']);

		if($sign===$_GET["sign"]){
			//商户订单号
			$out_trade_no = $_GET['out_trade_no'];

			//易支付交易号
			$trade_no = $_GET['trade_no'];

			//交易金额
			$money = $_GET['money'];

			if($_GET['trade_status'] == 'TRADE_SUCCESS') {
				if ($out_trade_no == TRADE_NO && round($money, 2)==round($order['realmoney'], 2)) {
					processReturn($order, $trade_no);
				}else{
					return ['type'=>'error','msg'=>'订单信息校验失败'];
				}
			}else{
				return ['type'=>'error','msg'=>'trade_status='.$_GET['trade_status']];
			}
		}
		else {
			//验证失败
			return ['type'=>'error','msg'=>'验证失败！'];
		}
	}

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();
		
		$apiurl = self::API_URL.'api/refund/confrim';
		$param = [
			'appid' => $channel['appid'],
			'trade_no' => $order['api_trade_no'],
			'money' => $order['refundmoney'],
			'timestamp' => ''.time()
		];
		$param['sign'] = self::make_sign($param, $channel['appkey']);
		$data = get_curl($apiurl, http_build_query($param));
		$result = json_decode($data, true);

		if(isset($result['code']) && $result['code']==200){
			return ['code'=>0];
		}else{
			return ['code'=>-1, 'msg'=>$result['message']?$result['message']:'接口返回错误'];
		}
	}

}