<?php
if(isset($_GET['pid'])){
	$queryArr=$_GET;
}elseif(isset($_POST['pid'])){
	$queryArr=$_POST;
}else{
	@header('Content-Type: text/html; charset=UTF-8');
	exit('你还未配置支付接口商户！');
}
$is_defend = true;
$nosession = true;
require './includes/common.php';

@header('Content-Type: text/html; charset=UTF-8');

$pid=intval($queryArr['pid']);
if(empty($pid))sysmsg('商户ID不能为空');
$userrow=$DB->getRow("SELECT `uid`,`gid`,`key`,`money`,`mode`,`pay`,`cert`,`status`,`channelinfo`,`qq`,`ordername` FROM `pre_user` WHERE `uid`='{$pid}' LIMIT 1");
if(!$userrow)sysmsg('商户不存在！');
if(isset($queryArr['__defend'])){
	$defend_result = $queryArr['__defend'];
	unset($queryArr['__defend']);
}
if(!\lib\Payment::verifySign($queryArr, $userrow['key']))sysmsg('签名校验失败，请返回重试！');

if($userrow['status']==0 || $userrow['pay']==0)sysmsg('商户已封禁，无法支付！');

if($userrow['pay']==2 && $conf['user_review']==1)sysmsg('商户没通过审核，请联系官方客服进行审核');

$type=daddslashes($queryArr['type']);
$out_trade_no=daddslashes($queryArr['out_trade_no']);
$notify_url=htmlspecialchars(daddslashes($queryArr['notify_url']));
$return_url=htmlspecialchars(daddslashes($queryArr['return_url']));
$name=htmlspecialchars(daddslashes($queryArr['name']));
$money=daddslashes($queryArr['money']);
$sitename=urlencode(base64_encode(htmlspecialchars($queryArr['sitename'])));
$param=isset($queryArr['param'])?htmlspecialchars(daddslashes($queryArr['param'])):null;


if(empty($out_trade_no))sysmsg('订单号(out_trade_no)不能为空');
if(empty($notify_url))sysmsg('通知地址(notify_url)不能为空');
if(empty($return_url))sysmsg('回调地址(return_url)不能为空');
if(empty($name))sysmsg('商品名称(name)不能为空');
if(empty($money))sysmsg('金额(money)不能为空');
if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))sysmsg('金额不合法');
if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])sysmsg('最大支付金额是'.$conf['pay_maxmoney'].'元');
if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])sysmsg('最小支付金额是'.$conf['pay_minmoney'].'元');
if(!preg_match('/^[a-zA-Z0-9.\_\-|]+$/',$out_trade_no))sysmsg('订单号(out_trade_no)格式不正确');

$domain=getdomain($notify_url);

if($conf['cert_force']==1 && $userrow['cert']==0){
	sysmsg('当前商户未完成实名认证，无法收款');
}
if($conf['forceqq']==1 && empty($userrow['qq'])){
	sysmsg('当前商户未填写联系QQ，无法收款');
}
if($conf['pay_domain_forbid']==1){
	if(!$DB->getRow("SELECT * FROM pre_domain WHERE uid=:uid AND (domain=:domain OR domain=:domain2) AND status=1 LIMIT 1", [':uid'=>$pid, ':domain'=>get_host($notify_url), ':domain2'=>'*.'.get_main_host($notify_url)])){
		sysmsg('该域名不可发起支付，原因：域名没过白，请前往支付平台授权支付域名');
	}
}

if(!empty($conf['blockname'])){
	$block_name = explode('|',$conf['blockname']);
	foreach($block_name as $rows){
		if(!empty($rows) && strpos($name,$rows)!==false){
			$DB->exec("INSERT INTO `pre_risk` (`uid`, `url`, `content`, `date`) VALUES (:uid, :domain, :rows, NOW())", [':uid'=>$pid,':domain'=>$domain,':rows'=>$rows]);
			sysmsg($conf['blockalert']?$conf['blockalert']:'该商品禁止出售');
		}
	}
}

$blackip = $DB->find('blacklist', '*', ['type'=>1, 'content'=>$clientip], null, 1);
if($blackip)sysmsg('系统异常无法完成付款');

if($conf['pay_iplimit'] > 0){
	$ipcount = $DB->getColumn("select count(*) from pre_order where `ip`='$clientip' and `date`='".date('Y-m-d')."' and status>0");
	if($ipcount >= $conf['pay_iplimit']){
		sysmsg('你今天已无法再发起支付，请明天再试');
	}
}

if(checkPayVerifyOpen($pid)){
	$defend_key = getDefendKey($pid, $out_trade_no);
	if(empty($defend_result) || $defend_key!==substr($defend_result,10,32)){
		showPayVerifyPage($defend_key, $queryArr);
	}
}

if(strlen($name)>127)$name=mb_strcut($name, 0, 127, 'utf-8');

