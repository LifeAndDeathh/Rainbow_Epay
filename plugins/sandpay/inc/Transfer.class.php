<?php

class SandpayTransfer
{
    // 商户号
    private $merId;
    // 公钥文件
    private $publicKeyPath = PAY_ROOT.'cert/sand.cer';
    // 私钥文件
    private $privateKeyPath = PAY_ROOT.'cert/client.pfx';
    // 私钥证书密码
    private $privateKeyPwd;
    // 接口地址
    private $apiUrl = 'https://caspay.sandpay.com.cn/agent-main/openapi/';
    // 接入类型  0-商户接入，默认；1-平台接入
    private $accessType = '0';

    public function __construct($merId, $privateKeyPwd, $testMode = 0){
        $this->merId = $merId;
        $this->privateKeyPwd = $privateKeyPwd;
        if(file_exists(PAY_ROOT.'cert/'.$merId.'.pfx')){
            $this->privateKeyPath = PAY_ROOT.'cert/'.$merId.'.pfx';
        }
        if($testMode == 1){
            $this->apiUrl = 'https://dsfp-uat01.sand.com.cn/agent-main/openapi/';
        }
    }

    // 实时代付
    public function agentpay($out_trade_no, $payee_account, $payee_real_name, $money, $remark){

        $params = array(
            'orderCode' => $out_trade_no,
            'version' => '01',
            'productId' => '00000004',
            'tranTime' => substr($out_trade_no,0,14),
            'tranAmt' => str_pad(strval($money*100),12,'0',STR_PAD_LEFT),
            'currencyCode' => '156',
            'accAttr' => '0',
            'accType' => '4',
            'accNo' => $payee_account,
            'accName' => $payee_real_name,
            'remark' => $remark, // 这个字段不要出现“代付”的字样
            'payMode' => '1',
            'channelType' => '07'
        );

        return $this->request('RTPM', 'agentpay', $params);
    }

    // 订单查询
    public function queryOrder($out_trade_no){

        $params = array(
            'orderCode' => $out_trade_no,
            'version' => '01',
            'productId' => '00000004',
            'tranTime' => substr($out_trade_no,0,14)
        );

        return $this->request('ODQU', 'queryOrder', $params);
    }

    // 商户余额查询
    public function queryBalance($out_trade_no){

        $params = array(
            'orderCode' => $out_trade_no,
            'version' => '01',
            'productId' => '00000004',
            'tranTime' => date('YmdHis', time())
        );

        return $this->request('MBQU', 'queryBalance', $params);
    }

    // 代付手续费查询
    public function queryAgentpayFee($out_trade_no, $payee_account, $money){

        $params = array(
            'orderCode' => $out_trade_no,
            'version' => '01',
            'productId' => '00000004',
            'tranTime' => date('YmdHis', time()),
            'tranAmt' => str_pad(strval($money*100),12,'0',STR_PAD_LEFT),
            'currencyCode' => '156',
            'accAttr' => '0',
            'accType' => '4',
            'accNo' => $payee_account
        );

        return $this->request('PTFQ', 'queryAgentpayFee', $params);
    }

    // 凭证申请
    public function getVoucherContent($out_trade_no){

        $params = array(
            'version' => '01',
            'productId' => '00000004',
            'tranTime' => substr($out_trade_no,0,14),
            'orderCode' => $out_trade_no,
            'voucherType' => '1',
            'fileType' => '1',
        );

        return $this->request('VHCT', 'getVoucherContent', $params);
    }

    // 请求接口
    private function request($transCode, $url, $body)
    {
        // 获取公私钥匙
        $priKey = $this->privateKey();
        $pubKey = $this->publicKey();

        //生成AESKey并使用公钥加密
        $AESKey = $this->aes_generate(16);
        $encryptKey = $this->RSAEncryptByPub($AESKey, $pubKey);

        //使用AESKey加密报文
        $encryptData = $this->AESEncrypt($body, $AESKey);

        //使用私钥签名报文
        $sign = $this->sign($body, $priKey);

        //拼接post数据
        $post = array(
            'transCode' => $transCode,
            'accessType' => $this->accessType,
            'merId' => $this->merId,
            'encryptKey' => $encryptKey,
            'encryptData' => $encryptData,
            'sign' => $sign
        );

        $ret = $this->httpPost($this->apiUrl . $url, $post);

        parse_str($ret, $arr);

        //使用私钥解密AESKey
        $decryptAESKey = $this->RSADecryptByPri($arr['encryptKey'], $priKey);
    
        //使用解密后的AESKey解密报文
        $decryptPlainText = $this->AESDecrypt($arr['encryptData'], $decryptAESKey);
    
        //使用公钥验签报文
        $verify = $this->verify($decryptPlainText, $arr['sign'], $pubKey);
        if (!$verify) {
            throw new \Exception('返回数据验签失败');
        }

        return json_decode($decryptPlainText, true);
    }

