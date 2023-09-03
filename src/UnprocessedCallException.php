<?php declare(strict_types=1);

namespace Amp\Rpc;

/**
 * Used to mark calls that can safely be retried on another server.
 */
class UnprocessedCallException extends RpcException
{
}
