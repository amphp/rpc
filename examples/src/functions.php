<?php

namespace Amp\Rpc\Examples;

use Amp\Rpc\Examples\Basic\Counter;
use Amp\Rpc\Examples\Basic\TimeService;
use Amp\Rpc\Server\RpcRegistry;
use Monolog\Logger;
use function Amp\now;

function createRegistry(Logger $logger, int $id): RpcRegistry
{
    $registry = new RpcRegistry();
    $registry->register(TimeService::class, new class($id) implements TimeService {
        private int $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function getCurrentTime(): float
        {
            return now();
        }

        public function getId(): int
        {
            return $this->id;
        }
    });

    $registry->register(Counter::class, new class($logger) implements Counter {
        private $counter = 0;
        private $logger;

        public function __construct(Logger $logger)
        {
            $this->logger = $logger;
        }


        public function increase(): void
        {
            $this->counter++;

            $this->logger->info('Increased counter by one');

            if ($this->counter >= 1999) {
                throw new \Exception('Failed to increase counter');
            }
        }

        public function decrease(): void
        {
            $this->counter--;

            $this->logger->info('Decreased counter by one');
        }

        public function get(): int
        {
            return $this->counter;
        }
    });

    return $registry;
}
