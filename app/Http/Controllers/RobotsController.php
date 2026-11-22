<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        $txt = <<<'TXT'
User-agent: OAI-SearchBot
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: Google-Extended
Allow: /

User-agent: GPTBot
Disallow: /

User-agent: CCBot
Disallow: /

User-agent: Bytespider
Disallow: /

User-agent: *
Disallow: /reader/
Disallow: /checkout/
Disallow: /wallet/
Disallow: /admin/
Disallow: /api/
TXT;

        return response($txt."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
