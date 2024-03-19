# rpc

This package allows to create proxy objects based on interfaces to call methods on an object living in a remote service.

Authentication isn't built-in, but can be implemented using middleware / interceptors.

## Installation

```
composer require amphp/rpc
```

## Usage

### Server

```php
<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Rpc\Server\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Amp\Socket;
use Monolog\Logger;
use function Amp\async;
use function Amp\ByteStream\getStdout;
use function Amp\Future\await;
use function Amp\Rpc\Examples\createRegistry;
use function Amp\trapSignal;

$logHandler = new StreamHandler(getStdout());
$logHandler->setFormatter(new ConsoleFormatter);
$logger = new Logger('server');
$logger->pushHandler($logHandler);

$registry = new RpcRegistry();
$registry->register(TimeService::class, new class($id) implements TimeService {
    public function getCurrentTime(): float
    {
        return now();
    }
});

$serializer = new NativeSerializer;

$server = SocketHttpServer::createForDirectAccess($logger);
$server->expose('0.0.0.0:1337');
$server->expose('[::]:1337');
$server->start(new RpcRequestHandler($serializer, $registry), new DefaultErrorHandler());

// Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
trapSignal(\SIGINT);

$server->stop();
```

### Client

```php
<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Rpc\Client\RpcClient;
use Amp\Rpc\Examples\Basic\TimeService;
use Amp\Rpc\Interceptor\RoundRobinBalancer;
use Amp\Serialization\NativeSerializer;
use Monolog\Logger;
use ProxyManager\Factory\RemoteObjectFactory;
use function Amp\ByteStream\getStdout;

$logHandler = new StreamHandler(getStdout());
$logHandler->setFormatter(new ConsoleFormatter);
$logger = new Logger('client');
$logger->pushHandler($logHandler);

$serializer = new NativeSerializer;

$proxyFactory = new RemoteObjectFactory(new RoundRobinBalancer([
    new RpcClient('http://localhost:1337/', $serializer),
]));

/** @var TimeService $timeService */
$timeService = $proxyFactory->createProxy(TimeService::class);

// This is a remote call via HTTP
var_dump($timeService->getCurrentTime());
```

## Security

If you discover any security related issues, please email [`contact@amphp.org`](mailto:contact@amphp.org) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [LICENSE](./LICENSE) for more information.
