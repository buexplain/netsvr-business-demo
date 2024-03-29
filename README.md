## 简介

这是一个基于[https://github.com/buexplain/netsvr](https://github.com/buexplain/netsvr)
与[https://github.com/buexplain/netsvr-business](https://github.com/buexplain/netsvr-business)
的、快速开发websocket业务的演示程序。\
启动步骤：

1. 启动网关服务：去[https://github.com/buexplain/netsvr/releases](https://github.com/buexplain/netsvr/releases)
   下载对应系统的压缩包，解压后里面有个以`netsvr-`
   开头的文件，把它跑起来。
2. 执行`git clone`下载本项目。
3. 进本项目，执行`copy .env.example .env`生成配置文件。
4. 执行服务器启动命令`php bin/hyperf.php business:start`，把本程序跑起来，本程序依赖`swoole`扩展。
   > 用swoole-cli启动的命令格式示例：`swoole-cli /cygdrive/f/netsvr-business-demo/bin/hyperf.php business:start`
5. 执行测试命令`php bin/hyperf.php netBus:test`
   > 用swoole-cli启动的命令格式示例：`swoole-cli /cygdrive/f/netsvr-business-demo/bin/hyperf.php netBus:test`

上述步骤完成后，可以在本项目的启动命令行看到连接打开信息，websocket客户端看到新用户进来的广播信息。

## 你需要关注的文件

1. `config/autoload/business.php` 配置文件
2. `config/autoload/dependencies.php` 接口替换为具体实现的文件
3. `config/routes-websocket.php` 路由文件
4. `app/Controller/WebsocketController.php` 控制器文件
5. `app/Protocol` 客户端发送数据、服务端返回数据的编解码协议，这里面实现了`protobuf`与`json`两种编解码的示例