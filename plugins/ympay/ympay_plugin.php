<?php

/**
 * @see https://bhzogpy55h.feishu.cn/wiki/wikcnKR6RBkeDIeW8MHv4VbVNEh
 */
class ympay_plugin
{
	static public $info = [
		'name'        => 'ympay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '源铭SAAS平台', //支付插件显示名称
		'author'      => '源铭', //支付插件作者
		'link'        => 'https://www.xgymwl.cn/', //支付插件作者链接
		'types'       => ['alipay','wxpay','qqpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appurl' => [
				'name' => 'API网关',
				'type' => 'input',
				'note' => '',
			],
			'appid' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '商户编号，代理商填写代理商机构号',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appkey' => [
				'name' => '平台公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appmchid' => [
				'name' => '子商户编号',
				'type' => 'input',
				'note' => '代理商才需要填写，普通商户需留空',
			],
		],
		'select_alipay' => [
			'1' => '当面付',
			'2' => 'PC支付',
			'3' => 'H5支付',
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => '公众号支付',
			'3' => '小程序H5支付',
			'4' => 'H5支付',
		],
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()==true){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='qqpay'){
			return ['type'=>'jump','url'=>'/pay/qqpay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat'){
				return self::wxjspay();
			}elseif($device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='qqpay'){
			return self::qqpay();
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//统一支付接口
	static private function addOrder($paytype, $sub_appid = null, $openid = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/PayService.class.php");

		$params = [
			'trade_type' => $paytype,
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'subject' => $ordername,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'client_ip' => $clientip,
		];
		if($sub_appid && $openid){
			$params['sub_appid'] = $sub_appid;
			$params['user_id'] = $openid;
			$params['channe_expend'] = json_encode(['is_raw' => '1']);
		}

		$client = new PayService($channel['appurl'],$channel['appid'],$channel['appmchid'],$channel['appkey'],$channel['appsecret']);
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->submit('pay.order/create', $params);
			return $result;
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device;
		$pay_type = 'alipayQr';
		if(in_array('3',$channel['apptype']) && (checkmobile() || $device == 'mobile')){
			$pay_type = 'alipayWap';
		}elseif(in_array('2',$channel['apptype']) && (!checkmobile() || $device == 'pc')){
			$pay_type = 'alipayPc';
		}
		try{
			$result = self::addOrder($pay_type);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		if($pay_type == 'alipayQr'){
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$result['payurl']];
		}else{
			return ['type'=>'jump','url'=>$result['payurl']];
		}
	}

	//微信扫码支付
	static public function wxpay(){
		try{
			$result = self::addOrder('wechatQr');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
			return ['type'=>'jump','url'=>$result['payurl']];
		} elseif (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$result['payurl']];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$result['payurl']];
		}
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl, $channel, $order;

		if(in_array('4',$channel['apptype'])){
			try{
				$result = self::addOrder('wechatWap');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$result['payurl']];
		}elseif(in_array('3',$channel['apptype'])){
			try{
				$result = self::addOrder('wechatLiteH5');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$result['payurl']];
		}elseif ($channel['appwxa']>0) {
            $wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
            try {
                $code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
            } catch (Exception $e) {
                return ['type'=>'error','msg'=>$e->getMessage()];
            }
            return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
        }elseif($channel['appwxmp']>0 || in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel;

		if($channel['appwxmp']>0){
			//①、获取用户openid
			$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
			try{
				$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
				$openid = $tools->GetOpenid();
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			$blocks = checkBlockUser($openid, TRADE_NO);
			if($blocks) return $blocks;

			//②、统一下单
			try{
				$result = self::addOrder('wechatPub', $wxinfo['appid'], $openid);
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}

			if($_GET['d']==1){
				$redirect_url='data.backurl';
			}else{
				$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
			}
			return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$result['payInfo'], 'redirect_url'=>$redirect_url]];
		}else{
			try{
				$result = self::addOrder('wechatPub');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$result['payurl']];
		}
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');
		
		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->AppGetOpenid($code);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');
		
		//②、统一下单
		try{
			$payinfo = self::addOrder('wechatLite', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($payinfo, true)]));
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::addOrder('qqQr');
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
			$code_url = self::addOrder('unionQr');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require(PAY_ROOT."inc/PayService.class.php");
		
		$client = new PayService($channel['appurl'],$channel['appid'],$channel['appmchid'],$channel['appkey'],$channel['appsecret']);
		$verify_result = $client->verifySign($_POST);

		if($verify_result) {//验证成功

			if ($_POST['order_status'] == 'SUCCESS') {
				$out_trade_no = $_POST['out_trade_no'];
				$api_trade_no = $_POST['trade_no'];
				$money = $_POST['total_amount'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $api_trade_no);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			return ['type'=>'html','data'=>'fail'];
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

		require(PAY_ROOT."inc/PayService.class.php");

		$params = [
			'refund_amount' => $order['refundmoney'],
			'refund_reason' => '订单退款',
			'out_refund_no' => date("YmdHis").rand(1111,9999),
			'trade_no' => $order['api_trade_no'],
		];
		
		try{
			$client = new PayService($channel['appurl'],$channel['appid'],$channel['appmchid'],$channel['appkey'],$channel['appsecret']);
			$result = $client->submit('pay.order/refund', $params);

			return ['code'=>0, 'trade_no'=>$result['out_refund_no'], 'refund_fee'=>$result['refund_amount']];

		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}
}