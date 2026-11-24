<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\Category;
use App\Models\TasteProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Taste profile (§4.10) — one row per user, feeds forYou. */
class TasteProfileController extends Controller
{
    use BuildsSeo;

    public function edit(Request $request, string $locale)
    {
        return view('profile.taste', [
            'profile' => TasteProfile::firstOrNew(['user_id' => $request->user()->id]),
            'categories' => Category::orderBy('sort_order')->get(),
            'authors' => Author::orderBy('name')->limit(100)->get(),
            'seo' => $this->seo(__('books.taste_title'), null, ['noindex' => true]),
        ]);
    }

    public function update(Request $request, string $locale)
    {
        $data = $request->validate([
            'genres' => ['nullable', 'array'],
            'genres.*' => ['integer', Rule::exists('categories', 'id')],
            'authors' => ['nullable', 'array'],
            'authors.*' => ['integer', Rule::exists('authors', 'id')],
            'languages' => ['nullable', 'array'],
            'languages.*' => [Rule::in(['bn', 'en'])],
            'reading_time' => ['nullable', 'string', Rule::in(['morning', 'afternoon', 'evening', 'night'])],
        ]);

        TasteProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'genres' => $data['genres'] ?? [],
                'authors' => $data['authors'] ?? [],
                'languages' => $data['languages'] ?? [],
                'reading_time' => $data['reading_time'] ?? null,
            ]
        );

        return back()->with('status', __('books.taste_saved'));
    }
}
