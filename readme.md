## Install

1.添加站点#
aaPanel 面板 > Website > Add site。

在 Domain 填入你指向服务器的域名
在 Database 选择MySQL
在 PHP Verison 选择PHP-74

首先获取源码包并上传；

2、配置网站信息：设置站点伪静态；
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
3、直接访问站点傻瓜安装

4、后台默认账号密码：admin，123456
