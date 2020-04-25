<?php

require __DIR__ . '/../../vendor/autoload.php';

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
use function Amp\ByteStream\getStdout;

// Notice that the counter remains persistent at the server, so repeated invocations of this script will result in different counts

Amp\Loop::run(static function () {
    $logHandler = new StreamHandler(getStdout());
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger('server');
    $logger->pushHandler($logHandler);

    $proxyFactory = new RemoteObjectFactory(new RpcClient('https://localhost:1338/', new NativeSerializer,
        (new HttpClientBuilder)->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory(null,
            (new ConnectContext())->withTlsContext((new ClientTlsContext(''))->withoutPeerVerification()))))->build()));
    $counter = $proxyFactory->createProxy(Counter::class);

    print yield $counter->get();
    print \PHP_EOL;

    yield $counter->increase();
    yield $counter->increase();

    print yield $counter->get();
    print \PHP_EOL;

    yield $counter->decrease();

    print yield $counter->get();
    print \PHP_EOL;

    $promises = [];
    for ($i = 0; $i < 999; $i++) {
        $promises[] = $counter->increase();
    }

    yield $promises;

    print yield $counter->get();
    print \PHP_EOL;
});