<?php
/**
 * 付款记录
**/
include("../includes/common.php");
$title='付款记录';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
  <div class="container" style="padding-top:70px;">
    <div class="col-md-12 center-block" style="float: none;">
<form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
  <div class="form-group">
    <label>搜索</label>
	<input type="text" class="form-control" name="value" placeholder="收款账号/姓名">
  </div>
  <div class="form-group">
    <input type="text" class="form-control" name="uid" style="width: 100px;" placeholder="商户号" value="">
  </div>
  <div class="form-group">
	<select name="type" class="form-control"><option value="">所有付款方式</option><option value="alipay">支付宝</option><option value="wxpay">微信</option><option value="qqpay">QQ钱包</option><option value="bank">银行卡</option></select>
  </div>
  <div class="form-group">
	<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">状态正在处理</option><option value="1">状态转账成功</option><option value="2">状态转账失败</option></select>
  </div>
  <button type="submit" class="btn btn-primary">搜索</button>
  <a href="./transfer_add.php" class="btn btn-success">新增付款</a>
  <a href="javascript:searchClear()" class="btn btn-default" title="刷新付款记录"><i class="fa fa-refresh"></i></a>
</form>

      <table id="listTable">
	  </table>
    </div>
  </div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/bootstrap-table.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 30;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax_transfer.php?act=transferList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'biz_no',
				title: '交易号<br/>第三方交易号',
				formatter: function(value, row, index) {
					return '<b>'+value+'</b><br/>'+row.pay_order_no;
				}
			},
			{
				field: 'uid',
				title: '商户号',
				formatter: function(value, row, index) {
					return value>0?'<a href="./ulist.php?column=uid&value='+value+'" target="_blank">'+value+'</a>':'管理员';
				}
			},
			{
				field: 'type',
				title: '付款方式<br/>(通道ID)',
				formatter: function(value, row, index) {
					let typename = '';
					if(value == 'alipay'){
						typename='<img src="/assets/icon/alipay.ico" width="16" onerror="this.style.display=\'none\'">支付宝';
					}else if(value == 'wxpay'){
						typename='<img src="/assets/icon/wxpay.ico" width="16" onerror="this.style.display=\'none\'">微信';
					}else if(value == 'qqpay'){
						typename='<img src="/assets/icon/qqpay.ico" width="16" onerror="this.style.display=\'none\'">QQ钱包';
					}else if(value == 'bank'){
						typename='<img src="/assets/icon/bank.ico" width="16" onerror="this.style.display=\'none\'">银行卡';
					}
					return typename+'('+row.channel+')';
				}
			},
			{
				field: 'account',
				title: '付款账号<br/>姓名',
				formatter: function(value, row, index) {
					return ''+value+'<br/>'+row.username+'';
				}
			},
			{
				field: 'money',
				title: '付款金额<br/>花费金额',
				formatter: function(value, row, index) {
					return '￥<b>'+value+'</b><br/>￥<b>'+row.costmoney+'</b>';
				}
			},
			{
				field: 'paytime',
				title: '付款时间<br/>备注',
				formatter: function(value, row, index) {
					return ''+value+'<br/>'+(row.desc?'<font color="#bf7fef">'+row.desc+'</font>':'')+'';
				}
			},
			{
				field: 'status',
				title: '状态',
				formatter: function(value, row, index) {
					if(value == '1'){
						return '<font color=green>转账成功</font>';
					}else if(value == '2'){
						return '<a href="javascript:showResult(\''+row.biz_no+'\')" title="点此查看失败原因"><font color=red>转账失败</font></a>';
					}else{
						return '<a href="javascript:queryStatus(\''+row.biz_no+'\')" title="点此查询转账状态"><font color=orange>正在处理</font></a>';
					}
				}
			},
			{
				field: 'status',
				title: '操作',
				formatter: function(value, row, index) {
					let html = '';
					if(row.status == '1'){
						html += '<a href="javascript:setStatus(\''+row.biz_no+'\', 2)" class="btn btn-warning btn-xs">改为失败</a> <a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a>';
					}else if(row.status == '2'){
						html += '<a href="javascript:setStatus(\''+row.biz_no+'\', 1)" class="btn btn-success btn-xs">改为成功</a> <a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a>';
					}else{
						html += '<a href="javascript:queryStatus(\''+row.biz_no+'\')" class="btn btn-info btn-xs">查询状态</a> ';
						if(row.uid > 0) html += '<a href="javascript:refund(\''+row.biz_no+'\')" class="btn btn-warning btn-xs">退回</a>';
						else html += '<a href="javascript:delItem(\''+row.biz_no+'\')" class="btn btn-danger btn-xs">删除</a>';
					}
					return html;
				}
			},
		],
	})
})
function showResult(biz_no) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_transfer.php?act=transfer_result&biz_no='+biz_no,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg, {icon:0, title:'失败原因', shadeClose:true});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function queryStatus(biz_no) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_transfer.php?act=transfer_query&biz_no='+biz_no,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				searchSubmit();
				layer.alert(data.msg, {title:'查询结果'});
			}else{
				layer.alert(data.msg, {icon:2, title:'查询失败'});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function setStatus(biz_no, status){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'post',
		url : 'ajax_transfer.php?act=setTransferStatus',
		data : {biz_no:biz_no, status:status},
		dataType : 'json',
		success : function(ret) {
			layer.close(ii);
			if (ret.code != 0) {
				alert(ret.msg);
			}
			searchSubmit();
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function delItem(biz_no) {
	var confirmobj = layer.confirm('你确实要删除此付款记录吗？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=delTransfer',
		data : {biz_no:biz_no},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.closeAll();
				searchSubmit();
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function refund(biz_no){
	var confirmobj = layer.confirm('确定将余额退给商户并改为失败状态吗？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax_transfer.php?act=refundTransfer',
		data : {biz_no:biz_no},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.alert(data.msg, {icon:1}, function(){ layer.closeAll();searchSubmit(); });
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
</script>