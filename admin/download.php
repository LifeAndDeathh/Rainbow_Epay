<?php
include("../includes/common.php");

if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

function display_type($type){
	if($type==1)
		return '֧����';
	elseif($type==2)
		return '΢��';
	elseif($type==3)
		return 'QQǮ��';
	elseif($type==4)
		return '���п�';
	else
		return 1;
}

function display_status($status){
	if($status==1){
		return '��֧��';
	}elseif($status==2){
		return '���˿�';
	}elseif($status==3){
		return '�Ѷ���';
	}else{
		return 'δ֧��';
	}
}

function text_encoding($text){
	return mb_convert_encoding($text, "GB2312", "UTF-8");
}

switch($act){
case 'settle':
$type = isset($_GET['type'])?trim($_GET['type']):'common';
$batch=$_GET['batch'];
$remark = text_encoding($conf['transfer_desc']);

if($type == 'mybank'){
	$data="�տ����,�տ�˺�,�տ����������,�տ������к�,���,����/��;\r\n";
	
	$rs=$DB->query("SELECT * from pre_settle where batch='$batch' and (type=1 or type=4) order by id asc");
	$i=0;
	while($row = $rs->fetch())
	{
		$i++;
		$data.=text_encoding($row['username']).','.$row['account'].','.($row['type']=='1'?'֧����':'').',,'.$row['realmoney'].','.$remark."\r\n";
	}

}elseif($type == 'alipay'){
	$data="֧�������������ļ�ģ��\r\n";
	$data.="��ţ����,�տ֧�����˺ţ����,�տ���������,�������λ��Ԫ��,��ע��ѡ�\r\n";

	$rs=$DB->query("SELECT * from pre_settle where batch='$batch' and type=1 order by id asc");
	$i=0;
	while($row = $rs->fetch())
	{
		$i++;
		$data.=$i.','.$row['account'].','.text_encoding($row['username']).','.$row['realmoney'].','.$remark."\r\n";
	}

}elseif($type == 'wxpay'){
	if(!$conf['transfer_wxpay'])sysmsg(mb_convert_encoding("δ����΢����ҵ����", "UTF-8", "GB2312"));
	$channel = \lib\Channel::get($conf['transfer_wxpay']);
	if(!$channel)sysmsg(mb_convert_encoding("��ǰ֧��ͨ����Ϣ������", "UTF-8", "GB2312"));
	$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
	if(!$wxinfo)sysmsg(mb_convert_encoding("֧��ͨ���󶨵�΢�Ź��ںŲ�����", "UTF-8", "GB2312"));

	$rs=$DB->query("SELECT * from pre_settle where batch='$batch' and type=2 order by id asc");
	$i=0;
	$table="�̼���ϸ���ţ����,�տ��û�openid�����,�տ��û�������ѡ�,�տ��û����֤��ѡ�,ת�˽������λ��Ԫ��,ת�˱�ע�����\r\n";
	$allmoney = 0;
	while($row = $rs->fetch())
	{
		$i++;
		$table.=$batch.$i.','.$row['account'].','.text_encoding($row['username']).',,'.$row['realmoney'].','.$remark."\r\n";
		$allmoney+=$row['realmoney'];
	}

	$data="΢��֧������ת�˵���Ǯģ�棨��ɾ��\r\n";
	$data.="�̼����ε��ţ����,".$batch."\r\n";
	$data.="�������ƣ����,����ת��".$batch."\r\n";
	$data.="ת��appid�����,".$wxinfo['appid']."\r\n";
	$data.="ת���ܽ������λ��Ԫ��,".$allmoney."\r\n";
	$data.="ת���ܱ��������,".$i."\r\n";
	$data.="���α�ע�����,����ת��".$batch."\r\n";
	$data.=",\r\n";
	$data.="ת����ϸ����ɾ��\r\n";
	$data.=$table;

}else{
	$data="���,�տʽ,�տ��˺�,�տ�������,�����Ԫ��,��������\r\n";
	$rs=$DB->query("SELECT * from pre_settle where batch='$batch' order by type asc,id asc");
	$i=0;
	while($row = $rs->fetch())
	{
		$i++;
		$data.=$i.','.display_type($row['type']).','.$row['account'].','.text_encoding($row['username']).','.$row['realmoney'].','.$remark."\r\n";
	}

}

$file_name='pay_'.$type.'_'.$batch.'.csv';
$file_size=strlen($data);
header("Content-Description: File Transfer");
header("Content-Type: application/force-download");
header("Content-Length: {$file_size}");
header("Content-Disposition:attachment; filename={$file_name}");
echo $data;
break;

case 'ustat':
$day = trim($_GET['day']);
$method = trim($_GET['method']);
if(!$day)exit("<script language='javascript'>alert('param error');history.go(-1);</script>");
$starttime = date("Y-m-d H:i:s", strtotime($day));
$endtime = date("Y-m-d H:i:s", strtotime($day) + 3600 * 24);
$data = [];
$columns = ['uid'=>'�̻�ID', 'total'=>'�ܼ�'];

if($method == 'type'){
	$paytype = [];
	$rs = $DB->getAll("SELECT id,name,showname FROM pre_type WHERE status=1");
	foreach($rs as $row){
		$paytype[$row['id']] = text_encoding($row['showname']);
		$columns['type_'.$row['id']] = text_encoding($row['showname']);
	}
	unset($rs);
}else{
	$channel = [];
	$rs = $DB->getAll("SELECT id,name FROM pre_channel WHERE status=1");
	foreach($rs as $row){
		$channel[$row['id']] = text_encoding($row['name']);
	}
	unset($rs);
}

$rs=$DB->query("SELECT uid,type,channel,money from pre_order where status=1 and date='$day'");
while($row = $rs->fetch())
{
	$money = (float)$row['money'];
	if(!array_key_exists($row['uid'], $data)) $data[$row['uid']] = ['uid'=>$row['uid'], 'total'=>0];
	$data[$row['uid']]['total'] += $money;
	if($method == 'type'){
		$ukey = 'type_'.$row['type'];
		if(!array_key_exists($ukey, $data[$row['uid']])) $data[$row['uid']][$ukey] = $money;
		else $data[$row['uid']][$ukey] += $money;
	}else{
		$ukey = 'channel_'.$row['channel'];
		if(!array_key_exists($ukey, $data[$row['uid']])) $data[$row['uid']][$ukey] = $money;
		else $data[$row['uid']][$ukey] += $money;
		if(!in_array($ukey, $columns)) $columns[$ukey] = $channel[$row['channel']];
	}
}
ksort($data);


$file='';
foreach($columns as $column){
	$file.=$column.',';
}
$file=substr($file,0,-1)."\r\n";
foreach($data as $row){
	foreach($columns as $key=>$column){
		if(!array_key_exists($key, $row))
			$file.='0,';
		else
			$file.=$row[$key].',';
	}
	$file=substr($file,0,-1)."\r\n";
}

$file_name='pay_'.$method.'_'.$day.'.csv';
$file_size=strlen($file);
header("Content-Description: File Transfer");
header("Content-Type: application/force-download");
header("Content-Length: {$file_size}");
header("Content-Disposition:attachment; filename={$file_name}");
echo $file;
break;

case 'order':
$starttime = trim($_GET['starttime']);
$endtime = trim($_GET['endtime']);
$uid = intval($_GET['uid']);
$type = intval($_GET['type']);
$channel = intval($_GET['channel']);
$dstatus = intval($_GET['dstatus']);

$paytype = [];
$rs = $DB->getAll("SELECT * FROM pre_type");
foreach($rs as $row){
	$paytype[$row['id']] = text_encoding($row['showname']);
}
unset($rs);

$sql=" 1=1";
if(!empty($uid)) {
	$sql.=" AND A.`uid`='$uid'";
}
if(!empty($type)) {
	$sql.=" AND A.`type`='$type'";
}elseif(!empty($channel)) {
	$sql.=" AND A.`channel`='$channel'";
}
if($dstatus>-1) {
	$sql.=" AND A.status={$dstatus}";
}
if(!empty($starttime)){
	$starttime = date("Y-m-d H:i:s", strtotime($starttime.' 00:00:00'));
	$sql.=" AND A.addtime>='{$starttime}'";
}
if(!empty($endtime)){
	$endtime = date("Y-m-d H:i:s", strtotime("+1 days", strtotime($endtime.' 00:00:00')));
	$sql.=" AND A.addtime<'{$endtime}'";
}

$file="ϵͳ������,�̻�������,�ӿڶ�����,�̻���,��վ����,��Ʒ����,�������,ʵ��֧��,�̻��ֳ�,֧����ʽ,֧��ͨ��ID,֧�����,֧���˺�,֧��IP,����ʱ��,���ʱ��,֧��״̬\r\n";

$rs = $DB->query("SELECT A.*,B.plugin FROM pre_order A LEFT JOIN pre_channel B ON A.channel=B.id WHERE{$sql} order by trade_no desc limit 100000");
while($row = $rs->fetch()){
	$file.='="'.$row['trade_no'].'",="'.$row['out_trade_no'].'",="'.$row['api_trade_no'].'",'.$row['uid'].','.$row['domain'].','.text_encoding($row['name']).','.$row['money'].','.$row['realmoney'].','.$row['getmoney'].','.$paytype[$row['type']].','.$row['channel'].','.$row['plugin'].','.$row['buyer'].','.$row['ip'].','.$row['addtime'].','.$row['endtime'].','.display_status($row['status'])."\r\n";
}

$file_name='order_'.$starttime.'_'.$endtime.'.csv';
$file_size=strlen($file);
header("Content-Description: File Transfer");
header("Content-Type: application/force-download");
header("Content-Length: {$file_size}");
header("Content-Disposition:attachment; filename={$file_name}");
echo $file;
break;

case 'wximg':
	if(!checkRefererHost())exit();
	$channelid = intval($_GET['channel']);
	$media_id = $_GET['mediaid'];
	$channel=\lib\Channel::get($channelid);
	$model = \lib\Complain\CommUtil::getModel($channel);
	$image = $model->getImage($media_id);
	if($image !== false){
		$seconds_to_cache = 3600*24*7;
		header("Cache-Control: max-age=$seconds_to_cache");
		header("Content-Type: image/jpeg");
		echo $image;
	}
break;

default:
	exit('No Act');
break;
}