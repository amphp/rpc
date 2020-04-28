<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Http\Server\HttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Rpc\Server\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Amp\Socket;
use Monolog\Logger;
use function Amp\ByteStream\getStdout;
use function Amp\Rpc\Examples\createRegistry;

Amp\Loop::run(static function () {
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

    $unencryptedServer = new HttpServer([
        Socket\Server::listen("0.0.0.0:1337"),
        Socket\Server::listen("[::]:1337"),
    ], new RpcRequestHandler($serializer, $unencryptedRegistry), $logger);

    $encryptedServer = new HttpServer([
        Socket\Server::listen("0.0.0.0:1338", $context),
        Socket\Server::listen("[::]:1338", $context),
    ], new RpcRequestHandler($serializer, $encryptedRegistry), $logger);

    yield [
        $unencryptedServer->start(),
        $encryptedServer->start(),
    ];

    // Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
    Amp\Loop::onSignal(\SIGINT, static function (string $watcherId) use ($unencryptedServer, $encryptedServer) {
        Amp\Loop::cancel($watcherId);

        yield [
            $unencryptedServer->stop(),
            $encryptedServer->stop(),
        ];
    });
});
