# 彩虹易支付
## 程序简介
彩虹易支付系统，专注于聚合支付网站解决方案提供，以信誉求市场，以稳定求发展，行业内最安全，简单易用，专业的技术团队，最放心的聚合支付系统。
特色功能：支付插件扩展、用户组管理、商户审核、实名认证、快捷登录、通道轮询、商户直清、服务商模式、实时结算、聚合收款码、风控机制。

## 安装
### 1.配置aaPanel
```
URL=https://www.aapanel.com/script/install_7.0_en.sh && if [ -f /usr/bin/curl ];then curl -ksSO "$URL" ;else wget --no-check-certificate -O install_7.0_en.sh "$URL";fi;bash install_7.0_en.sh aapanel
```
选择使用LNMP的环境安装方式勾选如下信息

- Nginx 1.21.4
- PHP 7.3
- MySQL 5.6.50

选择 Fast 快速编译后进行安装。

### 2.添加站点
aaPanel 面板 > Website > Add site。

> 在 Domain 填入你指向服务器的域名
>
> 在 Database 选择MySQL
>
> 在 PHP Verison 选择PHP

### 3.上传源码
路径如：/www/wwwroot/你的站点域名。

### 4.配置网站信息：设置站点伪静态
添加完成后编辑添加的站点 > URL rewrite 填入伪静态信息。
```
location / {
 if (!-e $request_filename) {
   rewrite ^/(.[a-zA-Z0-9\-\_]+).html$ /index.php?mod= last;
 }
 rewrite ^/pay/(.*)$ /pay.php?s= last;
}
location ^~ /plugins {
  deny all;
}
location ^~ /includes {
  deny all;
}
```
### 5.直接访问站点傻瓜安装

### 5.后台默认账号密码：admin，123456
