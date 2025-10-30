<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GiantBombGame;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportGiantBombCatalogueCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'catalogue:import-giantbomb
        {source? : Path to the Giant Bomb JSON export}
        {--dry-run : Validate the payload without writing to the database}';

    protected $description = 'Import the Giant Bomb catalogue JSON into the local mirror table.';

    public function handle(): int
    {
        $startedAt = Carbon::now();
        $path = $this->argument('source') ?? (string) config('catalogue.cross_reference.giant_bomb_catalogue_file', 'giant_bomb_games_detailed.json');
        $absolutePath = $this->resolvePath($path);

        if (! is_file($absolutePath)) {
            $this->components->error(sprintf('Source file not found: %s', $absolutePath));

            return self::FAILURE;
        }

        try {
            $payload = json_decode(File::get($absolutePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->components->error(sprintf('Unable to decode JSON: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        if (! is_array($payload) || $payload === []) {
            $this->components->warn('The JSON payload is empty. No changes were made.');

            return self::SUCCESS;
        }

        $rows = [];
        $processed = 0;
        $importedAt = Carbon::now();

        foreach ($payload as $guid => $record) {
            if (! is_array($record)) {
                continue;
            }

            $processed++;

            $resolvedGuid = $this->stringOrNull($record['guid'] ?? $guid);
            $name = $this->stringOrNull($record['name'] ?? null);

            if ($resolvedGuid === null || $name === null) {
                continue;
            }

            $normalizedName = $this->normalizeName($name);
            if ($normalizedName === null) {
                continue;
            }

            $image = is_array($record['image'] ?? null) ? $record['image'] : [];
            $primaryImage = $this->resolvePrimaryImage($record);
            $platforms = $this->extractPlatforms($record['platforms'] ?? null);
            $aliases = $this->extractAliases($record['aliases'] ?? null);

            $videos = $this->extractVideos($record['videos'] ?? null);
            $primaryVideo = collect($videos)
                ->first(function (array $video) {
                    return ($video['high_url'] ?? null) !== null || ($video['hd_url'] ?? null) !== null;
                })
                ?? ($videos[0] ?? null);

            try {
                $rows[] = [
                    'guid' => $resolvedGuid,
                    'giantbomb_id' => $this->extractNumericId($resolvedGuid),
                    'name' => $name,
                    'slug' => $this->resolveSlug($record['slug'] ?? null, $name),
                    'site_detail_url' => $this->stringOrNull($record['site_detail_url'] ?? null),
                    'deck' => $this->stringOrNull($record['deck'] ?? null, 512),
                    'description' => $this->stringOrNull($record['description'] ?? null),
                    'platforms' => $platforms !== [] ? json_encode($platforms, JSON_THROW_ON_ERROR) : null,
                    'aliases' => $aliases !== [] ? json_encode($aliases, JSON_THROW_ON_ERROR) : null,
                    'primary_image_url' => $primaryImage,
                    'image_super_url' => $this->stringOrNull($image['super_url'] ?? null),
                    'image_small_url' => $this->stringOrNull($image['small_url'] ?? null),
                    'image_original_url' => $this->stringOrNull($image['original_url'] ?? null),
                    'primary_video_name' => $this->stringOrNull($primaryVideo['name'] ?? null),
                    'primary_video_high_url' => $this->stringOrNull($primaryVideo['high_url'] ?? null),
                    'primary_video_hd_url' => $this->stringOrNull($primaryVideo['hd_url'] ?? null),
                    'video_count' => count($videos),
                    'videos' => $videos !== [] ? json_encode($videos, JSON_THROW_ON_ERROR) : null,
                    'normalized_name' => $normalizedName,
                    'payload_hash' => $this->hashPayload($record),
                    'last_synced_at' => $importedAt,
                    'created_at' => $importedAt,
                    'updated_at' => $importedAt,
                ];
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf('Unable to encode record for %s: %s', $resolvedGuid, $exception->getMessage()), previous: $exception);
            }
        }

        if ($rows === []) {
            $this->components->warn('No eligible records were found in the payload.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info(sprintf('Dry run: %d records parsed successfully from %s.', count($rows), $absolutePath));

            return self::SUCCESS;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            GiantBombGame::upsert(
                $chunk,
                ['guid'],
                [
                    'giantbomb_id',
                    'name',
                    'slug',
                    'site_detail_url',
                    'deck',
                    'description',
                    'platforms',
                    'aliases',
                    'primary_image_url',
                    'image_super_url',
                    'image_small_url',
                    'image_original_url',
                    'primary_video_name',
                    'primary_video_high_url',
                    'primary_video_hd_url',
                    'video_count',
                    'videos',
                    'normalized_name',
                    'payload_hash',
                    'last_synced_at',
                    'updated_at',
                ]
            );
        }

        GiantBombGame::query()
            ->where(function ($query) use ($startedAt): void {
                $query->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', $startedAt);
            })
            ->update(['last_synced_at' => null]);

        $this->components->info(sprintf(
            'Imported %d records (processed %d rows) from %s.',
            count($rows),
            $processed,
            $absolutePath
        ));

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (Str::startsWith($path, ['/']) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function normalizeName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $clean = preg_replace('/(\[[^\]]*\]|\([^)]*\))/u', ' ', $name) ?? $name;
        $normalized = Str::of($clean)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->value();

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveSlug(?string $slug, string $fallback): ?string
    {
        $candidate = $this->stringOrNull($slug);

        if ($candidate !== null && $candidate !== '') {
            return Str::slug($candidate);
        }

        return Str::slug($fallback) ?: null;
    }

    private function extractNumericId(string $guid): ?int
    {
        if (! str_contains($guid, '-')) {
            return null;
        }

        $parts = explode('-', $guid);
        $numeric = end($parts);

        return is_numeric($numeric) ? (int) $numeric : null;
    }

    private function extractPlatforms(mixed $platforms): array
    {
        if (! is_array($platforms)) {
            return [];
        }

        return collect($platforms)
            ->filter(fn ($platform) => is_array($platform))
            ->map(fn (array $platform) => $this->stringOrNull($platform['name'] ?? $platform['abbreviation'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function extractAliases(mixed $aliases): array
    {
        if ($aliases === null) {
            return [];
        }

        if (is_array($aliases)) {
            $values = $aliases;
        } else {
            $values = preg_split('/[\r\n,]+/', (string) $aliases) ?: [];
        }

        return collect($values)
            ->map(fn ($alias) => $this->stringOrNull($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolvePrimaryImage(array $record): ?string
    {
        $image = $record['image'] ?? null;
        if (is_array($image)) {
            foreach (['super_url', 'original_url', 'small_url'] as $key) {
                if (! empty($image[$key]) && is_string($image[$key])) {
                    return $image[$key];
                }
            }
        }

        $gallery = $record['images'] ?? null;
        if (is_array($gallery)) {
            foreach ($gallery as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                foreach (['super_url', 'original_url', 'small_url'] as $key) {
                    if (! empty($candidate[$key]) && is_string($candidate[$key])) {
                        return $candidate[$key];
                    }
                }
            }
        }

        return null;
    }

    private function hashPayload(array $record): string
    {
        return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function stringOrNull(mixed $value, ?int $maxLength = null): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if ($maxLength !== null && mb_strlen($trimmed) > $maxLength) {
            return mb_substr($trimmed, 0, $maxLength);
        }

        return $trimmed;
    }

    /**
     * @return array<int, array{name?:string,guid?:string,high_url?:string,hd_url?:string}>
     */
    private function extractVideos(mixed $videos): array
    {
        if (! is_array($videos)) {
            return [];
        }

        return collect($videos)
            ->filter(fn ($video) => is_array($video))
            ->map(function (array $video) {
                $normalized = [];

                if (($name = $this->stringOrNull($video['name'] ?? null)) !== null) {
                    $normalized['name'] = $name;
                }

                if (($guid = $this->stringOrNull($video['guid'] ?? null)) !== null) {
                    $normalized['guid'] = $guid;
                }

                if (($high = $this->stringOrNull($video['high_url'] ?? null)) !== null) {
                    $normalized['high_url'] = $high;
                }

                if (($hd = $this->stringOrNull($video['hd_url'] ?? null)) !== null) {
                    $normalized['hd_url'] = $hd;
                }

                return $normalized;
            })
            ->filter(fn (array $video) => $video !== [])
            ->values()
            ->all();
    }
}
