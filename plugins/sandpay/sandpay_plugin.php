<?php

class sandpay_plugin
{
	static public $info = [
		'name'        => 'sandpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '杉德支付', //支付插件显示名称
		'author'      => '杉德', //支付插件作者
		'link'        => 'https://www.sandpay.com.cn/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '私钥证书密码',
				'type' => 'input',
				'note' => '',
			],
			'appswitch' => [
				'name' => '环境选择',
				'type' => 'select',
				'options' => [0=>'生产环境',1=>'测试环境'],
			],
		],
		'select_bank' => [
			'1' => '银联支付',
			'2' => '快捷支付',
		],
		'select' => null,
		'note' => '将私钥证书命名为client.pfx（或商户编号.pfx）上传到 /plugins/sandpay/cert/', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()==true && $channel['appwxa']>0){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			if(in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/fastpay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
			}
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && $channel['appwxa']>0){
				return ['type'=>'jump','url'=>$siteurl.'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			if(in_array('2',$channel['apptype'])){
				return self::fastpay();
			}else{
				return self::bank();
			}
		}
	}

	//扫码下单
	static private function qrcode($productId, $payTool){
		global $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/Build.class.php");

		$client = new SandpayCommon($channel['appid'], $channel['appkey'], $channel['appswitch']);
		$client->productId = $productId;
		$client->body      = array(
			'payTool'     => $payTool,
			'orderCode'   => TRADE_NO,
			'totalAmount' => str_pad(strval($order['realmoney']*100),12,'0',STR_PAD_LEFT),
			'subject'     => $ordername,
			'body'        => $ordername,
			'notifyUrl'   => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client) {
			$ret = $client->request('orderCreate');
			return $ret['qrCode'];
		});
	}

	//收银台下单
	static private function cashier(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/Build.class.php");

		$client = new SandpayCommon($channel['appid'], $channel['appkey'], $channel['appswitch']);
		$client->productId = '00002000';
		$client->body      = array(
			'orderCode'   => TRADE_NO,
			'totalAmount' => str_pad(strval($order['realmoney']*100),12,'0',STR_PAD_LEFT),
			'subject'     => $ordername,
			'body'        => $ordername,
			'notifyUrl'   => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'frontUrl'    => $siteurl.'pay/return/'.TRADE_NO.'/',
		);
		$html_text = $client->form('cashierPay');
		return ['type'=>'html','data'=>$html_text];
	}

	//新版H5跳转下单
	static private function newh5($product_code, $pay_extra = []){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/Build.class.php");
		$client = new SandpayCommon($channel['appid'], $channel['appkey'], $channel['appswitch']);

		$param = [
			'version' => '10',
			'mer_no' =>  $channel['appid'],
			'mer_order_no' => TRADE_NO,
			'create_time' => date('YmdHis'),
			'order_amt' => $order['realmoney'],
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'create_ip' => str_replace('.','_',$clientip),
			'pay_extra' => json_encode($pay_extra),
			'accsplit_flag' => 'NO',
			'sign_type' => 'RSA',
			'store_id' => '000000',
		];
		$param['sign'] = $client->getSign($param);
		$param += [
			'expire_time' => date('YmdHis', time()+30*60),
			'goods_name' => $ordername,
			'product_code' => $product_code,
			'clear_cycle' => '1',
			'jump_scheme' => 'sandcash://scpay',
			'meta_option' => json_encode([["s" => "Android","n" => "wxDemo","id" => "com.pay.paytypetest","sc" => "com.pay.paytypetest"]]),
		];

		$query = http_build_query($param);

		if($product_code == '02010006'){
			$attr = 'applet';
		}elseif($product_code == '02020005'){
			$attr = 'alipaycode';
		}elseif($product_code == '02010002'){
			$attr = 'wechatpay';
		}elseif($product_code == '02020002'){
			$attr = 'alipay';
		}elseif($product_code == '02000001'){
			$attr = 'qrcode';
		}elseif($product_code == '05030001'){
			$attr = 'fastpayment';
		}elseif($product_code == '06030001'){
			$attr = 'unionpayh5';
		}

		if($channel['appswitch'] == 1){
			$payurl = "https://sandcash-uat01.sand.com.cn/pay/h5/".$attr."?".$query;
		}else{
			$payurl = "https://sandcash.mixienet.com.cn/pay/h5/".$attr."?".$query;
		}
		return $payurl;
	}

	//支付宝扫码支付
	static public function alipay(){
		/*if (checkmobile()==true) {
			$payurl = self::newh5('02020002');

			return ['type'=>'jump','url'=>$payurl];
		}else{*/
			try{
				$code_url = self::qrcode('00000006','0401');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
			}
	
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		//}
	}

	//微信扫码支付
	static public function wxpay(){
		try{
			$code_url = self::qrcode('00000005','0402');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}else{
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('00000012','0403');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//快捷支付
	static public function fastpay(){
		if(checkmobile())
			$payurl = self::newh5('06030001');
		else
			$payurl = self::newh5('05030001');
		return ['type'=>'jump','url'=>$payurl];
	}

	//微信公众号
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

		$payurl = self::newh5('02010002', ["mer_app_id"=>$wxinfo['appid'],"openid"=>$openid]);

		return ['type'=>'jump','url'=>$payurl];
	}

	//微信H5包装云函数
	static public function wxwappay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];

		$payurl = self::newh5('02010006', ["resourceAppid"=>$wxinfo['appid'],"resourceEnv"=>""]);

		return ['type'=>'jump','url'=>$payurl];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$sign   = $_POST['sign']; //签名
		$data   = stripslashes($_POST['data']); //支付数据

		//file_put_contents('logs.txt', 'sign='.$_POST['sign']."\r\n\r\ndata=".$_POST['data']);

		require(PAY_ROOT."inc/Build.class.php");

		$client = new SandpayCommon($channel['appid'], $channel['appkey'], $channel['appswitch']);
		$verifyFlag = $client->verify($data, $sign);

		if($verifyFlag){
			$array = json_decode($data, true);
			if($array['head']['respCode'] == '000000'){
				$out_trade_no = $array['body']['orderCode'];
				$trade_no = $array['body']['tradeNo'];
				$money = $array['body']['totalAmount'];
				$buyer = $array['body']['accLogonNo'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $trade_no, $buyer);
				}
				return ['type'=>'html','data'=>'respCode=000000'];
			}
		}
		return ['type'=>'html','data'=>'respCode=020002'];
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();

		require(PAY_ROOT."inc/Build.class.php");

		$client = new SandpayCommon($channel['appid'], $channel['appkey'], $channel['appswitch']);
		$client->body      = array(
			'orderCode'          => $order['api_trade_no'], //新的订单号
			'oriOrderCode'       => $order['trade_no'], //原订单号
			'refundAmount'       => str_pad(strval($order['refundmoney']*100),12,'0',STR_PAD_LEFT), //退款金额
			'notifyUrl'          => $conf['localurl'].'pay/refundnotify/'.TRADE_NO.'/',
		);
		try{
			$ret = $client->request('orderRefund');
			return ['code'=>0, 'trade_no'=>$ret['orderCode'], 'refund_fee'=>$ret['refundAmount']];
		}catch(Exception $ex){
			return ['code'=>-1,'msg'=>$ex->getMessage()];
		}
	}

	//退款回调
	static public function refundnotify(){
		global $channel, $order;

		$sign   = $_POST['sign']; //签名
		$data   = stripslashes($_POST['data']); //支付数据

		require(PAY_ROOT."inc/Build.class.php");

		$client = new SandpayCommon($channel['appid'], $channel['appkey'], $channel['appswitch']);
		$verifyFlag = $client->verify($data, $sign);

		if($verifyFlag){
			$array = json_decode($data, true);
			if($array['head']['respCode'] == '000000'){
				$out_trade_no = $array['body']['orderCode'];
				$trade_no = $array['body']['tradeNo'];
				$money = $array['body']['totalAmount'];
				return ['type'=>'html','data'=>'respCode=000000'];
			}
		}
		return ['type'=>'html','data'=>'respCode=020002'];
	}

	//转账
	static public function transfer($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require(PAY_ROOT."inc/Transfer.class.php");

		$client = new SandpayTransfer($channel['appid'], $channel['appkey']);
		try{
			$result = $client->agentpay($bizParam['out_biz_no'], $bizParam['payee_account'], $bizParam['payee_real_name'], $bizParam['money'], $bizParam['transfer_desc']);
			if (isset($result['respCode']) && $result['respCode']=='0000') {
				$status = $result['resultFlag'] == 0 ? 1 : 0;
				return ['code'=>0, 'status'=>$status, 'orderid'=>$result['sandSerial'], 'paydate'=>$result['tranDate']];
			}else{
				return ['code'=>-1, 'errcode'=>$result['respCode'], 'msg'=>'['.$result['respCode'].']'.$result['respDesc']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		require(PAY_ROOT."inc/Transfer.class.php");

		$client = new SandpayTransfer($channel['appid'], $channel['appkey']);
		try{
			$result = $client->queryOrder($bizParam['out_biz_no']);
			if (isset($result['respCode']) && $result['respCode']=='0000') {
				$status = $result['resultFlag'] == 0 ? 1 : 0;
				return ['code'=>0, 'status'=>$status];
			}else{
				return ['code'=>-1, 'msg'=>'['.$result['respCode'].']'.$result['respDesc']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//余额查询
	static public function balance_query($channel, $bizParam){
		if(empty($channel))exit();

		require(PAY_ROOT."inc/Transfer.class.php");

		$client = new SandpayTransfer($channel['appid'], $channel['appkey']);
		$out_biz_no = date("YmdHis").rand(11111,99999);
		try{
			$result = $client->queryBalance($out_biz_no);
			if (isset($result['respCode']) && $result['respCode']=='0000') {
				return  ['code'=>0, 'ammount'=>$result['balance'], 'msg'=>'当前账户余额：'.$result['balance'].' 元，可用额度：'.$result['creditAmt']];
			}else{
				return ['code'=>-1, 'msg'=>'['.$result['respCode'].']'.$result['respDesc']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}
}