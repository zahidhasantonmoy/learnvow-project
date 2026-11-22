<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;

/**
 * Blog is structure + 1 sample article only (A12) — the spec defines
 * NO posts table, so articles live here as static data until content
 * is supplied by the user/coordinator.
 */
class BlogController extends Controller
{
    use BuildsSeo;

    /** @return array<int, array<string, mixed>> */
    public static function articles(): array
    {
        return [
            [
                'slug' => 'reading-guide-for-beginners',
                'title' => 'নতুন পাঠকের গাইড: কোথা থেকে শুরু করবেন?',
                'title_en' => 'A Beginner’s Guide to Reading: Where to Start?',
                'excerpt' => 'প্রথম বই বেছে নেওয়া থেকে প্রতিদিন পড়ার অভ্যাস — নতুন পাঠকদের জন্য সহজ গাইড।',
                'excerpt_en' => 'From choosing your first book to building a daily reading habit — a simple guide for new readers.',
                'body' => "পড়া শুরু করা কঠিন মনে হতে পারে, কিন্তু ছোট করে শুরু করলেই হয়। প্রতিদিন মাত্র ১০ মিনিট পড়ুন — সময়ের চেয়ে নিয়মিততাই বেশি জরুরি।\n\nপ্রথমে এমন একটি বই বেছে নিন যা আপনার আগ্রহের সঙ্গে মেলে। LearnVow-এর ফ্রি বইগুলো দিয়ে শুরু করা যায় — গীতাঞ্জলি, পদ্মা নদীর মাঝি কিংবা দেবদাস।\n\nএকটি বই শেষ না করে আরেকটি শুরু করবেন না। শেষ করা বইয়ের সংখ্যা বাড়লেই আত্মবিশ্বাস বাড়ে। আর পড়ার সময় নোট নিন — কোন লাইন ভালো লাগল, কেন লাগল।\n\nশুভ পড়া!",
                'body_en' => "Starting to read can feel hard, but starting small is enough. Read just 10 minutes a day — consistency matters more than duration.\n\nFirst, pick a book that matches your interest. LearnVow’s free books are a good place to start — Gitanjali, Padma Nadir Majhi or Devdas.\n\nDon’t start another book before finishing one. Each finished book builds confidence. And take notes while you read — which lines you liked, and why.\n\nHappy reading!",
                'published_at' => '2026-11-22',
            ],
        ];
    }

    public function index()
    {
        return view('blog.index', [
            'articles' => self::articles(),
            'seo' => $this->seo(__('books.blog_title'), __('books.blog_description')),
        ]);
    }

    public function show(string $locale, string $slug)
    {
        $article = collect(self::articles())->firstWhere('slug', $slug);
        abort_if(! $article, 404);

        return view('blog.show', [
            'article' => $article,
            'seo' => $this->seo(
                (app()->getLocale() === 'bn' ? $article['title'] : $article['title_en']).' — LearnVow',
                app()->getLocale() === 'bn' ? $article['excerpt'] : $article['excerpt_en'],
                ['ogType' => 'article']
            ),
        ]);
    }
}
