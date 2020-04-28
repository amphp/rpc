<?php

namespace Amp\Rpc\Examples;

use Amp\Promise;
use Amp\Rpc\Examples\Basic\Counter;
use Amp\Rpc\Examples\Basic\TimeService;
use Amp\Rpc\Server\RpcRegistry;
use Amp\Success;
use Monolog\Logger;
use function Amp\getCurrentTime;

function createRegistry(Logger $logger, int $id): RpcRegistry
{
    $registry = new RpcRegistry();
    $registry->register(TimeService::class, new class($id) implements TimeService {
        private $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function getCurrentTime(): Promise
        {
            return new Success(getCurrentTime());
        }

        public function getId(): Promise
        {
            return new Success($this->id);
        }
    });

    $registry->register(Counter::class, new class($logger) implements Counter {
        private $counter = 0;
        private $logger;

        public function __construct(Logger $logger)
        {
            $this->logger = $logger;
        }


        public function increase(): Promise
        {
            $this->counter++;

            $this->logger->info('Increased counter by one');

            if ($this->counter >= 1999) {
                throw new \Exception('Failed to increase counter');
            }

            return new Success;
        }

        public function decrease(): Promise
        {
            $this->counter--;

            $this->logger->info('Decreased counter by one');

            return new Success;
        }

        public function get(): Promise
        {
            return new Success($this->counter);
        }
    });

    return $registry;
}
