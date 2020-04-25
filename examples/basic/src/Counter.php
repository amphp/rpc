<?php

namespace Amp\Rpc\Examples\Basic;

use Amp\Promise;

interface Counter
{
    public function increase(): Promise;

    public function decrease(): Promise;

    public function get(): Promise;
}