<?php

namespace Amp\Rpc\Server;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Promise;
use Amp\Rpc\RpcException;
use Amp\Serialization\Serializer;
use ProxyManager\Factory\RemoteObject\AdapterInterface as RpcAdapter;
use function Amp\call;
use function Amp\Rpc\cleanExceptionTrace;

final class RpcRequestHandler implements RequestHandler
{
    private $serializer;
    private $rpcAdapter;

    public function __construct(Serializer $serializer, RpcAdapter $rpcAdapter)
    {
        $this->serializer = $serializer;
        $this->rpcAdapter = $rpcAdapter;
    }

    public function handleRequest(Request $request): Promise
    {
        return call(function () use ($request) {
            if ($request->getMethod() !== 'POST') {
                return new Response(405);
            }

            $serializedPayload = yield $request->getBody()->buffer();
            $payload = $this->serializer->unserialize($serializedPayload);

            $class = $request->getHeader('rpc-class');
            $method = $request->getHeader('rpc-method');

            if (!\method_exists($class, $method)) {
                return $this->error(new RpcException($class . '::' . $method . ' not found'));
            }

            try {
                $promise = $this->rpcAdapter->call($class, $method, $payload);
                if (!$promise instanceof Promise) {
                    throw new \Error('Rpc calls must always return an instance of ' . Promise::class . ', got ' . \gettype($promise));
                }

                return $this->success(yield $promise);
            } catch (\Throwable $e) {
                return $this->error($e);
            }
        });
    }

    private function success($returnValue): Response
    {
        return new Response(200, ['content-type' => 'application/octet-stream', 'rpc-status' => 'ok'],
            $this->serializer->serialize($returnValue));
    }

    private function error(\Throwable $e): Response
    {
        $this->cleanExceptionTrace($e);

        return new Response(200, ['content-type' => 'application/octet-stream', 'rpc-status' => 'exception'],
            $this->serializer->serialize($e));
    }

    /**
     * Based on https://gist.github.com/Thinkscape/805ba8b91cdce6bcaf7c.
     */
    private function cleanExceptionTrace(\Throwable $exception): void
    {
        $baseClass = $exception instanceof \Exception ? \Exception::class : \Error::class;

        $traceProperty = (new \ReflectionClass($baseClass))->getProperty('trace');
        $traceProperty->setAccessible(true);

        $belowRequestHandler = false;

        $trace = $traceProperty->getValue($exception);
        foreach ($trace as $index => $call) {
            $trace[$index]['args'] = []; // Don't leak arguments across process boundaries

            if (isset($call['class']) && $call['class'] === __CLASS__) {
                $belowRequestHandler = true;
            }

            if ($belowRequestHandler) {
                unset($trace[$index]);
            }
        }

        $traceProperty->setValue($exception, $trace);
        $traceProperty->setAccessible(false);

        if ($previous = $exception->getPrevious()) {
            $this->cleanExceptionTrace($previous);
        }
    }
}