<?php

namespace Amp\Rpc\Client;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Rpc\RpcException;
use Amp\Serialization\Serializer;
use ProxyManager\Factory\RemoteObject\AdapterInterface as RpcAdapter;
use function Amp\call;

final class RpcClient implements RpcAdapter
{
    private $uri;
    private $serializer;
    private $httpClient;

    public function __construct(string $uri, Serializer $serializer, ?HttpClient $httpClient = null)
    {
        $this->uri = $uri;
        $this->serializer = $serializer;
        $this->httpClient = $httpClient ?? HttpClientBuilder::buildDefault();
    }

    public function call(string $class, string $method, array $params = [])
    {
        return call(function () use ($class, $method, $params) {
            $request = new Request($this->uri, 'POST');
            $request->setHeader('rpc-class', $class);
            $request->setHeader('rpc-method', $method);

            try {
                $request->setBody($this->serializer->serialize($params));
            } catch (\Throwable $e) {
                $errorMessage = 'Failed to serialize RPC parameters for ' . $class . '::' . $method . '()';

                throw new RpcException($errorMessage, 0, $e);
            }

            try {
                /** @var Response $response */
                $response = yield $this->httpClient->request($request);
                $serializedResult = yield $response->getBody()->buffer();
            } catch (\Throwable $e) {
                $errorMessage = 'Failed RPC call due to a fail in HTTP communication for ' . $class . '::' . $method . '()';

                throw new RpcException($errorMessage, 0, $e);
            }

            $rpcStatus = $response->getHeader('rpc-status');
            $httpStatus = $response->getStatus();

            if ($httpStatus !== 200 || !\in_array($rpcStatus, ['ok', 'exception'], true)) {
                throw new RpcException('Failed RPC call to ' . $class . '::' . $method . '() due to an unexpected HTTP status code: ' . $httpStatus . "\r\n" . $serializedResult);
            }

            try {
                $result = $this->serializer->unserialize($serializedResult);
            } catch (\Throwable $e) {
                $errorMessage = 'Failed to deserialize RPC result for ' . $class . '::' . $method . '()';

                throw new RpcException($errorMessage, 0, $e);
            }

            if ($rpcStatus === 'exception') {
                throw $result;
            }

            return $result;
        });
    }
}
