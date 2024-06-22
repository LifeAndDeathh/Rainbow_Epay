<?php

class PayService
{
	private $sign_type = 'RSA';
	private $version = '11';
	private $cusid;
	private $appid;
	private $platform_public_key;
	private $merchant_private_key;

	public function __construct($cusid, $appid, $platform_public_key, $merchant_private_key)
	{
		$this->cusid = $cusid;
		$this->appid = $appid;
		$this->platform_public_key = $platform_public_key;
		$this->merchant_private_key = $merchant_private_key;
	}

	//发起API请求
	public function submit($requrl, $params){
		$public_params = [
			'cusid' => $this->cusid,
			'appid' => $this->appid,
			'version' => $this->version,
			'signtype' => $this->sign_type,
		];

		$params = array_merge($public_params, $params);
		$params['sign'] = $this->generateSign($params);

		$response = get_curl($requrl, http_build_query($params));
		$result = json_decode($response, true);
		if(isset($result['retcode']) && $result['retcode']=='SUCCESS'){
			return $result;
		}elseif(isset($result['retmsg'])){
			throw new Exception($result['retmsg']);
		}else{
			throw new Exception('返回数据解析失败');
		}
	}


	//获取待签名字符串
	private function getSignContent($param){
		ksort($param);
		$signstr = '';
	
		foreach($param as $k => $v){
			if($k != "sign" && $v!=''){
				$signstr .= $k.'='.$v.'&';
			}
		}
		$signstr = substr($signstr,0,-1);
		return $signstr;
	}

	//请求参数签名
	private function generateSign($param){
		return $this->rsaPrivateSign($this->getSignContent($param));
	}

	//验签方法
	public function verifySign($param){
		if(empty($param['sign'])) return false;
		return $this->rsaPubilcSign($this->getSignContent($param), $param['sign']);
	}

	//商户私钥签名
	private function rsaPrivateSign($data){
		$priKey = $this->merchant_private_key;
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
		$pkeyid = openssl_pkey_get_private($res);
		if(!$pkeyid){
			throw new Exception('签名失败，商户私钥不正确');
		}
		openssl_sign($data, $signature, $pkeyid);
		$signature = base64_encode($signature);
		return $signature;
	}

	//平台公钥验签
	private function rsaPubilcSign($data, $signature){
		$pubKey = $this->platform_public_key;
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($pubKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
		$pubkeyid = openssl_pkey_get_public($res);
		if(!$pubkeyid){
			throw new Exception('验签失败，平台公钥不正确');
		}
		$result = openssl_verify($data, base64_decode($signature), $pubkeyid);
		return $result;
	}

}