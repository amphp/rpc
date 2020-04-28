<?php

namespace Amp\Rpc;

use Amp\Promise;
use ProxyManager\Factory\RemoteObject\AdapterInterface as Adapter;

interface RpcProxy extends Adapter
{
    public function call(string $class, string $method, array $params = []): Promise;
}