<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Rpc;

use Amp\Future;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\SocketHttpServer;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Rpc\Client\RpcClient;
use Amp\Rpc\Server\RpcRegistry;
use Amp\Rpc\Server\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Amp\Socket\ResourceServerSocket;
use DateTimeImmutable;
use Generator;
use ProxyManager\Factory\RemoteObjectFactory;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Amp\async;
use function Amp\delay;
use function Amp\Socket\listen;

interface IntegrationTestService
{
    public function toUppercase(string $value): Future;

    public function getObject(): Future;

    public function throwException(): Future;

    public function throwError(): Future;

    public function getNonSerializableValue(): Future;

    public function sendNonSerializableValue($value): Future;
}

interface UnregisteredIntegrationTestService
{
    public function foobar(): Future;
}

class IntegrationTest extends AsyncTestCase
{
    /** @var HttpServer */
    private $server;
    /** @var IntegrationTestService */
    private $client;
    /** @var UnregisteredIntegrationTestService */
    private $unregisteredClient;

    public function setUp(): void
    {
        parent::setUp();

        $registry = new RpcRegistry();
        $registry->register(IntegrationTestService::class, new class implements IntegrationTestService {
            public function toUppercase(string $value): Future
            {
                return Future::complete(\strtoupper($value));
            }

            public function getObject(): Future
            {
                return async(function() {
                    delay(0.1);
                    return new DateTimeImmutable('now');
                });
            }

            public function throwException(): Future
            {
                return Future::error(new \Exception(__METHOD__));
            }

            public function throwError(): Future
            {
                return Future::error(new \TypeError(__METHOD__));
            }

            public function getNonSerializableValue(): Future
            {
                return Future::complete((static function () {
                        yield;
                })());
            }

            public function sendNonSerializableValue($value): Future
            {
                return Future::error(new \LogicException());
            }
        });
        $this->server = SocketHttpServer::createForDirectAccess(new NullLogger);
        $this->server->expose("http://127.0.0.1:0");
        $this->server->start(
            new RpcRequestHandler(new NativeSerializer, $registry),
            new DefaultErrorHandler()
        );
        $proxyFactory = new RemoteObjectFactory(new RpcClient(
            'http://' . $this->server->getServers()[0]->getAddress() . '/',
            new NativeSerializer
        ));

        $this->client = $proxyFactory->createProxy(IntegrationTestService::class);
        $this->unregisteredClient = $proxyFactory->createProxy(UnregisteredIntegrationTestService::class);
    }

    public function testToUppercase(): void
    {
        try {
            $this->assertSame('FOOBAR', $this->client->toUppercase('fooBar')->await());
        } finally {
            $this->stop();
        }
    }

    public function testGetObject(): void
    {
        try {
            $this->assertInstanceOf(DateTimeImmutable::class, $this->client->getObject()->await());
        } finally {
            $this->stop();
        }
    }

    public function testException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('::' . 'throwException');

        try {
            $this->client->throwException()->await();
        } finally {
            $this->stop();
        }
    }

    public function testError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('::' . 'throwError');

        try {
            $this->client->throwError()->await();
        } finally {
            $this->stop();
        }
    }

    public function testNonSerializableReturn(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to serialize RPC return value');

        try {
            $this->client->getNonSerializableValue()->await();
        } finally {
            $this->stop();
        }
    }

    public function testNonSerializableParameter(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to serialize RPC parameters for Amp\Rpc\IntegrationTestService::sendNonSerializableValue()');

        try {
            $this->client->sendNonSerializableValue((static function () { yield; })())->await();
        } finally {
            $this->stop();
        }
    }

    public function testUnregistered(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to call Amp\Rpc\UnregisteredIntegrationTestService::foobar(), because Amp\Rpc\UnregisteredIntegrationTestService is not registered');

        try {
            $this->unregisteredClient->foobar()->await();
        } finally {
            $this->stop();
        }
    }

    protected function tearDown(): void
    {
        $this->server = null;
        $this->client = null;
        $this->unregisteredClient = null;

        parent::tearDown();
    }

    private function stop(): void
    {
        $this->server->stop();
    }
}
