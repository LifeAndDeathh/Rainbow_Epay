## Install

1、首先获取源码包并上传；

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
