<?php

namespace Amp\Rpc\Examples\Basic;

interface TimeService
{
    public function getCurrentTime(): int;

    public function getId(): int;
}
