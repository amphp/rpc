<?php

namespace Amp\Rpc\Server;

use Amp\Promise;
use Amp\Rpc\RpcProxy;
use Amp\Rpc\UnprocessedCallException;

class RpcRegistry implements RpcProxy
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
            $methodName = $method->getName();
            $call = $interface . '::' . $methodName . '()';

            // All supported versions support return types, so require them
            if (!$returnType || $returnType->allowsNull()) {
                throw new \Error($call . ' must declare return type ' . Promise::class);
            }

            if (!$returnType instanceof \ReflectionNamedType) {
                throw new \Error('Failed to check return type for ' . $call);
            }

            if ($returnType->getName() !== Promise::class) {
                throw new \Error($call . ' must declare return type ' . Promise::class);
            }
        }

        $this->objects[$lcInterface] = $object;
    }

    public function call(string $class, string $method, array $params = []): Promise
    {
        $lcClass = \strtolower($class);

        $object = $this->objects[$lcClass] ?? null;
        if ($object === null) {
            throw new UnprocessedCallException('Failed to call ' . $class . '::' . $method . '(), because ' . $class . ' is not registered');
        }

        return $object->{$method}(...$params);
    }
}
