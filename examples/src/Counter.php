<?php

namespace Amp\Rpc\Examples\Basic;

interface Counter
{
    public function increase(): void;

    public function decrease(): void;

    public function get(): int;
}
