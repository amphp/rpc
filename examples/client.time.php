<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Rpc\Client\RpcClient;
use Amp\Rpc\Examples\Basic\TimeService;
use Amp\Serialization\NativeSerializer;
use Monolog\Logger;
use ProxyManager\Factory\RemoteObjectFactory;
use function Amp\ByteStream\getStdout;

Amp\Loop::run(static function () {
    $logHandler = new StreamHandler(getStdout());
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger('server');
    $logger->pushHandler($logHandler);

    $proxyFactory = new RemoteObjectFactory(new RpcClient('http://localhost:1337/', new NativeSerializer));
    $timeService = $proxyFactory->createProxy(TimeService::class);

    \var_dump(yield $timeService->getCurrentTime());
});
