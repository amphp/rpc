<?php

namespace Amp\Rpc\Examples\Basic;

use Amp\Future;

interface Counter
{
    public function increase(): Future;

    public function decrease(): Future;

    /** @return Future<int> */
    public function get(): Future;
}
