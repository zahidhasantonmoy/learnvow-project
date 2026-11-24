<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

/** "For You" — rule-based recommendations (§4.3). */
class DiscoveryController extends Controller
{
    use BuildsSeo;

    public function __construct(protected RecommendationService $recommendations) {}

    public function forYou(Request $request, string $locale)
    {
        return view('books.for-you', [
            'recommendations' => $this->recommendations->forUser($request->user()),
            'seo' => $this->seo(__('books.for_you_title'), null, ['noindex' => true]),
        ]);
    }
}
