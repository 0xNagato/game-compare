<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TopGamesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'platform' => ['sometimes', 'string'],
            'genre' => ['sometimes', 'string'],
            'q' => ['sometimes', 'string', 'max:120'],
        ];
    }

    /**
     * @return array{limit:int,platforms:list<string>|null,genres:list<string>|null,query:string|null}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        $platforms = isset($validated['platform'])
            ? collect(explode(',', (string) $validated['platform']))
                ->map(fn (string $code) => trim(strtolower($code)))
                ->filter()
                ->unique()
                ->values()
                ->all()
            : null;

        $genres = isset($validated['genre'])
            ? collect(explode(',', (string) $validated['genre']))
                ->map(fn (string $slug) => trim(strtolower($slug)))
                ->filter()
                ->unique()
                ->values()
                ->all()
            : null;

        return [
            'limit' => (int) ($validated['limit'] ?? 20),
            'platforms' => $platforms,
            'genres' => $genres,
            'query' => $validated['q'] ?? null,
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
