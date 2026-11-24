<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\VocabularyWord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Vocabulary list (§4.11). Words are stored lowercase (unique per user). */
class VocabularyController extends Controller
{
    use BuildsSeo;

    public function index(Request $request, string $locale)
    {
        $query = VocabularyWord::where('user_id', $request->user()->id)->with('book');

        if ($request->filled('q')) {
            $query->where('word', 'like', '%'.mb_strtolower($request->input('q')).'%');
        }

        return view('reader.vocabulary', [
            'words' => $query->latest('created_at')->paginate(30)->withQueryString(),
            'seo' => $this->seo(__('reader.vocabulary_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(Request $request, string $locale)
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:100'],
            'meaning_en' => ['nullable', 'string', 'max:2000'],
            'meaning_bn' => ['nullable', 'string', 'max:2000'],
            'example' => ['nullable', 'string', 'max:2000'],
            'book_id' => ['nullable', 'integer', Rule::exists('books', 'id')],
            'cfi' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['word'] = mb_strtolower(trim($data['word']));

        VocabularyWord::firstOrCreate(
            ['user_id' => $request->user()->id, 'word' => $data['word']],
            $data
        );

        return back()->with('status', __('reader.vocabulary_added'));
    }

    public function destroy(Request $request, string $locale, VocabularyWord $word)
    {
        // Own words only (VocabularyPolicy states the same rule; the
        // policy class needs explicit registration — see report).
        abort_unless($word->user_id === $request->user()->id, 403);
        $word->delete();

        return back()->with('status', __('reader.vocabulary_deleted'));
    }
}
