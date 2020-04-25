<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Http\Server\HttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Promise;
use Amp\Rpc\Examples\Basic\Counter;
use Amp\Rpc\Examples\Basic\TimeService;
use Amp\Rpc\Server\RpcRegistry;
use Amp\Rpc\Server\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Amp\Socket;
use Amp\Success;
use Monolog\Logger;
use function Amp\ByteStream\getStdout;
use function Amp\getCurrentTime;

Amp\Loop::run(static function () {
    $cert = new Socket\Certificate('../../../http-server/test/server.pem');

    $context = (new Socket\BindContext)
        ->withTlsContext((new Socket\ServerTlsContext)->withDefaultCertificate($cert));

    $servers = [
        Socket\Server::listen("0.0.0.0:1337"),
        Socket\Server::listen("[::]:1337"),
        Socket\Server::listen("0.0.0.0:1338", $context),
        Socket\Server::listen("[::]:1338", $context),
    ];

    $logHandler = new StreamHandler(getStdout());
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger('server');
    $logger->pushHandler($logHandler);

    $registry = new RpcRegistry();
    $registry->register(TimeService::class, new class implements TimeService {
        public function getCurrentTime(): Promise
        {
            return new Success(getCurrentTime());
        }
    });

    $registry->register(Counter::class, new class($logger) implements Counter {
        private $counter = 0;
        private $logger;

        public function __construct(Logger $logger)
        {
            $this->logger = $logger;
        }


        public function increase(): Promise
        {
            $this->counter++;

            $this->logger->info('Increased counter by one');

            if ($this->counter >= 1999) {
                throw new \Exception('Failed to increase counter');
            }

            return new Success;
        }

        public function decrease(): Promise
        {
            $this->counter--;

            $this->logger->info('Decreased counter by one');

            return new Success;
        }

        public function get(): Promise
        {
            return new Success($this->counter);
        }
    });

    $server = new HttpServer($servers, new RpcRequestHandler(new NativeSerializer, $registry), $logger);

    yield $server->start();

    // Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
    Amp\Loop::onSignal(\SIGINT, static function (string $watcherId) use ($server) {
        Amp\Loop::cancel($watcherId);
        yield $server->stop();
    });
});