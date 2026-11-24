<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Vocabulary (spec §4.11). Empty / 503 until Builder A's table lands. */
class VocabularyController extends ApiController
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('vocabulary_words')) {
            return $this->ok([]);
        }

        $rows = DB::table('vocabulary_words')->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->get();

        return $this->ok($rows->map(fn ($w) => [
            'id' => $w->id, 'word' => $w->word, 'meaning_en' => $w->meaning_en,
            'meaning_bn' => $w->meaning_bn, 'example' => $w->example,
            'book_id' => $w->book_id, 'cfi' => $w->cfi,
        ])->values());
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('vocabulary_words')) {
            return $this->fail(__('api.vocabulary_unavailable'), 503);
        }

        $data = $request->validate([
            'word' => 'required|string|max:100',
            'meaning_en' => 'nullable|string|max:2000',
            'meaning_bn' => 'nullable|string|max:2000',
            'example' => 'nullable|string|max:2000',
            'book_id' => 'nullable|exists:books,id',
            'cfi' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        DB::table('vocabulary_words')->updateOrInsert(
            ['user_id' => $user->id, 'word' => $data['word']],
            array_merge($data, ['created_at' => now()])
        );

        return $this->ok(['word' => $data['word']], 201);
    }
}
