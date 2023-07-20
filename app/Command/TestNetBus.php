<?php

declare(strict_types=1);

namespace App\Command;

use App\Protocol\Cmd;
use App\Protocol\Proto\Protobuf\BroadcastProtocol;
use App\Protocol\Proto\Protobuf\SingleCastProtocol;
use ErrorException;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\NetBus;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Throwable;
use Swoole\Coroutine\Http\Client;

/**
 * swoole-cli /cygdrive/f/netsvr-business-demo/bin/hyperf.php netBus:test
 */
#[Command]
class TestNetBus extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('netBus:test');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('test netbus class');
    }

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function handle(): int
    {
        //测试连接到网关的websocket服务器
        $config = \Hyperf\Config\config('business.netsvrWorkers')[0];
        $client = new Client($config['host'], $config['port'] - 1);
        //如果网关支持连接的时候自定义uniqId，则务必保持uniqId的前两个字符是网关唯一id的16进制格式的字符
        //如果不保持这个规则，则你必须重新实现类 \NetsvrBusiness\Contract\ServerIdConvertInterface::class，确保uniqId转serverId正确
        $hex = ($config['serverId'] < 16 ? '0' . dechex($config['serverId']) : dechex($config['serverId']));
        $uniqId = $hex . uniqid();
        //获取自定义uniqId时，必须的token
        $token = NetBus::connOpenCustomUniqIdToken($config['serverId'])['token'];
        if ($client->upgrade('/netsvr?uniqId=' . $uniqId . '&token=' . $token) === false) {
            $this->error('连接到网关的websocket服务器失败 host: ' . $config['host'] . ' port: ' . ($config['port'] - 1));
            return 1;
        }
        //测试直接向网关的worker服务器发送数据
        $this->testBroadcast();
        $this->testSingleCast();
        //开始接收网关发回来的数据
        $message = '测试连接到网关的websocket服务器，以客户身份发送广播数据';
        Coroutine::create(function () use ($client, $message) {
            while (true) {
                $ret = $client->recv();
                if ($ret === false) {
                    return;
                }
                /**
                 * @var $router RouterInterface
                 */
                $router = \Hyperf\Support\make(RouterInterface::class);
                $router->decode($ret->data);
                switch ($router->getCmd()) {
                    case Cmd::PUBLIC_WELCOME:
                    case Cmd::PRIVATE_WELCOME:
                        echo 'recv ok：' . $router->getData() . PHP_EOL;
                        break;
                    case Cmd::BROADCAST:
                        $data = new BroadcastProtocol();
                        $data->decode($router->getData());
                        echo 'recv ok：' . $data->getFromUser() . ' --> ' . $data->getMessage() . PHP_EOL;
                        if ($data->getMessage() === $message) {
                            //接收到测试数据
                            return;
                        }
                        break;
                }
            }
        });
        //测试以客户身份发送数据
        $data = new BroadcastProtocol();
        $data->setMessage($message);
        /**
         * @var $router RouterInterface
         */
        $router = \Hyperf\Support\make(RouterInterface::class);
        $router->setCmd(Cmd::BROADCAST);
        $router->setData($data->encode());
        //给数据拼接上workerId前缀，指定具体的workerId的business服务来处理这个消息
        $str = str_pad((string)$config['workerId'], 3, '0', STR_PAD_LEFT) . $router->encode();
        if ($client->push($str) === true) {
            echo 'send ok：' . $data->getMessage() . PHP_EOL;
        }
        return 0;
    }

    /**
     * 获取网关中所有在线的连接id
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     * @throws ErrorException
     */
    protected function getAllOnlineUniqId(): array
    {
        $ret = NetBus::uniqIdList();
        $uniqIds = [];
        foreach ($ret as $v) {
            array_push($uniqIds, ...$v['uniqIds']);
        }
        return $uniqIds;
    }

    /**
     * 测试单播
     * @return void
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    protected function testSingleCast(): void
    {
        $data = new SingleCastProtocol();
        $data->setMessage('测试直接向网关的worker服务器发送单播数据');
        /**
         * @var $router RouterInterface
         */
        $router = \Hyperf\Support\make(RouterInterface::class);
        $router->setCmd(Cmd::BROADCAST);
        foreach (self::getAllOnlineUniqId() as $uniqId) {
            $data->setFromUser($uniqId);
            $router->setData($data->encode());
            NetBus::singleCast($uniqId, $router->encode());
        }
    }

    /**
     * 测试广播
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    protected function testBroadcast(): void
    {
        $data = new BroadcastProtocol();
        $data->setMessage("测试直接向网关的worker服务器发送广播数据");
        $data->setFromUser('系统管理员');
        /**
         * @var $router RouterInterface
         */
        $router = \Hyperf\Support\make(RouterInterface::class);
        $router->setCmd(Cmd::BROADCAST);
        $router->setData($data->encode());
        NetBus::broadcast($router->encode());
    }
}
