<?php

namespace App\Services\Media\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class ProductMediaData implements Arrayable
{
    public function __construct(
        public readonly string $source,
        public readonly ?string $externalId,
        public readonly string $mediaType,
        public readonly ?string $title,
        public readonly ?string $caption,
        public readonly string $url,
        public readonly ?string $thumbnailUrl,
        public readonly ?string $attribution,
        public readonly ?string $license,
        public readonly ?string $licenseUrl,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->externalId,
            'media_type' => $this->mediaType,
            'title' => $this->title,
            'caption' => $this->caption,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnailUrl,
            'attribution' => $this->attribution,
            'license' => $this->license,
            'license_url' => $this->licenseUrl,
            'metadata' => $this->metadata,
        ];
    }
}
