<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use Illuminate\Http\JsonResponse;

class PlatformsIndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $platforms = Platform::query()
            ->orderBy('family')
            ->orderBy('name')
            ->get()
            ->groupBy('family')
            ->map(fn ($group) => [
                'family' => $group->first()->family,
                'platforms' => $group->map(fn ($platform) => [
                    'code' => $platform->code,
                    'name' => $platform->name,
                    'metadata' => $platform->metadata,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'data' => $platforms,
            'meta' => [
                'generated_at' => now(),
                'count' => $platforms->sum(fn ($family) => $family['platforms']->count()),
            ],
        ]);
    }
}
