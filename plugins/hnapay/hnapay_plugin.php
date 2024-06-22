<?php
/***
 * https://www.yuque.com/chenyanfei-sjuaz/uhng8q
 */

class hnapay_plugin
{
	static public $info = [
		'name'        => 'hnapay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '新生支付', //支付插件显示名称
		'author'      => '新生支付', //支付插件作者
		'link'        => 'https://www.hnapay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户ID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '新生公钥(新收款密钥)',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥(新收款密钥)',
				'type' => 'textarea',
				'note' => '',
			],
			'appmchid' => [
				'name' => '报备编号',
				'type' => 'input',
				'note' => '',
			],
			'appswitch' => [
                'name' => '接口类型',
                'type' => 'select',
                'options' => [0 => '公众号/生活号支付', 1 => '支付宝H5', 2 => '扫码支付'],
            ],
		],
		'select' => null,
		'note' => '需要使用RSA密钥！<br/>如使用扫码支付，需将<b>收款密钥</b>中的<b>商户私钥</b>上传到/plugins/hnapay/cert/mch.key<br/>如使用付款功能，需将<b>付款密钥</b>中的<b>商户私钥</b>上传到/plugins/hnapay/cert/pay.key', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
            if($channel['appswitch']==0 && strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient')!==false){
                return ['type' => 'jump', 'url' => '/pay/alipayjs/' . TRADE_NO . '/?d=1'];
            }elseif($channel['appswitch']==1 && strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient')!==false){
                return ['type' => 'jump', 'url' => '/pay/alipayh5/' . TRADE_NO . '/'];
            }else{
                return ['type' => 'jump', 'url' => '/pay/alipay/' . TRADE_NO . '/'];
            }
		}elseif($order['typename']=='wxpay'){
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()==true){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
            }else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			if($channel['appswitch']==0 && $mdevice=='alipay'){
				return ['type' => 'jump', 'url' => '/pay/alipayjs/' . TRADE_NO . '/?d=1'];
			}elseif($channel['appswitch']==1 && $mdevice=='alipay'){
				return ['type' => 'jump', 'url' => '/pay/alipayh5/' . TRADE_NO . '/'];
			}else{
				return self::alipay();
			}
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat'){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//二维码下单通用
	static private function qrcode($orgCode){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$param = [
			'merOrderNum' => TRADE_NO,
			'tranAmt' => strval($order['realmoney']*100),
			'submitTime' => substr(TRADE_NO, 0, 14),
			'orgCode' => $orgCode,
			'goodsName' => $ordername,
			'tranIP' => $clientip,
			'notifyUrl' => $conf['localurl'].'pay/notifys/'.TRADE_NO.'/',
			'weChatMchId' => $channel['appmchid'],
		];
		
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 1);
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($pay, $param) {
			$result = $pay->scanPay($param);
			return $result['qrCodeUrl'];
		});
	}

	//JSAPI下单通用
	static private function jsapi($orgCode, $appId, $openId){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$param = [
			'tranAmt' => $order['realmoney'],
			'orgCode' => $orgCode,
			'notifyServerUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'merUserIp' => $clientip,
			'goodsInfo' => $ordername,
			'orderSubject' => $ordername,
			'merchantId' => $channel['appmchid'],
		];
		if($orgCode == 'WECHATPAY'){
			$param += [
				'appId' => $appId,
				'openId' => $openId,
			];
		}elseif($orgCode == 'ALIPAY'){
			$param += [
				'aliAppId' => $appId,
				'buyerId' => $openId,
			];
		}

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($pay, $param) {
			$result = $pay->jsapiPay($param, TRADE_NO);
			return $result['payInfo'];
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $siteurl;
		if($channel['appswitch']==2){
			try{
				$code_url = self::qrcode('ALIPAY');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}elseif($channel['appswitch']==1){
			if(checkmobile()){
				return self::alipayh5();
			}else{
				$code_url = $siteurl.'pay/alipayh5/'.TRADE_NO.'/?d=1';
			}
		}else{
			$code_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
		}
		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//支付宝JS支付
	static public function alipayjs(){
		global $conf;
		if(!isset($_GET['userid'])){
			$redirect_uri = '/pay/alipayjs/'.TRADE_NO.'/';
			return ['type'=>'jump','url'=>'/user/oauth.php?state='.urlencode(authcode($redirect_uri, 'ENCODE', SYS_KEY))];
		}

		$blocks = checkBlockUser($_GET['userid'], TRADE_NO);
		if($blocks) return $blocks;

		$achannel = \lib\Channel::get($conf['login_alipay']);

		try{
			$retData = self::jsapi('ALIPAY', $achannel['appid'], $_GET['userid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$retData['tradeNO'], 'redirect_url'=>$redirect_url]];
	}

	//支付宝H5支付
	static public function alipayh5(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if($_GET['d']=='1'){
			$front_url=$siteurl.'pay/ok/'.TRADE_NO.'/';
		}else{
			$front_url=$siteurl.'pay/return/'.TRADE_NO.'/';
		}
		require(PAY_ROOT.'inc/HnaPayApi.class.php');
		$param = [
			'tranAmt' => $order['realmoney'],
			'payType' => 'HnaZFB',
			'exPayMode'=>'',
			'cardNo'=>'',
			'holderName'=>'',
			'identityCode'=>'',
			'merUserId'=>'',
			'orderExpireTime'=>'10',
			'frontUrl' => $front_url,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'riskExpand'=>'',
			'goodsInfo'=>'',
			'orderSubject' => $ordername,
			'orderDesc'=>'',
			'merchantId' => json_encode(['02'=>$channel['appmchid']]),
			'bizProtocolNo'=>'',
			'payProtocolNo'=>'',
			'merUserIp' => $clientip,
			'payLimit'=>'',
		];
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$html = $pay->h5Pay($param, TRADE_NO);
		return ['type'=>'html','data'=>$html];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl;
		if($channel['appswitch']==2){
			try {
				$code_url = self::qrcode('WECHATPAY');
			} catch (Exception $ex) {
				return ['type' => 'error', 'msg' => '微信支付下单失败！' . $ex->getMessage()];
			}
		}else{
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}
		if (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

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

		try{
			$pay_info = self::jsapi('WECHATPAY', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($pay_info), 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');

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

		try{
			$pay_info = self::jsapi('WECHATPAY', $wxinfo['appid'], $openid);
		}catch(Exception $ex){
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败 '.$ex->getMessage()]));
		}

		exit(json_encode(['code'=>0, 'data'=>$pay_info]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if($channel['appwxa']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}


	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('UNIONPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'银联云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		if(!isset($_POST)) return ['type'=>'html','data'=>'no_data'];
		//file_put_contents('logs.txt' , http_build_query($_POST));

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);

		if($_POST['tranCode'] == 'MUP11'){
			$verify_result = $pay->alipayh5Verify($_POST);
		}else{
			$verify_result = $pay->jsapiVerify($_POST);
		}
		if($verify_result){
			if($_POST['resultCode'] == '0000'){
				$out_trade_no = $_POST['merOrderId'];
				$trade_no = $_POST['hnapayOrderId'];
				if(!empty($_POST['realBankOrderId'])) $trade_no = $_POST['realBankOrderId'];
				$money = $_POST['tranAmt'];
				$buyer = $_POST['userId'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $trade_no, $buyer);
				}
				return ['type'=>'html','data'=>'200'];
			}else{
				return ['type'=>'html','data'=>'200'];
			}
		}else{
			return ['type'=>'html','data'=>'sign_error'];
		}
	}

	//异步回调(扫码支付)
	static public function notifys(){
		global $channel, $order;

		//file_put_contents('logs.txt' , http_build_query($_POST));

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 1);

		if($pay->scanVerify($_POST)){
			if($_POST['respCode'] == '0000'){
				$out_trade_no = $_POST['merOrderNum'];
				$trade_no = $_POST['hnapayOrderId'];
				if(!empty($_POST['realBankOrderId'])) $trade_no = $_POST['realBankOrderId'];
				$money = $_POST['tranAmt'];
				$buyer = $_POST['userId'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $trade_no, $buyer);
				}
				return ['type'=>'html','data'=>'200'];
			}else{
				return ['type'=>'html','data'=>'200'];
			}
		}else{
			return ['type'=>'html','data'=>'sign_error'];
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

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();

		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$param = [
			'orgMerOrderId' => $order['trade_no'],
			'orgSubmitTime' => substr($order['trade_no'], 0, 14),
			'orderAmt' => $order['realmoney'],
			'refundOrderAmt' => $order['refundmoney'],
			'notifyUrl' => $conf['localurl'].'pay/refundnotify/'.TRADE_NO.'/',
		];

		try{
			$result = $pay->refund($param, 'REF'.$order['trade_no']);
			return ['code'=>0, 'trade_no'=>$result['orgMerOrderId'], 'refund_fee'=>$result['refundAmt']];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//退款异步回调
	static public function refundnotify(){
		global $channel, $order;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret']);
		if($pay->jsapiVerify($_POST)){
			if($_POST['resultCode'] == '0000'){
				return ['type'=>'html','data'=>'200'];
			}else{
				return ['type'=>'html','data'=>'status_error'];
			}
		}else{
			return ['type'=>'html','data'=>'sign_error'];
		}
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $conf, $clientip;
		if(empty($channel) || empty($bizParam))exit();

		define('PAY_ROOT', PLUGIN_ROOT.$channel['plugin'].'/');
		require(PAY_ROOT.'inc/HnaPayApi.class.php');

		$param = [
			'tranAmt' => $bizParam['money'],
			'payType' => '1',
			'auditFlag' => '0',
			'payeeName' => $bizParam['payee_real_name'],
			'payeeAccount' => $bizParam['payee_account'],
			'note' => '',
			'remark' => $bizParam['transfer_desc'],
			'bankCode' => '',
			'payeeType' => '1',
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
			'paymentTerminalInfo' => '01|A10001',
			'deviceInfo' => $clientip,
		];

		$client = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 2);
		try{
			$result = $client->transfer($param, $bizParam['out_biz_no']);
			return ['code'=>0, 'status'=>1, 'orderid'=>$result['hnapayOrderId'], 'paydate'=>date('Y-m-d H:i:s')];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//付款异步回调
	static public function transfernotify(){
		global $channel;

		require(PAY_ROOT.'inc/HnaPayApi.class.php');
		$pay = new HnaPayApi($channel['appid'], $channel['appkey'], $channel['appsecret'], 2);
		if($pay->transferVerify($_POST)){
			if($_POST['resultCode'] == '0000'){
				$status = 1;
			}else{
				$status = 2;
			}
			if($_POST['errorMsg']){
				$errmsg = '['.$_POST['errorCode'].']'.$_POST['errorMsg'];
			}
			processTransfer($_POST['merOrderId'], $status, $errmsg);
			return ['type'=>'html','data'=>'200'];
		}else{
			return ['type'=>'html','data'=>'sign_error'];
		}
	}

}