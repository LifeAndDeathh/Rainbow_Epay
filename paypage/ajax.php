<?php
include("./inc.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

$uid=intval($_POST['uid']);
$money=daddslashes($_POST['money']);
$payer=daddslashes($_POST['payer']);
$paytype=$_POST['paytype'];
$direct=intval($_POST['direct']);
if($_POST['token']!=$_SESSION['paypage_token'])showerrorjson('CSRF TOKEN ERROR');
if(!$uid || $uid!=$_SESSION['paypage_uid'])showerrorjson('收款方信息无效');
if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))showerrorjson('金额不合法');
if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])showerrorjson('最大支付金额是'.$conf['pay_maxmoney'].'元');
if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])showerrorjson('最小支付金额是'.$conf['pay_minmoney'].'元');

$blackip = $DB->find('blacklist', '*', ['type'=>1, 'content'=>$clientip], null, 1);
if($blackip)showerrorjson('系统异常无法完成付款');
if($payer){
	$black = $DB->find('blacklist', '*', ['type'=>0, 'content'=>$payer], null, 1);
	if($black)showerrorjson('系统异常无法完成付款');
}

if(!empty($paytype) && isset($_SESSION['paypage_typeid']) && isset($_SESSION['paypage_paymax']) && isset($_SESSION['paypage_paymin'])){
	if(!empty($_SESSION['paypage_paymin']) && $_SESSION['paypage_paymin']>0 && $money<$_SESSION['paypage_paymin']){
		showerrorjson('当前支付通道最大支付金额是'.$_SESSION['paypage_paymin'].'元');
	}
	if(!empty($_SESSION['paypage_paymax']) && $_SESSION['paypage_paymax']>0 && $money>$_SESSION['paypage_paymax']){
		showerrorjson('当前支付通道最小支付金额是'.$_SESSION['paypage_paymax'].'元');
	}
}

$userrow = $DB->getRow("SELECT `mode`,`ordername`,`channelinfo` FROM `pre_user` WHERE `uid`='{$uid}' LIMIT 1");

$trade_no=date("YmdHis").rand(11111,99999);
$return_url=$siteurl.'paypage/success.php?trade_no='.$trade_no;
$domain=getdomain($return_url);
if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`notify_url`,`return_url`,`domain`,`ip`,`buyer`,`status`) VALUES (:trade_no, :out_trade_no, :uid, 3, NOW(), :name, :money, :notify_url, :return_url, :domain, :clientip, :buyer, 0)", [':trade_no'=>$trade_no, ':out_trade_no'=>$trade_no, ':uid'=>$uid, ':name'=>'在线收款', ':money'=>$money, ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':buyer'=>$payer]))showerrorjson('创建订单失败，请返回重试！');

$_SESSION['paypage_trade_no'] = $trade_no;

$result=[];
$result['code']=0;
$result['msg']='succ';
$result['trade_no']=$trade_no;
$result['direct']=$direct;

if(!empty($paytype) && isset($_SESSION['paypage_typeid']) && isset($_SESSION['paypage_channel']) && isset($_SESSION['paypage_rate'])){
	$typeid = intval($_SESSION['paypage_typeid']);
	$channelid = intval($_SESSION['paypage_channel']);
	$subchannelid = intval($_SESSION['paypage_subchannel']);
	if($direct==1){
		if($userrow['mode']==1){
			$realmoney = round($money*(100+100-$_SESSION['paypage_rate'])/100,2);
			$getmoney = $money;
		}else{
			$realmoney = $money;
			$getmoney = round($money*$_SESSION['paypage_rate']/100,2);
		}

		if(!empty($conf['pay_payaddstart'])&&$conf['pay_payaddstart']!=0&&!empty($conf['pay_payaddmin'])&&$conf['pay_payaddmin']!=0&&!empty($conf['pay_payaddmax'])&&$conf['pay_payaddmax']!=0&&$realmoney>=$conf['pay_payaddstart'])$realmoney = round($realmoney + randomFloat(round($conf['pay_payaddmin'],2),round($conf['pay_payaddmax'],2)), 2);

		$DB->exec("UPDATE pre_order SET type='$typeid',channel='$channelid',realmoney='$realmoney',getmoney='$getmoney' WHERE trade_no='$trade_no'");

		$ordername = 'onlinepay'.time();
		if(!empty($userrow['ordername']))$conf['ordername']=$userrow['ordername'];
		$ordername = !empty($conf['ordername'])?ordername_replace($conf['ordername'],$ordername,$uid,$trade_no):$ordername;
		$channel = $subchannelid > 0 ? \lib\Channel::getSub($subchannelid) : \lib\Channel::get($channelid, $userrow['channelinfo']);
		if(!$channel)showerrorjson('支付通道不存在');

		$order['trade_no'] = $trade_no;
		$order['out_trade_no'] = $trade_no;
		$order['uid'] = $uid;
		$order['addtime'] = $date;
		$order['name'] = '在线收款';
		$order['realmoney'] = $realmoney;
		$order['type'] = $typeid;
		$order['channel'] = $channelid;
		$order['typename'] = $paytype;
		$order['profits'] = \lib\Payment::updateOrderProfits($order, $channel['plugin']);
		$order['profits2'] = \lib\Payment::updateOrderProfits2($order, $channel['plugin']);
		
		$paydata = \lib\Plugin::loadForJsapi($trade_no,$paytype,$realmoney,$ordername,$payer);

		$result['paydata'] = $paydata;
	}else{
		$result['url'] = '/submit2.php?typeid='.$typeid.'&trade_no='.$trade_no;
	}
}else{
	$result['url'] = '/cashier.php?trade_no='.$trade_no;
}

exit(json_encode($result));