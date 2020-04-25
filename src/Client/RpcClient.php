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

    public function call(string $wrappedClass, string $method, array $params = [])
    {
        return call(function () use ($wrappedClass, $method, $params) {
            $request = new Request($this->uri, 'POST');
            $request->setHeader('rpc-class', $wrappedClass);
            $request->setHeader('rpc-method', $method);
            $request->setBody($this->serializer->serialize($params));

            /** @var Response $response */
            $response = yield $this->httpClient->request($request);
            $serializedResult = yield $response->getBody()->buffer();

            $rpcStatus = $response->getHeader('rpc-status');
            if ($response->getStatus() !== 200 || !\in_array($rpcStatus, ['ok', 'exception'], true)) {
                throw new RpcException('Failed RPC call to ' . $wrappedClass . '::' . $method . ', bad response code from server: ' . $serializedResult . "\r\n" . $serializedResult);
            }

            $result = $this->serializer->unserialize($serializedResult);

            if ($rpcStatus === 'exception') {
                throw $result;
            }

            return $result;
        });
    }
}