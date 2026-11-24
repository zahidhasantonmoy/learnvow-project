<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collections / shelves (spec §4.2). Builder A owns the models; this
 * controller works directly on the tables (thin queries, no new shared
 * service) and degrades to empty lists until those tables land.
 */
class CollectionController extends ApiController
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('collections')) {
            return $this->ok([]);
        }

        $rows = DB::table('collections')->where('user_id', $request->user()->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        return $this->ok($rows->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'name_en' => $c->name_en,
            'description' => $c->description, 'is_public' => (bool) $c->is_public,
            'books_count' => Schema::hasTable('collection_items')
                ? DB::table('collection_items')->where('collection_id', $c->id)->count() : 0,
        ])->values());
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('collections')) {
            return $this->fail(__('api.collections_unavailable'), 503);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'nullable|boolean',
        ]);

        $user = $request->user();
        if (DB::table('collections')->where('user_id', $user->id)->count() >= 20) {
            return $this->fail(__('api.collections_limit'), 422);
        }
        if (DB::table('collections')->where('user_id', $user->id)->where('name', $data['name'])->exists()) {
            return $this->fail(__('api.collection_name_taken'), 422);
        }

        $id = DB::table('collections')->insertGetId([
            'user_id' => $user->id, 'name' => $data['name'], 'name_en' => $data['name_en'] ?? null,
            'description' => $data['description'] ?? null, 'is_public' => (bool) ($data['is_public'] ?? false),
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id, 'name' => $data['name'], 'is_public' => (bool) ($data['is_public'] ?? false)], 201);
    }
}
