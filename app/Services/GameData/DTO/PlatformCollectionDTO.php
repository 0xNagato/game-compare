<?php

namespace App\Services\GameData\DTO;

/**
 * @psalm-type PlatformDTO = array{code:string,name:string,family:string}
 */
final class PlatformCollectionDTO
{
    /**
     * @param  list<PlatformDTO>  $platforms
     */
    public function __construct(public readonly array $platforms) {}
}
