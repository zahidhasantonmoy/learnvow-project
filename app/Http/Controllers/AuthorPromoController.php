<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AuthorPromoController extends Controller
{
    use BuildsSeo;

    public function index(string $locale, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);

        $promos = \App\Models\Coupon::where('author_id', $author->id)
            ->latest()->get();

        return view('author.promos', [
            'author' => $author,
            'promos' => $promos,
            'books' => $author->books()->orderBy('title')->get(),
            'limits' => $authors->promoLimits(),
            'canCreate' => $authors->canCreatePromo(),
            'seo' => $this->seo(__('author.promos_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(string $locale, Request $request, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor($request->user());
        abort_unless($author !== null, 403);

        $data = $request->validate([
            'book_id' => 'required|exists:books,id',
            'code' => 'nullable|string|max:32',
            'percent' => 'required|integer|min:1|max:100',
            'duration_days' => 'required|integer|min:1',
            'max_uses' => 'nullable|integer|min:1',
        ]);

        try {
            $authors->createPromo($author, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['percent' => $e->getMessage()])->withInput();
        }

        return back()->with('status', __('author.promo_success'));
    }
}
