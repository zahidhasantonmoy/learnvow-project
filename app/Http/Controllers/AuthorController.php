<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\Follow;
use App\Services\BookService;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    use BuildsSeo;

    public function show(string $locale, string $slug, BookService $books)
    {
        $author = Author::approved()->where('slug', $slug)->firstOrFail();
        $authorBooks = $author->publishedBooks()->with('category')
            ->orderByDesc('ratings_count')->get();
        $user = auth()->user();
        $isFollowing = $user
            ? Follow::where('user_id', $user->id)->where('author_id', $author->id)->exists()
            : false;

        return view('authors.show', [
            'author' => $author,
            'books' => $authorBooks,
            'isFollowing' => $isFollowing,
            'followersCount' => $author->followers()->count(),
            'seo' => $this->seo(
                $author->name.' — LearnVow',
                app()->getLocale() === 'bn' ? ($author->bio ?? $author->bio_en) : ($author->bio_en ?? $author->bio),
                ['ogType' => 'profile', 'jsonLd' => $books->authorJsonLd($author)]
            ),
        ]);
    }

    public function follow(Request $request, string $locale, string $slug)
    {
        $author = Author::approved()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $existing = Follow::where('user_id', $user->id)->where('author_id', $author->id)->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            Follow::create(['user_id' => $user->id, 'author_id' => $author->id]);
            $following = true;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'following' => $following,
                'followers_count' => $author->followers()->count(),
            ]);
        }

        return back()->with('status', $following
            ? __('books.followed_author', ['name' => $author->name])
            : __('books.unfollowed_author', ['name' => $author->name]));
    }
}
