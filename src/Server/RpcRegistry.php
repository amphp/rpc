<?php

namespace Amp\Rpc\Server;

use Amp\Promise;
use Amp\Rpc\RpcException;
use ProxyManager\Factory\RemoteObject\AdapterInterface as RpcAdapter;
use function Amp\Rpc\cleanExceptionTrace;

class RpcRegistry implements RpcAdapter
{
    private $objects = [];

    public function register(string $interface, object $object): void
    {
        $lcInterface = \strtolower($interface);

        if (isset($this->objects[$lcInterface])) {
            throw new \Error('Mapping already exists for ' . $interface);
        }

        if (!$object instanceof $interface) {
            throw new \Error('Invalid mapping for ' . $interface . ', because ' . \get_class($object) . ' does not implement ' . $interface);
        }

        $reflection = new \ReflectionClass($interface);

        if (!$reflection->isInterface()) {
            throw new \Error('Invalid mapping for ' . $interface . ', because ' . $interface . ' is not an interface');
        }

        foreach ($reflection->getMethods() as $method) {
            $returnType = $method->getReturnType();
            if (!$returnType) {
                continue;
            }

            if ($returnType->allowsNull()) {
                throw new \Error('Invalid mapping for ' . $interface . ', because ' . $interface . '::' . $method->getName() . ' has nullable return type, expected ' . Promise::class);
            }

            if (!$returnType instanceof \ReflectionNamedType) {
                throw new \Error('Invalid mapping for ' . $interface . ', because ' . $interface . '::' . $method->getName() . ' has unexpected return type class ' . \get_class($returnType));
            }

            if ($returnType->getName() !== Promise::class) {
                throw new \Error('Invalid mapping for ' . $interface . ', because ' . $interface . '::' . $method->getName() . ' has unexpected return type ' . $returnType->getName() . ', expected ' . Promise::class);
            }
        }

        $this->objects[$lcInterface] = $object;
    }

    public function call(string $wrappedClass, string $method, array $params = [])
    {
        $lcClass = \strtolower($wrappedClass);

        $object = $this->objects[$lcClass] ?? null;
        if ($object === null) {
            throw new RpcException('Failed to call ' . $wrappedClass . '::' . $method . ', because no mapping exists');
        }

        return $object->{$method}(...$params);
    }
}