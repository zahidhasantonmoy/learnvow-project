<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

/** Shared JSON envelope (spec §1.4): {"data": ..., "meta": {"locale": ...}}. */
abstract class ApiController extends Controller
{
    protected function ok(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge(['locale' => app()->getLocale()], $meta),
        ], $status);
    }

    protected function fail(string $message, int $status = 422, array $errors = []): JsonResponse
    {
        // Same envelope as successes (spec §1.4): {message, meta:{locale}}.
        $payload = ['message' => $message, 'meta' => ['locale' => app()->getLocale()]];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function bookArray(Book $book, ?bool $canRead = null): array
    {
        return [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'title_en' => $book->title_en,
            'description' => $book->description,
            'language' => $book->language,
            'price_bdt' => (int) $book->price_bdt,
            'list_price_bdt' => $book->list_price_bdt !== null ? (int) $book->list_price_bdt : null,
            'is_free' => (bool) $book->is_free,
            'pages' => $book->pages,
            'rating_avg' => $book->rating_avg,
            'ratings_count' => (int) $book->ratings_count,
            'sales_count' => (int) $book->sales_count,
            'author' => $book->author ? ['id' => $book->author->id, 'name' => $book->author->name] : null,
            'category' => $book->category ? ['id' => $book->category->id, 'name' => $book->category->name, 'slug' => $book->category->slug] : null,
            'can_read' => $canRead,
            'published_at' => $book->published_at?->toIso8601String(),
        ];
    }

    protected function deviceArray(\App\Models\Device $device, ?int $currentTokenId = null): array
    {
        return [
            'id' => $device->id,
            'device_name' => $device->device_name,
            'device_type' => $device->device_type,
            'platform' => $device->platform,
            'last_ip' => $device->last_ip,
            'last_active_at' => $device->last_active_at?->toIso8601String(),
            'is_current' => $currentTokenId !== null && (int) $device->api_token_id === $currentTokenId,
            'inactive_90_days' => $device->last_active_at !== null && $device->last_active_at->lt(now()->subDays(90)),
        ];
    }
}
