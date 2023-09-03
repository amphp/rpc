<?php declare(strict_types=1);

namespace Amp\Rpc\Client;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Rpc\RpcException;
use Amp\Rpc\RpcProxy;
use Amp\Rpc\UnprocessedCallException;
use Amp\Serialization\Serializer;

final class RpcClient implements RpcProxy
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

    public function call(string $wrappedClass, string $method, array $params = []): mixed
    {
        $request = new Request($this->uri, 'POST');
        $request->setHeader('rpc-class', $wrappedClass);
        $request->setHeader('rpc-method', $method);

        try {
            $request->setBody($this->serializer->serialize($params));
        } catch (\Throwable $e) {
            $errorMessage = 'Failed to serialize RPC parameters for ' . $wrappedClass . '::' . $method . '()';

            throw new RpcException($errorMessage, 0, $e);
        }

        try {
            $response = $this->httpClient->request($request);
            $serializedResult = $response->getBody()->buffer();
        } catch (\Throwable $e) {
            $errorMessage = 'Failed RPC call due to an HTTP communication failure for ' . $wrappedClass . '::' . $method . '()';

            if ($request->isUnprocessed()) {
                throw new UnprocessedCallException($errorMessage, 0, $e);
            }

            throw new RpcException($errorMessage, 0, $e);
        }

        $rpcStatus = $response->getHeader('rpc-status');
        $httpStatus = $response->getStatus();

        if ($httpStatus !== 200 || !\in_array($rpcStatus, ['ok', 'exception'], true)) {
            throw new RpcException('Failed RPC call to ' . $wrappedClass . '::' . $method . '() due to an unexpected HTTP status code: ' . $httpStatus . "\r\n" . $serializedResult);
        }

        try {
            $result = $this->serializer->unserialize($serializedResult);
        } catch (\Throwable $e) {
            $errorMessage = 'Failed to deserialize RPC result for ' . $wrappedClass . '::' . $method . '()';

            throw new RpcException($errorMessage, 0, $e);
        }

        if ($rpcStatus === 'exception') {
            throw $result;
        }

        return $result;
    }
}
