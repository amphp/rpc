<?php

namespace Amp\Rpc;

use Amp\Future;
use ProxyManager\Factory\RemoteObject\AdapterInterface as Adapter;

interface RpcProxy extends Adapter
{
    public function call(string $class, string $method, array $params = []): Future;
}
