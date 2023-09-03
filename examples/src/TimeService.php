<?php declare(strict_types=1);

namespace Amp\Rpc\Examples\Basic;

interface TimeService
{
    public function getCurrentTime(): float;

    public function getId(): int;
}
