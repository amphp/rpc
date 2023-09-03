<?php

namespace Amp\Rpc\Examples\Basic;

interface TimeService
{
    public function getCurrentTime(): float;

    public function getId(): int;
}