    // 发送请求
    private function httpPost($url, $params)
    {
        if (empty($url) || empty($params)) {
            throw new \Exception('请求参数错误');
        }
        $params = http_build_query($params);
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $data  = curl_exec($ch);
            $err   = curl_error($ch);
            $errno = curl_errno($ch);
            if ($errno) {
                $msg = 'curl errInfo: ' . $err . ' curl errNo: ' . $errno;
                throw new \Exception($msg);
            }
            curl_close($ch);
            return $data;
        } catch (\Exception $e) {
            if ($ch) curl_close($ch);
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | step3.签名 + 验签
    |--------------------------------------------------------------------------
    */

    // 公钥
    private function publicKey()
    {
        try {
            $file = file_get_contents($this->publicKeyPath);
            if (!$file) {
                throw new \Exception('getPublicKey::file_get_contents ERROR');
            }
            $cert   = chunk_split(base64_encode($file), 64, "\n");
            $cert   = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
            $res    = openssl_pkey_get_public($cert);
            $detail = openssl_pkey_get_details($res);
            openssl_free_key($res);
            if (!$detail) {
                throw new \Exception('getPublicKey::openssl_pkey_get_details ERROR');
            }
            return $detail['key'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 私钥
    private function privateKey()
    {
        try {
            $file = file_get_contents($this->privateKeyPath);
            if (!$file) {
                throw new \Exception('getPrivateKey::file_get_contents');
            }
            if (!openssl_pkcs12_read($file, $cert, $this->privateKeyPwd)) {
                throw new \Exception('getPrivateKey::openssl_pkcs12_read ERROR');
            }
            return $cert['pkey'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 私钥加签
    private function sign($plainText, $path)
    {
        $plainText = json_encode($plainText);
        try {
            $resource = openssl_pkey_get_private($path);
            $result   = openssl_sign($plainText, $sign, $resource);
            openssl_free_key($resource);
            if (!$result) throw new \Exception('sign error');
            return base64_encode($sign);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 公钥验签
    public function verify($plainText, $sign, $path)
    {
        $resource = openssl_pkey_get_public($path);
        $result   = openssl_verify($plainText, base64_decode($sign), $resource);
        openssl_free_key($resource);

        return $result;
    }

    // 公钥加密AESKey
    private function RSAEncryptByPub($plainText, $puk)
    {
        if (!openssl_public_encrypt($plainText, $cipherText, $puk, OPENSSL_PKCS1_PADDING)) {
            throw new \Exception('AESKey 加密错误');
        }

        return base64_encode($cipherText);
    }

    // 私钥解密AESKey
    private function RSADecryptByPri($cipherText, $prk)
    {
        if (!openssl_private_decrypt(base64_decode($cipherText), $plainText, $prk, OPENSSL_PKCS1_PADDING)) {
            throw new \Exception('AESKey 解密错误');
        }

        return (string)$plainText;
    }

    // AES加密
    private function AESEncrypt($plainText, $key)
    {
        $plainText = json_encode($plainText);
        $result = openssl_encrypt($plainText, 'AES-128-ECB', $key, 1);

        if (!$result) {
            throw new \Exception('报文加密错误');
        }

        return base64_encode($result);
    }

    // AES解密
    private function AESDecrypt($cipherText, $key)
    {
        $result = openssl_decrypt(base64_decode($cipherText), 'AES-128-ECB', $key, 1);

        if (!$result) {
            throw new \Exception('报文解密错误');
        }

        return $result;
    }

    // 生成AESKey
    private function aes_generate($size)
    {
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $arr = array();
        for ($i = 0; $i < $size; $i++) {
            $arr[] = $str[mt_rand(0, 61)];
        }

        return implode('', $arr);
    }

    
}
