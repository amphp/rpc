<?php

namespace Amp\Rpc\Examples;

use Amp\Future;
use Amp\Rpc\Examples\Basic\Counter;
use Amp\Rpc\Examples\Basic\TimeService;
use Amp\Rpc\Server\RpcRegistry;
use Monolog\Logger;

function createRegistry(Logger $logger, int $id): RpcRegistry
{
    $registry = new RpcRegistry();
    $registry->register(TimeService::class, new class($id) implements TimeService {
        private $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function getCurrentTime(): Future
        {
            return Future::complete(time());
        }

        public function getId(): Future
        {
            return Future::complete($this->id);
        }
    });

    $registry->register(Counter::class, new class($logger) implements Counter {
        private $counter = 0;
        private $logger;

        public function __construct(Logger $logger)
        {
            $this->logger = $logger;
        }


        public function increase(): Future
        {
            $this->counter++;

            $this->logger->info('Increased counter by one');

            if ($this->counter >= 1999) {
                throw new \Exception('Failed to increase counter');
            }
            return Future::complete();
        }

        public function decrease(): Future
        {
            $this->counter--;

            $this->logger->info('Decreased counter by one');

            return Future::complete();
        }

        public function get(): Future
        {
            return Future::complete($this->counter);
        }
    });

    return $registry;
}
