<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Rpc\Client\RpcClient;
use Amp\Rpc\Examples\Basic\Counter;
use Amp\Serialization\NativeSerializer;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Monolog\Logger;
use ProxyManager\Factory\RemoteObjectFactory;
use function Amp\async;
use function Amp\ByteStream\getStdout;
use function Amp\Future\awaitAll;

// Notice that the counter remains persistent at the server, so repeated invocations of this script will result in different counts

    $logHandler = new StreamHandler(getStdout());
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger('server');
    $logger->pushHandler($logHandler);

    $context = (new ConnectContext)
        ->withTlsContext((new ClientTlsContext(''))->withCaFile(__DIR__ . '/server.pem'));

    $httpConnectionPool = new UnlimitedConnectionPool(new DefaultConnectionFactory(null, $context));

    $proxyFactory = new RemoteObjectFactory(new RpcClient(
        'https://localhost:1338/',
        new NativeSerializer,
        (new HttpClientBuilder)->usingPool($httpConnectionPool)->build()
    ));
    $counter = $proxyFactory->createProxy(Counter::class);

    print $counter->get();
    print PHP_EOL;

    $counter->increase();
    $counter->increase();

    print $counter->get();
    print PHP_EOL;

    $counter->decrease();

    print $counter->get();
    print PHP_EOL;

    $promises = [];
    for ($i = 0; $i < 999; $i++) {
        $promises[] = async(function () use ($counter) {
            $counter->increase();
        });
    }

    awaitAll($promises);

    print $counter->get();
    print PHP_EOL;
