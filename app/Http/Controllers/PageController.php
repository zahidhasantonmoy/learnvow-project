<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;

class PageController extends Controller
{
    use BuildsSeo;

    public function terms()
    {
        return view('pages.terms', ['seo' => $this->seo(__('books.terms_title'))]);
    }

    public function privacy()
    {
        return view('pages.privacy', ['seo' => $this->seo(__('books.privacy_title'))]);
    }

    public function refundPolicy()
    {
        return view('pages.refund-policy', ['seo' => $this->seo(__('books.refund_title'))]);
    }

    public function copyright()
    {
        return view('pages.copyright', ['seo' => $this->seo(__('books.copyright_title'))]);
    }

    public function help()
    {
        return view('pages.help', ['seo' => $this->seo(__('books.help_title'))]);
    }
}
