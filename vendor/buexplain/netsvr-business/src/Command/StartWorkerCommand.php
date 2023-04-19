<?php
/**
 * Copyright 2023 buexplain@qq.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace NetsvrBusiness\Command;

use NetsvrBusiness\Contract\DispatcherFactoryInterface;
use NetsvrBusiness\Contract\WorkerSocketInterface;
use NetsvrBusiness\Contract\WorkerSocketManagerInterface;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Process\Pool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class StartWorkerCommand extends WorkerCommand
{
    protected bool $running = true;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->setName('business:start')
            ->setDefinition([
                new InputOption('workers', 'w', InputOption::VALUE_REQUIRED, 'Specify the number of process.', swoole_cpu_num()),
            ])
            ->setDescription('Start business service.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->isRun()) {
            $this->logger->notice('The business service is running.');
            return 0;
        }
        if (!class_exists('\Google\Protobuf\Internal\Message')) {
            $this->logger->error('Class "Google\Protobuf\Internal\Message" not found, you can run command: composer require google/protobuf');
            return 1;
        }
        //获取一下调度器，提前把路由加载进来，避免真正处理数据的时候发生路由注册的错误
        ApplicationContext::getContainer()->get(DispatcherFactoryInterface::class)->get();
        //开始连接网关
        $workers = intval($input->getOption('workers'));
        $pool = new Pool($workers > 0 ? $workers : swoole_cpu_num());
        $pool->set(['enable_coroutine' => true]);
        $pool->on('WorkerStart', function (Pool $pool, $workerProcessId) {
            //记录主进程pid
            if ($workerProcessId === 0) {
                file_put_contents($this->pidFile, (string)$pool->master_pid);
            }
            $manager = ApplicationContext::getContainer()->get(WorkerSocketManagerInterface::class);
            $manager->loggerPrefix = "Business#$workerProcessId ";
            //连接所有的网关机器
            $config = config('business', []);
            if (empty($config)) {
                $this->logger->error('The business service config business.php not found, may be not run command： php bin/hyperf.php vendor:publish buexplain/netsvr-business');
                $this->running = false;
            }
            $wg = new Coroutine\WaitGroup();
            foreach ($config as $item) {
                $wg->add();
                Coroutine::create(function () use ($wg, $item, $manager, $workerProcessId) {
                    try {
                        $socket = make(WorkerSocketInterface::class, $item);
                        $socket->loggerPrefix = "Business#$workerProcessId ";
                        $socket->connect();
                        $manager->add($socket);
                    } catch (Throwable $throwable) {
                        $message = sprintf("Business#%d Socket %s:%s %s", $workerProcessId, $item['host'], $item['port'], $throwable->getMessage());
                        $this->logger->error($message);
                        $this->running = false;
                    } finally {
                        $wg->done();
                    }
                });
            }
            $wg->wait();
            if ($this->running === false) {
                $pool->shutdown();
                return;
            }
            unset($wg, $config);
            //向网关发起注册
            if ($manager->register() === false) {
                $pool->shutdown();
                return;
            }
            //监听进程关闭信号
            Process::signal(SIGTERM, function () use ($workerProcessId, $manager) {
                $this->running = false;
                $this->logger->notice("Business#$workerProcessId starting unregister.");
                $manager->unregister();
                $this->logger->notice("Business#$workerProcessId starting disconnect.");
                $manager->close();
            });
            $this->logger->notice("Business#$workerProcessId started.");
            //不断的从网关读取数据，并分发到对应的控制器
            while (true) {
                $router = $manager->receive();
                if ($router === false) {
                    break;
                }
                //收到新数据，开一个协程去处理
                Coroutine::create(function () use ($router) {
                    try {
                        ApplicationContext::getContainer()->get(DispatcherFactoryInterface::class)->get()->dispatch($router);
                    } catch (Throwable $throwable) {
                        $message = sprintf(
                            "%d --> %s in %s on line %d\nThrowable: %s\nStack trace:\n%s",
                            $throwable->getCode(),
                            $throwable->getMessage(),
                            $throwable->getFile(),
                            $throwable->getLine(),
                            get_class($throwable),
                            $throwable->getTraceAsString()
                        );
                        $this->logger->error($message);
                    }
                });
            }
            $this->logger->notice("Business#$workerProcessId stopped.");
        });
        $pool->start();
        //删除记录的主进程pid
        @unlink($this->pidFile);
        return 0;
    }
}
