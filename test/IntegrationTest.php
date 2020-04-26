<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Rpc;

use Amp\Delayed;
use Amp\Failure;
use Amp\Http\Server\HttpServer;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Rpc\Client\RpcClient;
use Amp\Rpc\Server\RpcRegistry;
use Amp\Rpc\Server\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Amp\Socket\Server;
use Amp\Success;
use ProxyManager\Factory\RemoteObjectFactory;
use Psr\Log\NullLogger;
use function Amp\call;

interface IntegrationTestService
{
    public function toUppercase(string $value): Promise;

    public function getObject(): Promise;

    public function throwException(): Promise;

    public function throwError(): Promise;

    public function getNonSerializableValue(): Promise;

    public function sendNonSerializableValue($value): Promise;
}

interface UnregisteredIntegrationTestService
{
    public function foobar(): Promise;
}

class IntegrationTest extends AsyncTestCase
{
    /** @var HttpServer */
    private $server;
    /** @var Server */
    private $socket;
    /** @var IntegrationTestService */
    private $client;
    /** @var UnregisteredIntegrationTestService */
    private $unregisteredClient;

    public function setUp(): void
    {
        parent::setUp();

        Promise\wait(call(function () {
            $this->socket = Server::listen("127.0.0.1:0");

            $registry = new RpcRegistry();
            $registry->register(IntegrationTestService::class, new class implements IntegrationTestService {
                public function toUppercase(string $value): Promise
                {
                    return new Success(\strtoupper($value));
                }

                public function getObject(): Promise
                {
                    return new Delayed(100, new \DateTimeImmutable('now'));
                }

                public function throwException(): Promise
                {
                    return new Failure(new \Exception(__METHOD__));
                }

                public function throwError(): Promise
                {
                    throw new \TypeError(__METHOD__);
                }

                public function getNonSerializableValue(): Promise
                {
                    return new Success((static function () {
                        yield;
                    })());
                }

                public function sendNonSerializableValue($value): Promise
                {
                    // TODO: Implement sendNonSerializableValue() method.
                }
            });

            $this->server = new HttpServer(
                [$this->socket],
                new RpcRequestHandler(new NativeSerializer, $registry),
                new NullLogger
            );

            yield $this->server->start();
        }));

        $proxyFactory = new RemoteObjectFactory(new RpcClient(
            'http://' . (string) $this->socket->getAddress() . '/',
            new NativeSerializer
        ));

        $this->client = $proxyFactory->createProxy(IntegrationTestService::class);
        $this->unregisteredClient = $proxyFactory->createProxy(UnregisteredIntegrationTestService::class);
    }

    public function testToUppercase(): \Generator
    {
        try {
            $this->assertSame('FOOBAR', yield $this->client->toUppercase('fooBar'));
        } finally {
            yield $this->stop();
        }
    }

    public function testGetObject(): \Generator
    {
        try {
            $this->assertInstanceOf(\DateTimeImmutable::class, yield $this->client->getObject());
        } finally {
            yield $this->stop();
        }
    }

    public function testException(): \Generator
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('::' . 'throwException');

        try {
            yield $this->client->throwException();
        } finally {
            yield $this->stop();
        }
    }

    public function testError(): \Generator
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('::' . 'throwError');

        try {
            yield $this->client->throwError();
        } finally {
            yield $this->stop();
        }
    }

    public function testNonSerializableReturn(): \Generator
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to serialize RPC return value');

        try {
            yield $this->client->getNonSerializableValue();
        } finally {
            yield $this->stop();
        }
    }

    public function testNonSerializableParameter(): \Generator
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to serialize RPC parameters for Amp\Rpc\IntegrationTestService::sendNonSerializableValue()');

        try {
            yield $this->client->sendNonSerializableValue((static function () { yield; })());
        } finally {
            yield $this->stop();
        }
    }

    public function testUnregistered(): \Generator
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Failed to call Amp\Rpc\UnregisteredIntegrationTestService::foobar(), because Amp\Rpc\UnregisteredIntegrationTestService is not registered');

        try {
            yield $this->unregisteredClient->foobar();
        } finally {
            yield $this->stop();
        }
    }

    protected function tearDown(): void
    {
        $this->socket = null;
        $this->server = null;
        $this->client = null;
        $this->unregisteredClient = null;

        parent::tearDown();
    }

    private function stop(): Promise
    {
        return call(function () {
            yield $this->server->stop();
            $this->socket->close();
        });
    }
}
