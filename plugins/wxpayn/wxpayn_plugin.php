<?php

class wxpayn_plugin
{
	static public $info = [
		'name'        => 'wxpayn', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '微信官方支付V3', //支付插件显示名称
		'author'      => '微信', //支付插件作者
		'link'        => 'https://pay.weixin.qq.com/', //支付插件作者链接
		'types'       => ['wxpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['wxpay'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
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
				'name' => '商户API证书序列号',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户APIv3密钥',
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
		'note' => '<p>请将商户API私钥“apiclient_key.pem”放到 /plugins/wxpayn/cert/ 文件夹内（或 /plugins/wxpayn/cert/商户号/ 文件夹内）。</p><p>上方APPID填写公众号或小程序的皆可，需要在微信支付后台关联对应的公众号或小程序才能使用。无认证的公众号或小程序无法发起支付！</p>', //支付密钥填写说明
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

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->nativePay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys);
				$result = $client->combineNativePay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO);
			}
			$code_url = $result['code_url'];
		} catch (Exception $e) {
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
		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openid
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->jsapiPay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys);
				$result = $client->combineJsapiPay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO);
			}
			$jsApiParameters = json_encode($result);
		} catch (Exception $e) {
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
		global $siteurl, $channel, $order, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) throw new Exception('支付通道绑定的微信公众号不存在');
		$channel['appid'] = $wxinfo['appid'];

		$param = [
			'description' => $name,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($money*100)),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openid
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->jsapiPay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys);
				$result = $client->combineJsapiPay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO);
			}
			$jsApiParameters = json_encode($result);
			return $jsApiParameters;
		} catch (Exception $e) {
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

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip,
				'h5_info' => [
					'type' => 'Wap',
					'app_name' => $conf['sitename'],
					'app_url' => $siteurl,
				],
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->h5Pay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys);
				$result = $client->combineH5Pay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO);
			}
			$redirect_url=$siteurl.'pay/return/'.TRADE_NO.'/';
			$url=$result['h5_url'].'&redirect_url='.urlencode($redirect_url);
			return ['type'=>'jump','url'=>$url];
		} catch (Exception $e) {
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
		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openid
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$jsApiParameters = $client->jsapiPay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys);
				$jsApiParameters = $client->combineJsapiPay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO);
			}
			exit(json_encode(['code'=>0, 'data'=>$jsApiParameters]));
		} catch (Exception $e) {
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败！'.$e->getMessage()]));
		}
	}

	//APP支付
	static public function apppay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $mdevice;

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];
		if($order['profits']>0){
			$param['settle_info'] = ['profit_sharing' => true];
		}
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$submoneys = combinepay_submoneys($param['amount']['total']);
			if(!$submoneys){
				$result = $client->appPay($param);
			}else{
				$param = self::combineOrderParams($param, $submoneys);
				$result = $client->combineAppPay($param);
				\lib\Payment::updateOrderCombine(TRADE_NO);
			}
			if($mdevice == 'app'){
				exit(json_encode(['code'=>0, 'data'=>$result]));
			}
			$params = [
				'nonceStr' => $result['noncestr'],
				'package' => $result['package'],
				'partnerId' => $result['partnerid'],
				'prepayId' => $result['prepayid'],
				'timeStamp' => $result['timestamp'],
				'sign' => $result['sign'],
			];
			$code_url = 'weixin://app/'.$result['appid'].'/pay/?'.http_build_query($params);
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

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$data = $client->notify();
		} catch (Exception $e) {
			$client->replyNotify(false, $e->getMessage());
			exit;
		}

		if(isset($data['combine_out_trade_no'])){ //合单支付
			if($data['combine_out_trade_no'] == TRADE_NO){
				processNotify($order, $data['combine_out_trade_no'], $data['combine_payer_info']['openid']);
			}
		}else{
			if ($data['trade_state'] == 'SUCCESS') {
				if($data['out_trade_no'] == TRADE_NO){
					processNotify($order, $data['transaction_id'], $data['payer']['openid']);
				}
			}
		}
		$client->replyNotify(true);
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$param = [
			'transaction_id' => $order['api_trade_no'],
			'out_refund_no' => $order['trade_no'],
			'amount' => [
				'refund' => intval(round($order['refundmoney']*100)),
				'total' => intval(round($order['realmoney']*100)),
				'currency' => 'CNY'
			]
		];

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$result = $client->refund($param);
			$result = ['code'=>0, 'trade_no'=>$result['out_trade_no'], 'refund_fee'=>$result['amount']['refund']];
		} catch (Exception $e) {
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}

	//合单退款
	static public function refund_combine($order){
		global $channel;
		if(empty($order))exit();

		$refundmoney = intval(round($order['refundmoney']*100));
		$totalmoney = intval(round($order['realmoney']*100));
		$leftmoney = $refundmoney;
		if($refundmoney>$totalmoney){
			return ['code'=>-1, 'msg'=>'退款金额不能大于订单金额'];
		}

		//查询子单列表
		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\PaymentService($wechatpay_config);
			$result = $client->combineQueryOrder($order['api_trade_no']);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		//循环退款
		$i = 1;
		$success = 0;
		foreach($result['sub_orders'] as $sub_order){
			$money = $sub_order['amount']['total_amount'];
			if($refundmoney<$totalmoney){
				if($leftmoney-$money<0) $money = $leftmoney;
			}
			$param = [
				'transaction_id' => $sub_order['transaction_id'],
				'out_refund_no' => $order['trade_no'].$i,
				'amount' => [
					'refund' => $money,
					'total' => $sub_order['amount']['total_amount'],
					'currency' => $sub_order['amount']['currency'],
				]
			];
	
			try{
				$result = $client->refund($param);
				$success++;
			} catch (Exception $e) {
				return ['code'=>-1, 'msg'=>$e->getMessage()];
			}
			$i++;
			$leftmoney-=$money;
			if($leftmoney<=0)break;
		}

		return ['code'=>0];
	}

	//处理合单支付参数
	static private function combineOrderParams($param, $submoneys){
		$sub_orders = [];
        $i = 1;
        foreach($submoneys as $money){
            $sub_order = [
                'attach' => 'combine',
                'amount' => [
                    'total_amount' => $money,
                    'currency' => $param['amount']['currency'],
                ],
                'out_trade_no' => $param['out_trade_no'].$i,
                'description' => $param['description'],
            ];
            $sub_orders[] = $sub_order;
            $i++;
        }
        $newparam = [
            'combine_out_trade_no' => $param['out_trade_no'],
            'scene_info' => $param['scene_info'],
            'sub_orders' => $sub_orders,
            'notify_url' => $param['notify_url'],
        ];
		if(isset($param['payer'])){
			$newparam['combine_payer_info'] = $param['payer'];
		}
		return $newparam;
	}

	static private $fail_reason_desc = ['ACCOUNT_FROZEN'=>'该用户账户被冻结', 'REAL_NAME_CHECK_FAIL'=>'收款人未实名认证', 'NAME_NOT_CORRECT'=>'收款人姓名校验不通过', 'OPENID_INVALID'=>'Openid校验失败', 'TRANSFER_QUOTA_EXCEED'=>'超过用户单笔收款额度', 'DAY_RECEIVED_QUOTA_EXCEED'=>'超过用户单日收款额度', 'MONTH_RECEIVED_QUOTA_EXCEED'=>'超过用户单月收款额度', 'DAY_RECEIVED_COUNT_EXCEED'=>'超过用户单日收款次数', 'PRODUCT_AUTH_CHECK_FAIL'=>'未开通该权限或权限被冻结', 'OVERDUE_CLOSE'=>'超过系统重试期，系统自动关闭', 'ID_CARD_NOT_CORRECT'=>'收款人身份证校验不通过', 'ACCOUNT_NOT_EXIST'=>'该用户账户不存在', 'TRANSFER_RISK'=>'该笔转账可能存在风险，已被微信拦截', 'OTHER_FAIL_REASON_TYPE'=>'其它失败原因', 'REALNAME_ACCOUNT_RECEIVED_QUOTA_EXCEED'=>'用户账户收款受限，请引导用户在微信支付查看详情', 'RECEIVE_ACCOUNT_NOT_PERMMIT'=>'未配置该用户为转账收款人', 'PAYER_ACCOUNT_ABNORMAL'=>'商户账户付款受限，可前往商户平台获取解除功能限制指引', 'PAYEE_ACCOUNT_ABNORMAL'=>'用户账户收款异常，请引导用户完善身份信息', 'TRANSFER_REMARK_SET_FAIL'=>'转账备注设置失败，请调整后重新再试','TRANSFER_SCENE_UNAVAILABLE'=>'该转账场景暂不可用，请确认转账场景ID是否正确','TRANSFER_SCENE_INVALID'=>'你尚未获取该转账场景，请确认转账场景ID是否正确','RECEIVE_ACCOUNT_NOT_CONFIGURE'=>'请前往商户平台-商家转账到零钱-前往功能-转账场景中添加','BLOCK_B2C_USERLIMITAMOUNT_MONTH'=>'用户账户存在风险收款受限，本月不支持继续向该用户付款','MERCHANT_REJECT'=>'转账验密人已驳回转账','MERCHANT_NOT_CONFIRM'=>'转账验密人超时未验密'];

	//转账
	static public function transfer($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		$out_batch_no = $bizParam['out_biz_no'];
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		$transfer_detail = [
			'out_detail_no' => $bizParam['out_biz_no'],
			'transfer_amount' => intval(round($bizParam['money']*100)),
			'transfer_remark' => $bizParam['transfer_desc'],
			'openid' => $bizParam['payee_account'],
		];
		if(!empty($bizParam['payee_real_name'])){
			$transfer_detail['user_name'] = $client->rsaEncrypt($bizParam['payee_real_name']);
		}
		$param = [
			'out_batch_no' => $out_batch_no,
			'batch_name' => '转账给'.$bizParam['payee_real_name'],
			'batch_remark' => date("YmdHis"),
			'total_amount' => intval(round($bizParam['money']*100)),
			'total_num' => 1,
			'transfer_detail_list' => [
				$transfer_detail
			],
		];

		try{
			$result = $client->transfer($param);
		} catch (Exception $e) {
			$errorMsg = $e->getMessage();
			if(!strpos($errorMsg, '对应的订单已经存在')){
				return ['code'=>-1, 'msg'=>$errorMsg];
			}
		}
		$batch_id = $result['batch_id'];

		usleep(500000);

		try{
			$result = $client->transferoutdetail($out_batch_no, $bizParam['out_biz_no']);
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		if($result['detail_status'] == 'PROCESSING'){
			return ['code'=>0, 'status'=>0, 'orderid'=>$result['detail_id'], 'paydate'=>$result['update_time']];
		}elseif($result['detail_status'] == 'FAIL'){
			return ['code'=>-1, 'errcode'=>$result['fail_reason'], 'msg'=>'['.$result['fail_reason'].']'.self::$fail_reason_desc[$result['fail_reason']]];
		}elseif($result['detail_status'] == 'SUCCESS'){
			return ['code'=>0, 'status'=>1, 'orderid'=>$result['detail_id'], 'paydate'=>$result['update_time']];
		}else{
			return ['code'=>-1, 'msg'=>'转账状态未知'];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$wechatpay_config = require(PLUGIN_ROOT.'wxpayn/inc/config.php');
		try{
			$client = new \WeChatPay\V3\TransferService($wechatpay_config);
			$result = $client->transferoutdetail($bizParam['out_biz_no'], $bizParam['out_biz_no']);
			if($result['detail_status'] == 'SUCCESS'){
				$status = 1;
			}elseif($result['detail_status'] == 'FAIL'){
				$status = 2;
			}else{
				$status = 0;
			}
			return ['code'=>0, 'status'=>$status, 'amount'=>round($result['transfer_amount']/100, 2), 'paydate'=>$result['update_time'], 'errmsg'=>'['.$result['fail_reason'].']'.self::$fail_reason_desc[$result['fail_reason']]];
		} catch (Exception $e) {
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	//投诉通知回调
	static public function complainnotify(){
		global $channel;

		$wechatpay_config = require(PAY_ROOT.'inc/config.php');
		try{
			$client = new \WeChatPay\V3\BaseService($wechatpay_config);
			$data = $client->notify();
		} catch (Exception $e) {
			$client->replyNotify(false, $e->getMessage());
			exit;
		}

		$model = \lib\Complain\CommUtil::getModel($channel);
		$model->refreshNewInfo($data['complaint_id'], $data['action_type']);

		$client->replyNotify(true);
	}
}