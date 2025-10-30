<?php

namespace App\Services\GameData\DTO;

final class GameUid
{
    public function __construct(public readonly string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
