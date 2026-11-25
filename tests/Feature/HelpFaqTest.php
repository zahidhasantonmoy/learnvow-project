<?php

namespace Tests\Feature;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role-audit Medium-2 fix: Support-written FAQs must reach readers on
 * /{locale}/help. Active FAQs render per locale, grouped by category;
 * inactive FAQs never appear.
 */
class HelpFaqTest extends TestCase
{
    use RefreshDatabase;

    private function makeFaq(array $overrides = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'TrxID যাচাই করতে কত সময় লাগে?',
            'question_en' => 'How long does TrxID verification take?',
            'answer' => 'সাধারণত ১২ ঘণ্টার মধ্যে যাচাই হয়।',
            'answer_en' => 'Verification usually completes within 12 hours.',
            'category' => 'payment',
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides));
    }

    public function test_help_page_renders_active_faq_in_english(): void
    {
        $this->makeFaq();

        $this->get('/en/help')
            ->assertOk()
            ->assertSee('Frequently asked questions')
            ->assertSee('How long does TrxID verification take?')
            ->assertSee('Verification usually completes within 12 hours.')
            ->assertDontSee('TrxID যাচাই করতে কত সময় লাগে?');
    }

    public function test_help_page_renders_active_faq_in_bangla(): void
    {
        $this->makeFaq();

        $this->get('/bn/help')
            ->assertOk()
            ->assertSee('প্রায়শই জিজ্ঞাসিত প্রশ্ন')
            ->assertSee('TrxID যাচাই করতে কত সময় লাগে?')
            ->assertSee('সাধারণত ১২ ঘণ্টার মধ্যে যাচাই হয়।')
            ->assertDontSee('How long does TrxID verification take?');
    }

    public function test_help_page_falls_back_to_bangla_when_english_text_is_missing(): void
    {
        $this->makeFaq(['question_en' => null, 'answer_en' => null]);

        $this->get('/en/help')
            ->assertOk()
            ->assertSee('TrxID যাচাই করতে কত সময় লাগে?')
            ->assertSee('সাধারণত ১২ ঘণ্টার মধ্যে যাচাই হয়।');
    }

    public function test_help_page_hides_inactive_faq(): void
    {
        $this->makeFaq(['is_active' => false]);

        $this->get('/en/help')
            ->assertOk()
            ->assertDontSee('How long does TrxID verification take?')
            ->assertDontSee('Frequently asked questions');

        $this->get('/bn/help')
            ->assertOk()
            ->assertDontSee('TrxID যাচাই করতে কত সময় লাগে?');
    }

    public function test_help_page_groups_faqs_by_category(): void
    {
        $this->makeFaq(['category' => 'payment']);
        $this->makeFaq([
            'question' => 'বই কীভাবে পড়ব?',
            'question_en' => 'How do I read a book?',
            'answer' => 'লাইব্রেরি থেকে বই খুলুন।',
            'answer_en' => 'Open the book from your library.',
            'category' => 'reading',
            'sort_order' => 1,
        ]);
        $this->makeFaq([
            'question' => 'সাধারণ প্রশ্ন?',
            'question_en' => 'A general question?',
            'answer' => 'সাধারণ উত্তর।',
            'answer_en' => 'A general answer.',
            'category' => null,
            'sort_order' => 2,
        ]);

        $this->get('/en/help')
            ->assertOk()
            ->assertSee('payment')
            ->assertSee('reading')
            ->assertSee('General')
            ->assertSee('How long does TrxID verification take?')
            ->assertSee('How do I read a book?')
            ->assertSee('A general question?');
    }

    public function test_help_page_keeps_static_sections(): void
    {
        $this->makeFaq();

        $this->get('/en/help')
            ->assertOk()
            ->assertSee(__('books.help_reading_heading', [], 'en'))
            ->assertSee(__('books.help_payment_heading', [], 'en'))
            ->assertSee(__('books.help_contact_heading', [], 'en'));
    }
}
