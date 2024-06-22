<?php

class wxpaysl_plugin
{
	static public $info = [
		'name'        => 'wxpaysl', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '微信官方支付服务商版', //支付插件显示名称
		'author'      => '微信', //支付插件作者
		'link'        => 'https://pay.weixin.qq.com/partner/public/home', //支付插件作者链接
		'types'       => ['wxpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '公众号或小程序APPID',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户API密钥',
				'type' => 'input',
				'note' => 'APIv2密钥',
			],
			'appurl' => [
				'name' => '子商户号',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => [ //选择已开启的支付方式
			'1' => '扫码支付',
			'2' => '公众号支付',
			'3' => 'H5支付',
			'4' => '小程序支付',
			'5' => 'APP支付',
		],
		'note' => '<p>上方APPID填写公众号或小程序的皆可，需要在微信服务商后台关联对应的公众号或小程序才能使用。无认证的公众号或小程序无法发起支付！</p><p>如需资金下发（如退款）功能，请将<a href="https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=4_3" target="_blank" rel="noreferrer">API证书</a>放置于<font color="red">/plugins/wxpay/cert/</font>文件夹（或<font color="red">/plugins/wxpay/cert/商户ID/</font>文件夹）</p><p>点金计划商家小票链接（用于公众号支付跳转回网站）：[siteurl]gold.php</p>', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $submit2, $conf;

		$urlpre = '/';
		if (!empty($conf['localurl_wxpay']) && !strpos($conf['localurl_wxpay'], $_SERVER['HTTP_HOST'])) {
			$urlpre = $conf['localurl_wxpay'];
		}
		
		if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
			if(in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/jspay/'.TRADE_NO.'/?d=1'];
			}elseif(in_array('4',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/wap/'.TRADE_NO.'/'];
			}elseif(in_array('1',$channel['apptype']) && $conf['wework_payopen'] == 1){
				return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
			}else{
				if(!$submit2){
					return ['type'=>'jump','url'=>'/pay/submit/'.TRADE_NO.'/'];
				}
				return ['type'=>'page','page'=>'wxopen'];
			}
		}elseif(checkmobile()==true){
			if(in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/h5/'.TRADE_NO.'/'];
			}elseif(in_array('5',$channel['apptype']) && strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone OS')!==false){
				return ['type'=>'jump','url'=>$urlpre.'pay/apppay/'.TRADE_NO.'/'];
			}elseif(in_array('2',$channel['apptype']) || in_array('4',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/wap/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
			}
		}else{
			return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		$urlpre = $siteurl;
		if (!empty($conf['localurl_wxpay']) && !strpos($conf['localurl_wxpay'], $_SERVER['HTTP_HOST'])) {
			$urlpre = $conf['localurl_wxpay'];
		}

		if($mdevice=='wechat'){
			if(in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/jspay/'.TRADE_NO.'/?d=1'];
			}elseif(in_array('4',$channel['apptype'])){
				return self::wap();
			}else{
				return ['type'=>'jump','url'=>$siteurl.'pay/submit/'.TRADE_NO.'/'];
			}
		}elseif($device=='mobile'){
			if(in_array('5',$channel['apptype']) && $mdevice == 'app'){
				return self::apppay();
			}elseif(in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/h5/'.TRADE_NO.'/'];
			}elseif(in_array('5',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/submit/'.TRADE_NO.'/'];
			}elseif(in_array('2',$channel['apptype']) || in_array('4',$channel['apptype'])){
				return self::wap();
			}else{
				return self::qrcode();
			}
		}else{
			return self::qrcode();
		}
	}

	//扫码支付
	static public function qrcode(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if(in_array('1',$channel['apptype'])){

		$params = [
			'body' => $ordername,
			'out_trade_no' => TRADE_NO,
			'total_fee' => strval($order['realmoney']*100),
			'spbill_create_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'product_id' => '01001',
		];
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		$client = new \WeChatPay\PaymentService($wechatpay_config);
		try{
			$result = $client->nativePay($params);
			$code_url = $result['code_url'];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
		}elseif(in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/jspay/'.TRADE_NO.'/';
		}elseif(in_array('4',$channel['apptype'])){
			$code_url = $siteurl.'pay/wap/'.TRADE_NO.'/';
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}
		return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
	}

	//JS支付
	static public function jspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
		
		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
		$channel['appid'] = $wxinfo['appid'];

		//①、获取用户openid
		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->GetOpenid();
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;

		//②、统一下单
		$params = [
			'body' => $ordername,
			'out_trade_no' => TRADE_NO,
			'total_fee' => strval($order['realmoney']*100),
			'spbill_create_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'openid' => $openid,
		];
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		$client = new \WeChatPay\PaymentService($wechatpay_config);
		try{
			$result = $client->jsapiPay($params);
			$jsApiParameters = json_encode($result);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
		
		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$jsApiParameters, 'redirect_url'=>$redirect_url]];
	}

	//聚合收款码接口
	static public function jsapi($type,$money,$name,$openid){
		global $siteurl, $channel, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) throw new Exception('支付通道绑定的微信公众号不存在');
		$channel['appid'] = $wxinfo['appid'];

		$params = [
			'body' => $name,
			'out_trade_no' => TRADE_NO,
			'total_fee' => strval($money*100),
			'spbill_create_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'openid' => $openid,
		];
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		$client = new \WeChatPay\PaymentService($wechatpay_config);
		try{
			$result = $client->jsapiPay($params);
			$jsApiParameters = json_encode($result);
			return $jsApiParameters;
		}catch(Exception $e){
			throw new Exception('微信支付下单失败！'.$e->getMessage());
		}
	}

	//手机支付
	static public function wap(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
		
		if(in_array('4',$channel['apptype']) && !isset($_GET['qrcode'])){
			try{
				$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
				if(!$wxinfo)return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			$code_url = $siteurl.'pay/jspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}
	}

	//H5支付
	static public function h5(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$scene_info = [
			'h5_info' => [
				'type' => 'Wap',
				'wap_url' => $siteurl,
				'wap_name' => $conf['sitename']
			]
		];
		$params = [
			'body' => $ordername,
			'out_trade_no' => TRADE_NO,
			'total_fee' => strval($order['realmoney']*100),
			'spbill_create_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'scene_info' => json_encode($scene_info, JSON_UNESCAPED_UNICODE),
		];
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		$client = new \WeChatPay\PaymentService($wechatpay_config);
		try{
			$result = $client->h5Pay($params);
			$redirect_url=$siteurl.'pay/return/'.TRADE_NO.'/';
			$url=$result['mweb_url'].'&redirect_url='.urlencode($redirect_url);
			return ['type'=>'jump','url'=>$url];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
	}

	//小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		$channel['appid'] = $wxinfo['appid'];
		
		//①、获取用户openid
		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->AppGetOpenid($code);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		//②、统一下单
		$params = [
			'body' => $ordername,
			'out_trade_no' => TRADE_NO,
			'total_fee' => strval($order['realmoney']*100),
			'spbill_create_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'openid' => $openid,
		];
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		$client = new \WeChatPay\PaymentService($wechatpay_config);
		try{
			$jsApiParameters = $client->jsapiPay($params);
			exit(json_encode(['code'=>0, 'data'=>$jsApiParameters]));
		}catch(Exception $e){
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败！'.$e->getMessage()]));
		}
	}

	//APP支付
	static public function apppay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $mdevice;

		$params = [
			'body' => $ordername,
			'out_trade_no' => TRADE_NO,
			'total_fee' => strval($order['realmoney']*100),
			'spbill_create_ip' => $clientip,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		];
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		$client = new \WeChatPay\PaymentService($wechatpay_config);
		try{
			$result = $client->appPay($params);
			if($mdevice == 'app'){
				exit(json_encode(['code'=>0, 'data'=>$result]));
			}
			$param = [
				'nonceStr' => $result['noncestr'],
				'package' => $result['package'],
				'partnerId' => $result['partnerid'],
				'prepayId' => $result['prepayid'],
				'timeStamp' => $result['timestamp'],
				'sign' => $result['sign'],
			];
			$code_url = 'weixin://app/'.$result['appid'].'/pay/?'.http_build_query($param);
			return ['type'=>'qrcode','page'=>'wxpay_h5','url'=>$code_url];
		}catch(Exception $e){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$isSuccess = true;
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\PaymentService($wechatpay_config);
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
			'total_fee' => strval($order['realmoney']*100),
			'refund_fee' => strval($order['refundmoney']*100),
		];
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		$client = new \WeChatPay\PaymentService($wechatpay_config);
		try{
			$result = $client->refund($params);
			$result = ['code'=>0, 'trade_no'=>$result['transaction_id'], 'refund_fee'=>$result['refund_fee']];
		} catch(Exception $e) {
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}
}