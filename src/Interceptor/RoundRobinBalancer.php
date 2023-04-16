<?php

namespace Amp\Rpc\Interceptor;

use Amp\Future;
use Amp\Rpc\RpcProxy;
use Amp\Rpc\UnprocessedCallException;

final class RoundRobinBalancer implements RpcProxy
{
    /** @var RpcProxy[] */
    private $proxies;

    /** @var int */
    private $next = 0;

    /**
     * @param RpcProxy[] $proxies
     */
    public function __construct(array $proxies)
    {
        $this->proxies = $proxies;
    }

    public function call(string $class, string $method, array $params = []): Future
    {
        $index = $this->next;

        if (++$this->next === \count($this->proxies)) {
            $this->next = 0;
        }

        $attempt = 0;

        do {
            try {
                return $this->proxies[($index + $attempt) % \count($this->proxies)]->call($class, $method, $params);
            } catch (UnprocessedCallException $e) {
                $attempt++;
            }
        } while ($attempt < \count($this->proxies));

        throw new UnprocessedCallException("Giving up calling {$class}::{$method}() after {$attempt} attempts returning " . UnprocessedCallException::class);
    }
}
