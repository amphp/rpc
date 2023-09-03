<?php

namespace Amp\Rpc\Interceptor;

use Amp\Rpc\RpcProxy;
use Amp\Rpc\UnprocessedCallException;

final class RoundRobinBalancer implements RpcProxy
{
    /** @var RpcProxy[] */
    private array $proxies;

    private int $next = 0;

    /**
     * @param RpcProxy[] $proxies
     */
    public function __construct(array $proxies)
    {
        $this->proxies = $proxies;
    }

    public function call(string $wrappedClass, string $method, array $params = []): mixed
    {
        $index = $this->next;

        if (++$this->next === \count($this->proxies)) {
            $this->next = 0;
        }

        $attempt = 0;

        do {
            try {
                return $this->proxies[($index + $attempt) % \count($this->proxies)]->call($wrappedClass, $method, $params);
            } catch (UnprocessedCallException $e) {
                $attempt++;
            }
        } while ($attempt < \count($this->proxies));

        throw new UnprocessedCallException("Giving up calling {$wrappedClass}::{$method}() after {$attempt} attempts returning " . UnprocessedCallException::class);
    }
}
