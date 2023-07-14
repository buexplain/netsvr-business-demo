# netsvr-business

这是一个基于hyperf框架开发的，可以快速开发websocket全双工通信业务的包，它基于[https://github.com/buexplain/netsvr](https://github.com/buexplain/netsvr)
进行工作。

ps：如果你的项目是非协程的，串行执行php代码的，则可以使用这个包：[https://github.com/buexplain/netsvr-business-serial](https://github.com/buexplain/netsvr-business-serial)

## 使用步骤

1. 下载并启动网关服务：[https://github.com/buexplain/netsvr/releases](https://github.com/buexplain/netsvr/releases)，该服务会启动：websocket服务器、worker服务器
2. 在hyperf项目里面安装本包以及protobuf包：
   > composer require buexplain/netsvr-business
   >
   > php bin/hyperf.php vendor:publish buexplain/netsvr-business
   >
   > composer require google/protobuf
3. 修改配置文件`config/autoload/business.php`，把里面的网关ip、port改成网关服务的worker服务器地址
4. 执行启动指令：`php bin/hyperf.php business:start`
5. 打开一个在线测试websocket的网页，连接到网关服务的websocket服务器，发送消息：`001你好`，注意这个`001`就是配置文件`config/autoload/business.php`
   里面的`workerId`

## 使用该包的演示项目

[https://github.com/buexplain/netsvr-business-demo](https://github.com/buexplain/netsvr-business-demo)