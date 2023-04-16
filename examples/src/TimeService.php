<?php

namespace Amp\Rpc\Examples\Basic;

use Amp\Future;

interface TimeService
{
    public function getCurrentTime(): Future;

    public function getId(): Future;
}
