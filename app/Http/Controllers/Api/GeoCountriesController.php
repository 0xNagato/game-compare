<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GeoCountriesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $path = storage_path('app/geo/countries.json');

        if (! is_file($path)) {
            $this->bootstrapGeoJson($path);
        }

        if (! is_file($path)) {
            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'GeoJSON not available',
                ],
            ], 404);
        }

        $contents = file_get_contents($path) ?: '';
        $etag = sha1($contents);

        if (in_array($etag, $request->getETags(), true)) {
            return response()->noContent(304)->setEtag($etag);
        }

        return response($contents, 200, ['Content-Type' => 'application/json'])
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    protected function bootstrapGeoJson(string $path): void
    {
        $url = config('services.geo.countries_url');

        if (! $url) {
            return;
        }

        try {
            $response = Http::timeout(12)->acceptJson()->get($url);
        } catch (\Throwable $exception) {
            report($exception);

            return;
        }

        if (! $response->successful() || trim($response->body()) === '') {
            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $response->body());
    }
}
