<?php
// 微信手机扫码支付页面

if(!defined('IN_PLUGIN'))exit();
?>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no, width=device-width">
  <meta name="renderer" content="webkit"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <title>微信支付手机版</title>
  <link href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body>

<div class="col-xs-12 col-sm-10 col-md-8 col-lg-6 center-block" style="float: none;">
<div class="panel panel-primary">
	<div class="panel-heading" style="text-align: center;"><h3 class="panel-title">
		<img src="/assets/icon/wechat.ico">微信支付手机版
	</div>
		<div class="list-group" style="text-align: center;">
			<div class="list-group-item list-group-item-info">长按保存到相册使用微信扫码完成支付</div>
			<div class="list-group-item">
			<div class="qr-image" id="qrcode"></div>
			</div>
			<div class="list-group-item list-group-item-info">或复制以下链接到微信打开：</div>
			<div class="list-group-item" style="word-wrap: break-word;">
			<a href="<?php echo $code_url?>"><?php echo $code_url?></a><br/><button id="copy-btn" data-clipboard-text="<?php echo $code_url?>" class="btn btn-info btn-sm">一键复制</button>
			</div>
			<div class="list-group-item"><small>提示：你可将以上链接发到自己微信的聊天框（在微信顶部搜索框可以搜到自己的微信），点击即可进入支付！</small></div>
			<div class="list-group-item"><a href="weixin://" class="btn btn-primary">打开微信</a>&nbsp;<a href="#" onclick="checkresult()" class="btn btn-success">检测支付状态</a></div>
		</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="<?php echo $cdnpublic?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script src="<?php echo $cdnpublic?>clipboard.js/1.7.1/clipboard.min.js"></script>
<script>
	var clipboard = new Clipboard('#copy-btn');
	clipboard.on('success', function(e) {
		layer.msg('复制成功，请到微信里面粘贴');
	});
	clipboard.on('error', function(e) {
		layer.msg('复制失败，请长按链接后手动复制');
	});
	$('#qrcode').qrcode({
        text: "<?php echo $code_url?>",
        width: 230,
        height: 230,
        foreground: "#000000",
        background: "#ffffff",
        typeNumber: -1
    });
    function loadmsg() {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: "/getshop.php",
            data: {type: "wxpay", trade_no: "<?php echo $order['trade_no']?>"},
            success: function (data) {
                if (data.code == 1) {
					layer.msg('支付成功，正在跳转中...', {icon: 16,shade: 0.1,time: 15000});
					setTimeout(window.location.href=data.backurl, 1000);
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
					layer.msg('支付成功，正在跳转中...', {icon: 16,shade: 0.1,time: 15000});
					setTimeout(window.location.href=data.backurl, 1000);
                }else{
					layer.msg('您还未完成付款，请继续付款', {shade: 0,time: 1500});
				}
            },
            error: function () {
                layer.msg('服务器错误');
            }
        });
    }
    window.onload = loadmsg();
</script>
</body>
</html>