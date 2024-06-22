<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'orderList':
	$paytype = [];
	$paytypes = [];
	$rs = $DB->getAll("SELECT * FROM pre_type");
	foreach($rs as $row){
		$paytype[$row['id']] = $row['showname'];
		$paytypes[$row['id']] = $row['name'];
	}
	unset($rs);

	$sql=" 1=1";
	if(isset($_POST['uid']) && !empty($_POST['uid'])) {
		$uid = intval($_POST['uid']);
		$sql.=" AND A.`uid`='$uid'";
	}
	if(isset($_POST['type']) && !empty($_POST['type'])) {
		$type = intval($_POST['type']);
		$sql.=" AND A.`type`='$type'";
	}elseif(isset($_POST['channel']) && !empty($_POST['channel'])) {
		$channel = intval($_POST['channel']);
		$sql.=" AND A.`channel`='$channel'";
	}elseif(isset($_POST['subchannel']) && !empty($_POST['subchannel'])) {
		$subchannel = intval($_POST['subchannel']);
		$sql.=" AND A.`subchannel`='$subchannel'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND A.status={$dstatus}";
	}
	if(!empty($_POST['starttime']) || !empty($_POST['endtime'])){
		if(!empty($_POST['starttime'])){
			$starttime = daddslashes($_POST['starttime']);
			$sql.=" AND A.addtime>='{$starttime} 00:00:00'";
		}
		if(!empty($_POST['endtime'])){
			$endtime = daddslashes($_POST['endtime']);
			$sql.=" AND A.addtime<='{$endtime} 23:59:59'";
		}
	}
	if(isset($_POST['value']) && !empty($_POST['value'])) {
		if($_POST['column']=='name'){
			$sql.=" AND A.`{$_POST['column']}` like '%{$_POST['value']}%'";
		}else{
			$sql.=" AND A.`{$_POST['column']}`='{$_POST['value']}'";
		}
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_order A WHERE{$sql}");
	$list = $DB->getAll("SELECT A.*,B.plugin FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE{$sql} order by trade_no desc limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		$row['typename'] = $paytypes[$row['type']];
		$row['typeshowname'] = $paytype[$row['type']];
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;

case 'riskList':
	$sql=" 1=1";
	if(isset($_POST['value']) && !empty($_POST['value'])) {
		$sql.=" AND `{$_POST['column']}`='{$_POST['value']}'";
	}
	if(isset($_POST['type']) && $_POST['type']>-1) {
		$type = intval($_POST['type']);
		$sql.=" AND `type`={$type}";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_risk WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_risk WHERE{$sql} order by id desc limit $offset,$limit");

	exit(json_encode(['total'=>$total, 'rows'=>$list]));
break;

case 'setStatus': //改变订单状态
	$trade_no=trim($_GET['trade_no']);
	$status=is_numeric($_GET['status'])?intval($_GET['status']):exit('{"code":200}');
	if($status==5){
		if($DB->exec("DELETE FROM pre_order WHERE trade_no='$trade_no'"))
			exit('{"code":200}');
		else
			exit('{"code":400,"msg":"删除订单失败！['.$DB->error().']"}');
	}else{
		if($DB->exec("update pre_order set status='$status' where trade_no='$trade_no'")!==false)
			exit('{"code":200}');
		else
			exit('{"code":400,"msg":"修改订单失败！['.$DB->error().']"}');
	}
break;
case 'order': //订单详情
	$trade_no=trim($_GET['trade_no']);
	$row=$DB->getRow("select A.*,B.showname typename,C.name channelname from pre_order A,pre_type B,pre_channel C where trade_no='$trade_no' and A.type=B.id and A.channel=C.id limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在或未成功选择支付通道！"}');
	$result=array("code"=>0,"msg"=>"succ","data"=>$row);
	exit(json_encode($result));
break;
case 'operation': //批量操作订单
	$status=is_numeric($_POST['status'])?intval($_POST['status']):exit('{"code":-1,"msg":"请选择操作"}');
	$checkbox=$_POST['checkbox'];
	$i=0;
	foreach($checkbox as $trade_no){
		if($status==4)$DB->exec("DELETE FROM pre_order WHERE trade_no='$trade_no'");
		elseif($status==3){
			\lib\Order::unfreeze($trade_no);
		}
		elseif($status==2){
			\lib\Order::freeze($trade_no);
		}
		else $DB->exec("update pre_order set status='$status' where trade_no='$trade_no' limit 1");
		$i++;
	}
	exit('{"code":0,"msg":"成功改变'.$i.'条订单状态"}');
break;
case 'getmoney': //退款查询
	if(!$conf['admin_paypwd'])exit('{"code":-1,"msg":"你还未设置支付密码"}');
	$trade_no=trim($_POST['trade_no']);
	$api=isset($_POST['api'])?intval($_POST['api']):0;
	$result = \lib\Order::refund_info($trade_no, $api);
	exit(json_encode($result));
break;
case 'refund': //退款操作
	$trade_no=trim($_POST['trade_no']);
	$money = trim($_POST['money']);
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额输入错误"}');

	$result = \lib\Order::refund($trade_no, $money);
	if($result['code'] == 0){
		$result['msg'] = '已成功从UID:'.$result['uid'].'扣除'.$result['reducemoney'].'元余额';
	}
	exit(json_encode($result));
break;
case 'apirefund': //API退款操作
	$trade_no=trim($_POST['trade_no']);
	$paypwd=trim($_POST['paypwd']);
	$money = trim($_POST['money']);
	if(!is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额输入错误"}');
	if($paypwd!=$conf['admin_paypwd'])
		exit('{"code":-1,"msg":"支付密码输入错误！"}');
		
	$result = \lib\Order::refund($trade_no, $money, 1);
	if($result['code'] == 0){
		$result['msg'] = '退款成功！退款金额￥'.$result['money'];
		if($result['reducemoney']>0){
			$result['msg'] .= '，并成功从UID:'.$result['uid'].'扣除'.$result['reducemoney'].'元余额';
		}
	}
	exit(json_encode($result));
break;
case 'freeze': //冻结订单
	$trade_no=trim($_POST['trade_no']);
	$result = \lib\Order::freeze($trade_no);
	exit(json_encode($result));
break;
case 'unfreeze': //解冻订单
	$trade_no=trim($_POST['trade_no']);
	$result = \lib\Order::unfreeze($trade_no);
	exit(json_encode($result));
break;
case 'notify': //获取回调地址
	$trade_no=trim($_POST['trade_no']);
	$row=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$url=creat_callback($row);
	if($row['notify']>0)
		$DB->exec("update pre_order set notify=0,notifytime=NULL where trade_no='$trade_no'");
	exit('{"code":0,"url":"'.($_POST['isreturn']==1?$url['return']:$url['notify']).'"}');
break;
case 'fillorder': //手动补单
	$trade_no=trim($_POST['trade_no']);
	$row=$DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE trade_no=:trade_no limit 1", [':trade_no'=>$trade_no]);
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	if($row['status']>0)exit('{"code":-1,"msg":"当前订单不是未完成状态！"}');
	if($DB->exec("update `pre_order` set `status` ='1' where `trade_no`='$trade_no'")){
		$DB->exec("update `pre_order` set `endtime` ='$date',`date` =NOW() where `trade_no`='$trade_no'");
		$channel=\lib\Channel::get($row['channel']);
		processOrder($row);
	}
	exit('{"code":0,"msg":"补单成功"}');
break;
case 'alipaydSettle': //支付宝直付通确认结算
	$trade_no=trim($_POST['trade_no']);
	$row=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	if($row['status']==0)exit('{"code":-1,"msg":"当前订单状态是未支付"}');
	$channel = $row['subchannel'] > 0 ? \lib\Channel::getSub($row['subchannel']) : \lib\Channel::get($row['channel']);
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	try{
		if($channel['plugin'] == 'alipayd'){
			\lib\Payment::alipaydSettle($row['api_trade_no'], $row['realmoney']);
		}elseif($channel['plugin'] == 'wxpaynp'){
			\lib\Payment::wxpaynpSettle($trade_no, $row['api_trade_no']);
		}else{
			exit('{"code":-1,"msg":"支付插件不支持该操作"}');
		}
		$DB->exec("update `pre_order` set `settle`=2 where `trade_no`='$trade_no'");
		exit('{"code":0,"msg":"结算成功！"}');
	}catch(Exception $e){
		$errmsg = $e->getMessage();
		if(strpos($errmsg, 'ALREADY_CONFIRM_SETTLE')){
			$DB->exec("update `pre_order` set `settle`=2 where `trade_no`='$trade_no'");
			exit('{"code":0,"msg":"'.$errmsg.'"}');
		}
		$DB->exec("update `pre_order` set `settle`=3 where `trade_no`='$trade_no'");
		exit('{"code":-1,"msg":"结算失败,'.$errmsg.'"}');
	}
break;
case 'alipayPreAuthPay': //支付宝授权资金支付
	$trade_no=trim($_POST['trade_no']);
	$order=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$order)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel']);
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	try{
		$result = \lib\Payment::alipayPreAuthPay($trade_no);

		$api_trade_no = $result['trade_no'];
		$buyer_id = $result['buyer_user_id'];
		$total_amount = $result['total_amount'];
		processNotify($order, $api_trade_no, $buyer_id);

		exit('{"code":0,"msg":"授权资金支付成功！"}');
	}catch(Exception $e){
		$errmsg = $e->getMessage();
		exit('{"code":-1,"msg":"授权资金支付失败,'.$errmsg.'"}');
	}
break;
case 'alipayUnfreeze': //支付宝授权资金解冻
	$trade_no=trim($_POST['trade_no']);
	$order=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$order)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel']);
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	try{
		\lib\Payment::alipayUnfreeze($trade_no);
		$DB->exec("update `pre_order` set `status`=0 where `trade_no`='$trade_no'");
		exit('{"code":0,"msg":"授权资金解冻成功！"}');
	}catch(Exception $e){
		$errmsg = $e->getMessage();
		exit('{"code":-1,"msg":"授权资金解冻失败,'.$errmsg.'"}');
	}
break;
case 'alipayRedPacketTansfer': //支付宝红包转账重试
	$trade_no=trim($_POST['trade_no']);
	$order=$DB->getRow("select * from pre_order where trade_no='$trade_no' limit 1");
	if(!$order)
		exit('{"code":-1,"msg":"当前订单不存在！"}');
	$channel = $order['subchannel'] > 0 ? \lib\Channel::getSub($order['subchannel']) : \lib\Channel::get($order['channel']);
	if(!$channel){
		exit('{"code":-1,"msg":"当前支付通道信息不存在"}');
	}
	if(!empty($channel['appmchid'])) $payee_user_id = $channel['appmchid'];
	else $payee_user_id = $DB->findColumn('user', 'alipay_uid', ['uid'=>$order['uid']]);
	if(!$payee_user_id) exit('{"code":-1,"msg":"当前商户未绑定支付宝账号"}');
	try{
		\lib\Payment::alipayRedPacketTransfer($payee_user_id, $order['money'], $order['api_trade_no']);
		$DB->exec("update `pre_order` set `settle`=2 where `trade_no`='$trade_no'");
		exit('{"code":0,"msg":"红包打款成功！"}');
	}catch(Exception $e){
		$errmsg = $e->getMessage();
		exit('{"code":-1,"msg":"红包打款失败,'.$errmsg.'"}');
	}
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}