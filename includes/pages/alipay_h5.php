<?php
// 支付宝H5支付页面

if(!defined('IN_PLUGIN'))exit();
?>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no, width=device-width">
  <meta name="renderer" content="webkit"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <title>支付宝支付手机版</title>
  <link href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body>

<div class="col-xs-12 col-sm-10 col-md-8 col-lg-6 center-block" style="float: none;">
<div class="panel panel-default">
	<div class="panel-heading" style="text-align: center;"><h3 class="panel-title">
		<img src="/assets/icon/alipay.ico"> 支付宝支付手机版
	</div>
		<div class="list-group" style="text-align: center;">
            <div class="list-group-item list-group-item-info">~~~~~~~~~~~~~~~~</div>
			<div class="list-group-item"><h1>￥<?php echo $order['realmoney']?><h1></div>
			<div class="list-group-item">商品名称：<?php echo $order['name']?><br/>商户订单号：<?php echo $order['trade_no']?><br/>创建时间：<?php echo $order['addtime']?></div>
			<div class="list-group-item"><a href="" id="openUrl" class="btn btn-primary btn-block btn-lg">打开支付宝APP继续付款</a></div>
			<div class="list-group-item"><a href="#" onclick="checkresult()" class="btn btn-success btn-block btn-lg">检测支付状态</a></div>
		</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script>
	var url_scheme = '<?php echo $code_url?>';
    function loadmsg() {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: "/getshop.php",
            data: {type: "wxpay", trade_no: "<?php echo $order['trade_no']?>"},
            success: function (data) {
                if (data.code == 1) {
					layer.msg('支付成功，正在跳转中...', {icon: 16,shade: 0.01,time: 15000});
					setTimeout(window.location.href=<?php echo $redirect_url?>, 1000);
                }else{
                    setTimeout("loadmsg()", 2000);
                }
            },
            error: function () {
                setTimeout("loadmsg()", 2000);
            }
        });
    }
	function checkresult() {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: "/getshop.php",
            data: {type: "wxpay", trade_no: "<?php echo $order['trade_no']?>"},
            success: function (data) {
                if (data.code == 1) {
					layer.msg('支付成功，正在跳转中...', {icon: 16,shade: 0.01,time: 15000});
					setTimeout(window.location.href=<?php echo $redirect_url?>, 1000);
                }else{
					layer.msg('您还未完成付款，请继续付款', {shade: 0,time: 1500});
				}
            },
            error: function () {
                layer.msg('服务器错误');
            }
        });
    }
    window.onload = function(){
		document.getElementById("openUrl").href = url_scheme; 
        window.location.href = url_scheme;
		setTimeout("loadmsg()", 3000);
	}
</script>
</body>
</html>