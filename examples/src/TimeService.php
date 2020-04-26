<?php

namespace Amp\Rpc\Examples\Basic;

use Amp\Promise;

interface TimeService
{
    public function getCurrentTime(): Promise;
}