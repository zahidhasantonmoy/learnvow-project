<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Services\AuthorService;
use Illuminate\Support\Facades\Gate;

class AuthorDashboardController extends Controller
{
    use BuildsSeo;

    /** Author Studio dashboard (spec §1.2) — approved author only. */
    public function index(string $locale, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);

        $author = $authors->approvedAuthorFor(auth()->user());

        return view('author.dashboard', [
            'author' => $author,
            'data' => $authors->dashboardData($author),
            'seo' => $this->seo(__('author.dashboard_title'), null, ['noindex' => true]),
        ]);
    }
}
