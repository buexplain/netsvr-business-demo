# netsvr-business

这是一个可以快速开发websocket业务的包，它基于[https://github.com/buexplain/netsvr](https://github.com/buexplain/netsvr)
进行工作。

## 使用步骤

1. 下载并启动网关服务：https://github.com/buexplain/netsvr/releases
2. 在hyperf项目里面安装本包以及protobuf包：
   > composer require buexplain/netsvr-business
   >
   > php bin/hyperf.php vendor:publish buexplain/netsvr-business
   >
   > composer require google/protobuf

3. 新增路由文件`routes-websocket.php`
   ```php
   <?php
   
   declare(strict_types=1);
   
   use App\Controller\WebsocketController;
   use Hyperf\Context\ApplicationContext;
   use Netsvr\Cmd;
   use NetsvrBusiness\Contract\DispatcherFactoryInterface;
   
   $dispatcher = ApplicationContext::getContainer()->get(DispatcherFactoryInterface::class)->get();
   
   $dispatcher->addRoute(Cmd::ConnOpen, [WebsocketController::class, 'onOpen']);
   $dispatcher->addRoute(Cmd::Transfer, [WebsocketController::class, 'onMessage']);
   $dispatcher->addRoute(Cmd::ConnClose, [WebsocketController::class, 'onClose']);
   ```

4. 新增控制器文件
    ```php
   <?php
   
   declare(strict_types=1);
   
   namespace App\Controller;
   
   use NetsvrBusiness\Contract\WorkerSocketManagerInterface;
   use NetsvrBusiness\Contract\RouterInterface;
   use NetsvrBusiness\Contract\ClientDataInterface;
   use Netsvr\Broadcast;
   use Netsvr\Cmd;
   use Netsvr\ConnClose;
   use Netsvr\ConnOpen;
   use Netsvr\Router;
   use Netsvr\Transfer;
   
   class WebsocketController
   {
       /**
        * 处理用户连接打开信息
        * @param WorkerSocketManagerInterface $manager
        * @param ConnOpen $connOpen
        * @return void
        */
       public function onOpen(WorkerSocketManagerInterface $manager, ConnOpen $connOpen): void
       {
           //构造一个广播对象
           $broadcast = new Broadcast();
           $broadcast->setData("有新用户进来 --> " . $connOpen->getUniqId());
           //构造一个路由对象
           $router = new Router();
           //设置命令为广播
           $router->setCmd(Cmd::Broadcast);
           //将广播对象序列化到路由对象上
           $router->setData($broadcast->serializeToString());
           //将路由对象序列化后发给网关
           $data = $router->serializeToString();
           $manager->send($data);
           echo '连接打开：' . $connOpen->serializeToJsonString(), PHP_EOL;
       }
   
       /**
        * 处理用户发来的信息
        * @param WorkerSocketManagerInterface $manager 与网关服务的连接的管理器
        * @param Transfer $transfer 网关转发客户数据时，使用的对象
        * @param RouterInterface $clientRouter 客户发送业务数据时，使用路由
        * @return void
        */
       public function onMessage(WorkerSocketManagerInterface $manager, Transfer $transfer, RouterInterface $clientRouter): void
       {
           $clientRouter->setData($transfer->getUniqId() . '：' . $clientRouter->getData());
           $broadcast = new Broadcast();
           $broadcast->setData($clientRouter->encode());
           $router = new Router();
           $router->setCmd(Cmd::Broadcast);
           $router->setData($broadcast->serializeToString());
           $data = $router->serializeToString();
           $manager->send($data);
           echo '收到消息：' . $clientRouter->getCmd() . ' --> ' . $clientRouter->getData(), PHP_EOL;
       }
   
       /**
        * 处理用户连接关闭的信息
        * @param WorkerSocketManagerInterface $manager
        * @param ConnClose $connClose
        * @return void
        */
       public function onClose(WorkerSocketManagerInterface $manager, ConnClose $connClose): void
       {
           $broadcast = new Broadcast();
           $broadcast->setData("有用户退出 --> " . $connClose->getUniqId());
           $router = new Router();
           $router->setCmd(Cmd::Broadcast);
           $router->setData($broadcast->serializeToString());
           $data = $router->serializeToString();
           $manager->send($data);
           echo '连接关闭：' . $connClose->serializeToJsonString(), PHP_EOL;
       }
   }
   ```
5. 修改配置文件`business.php`，把里面的网关ip、port改正确
6. 执行启动命令：`php bin/hyperf.php business:start`
7. 打开一个在线测试websocket的网页，连接到网关服务，发送消息：`001你好`，注意这个`001`就是配置文件`business.php`
   里面的`workerId`

## 使用该包的演示项目

[https://github.com/buexplain/netsvr-business-demo](https://github.com/buexplain/netsvr-business-demo)