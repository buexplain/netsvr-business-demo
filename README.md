## 简介

这是一个基于 https://github.com/buexplain/netsvr 的演示程序，实现了`netsvr`的业务进程部分。\
启动步骤：
1. 去 https://github.com/buexplain/netsvr/releases 下载对应系统的压缩包，解压后里面有个以`netsvr-`开头的文件，把它跑起来。
2. 执行 `php bin/business.php`，把本程序跑起来，本程序依赖swoole扩展。
3. 找个在线测试的websocket网站，连接到`ws://127.0.0.1:6060//netsvr`
4. 连接成功后，发一条消息：`001你好`

上述步骤完成后，可以在php端看到连接打开信息，websocket客户端看到新用户进来的广播信息。\
注意，每个消息的前三个字符必须是业务进程的workerId，也就是那个`001`，这个是可以改的。\
请阅读 https://github.com/buexplain/netsvr 的readme里面关于客户数据转发的章节。