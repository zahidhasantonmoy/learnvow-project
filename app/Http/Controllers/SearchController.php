<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use BuildsSeo;

    public function index(Request $request)
    {
        $query = (string) $request->query('q', '');

        return view('search.index', [
            'query' => $query,
            // Filtered search results must not be indexed (spec §3.1).
            'seo' => $this->seo(__('search.title'), __('search.description'), ['noindex' => true]),
        ]);
    }
}
