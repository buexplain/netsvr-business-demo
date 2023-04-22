<?php

declare(strict_types=1);

use App\Controller\WebsocketController;
use App\Protocol\BroadcastProtocol;
use App\Protocol\SingleCastProtocol;
use Hyperf\Context\ApplicationContext;
use Netsvr\Cmd;
use NetsvrBusiness\Contract\DispatcherInterface;

$dispatcher = ApplicationContext::getContainer()->get(DispatcherInterface::class);

$dispatcher->addRoute(Cmd::ConnOpen, [WebsocketController::class, 'onOpen']);
$dispatcher->addRoute(Cmd::ConnClose, [WebsocketController::class, 'onClose']);
$dispatcher->addRoute(BroadcastProtocol::CMD, [WebsocketController::class, 'broadcast']);
$dispatcher->addRoute(SingleCastProtocol::CMD, [WebsocketController::class, 'singleCast']);