$firstGetChannel = true;
$oldorder = $DB->getRow("SELECT * FROM `pre_order` WHERE `uid`=:uid AND `out_trade_no`=:out_trade_no", [':uid'=>$pid, ':out_trade_no'=>$out_trade_no]);
if($oldorder && time() - strtotime($oldorder['addtime']) < 864000){
	if($oldorder['status']>0){
		sysmsg('该订单('.$out_trade_no.')已完成支付，请勿重复发起支付');
	}
	if(round($oldorder['money'],2) != round($money,2) || $oldorder['name'] != $name || $oldorder['notify_url'] != $notify_url || $oldorder['return_url'] != $return_url || $oldorder['param'] != $param){
		sysmsg('该订单('.$out_trade_no.')支付参数有变化，请更换订单号重新发起支付');
	}
	$trade_no=$oldorder['trade_no'];
	$typeid = $DB->getColumn("SELECT id FROM pre_type WHERE name=:name LIMIT 1", [':name'=>$type]);
	if($oldorder['type'] > 0 && $oldorder['channel'] > 0 && $oldorder['realmoney'] > 0 && $oldorder['getmoney'] > 0 && $typeid == $oldorder['type']){ //订单已经获取过支付通道信息
		$firstGetChannel = false;
	}
}else{
	$trade_no=date("YmdHis").rand(11111,99999);
	if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`param`,`domain`,`ip`,`status`) VALUES (:trade_no, :out_trade_no, :uid, NOW(), :name, :money, :notify_url, :return_url, :param, :domain, :clientip, 0)", [':trade_no'=>$trade_no, ':out_trade_no'=>$out_trade_no, ':uid'=>$pid, ':name'=>$name, ':money'=>$money, ':notify_url'=>$notify_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':param'=>$param]))sysmsg('创建订单失败，请返回重试！');
}


if(empty($type)){
	echo "<script>window.location.replace('./cashier.php?trade_no={$trade_no}&sitename={$sitename}');</script>";
	exit;
}

// 获取订单支付方式ID、支付插件、支付通道、支付费率
if($firstGetChannel){
	$submitData = \lib\Channel::submit($type, $userrow['gid'], $money);
	if(!$submitData){
		echo "<script>window.location.replace('./cashier.php?trade_no={$trade_no}&sitename={$sitename}&other=1');</script>";
		exit;
	}
	if($userrow['mode']==1){ //订单加费模式
		$realmoney = round($money*(100+100-$submitData['rate'])/100,2);
		$getmoney = $money;
		if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
			$feemoney = round($money*(100-$submitData['rate'])/100,2);
			if($feemoney < round($conf['payfee_lessthan'], 2)){
				$realmoney = round($money + $conf['payfee_mincost'], 2);
			}
		}
	}else{
		$realmoney = $money;
		$getmoney = round($money*$submitData['rate']/100,2);
		if($conf['payfee_lessthan'] > 0 && $conf['payfee_mincost'] > 0){
			$feemoney = round($money*(100-$submitData['rate'])/100,2);
			if($feemoney < round($conf['payfee_lessthan'], 2)){
				$getmoney = round($money - $conf['payfee_mincost'], 2);
				if($getmoney < 0) $getmoney = 0;
			}
		}
	}
}else{
	$submitData = \lib\Channel::info($oldorder['channel']);
	$submitData['typename'] = $type;
	$submitData['subchannel'] = $oldorder['subchannel'];
	$realmoney = $oldorder['realmoney'];
	$getmoney = $oldorder['getmoney'];
}

// 判断通道单笔支付限额
if(!empty($submitData['paymin']) && $submitData['paymin']>0 && $money<$submitData['paymin']){
	sysmsg('<center>当前支付方式单笔最小限额为'.$submitData['paymin'].'元，请选择其他支付方式！</center>', '跳转提示');
}
if(!empty($submitData['paymax']) && $submitData['paymax']>0 && $money>$submitData['paymax']){
	sysmsg('<center>当前支付方式单笔最大限额为'.$submitData['paymax'].'元，请选择其他支付方式！</center>', '跳转提示');
}
// 商户直清模式判断商户余额
if($submitData['mode']==1 && $realmoney-$getmoney>$userrow['money']){
	sysmsg('当前商户余额不足，无法完成支付，请商户登录用户中心充值余额');
}

if($firstGetChannel){
	// 随机增减金额
	if(!empty($conf['pay_payaddstart'])&&$conf['pay_payaddstart']!=0&&!empty($conf['pay_payaddmin'])&&$conf['pay_payaddmin']!=0&&!empty($conf['pay_payaddmax'])&&$conf['pay_payaddmax']!=0&&$realmoney>=$conf['pay_payaddstart'])$realmoney = round($realmoney + randomFloat(round($conf['pay_payaddmin'],2),round($conf['pay_payaddmax'],2)), 2);

	$DB->update('order', ['type'=>$submitData['typeid'], 'channel'=>$submitData['channel'], 'subchannel'=>$submitData['subchannel'], 'realmoney'=>$realmoney, 'getmoney'=>$getmoney], ['trade_no'=>$trade_no]);
}


$order['trade_no'] = $trade_no;
$order['out_trade_no'] = $out_trade_no;
$order['uid'] = $pid;
$order['addtime'] = $date;
$order['name'] = $name;
$order['realmoney'] = $realmoney;
$order['type'] = $submitData['typeid'];
$order['channel'] = $submitData['channel'];
$order['subchannel'] = $submitData['subchannel'];
$order['typename'] = $submitData['typename'];
$order['profits'] = \lib\Payment::updateOrderProfits($order, $submitData['plugin']);
$order['profits2'] = \lib\Payment::updateOrderProfits2($order, $submitData['plugin']);

try{
	$result = \lib\Plugin::loadForSubmit($submitData['plugin'], $trade_no);
	$result['submit'] = true;
	\lib\Payment::echoDefault($result);
}catch(Exception $e){
	sysmsg($e->getMessage());
}
