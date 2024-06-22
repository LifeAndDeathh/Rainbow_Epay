<?php

class heepay_plugin
{
	static public $info = [
		'name'        => 'heepay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '汇付宝', //支付插件显示名称
		'author'      => '汇付宝', //支付插件作者
		'link'        => 'https://www.heepay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['alipay','wxpay','bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '支付密钥',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '退款密钥',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '付款密钥',
				'type' => 'input',
				'note' => '不需要付款功能的可留空',
			],
			'appurl' => [
				'name' => '付款3DES加密密钥',
				'type' => 'input',
				'note' => '不需要付款功能的可留空',
			],
		],
		'select_bank' => [
			'1' => '网银支付',
			'2' => '银联支付',
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static private function getPayParam($pay_type, $sub_appid = null, $sub_openid = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $device, $mdevice;

		$param = [
			'version' => '1',
			'pay_type' => $pay_type,
			'agent_id' => $channel['appid'],
			'agent_bill_id' => TRADE_NO,
			'agent_bill_time' => date('YmdHis'),
			'pay_amt' => $order['realmoney'],
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'user_ip' => str_replace('.', '_', $clientip),
			'goods_name' => mb_convert_encoding($ordername,'GBK','UTF-8'),
			'sign_type' => 'MD5'
		];
		if(checkmobile() || $device=='mobile'){
			$param['is_phone'] = '1';
		}
		if($pay_type == '30' && $sub_appid && $sub_openid){
			$param['meta_option'] = base64_encode('{"s":"'.mb_convert_encoding('微信小程序','GBK','UTF-8').'","n":"'.mb_convert_encoding('在线商城','GBK','UTF-8').'","id":"'.$siteurl.'","is_minipg":"1","wx_openid":"'.$sub_openid.'","wx_sub_appid":"'.$sub_appid.'"}');
		}
		elseif($pay_type == '30' && (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false || $mdevice=='wechat')){
			$param['is_frame'] = '1';
			$param['meta_option'] = base64_encode('{"s":"WAP","n":"'.mb_convert_encoding('在线商城','GBK','UTF-8').'","id":"'.$siteurl.'"}');
		}

		$signstr = 'version='.$param['version'].'&agent_id='.$param['agent_id'].'&agent_bill_id='.$param['agent_bill_id'].'&agent_bill_time='.$param['agent_bill_time'].'&pay_type='.$param['pay_type'].'&pay_amt='.$param['pay_amt'].'&notify_url='.$param['notify_url'].'&return_url='.$param['return_url'].'&user_ip='.$param['user_ip'].'&key='.$channel['appkey'];
		$param['sign'] = md5($signstr);
		return $param;
	}

	static private function getBankPayParam(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip, $device, $mdevice;

		$param = [
			'version' => '3',
			'pay_type' => '20',
			'pay_code' => '0',
			'agent_id' => $channel['appid'],
			'agent_bill_id' => TRADE_NO,
			'agent_bill_time' => date('YmdHis'),
			'pay_amt' => $order['realmoney'],
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'return_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'user_ip' => str_replace('.', '_', $clientip),
			'goods_name' => mb_convert_encoding($ordername,'GBK','UTF-8'),
			'bank_card_type' => '-1',
			'sign_type' => 'MD5'
		];

		$signstr = 'version='.$param['version'].'&agent_id='.$param['agent_id'].'&agent_bill_id='.$param['agent_bill_id'].'&agent_bill_time='.$param['agent_bill_time'].'&pay_type='.$param['pay_type'].'&pay_amt='.$param['pay_amt'].'&notify_url='.$param['notify_url'].'&return_url='.$param['return_url'].'&user_ip='.$param['user_ip'].'&bank_card_type='.$param['bank_card_type'].'&key='.$channel['appkey'];
		$param['sign'] = md5($signstr);
		return $param;
	}

	static public function submit(){
		global $siteurl, $channel, $order;

		if($order['typename'] == 'alipay'){
			$pay_type = '22';
		}elseif($order['typename'] == 'wxpay'){
			if(checkmobile() && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')===false){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}
			$pay_type = '30';
		}elseif($order['typename'] == 'bank'){
			if(in_array('1',$channel['apptype'])){
				$pay_type = '20';
			}else{
				$pay_type = checkmobile() ? '34' : '64';
			}
		}

		$apiurl = 'https://pay.Heepay.com/Payment/Index.aspx';
		$param = $pay_type == '20' ? self::getBankPayParam() : self::getPayParam($pay_type);
		$url = $apiurl.'?'.http_build_query($param);

		return ['type'=>'jump','url'=>$url];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf;

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
		$apiurl = 'https://pay.Heepay.com/Payment/Index.aspx';
		$param = self::getPayParam('30', $wxinfo['appid'], $openid);
		$response = get_curl($apiurl, http_build_query($param));
		$result = json_decode($response, true);
		if(isset($result['package'])){
			exit(json_encode(['code'=>0, 'data'=>$result]));
		}elseif(preg_match('!Object moved to <a href=\"(.*?)\">here!', $response, $match)){
			if(strpos($match[1], 'Error.aspx?message=')){
				$message = explode('Error.aspx?message=', $match[1])[1];
				$message = self::unicode_urldecode($message);
				exit('{"code":-1,"msg":"'.$message.'"}');
			}else{
				exit('{"code":-1,"msg":"微信支付下单失败！"}');
			}
		}else{
			exit('{"code":-1,"msg":"微信支付下单失败！"}');
		}
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl, $channel, $order;

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
			$code_url = $siteurl.'pay/submit/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$signstr = 'result='.$_GET['result'].'&agent_id='.$_GET['agent_id'].'&jnet_bill_no='.$_GET['jnet_bill_no'].'&agent_bill_id='.$_GET['agent_bill_id'].'&pay_type='.$_GET['pay_type'].'&pay_amt='.$_GET['pay_amt'].'&remark='.$_GET['remark'].'&key='.$channel['appkey'];
		$sign = md5($signstr);

		if($sign===$_GET["sign"]){
			if($_GET['result'] == '1'){
				$out_trade_no = $_GET['agent_bill_id'];
				$api_trade_no = $_GET['jnet_bill_no'];
				$money = $_GET['pay_amt'];

				if ($out_trade_no == TRADE_NO && round($money,2)==round($order['realmoney'],2)) {
					processNotify($order, $api_trade_no, $_GET['pay_user']);
				}
				return ['type'=>'html','data'=>'ok'];
			}else{
				return ['type'=>'html','data'=>'result='.$_GET['result']];
			}
		}else{
			return ['type'=>'html','data'=>'error'];
		}
	}

	//同步回调
	static public function return(){
		global $channel, $order;

		$signstr = 'result='.$_GET['result'].'&agent_id='.$_GET['agent_id'].'&jnet_bill_no='.$_GET['jnet_bill_no'].'&agent_bill_id='.$_GET['agent_bill_id'].'&pay_type='.$_GET['pay_type'].'&pay_amt='.$_GET['pay_amt'].'&remark='.$_GET['remark'].'&key='.$channel['appkey'];
		$sign = md5($signstr);

		if($sign===$_GET["sign"]){
			if($_GET['result'] == '1'){
				$out_trade_no = $_GET['agent_bill_id'];
				$api_trade_no = $_GET['jnet_bill_no'];
				$money = $_GET['pay_amt'];

				if ($out_trade_no == TRADE_NO && round($money,2)==round($order['realmoney'],2)) {
					processReturn($order, $api_trade_no, $_GET['pay_user']);
				}else{
					return ['type'=>'error','msg'=>'订单信息校验失败'];
				}
			}else{
				return ['type'=>'error','msg'=>'result='.$_GET['result']];
			}
		}else{
			return ['type'=>'error','msg'=>'验证失败！'];
		}
	}

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();

		$apiurl = 'https://pay.heepay.com/API/Payment/PaymentRefund.aspx';
		$param = [
			'version' => '1',
			'agent_id' => $channel['appid'],
			'agent_bill_id' => $order['trade_no'],
			'notify_url' => $conf['localurl'].'pay/refundnotify/'.TRADE_NO.'/',
			'sign_type' => 'MD5'
		];
		$signstr = 'agent_bill_id='.$param['agent_bill_id'].'&agent_id='.$param['agent_id'].'&key='.$channel['appsecret'].'&notify_url='.$param['notify_url'].'&version='.$param['version'];
		$param['sign'] = md5(strtolower($signstr));

		$data = get_curl($apiurl, http_build_query($param));
		$data = mb_convert_encoding($data,'UTF-8','GBK');
		libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

		if(isset($result['ret_code']) && $result['ret_code']=='0000'){
			$result = ['code'=>0];
		}else{
			$result = ['code'=>-1, 'msg'=>$result["ret_msg"]?$result["ret_msg"]:'返回内容解析失败'];
		}
		return $result;
	}

	//退款异步回调
	static public function refundnotify(){
		global $channel, $order;

		$signstr = 'agent_id='.$_GET['agent_id'].'&hy_bill_no='.$_GET['hy_bill_no'].'&agent_bill_id='.$_GET['agent_bill_id'].'&agent_refund_bill_no='.$_GET['agent_refund_bill_no'].'&refund_amt='.$_GET['refund_amt'].'&refund_status='.$_GET['refund_status'].'&hy_deal_time='.$_GET['hy_deal_time'].'&key='.$channel['appsecret'];
		$sign = md5(strtolower($signstr));

		if($sign===$_GET["sign"]){
			return ['type'=>'html','data'=>'ok'];
		}else{
			return ['type'=>'html','data'=>'error'];
		}
	}

	static private function unicode_urldecode($url){
		preg_match_all('/%u([[:alnum:]]{4})/', $url, $a);
		foreach ($a[1] as $uniord) {
			$dec = hexdec($uniord);
			$utf = '';
			if ($dec < 12) {
				$utf = chr($dec);
			} else if ($dec < 204) {
				$utf = chr(192 + (($dec - ($dec % 64)) / 64));
				$utf .= chr(128 + ($dec % 64));
			} else {
				$utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
				$utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
				$utf .= chr(128 + ($dec % 64));
			}
			$url = str_replace('%u' . $uniord, $utf, $url);
		}
		return urldecode($url);
	}

	static private function queryBankCardInfo($channel, $bank_card_no){
		$apiurl = 'https://pay.heepay.com/API/PayTransit/QueryBankCardInfo.aspx';
		$param = [
			'version' => '3',
			'agent_id' => $channel['appid'],
			'bank_card_no' => $bank_card_no,
		];
		$signstr = 'agent_id='.$param['agent_id'].'&bank_card_no='.$param['bank_card_no'].'&key='.$channel['appmchid'].'&version='.$param['version'];
		$param['sign'] = md5(strtolower($signstr));

		$data = get_curl($apiurl, http_build_query($param));
		$data = mb_convert_encoding($data,'UTF-8','GBK');
		libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

		if(isset($result['ret_code']) && $result['ret_code']=='0000'){
			$result = ['code'=>0, 'bank_card_no'=>$result['bank_card_no'], 'bank_name'=>$result['bank_name'], 'bank_type'=>$result['bank_type'], 'bank_card_type'=>$result['bank_card_type']];
		}else{
			$result = ['code'=>-1, 'msg'=>$result["ret_msg"]?$result["ret_msg"]:'返回内容解析失败'];
		}
		return $result;
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $conf;
		if(empty($channel) || empty($bizParam))exit();

		if($bizParam['type'] == 'bank'){
			$bank_card_info = self::queryBankCardInfo($channel, $bizParam['payee_account']);
			if($bank_card_info['code']==-1) return ['code'=>-1, 'msg'=>'查询银行卡信息失败：'.$bank_card_info['msg']];

			$detail_data = $bizParam['out_biz_no'].'^'.$bank_card_info['bank_type'].'^0^'.$bizParam['payee_account'].'^'.$bizParam['payee_real_name'].'^'.sprintf('%.2f' , $bizParam['money']).'^'.$bizParam['transfer_desc'].'^浙江省^杭州市^'.$bank_card_info['bank_name'];
			$apiurl = 'https://pay.heepay.com/API/PayTransit/PayTransferWithSmallAll.aspx';
		}else{
			$detail_data = $bizParam['out_biz_no'].'^0^'.$bizParam['payee_account'].'^'.$bizParam['payee_real_name'].'^'.sprintf('%.2f' , $bizParam['money']).'^'.$bizParam['transfer_desc'];
			$apiurl = 'https://pay.heepay.com/API/PayTransit/PayTransferThridWithSmall.aspx';
			if($bizParam['type'] == 'alipay') $transit_type = '4';
			elseif($bizParam['type'] == 'wxpay') $transit_type = '5';
		}
		$param = [
			'version' => '3',
			'agent_id' => $channel['appid'],
			'batch_no' => $bizParam['out_biz_no'],
			'batch_amt' => sprintf('%.2f' , $bizParam['money']),
			'batch_num' => '1',
			'detail_data' => $detail_data,
			'notify_url' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
			'ext_param1' => '123',
			'transit_type' => $transit_type,
			'sign_type' => 'MD5'
		];
		$signstr = 'agent_id='.$param['agent_id'].'&batch_amt='.$param['batch_amt'].'&batch_no='.$param['batch_no'].'&batch_num='.$param['batch_num'].'&detail_data='.$param['detail_data'].'&ext_param1='.$param['ext_param1'].'&key='.$channel['appmchid'].'&notify_url='.$param['notify_url'].'&version='.$param['version'];
		$param['sign'] = md5(strtolower($signstr));
		$param['detail_data'] = mb_convert_encoding($param['detail_data'],'GBK','UTF-8');
		$param['detail_data'] = self::tripleDesEncrypt($param['detail_data'], $channel['appurl']);

		$data = get_curl($apiurl, http_build_query($param));
		$data = mb_convert_encoding($data,'UTF-8','GBK');
		libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

		if(isset($result['ret_code']) && $result['ret_code']=='0000'){
			return ['code'=>0, 'status'=>0, 'orderid'=>$bizParam['out_biz_no'], 'paydate'=>date('Y-m-d H:i:s')];
		}else{
			$result = ['code'=>-1, 'msg'=>$result["ret_msg"]?$result["ret_msg"]:'返回内容解析失败'];
		}
		return $result;
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$apiurl = 'https://pay.heepay.com/API/PayTransit/QueryTransfer.aspx';
		$param = [
			'version' => '3',
			'agent_id' => $channel['appid'],
			'batch_no' => $bizParam['out_biz_no'],
			'sign_type' => 'MD5'
		];
		$signstr = 'agent_id='.$param['agent_id'].'&batch_no='.$param['batch_no'].'&key='.$channel['appmchid'].'&version='.$param['version'];
		$param['sign'] = md5(strtolower($signstr));

		$data = get_curl($apiurl, http_build_query($param));
		$data = mb_convert_encoding($data,'UTF-8','GBK');
		libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

		if(isset($result['ret_code']) && $result['ret_code']=='0000'){
			$status = 0;
			if($result['detail_data']){
				$detail_data = self::tripleDesDecrypt($result['detail_data'], $channel['appurl']);
				$detail_data = mb_convert_encoding($detail_data,'UTF-8','GBK');
				$row = explode('|', $detail_data)[0];
				$arr = explode('^', $row);
				if($arr[0] == $bizParam['out_biz_no']){
					$status = $arr[4] == 'S' ? 1 : 2;
					if($arr[4] == 'S') $paydate = $arr[5];
					else $errmsg = $arr[5];
				}
			}
			$result = ['code'=>0, 'status'=>$status, 'amount'=>$result['batch_amt'], 'errmsg'=>$errmsg, 'paydate'=>$paydate];
		}else{
			$result = ['code'=>-1, 'msg'=>$result["ret_msg"]?$result["ret_msg"]:'返回内容解析失败'];
		}
		return $result;
	}

	//付款凭证
	static public function transfer_proof($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$apiurl = 'https://www.heepay.com/API/PayTransit/PayTransferGetProof.aspx';
		$param = [
			'version' => '3',
			'agent_id' => $channel['appid'],
			'batch_no' => $bizParam['out_biz_no'],
			'sign_type' => 'MD5'
		];
		$signstr = 'agent_id='.$param['agent_id'].'&batch_no='.$param['batch_no'].'&key='.$channel['appmchid'].'&version='.$param['version'];
		$param['sign'] = md5(strtolower($signstr));

		$data = get_curl($apiurl, http_build_query($param));
		$data = mb_convert_encoding($data,'UTF-8','GBK');
		libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

		if(isset($result['ret_code']) && $result['ret_code']=='0000'){
			if($result['file_path']){
				$file_path = self::tripleDesDecrypt($result['file_path'], $channel['appurl']);
				$result = ['code'=>0, 'url'=>$file_path];
			}else{
				$result = ['code'=>-1, 'msg'=>$result["ret_msg"]?$result["ret_msg"]:'未返回下载地址'];
			}
		}else{
			$result = ['code'=>-1, 'msg'=>$result["ret_msg"]?$result["ret_msg"]:'返回内容解析失败'];
		}
		return $result;
	}

	//余额查询
	static public function balance_query($channel, $bizParam){
		if(empty($channel))exit();

		$apiurl = 'https://www.heepay.com/API/Merchant/QueryBank.aspx';
		$param = [
			'version' => '1',
			'agent_id' => $channel['appid'],
		];
		$signstr = 'version='.$param['version'].'&agent_id='.$param['agent_id'].'&key='.$channel['appmchid'];
		$param['sign'] = md5($signstr);

		$data = get_curl($apiurl, http_build_query($param));
		$data = mb_convert_encoding($data,'UTF-8','GBK');
		$status = substr($data, 0, 1);
		$ret = str_replace('|', '&', substr($data, 2));
		parse_str($ret, $result);

		if($status == 'S'){
			$result = ['code'=>0, 'amount'=>$result['can_Used_Amt']];
		}else{
			$result = ['code'=>-1, 'msg'=>$ret];
		}
		return $result;
	}

	//转账异步回调
	static public function transfernotify(){
		global $channel;

		$signstr = 'ret_code='.$_POST['ret_code'].'&ret_msg='.$_POST['ret_msg'].'&agent_id='.$_POST['agent_id'].'&hy_bill_no='.$_POST['hy_bill_no'].'&status='.$_POST['status'].'&batch_no='.$_POST['batch_no'].'&batch_amt='.$_POST['batch_amt'].'&batch_num='.$_POST['batch_num'].'&detail_data='.$_POST['detail_data'].'&ext_param1='.$_POST['ext_param1'].'&key='.$channel['appmchid'];
		$signstr = mb_convert_encoding($signstr,'UTF-8','GBK');
		$sign = md5(strtolower($signstr));

		if($sign===$_POST["sign"]){
			if($_POST['status']==1){
				$detail_data = mb_convert_encoding($_POST['detail_data'],'UTF-8','GBK');
				$detail_data = explode('|', $detail_data);
				foreach($detail_data as $row){
					$arr = explode('^', $row);
					if(!$arr[0]) continue;
					$out_biz_no = $arr[0];
					$status = $arr[4] == 'S' ? 1 : 2;
					$errmsg = $arr[4] == 'F' ? $arr[5] : null;
					processTransfer($out_biz_no, $status, $errmsg);
				}
			}
			return ['type'=>'html','data'=>'ok'];
		}else{
			return ['type'=>'html','data'=>'error'];
		}
	}

	static private function tripleDesEncrypt($data, $key){
		$encrypted = openssl_encrypt($data, 'des-ede3', $key, OPENSSL_RAW_DATA);
		return strtoupper(bin2hex($encrypted));
	}
	static private function tripleDesDecrypt($data, $key){
		$data = hex2bin($data);
		return openssl_decrypt($data, 'des-ede3', $key, OPENSSL_RAW_DATA);
	}

}