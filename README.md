# short-url 缩短链接

### 部署:

在serv00.net创建一个帐号，将`template.html` `index.php` `.htaccess` 三个文件
上传到`/usr/home/你的帐号/domains/你的帐号.serv00.net/public_html/*`里面 
打开浏览器打开`你的帐号.serv00.net/short` 即可
![](./预览图UI.png)

### 忘记密码:

在`/usr/home/你的帐号/domains/你的帐号.serv00.net/public_html/shortlinks/后缀名.json`里面的`"password":"abc"`  其中的abc就是密码，改成`"password":""`或者直接删除这个`后缀名.json`即可重置密码
