<?php declare(strict_types=1);

namespace Amp\Rpc\Server;

use Amp\Rpc\RpcProxy;
use Amp\Rpc\UnprocessedCallException;

final class RpcRegistry implements RpcProxy
{
    private array $objects = [];

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

        $this->objects[$lcInterface] = $object;
    }

    public function call(string $wrappedClass, string $method, array $params = []): mixed
    {
        $lcClass = \strtolower($wrappedClass);

        $object = $this->objects[$lcClass] ?? null;
        if ($object === null) {
            throw new UnprocessedCallException('Failed to call ' . $wrappedClass . '::' . $method . '(), because ' . $wrappedClass . ' is not registered');
        }

        return $object->{$method}(...$params);
    }
}
