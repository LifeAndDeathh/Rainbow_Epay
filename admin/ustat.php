<?php
/**
 * 商户支付统计
**/
include("../includes/common.php");
$title='商户支付统计';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

?>
<style>
#orderItem .orderTitle{word-break:keep-all;}
#orderItem .orderContent{word-break:break-all;}
.dates{max-width: 120px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
  <div class="container" style="padding-top:70px;">
    <div class="col-md-12 center-block" style="float: none;">
<form method="GET" class="form-inline" id="searchToolbar">
  <div class="form-group input-daterange">
	<label>查询日期:</label>
    <input type="text" id="day" name="day" class="form-control dates" placeholder="查询日期" autocomplete="off" value="<?php echo date("Y-m-d")?>" required>
  </div>
  <div class="form-group">
	<select name="method" class="form-control"><option value="type">以支付方式查看</option><option value="channel">以支付通道查看</option></select>
  </div>
  <button type="button" class="btn btn-primary" onclick="loadTable()">立即查询</button>
  &nbsp;<a href="javascript:exportTable()" class="btn btn-success" id="exportbtn" style="display:none">导出</a>
</form>

      <table id="listTable">
	  </table>
    </div>
  </div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/bootstrap-table.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>

function loadTable(){
	var day = $("input[name='day']").val();
	var method = $("select[name='method']").val();
	if(day == ''){
		layer.alert('查询日期不能为空');return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : "POST",
		url : "ajax_user.php?act=userPayStat",
		data : {day:day, method:method},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				showTable(data);
				$("#exportbtn").show();
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		} 
	});
}
function showTable(data){
	$('#listTable').bootstrapTable('destroy');
	var columns = [];
	$.each(data.columns, function(index, item){
		if(index == 'uid'){
			columns.push({
				field: index,
				title: item,
				sortable: true,
				formatter: function(value, row, index) {
					return '<a href="./ulist.php?my=search&column=uid&value='+value+'" target="_blank">'+value+'</a>';
				}
			});
		}else{
			columns.push({
				field: index,
				title: item,
				sortable: true,
				formatter: function(value, row, index) {
					if(value == null) return 0;
					else return value.toFixed(2)
				}
			});
		}
	})
	$("#listTable").bootstrapTable({
		data: data.data,
		pageSize: 20,
		sidePagination: 'client',
		classes: 'table table-striped table-hover table-bordered',
		columns: columns,
	})
	return false;
}

function exportTable(){
	var day = $("input[name='day']").val();
	var method = $("select[name='method']").val();
	if(day == ''){
		layer.alert('查询日期不能为空');return false;
	}
	window.location.href='./download.php?act=ustat&day='+day+'&method='+method;
}

$(document).ready(function(){
	$('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
		autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
})
</script>