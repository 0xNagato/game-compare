<?php

namespace App\Support\Concerns;

trait HasIdempotencyKey
{
    public function uniqueId(): string
    {
        return property_exists($this, 'idempotencyKey')
            ? (string) $this->idempotencyKey
            : spl_object_hash($this);
    }
}
