<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Rpc;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\SocketHttpServer;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Rpc\Client\RpcClient;
use Amp\Rpc\Server\RpcRegistry;
use Amp\Rpc\Server\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Amp\Socket\ServerSocket;
use ProxyManager\Factory\RemoteObjectFactory;
use Psr\Log\NullLogger;
use function Amp\delay;

interface IntegrationTestService
{
    public function toUppercase(string $value): string;

    public function getObject(): object;

    public function throwException(): never;

    public function throwError(): never;

    public function getNonSerializableValue(): mixed;

    public function sendNonSerializableValue($value): mixed;
}

interface UnregisteredIntegrationTestService
{
    public function foobar(): mixed;
}

class IntegrationTest extends AsyncTestCase
{
    private HttpServer $server;
    private ServerSocket $socket;
    private IntegrationTestService $client;
    private UnregisteredIntegrationTestService $unregisteredClient;

    public function setUp(): void
    {
        parent::setUp();

        $registry = new RpcRegistry();
        $registry->register(IntegrationTestService::class, new class implements IntegrationTestService {
            public function toUppercase(string $value): string
            {
                return \strtoupper($value);
            }

            public function getObject(): object
            {
                delay(0.1);

                return new \DateTimeImmutable('now');
            }

            public function throwException(): never
            {
                throw new \Exception(__METHOD__);
            }

            public function throwError(): never
            {
                throw new \TypeError(__METHOD__);
            }

            public function getNonSerializableValue(): mixed
            {
                return (static function () {
                    yield;
                })();
            }

            public function sendNonSerializableValue($value): mixed
            {
                // nothing to do
                return null;
            }
        });

        $this->server = SocketHttpServer::createForDirectAccess(new NullLogger);
        $this->server->expose("127.0.0.1:0");
        $this->server->start(new RpcRequestHandler(new NativeSerializer, $registry), new DefaultErrorHandler());

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
            $this->assertSame('FOOBAR', $this->client->toUppercase('fooBar'));
        } finally {
            $this->stop();
        }
    }

    public function testGetObject(): void
    {
        try {
            $this->assertInstanceOf(\DateTimeImmutable::class, $this->client->getObject());
        } finally {
            $this->stop();
        }
    }

    public function testException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('::' . 'throwException');

        try {
            $this->client->throwException();
        } finally {
            $this->stop();
        }
    }

    public function testError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('::' . 'throwError');

        try {
            $this->client->throwError();
        } finally {
            $this->stop();
        }
    }

    public function testNonSerializableReturn(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to serialize RPC return value');

        try {
            $this->client->getNonSerializableValue();
        } finally {
            $this->stop();
        }
    }

    public function testNonSerializableParameter(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to serialize RPC parameters for Amp\Rpc\IntegrationTestService::sendNonSerializableValue()');

        try {
            $this->client->sendNonSerializableValue((static function () {
                yield;
            })());
        } finally {
            $this->stop();
        }
    }

    public function testUnregistered(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to call Amp\Rpc\UnregisteredIntegrationTestService::foobar(), because Amp\Rpc\UnregisteredIntegrationTestService is not registered');

        try {
            $this->unregisteredClient->foobar();
        } finally {
            $this->stop();
        }
    }

    private function stop(): void
    {
        $this->server->stop();
    }
}
