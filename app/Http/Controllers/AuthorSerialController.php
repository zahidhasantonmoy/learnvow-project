<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\Serial;
use App\Services\AuthorService;
use Illuminate\Http\Request;

/**
 * Author serials (Phase 4 spec §4.7): the serial's Author creates the
 * serial from Author Studio and adds episodes (SerialEpisodeManager);
 * Admin approves via AdminService::approveSerial() (Filament
 * SerialResource). Gated by the `serials.publish` permission (author).
 */
class AuthorSerialController extends Controller
{
    use BuildsSeo;

    /** Approved Author row for this user, or 403. */
    protected function author(AuthorService $authors): Author
    {
        abort_unless(auth()->user()->can('serials.publish'), 403);
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);

        return $author;
    }

    /** The author's own serial, or 404 (never another author's). */
    protected function ownSerial(Author $author, Serial $serial): Serial
    {
        abort_unless((int) $serial->author_id === (int) $author->id, 404);

        return $serial;
    }

    public function index(string $locale, AuthorService $authors)
    {
        $author = $this->author($authors);

        return view('author.serials.index', [
            'author' => $author,
            'serials' => Serial::where('author_id', $author->id)
                ->withCount('episodes')->latest()->get(),
            'seo' => $this->seo(__('author.my_serials'), null, ['noindex' => true]),
        ]);
    }

    public function create(string $locale, AuthorService $authors)
    {
        $author = $this->author($authors);

        return view('author.serials.create', [
            'author' => $author,
            'seo' => $this->seo(__('author.serial_create_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(string $locale, Request $request, AuthorService $authors)
    {
        $author = $this->author($authors);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'is_free' => 'nullable|boolean',
        ]);

        $serial = Serial::create([
            'author_id' => $author->id,
            'title' => $data['title'],
            'title_en' => $data['title_en'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'ongoing',
            'is_free' => $request->boolean('is_free', true),
            'is_subscription_included' => true,
        ]);

        return redirect()->route('author.serials.show', ['locale' => $locale, 'serial' => $serial->id])
            ->with('status', __('author.serial_created'));
    }

    /** Manage one serial: episode list + SerialEpisodeManager. */
    public function show(string $locale, AuthorService $authors, Serial $serial)
    {
        $author = $this->author($authors);
        $serial = $this->ownSerial($author, $serial);

        return view('author.serials.show', [
            'author' => $author,
            'serial' => $serial->load('episodes'),
            'seo' => $this->seo($serial->title, null, ['noindex' => true]),
        ]);
    }
}
