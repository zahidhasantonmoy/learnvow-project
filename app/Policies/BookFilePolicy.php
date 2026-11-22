<?php

namespace App\Policies;

use App\Models\BookFile;
use App\Models\User;
use App\Services\BookService;

/**
 * A book file may only be opened / downloaded by a user who may read
 * the book (BookService::canRead, spec §2.3). The raw path is never
 * exposed — access always goes through the signed-file route.
 */
class BookFilePolicy
{
    public function view(User $user, BookFile $file): bool
    {
        return app(BookService::class)->canRead($user, $file->book);
    }

    public function download(User $user, BookFile $file): bool
    {
        return $this->view($user, $file);
    }
}
