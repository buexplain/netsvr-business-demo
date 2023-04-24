<?php

use App\Protocol\Proto\Protobuf\Router;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Router\JsonRouter;

return [
//    RouterInterface::class => JsonRouter::class, //将路由接口替换为json版，这样客户端在打包业务数据的时候就必须用该路由指定的json格式打包
    RouterInterface::class => Router::class //将路由接口替换为proto版，这样客户端在打包业务数据的时候就必须用该路由指定的proto格式打包
];