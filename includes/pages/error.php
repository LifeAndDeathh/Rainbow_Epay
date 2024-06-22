<?php
/*
 * 支付失败提示页面
*/
if(!defined('IN_PLUGIN'))exit();
?>
<html class="weui-msg">
<head>
    <meta charset="UTF-8">
    <meta id="viewport" name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>错误提示</title>
    <link href="<?php echo $cdnpublic?>weui/2.5.12/style/weui.min.css" rel="stylesheet">
    <style>.page{position:absolute;top:0;right:0;bottom:0;left:0;overflow-y:auto;-webkit-overflow-scrolling:touch;box-sizing:border-box}</style>
</head>
<body>
<div class="container">
<div class="page">
<div class="weui-msg">
    <div class="weui-msg__icon-area">
        <i class="weui-icon-warn weui-icon_msg"></i>
    </div>
    <div class="weui-msg__text-area">
        <h2 class="weui-msg__title">错误提示</h2>
        <p class="weui-msg__desc">支付失败或支付超时，请返回重新发起支付</p>
    </div>
    <div class="weui-msg__extra-area">
        <div class="weui-footer"><p class="weui-footer__links"></p></div>
    </div>
</div>
</div>
</div>
<script>
document.body.addEventListener('touchmove', function (event) {
	event.preventDefault();
},{ passive: false });
</script>
</body>
</html>