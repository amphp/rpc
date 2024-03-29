<?php declare(strict_types=1);

namespace Amp\Rpc\Examples\Basic;

interface Counter
{
    public function increase(): void;

    public function decrease(): void;

    public function get(): int;
}
