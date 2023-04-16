<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Rpc\Client\RpcClient;
use Amp\Rpc\Examples\Basic\TimeService;
use Amp\Rpc\Interceptor\RoundRobinBalancer;
use Amp\Serialization\NativeSerializer;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Monolog\Logger;
use ProxyManager\Factory\RemoteObjectFactory;
use function Amp\ByteStream\getStdout;

$logHandler = new StreamHandler(getStdout());
$logHandler->setFormatter(new ConsoleFormatter);
$logger = new Logger('server');
$logger->pushHandler($logHandler);

$serializer = new NativeSerializer;

$context = (new ConnectContext)
    ->withTlsContext((new ClientTlsContext(''))->withCaFile(__DIR__ . '/server.pem'));

$httpConnectionPool = new UnlimitedConnectionPool(new DefaultConnectionFactory(null, $context));

$proxyFactory = new RemoteObjectFactory(new RoundRobinBalancer([
    new RpcClient('http://localhost:1337/', $serializer),
    new RpcClient(
        'https://localhost:1338/',
        $serializer,
        (new HttpClientBuilder)->usingPool($httpConnectionPool)->build()
    ),
]));

/** @var TimeService $timeService */
$timeService = $proxyFactory->createProxy(TimeService::class);

var_dump($timeService->getCurrentTime()->await());

for ($i = 0; $i < 5; $i++) {
    var_dump($timeService->getId()->await());
}
