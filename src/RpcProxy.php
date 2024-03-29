<?php declare(strict_types=1);

namespace Amp\Rpc;

use ProxyManager\Factory\RemoteObject\AdapterInterface as Adapter;

interface RpcProxy extends Adapter
{
    public function call(string $wrappedClass, string $method, array $params = []): mixed;
}
