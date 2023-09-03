<?php

require __DIR__ . '/../vendor/autoload.php';

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

$cert = new Socket\Certificate(__DIR__ . '/server.pem');

$context = (new Socket\BindContext)
    ->withTlsContext((new Socket\ServerTlsContext)->withDefaultCertificate($cert));

// You can also expose the same registry instead of two, but we want to simulate two servers
$unencryptedRegistry = createRegistry($logger, 1);
$encryptedRegistry = createRegistry($logger, 2);

$serializer = new NativeSerializer;

$unencryptedServer = SocketHttpServer::createForDirectAccess($logger);
$unencryptedServer->expose('0.0.0.0:1337');
$unencryptedServer->expose('[::]:1337');

$encryptedServer = SocketHttpServer::createForDirectAccess($logger);
$encryptedServer->expose('0.0.0.0:1338', $context);
$encryptedServer->expose('[::]:1338', $context);

await([
    async($unencryptedServer->start(...), new RpcRequestHandler($serializer, $unencryptedRegistry), new DefaultErrorHandler()),
    async($encryptedServer->start(...), new RpcRequestHandler($serializer, $encryptedRegistry), new DefaultErrorHandler()),
]);

// Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
trapSignal(\SIGINT);

await([
    async($unencryptedServer->stop(...)),
    async($encryptedServer->stop(...)),
]);
