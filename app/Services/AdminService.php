<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * Admin approval rules for books / authors / reviews (spec §6, Builder C).
 * Used by Filament resources and (once A lands them) by policies.
 *
 * Status transitions enforced here — never change a status by direct
 * assignment in a controller / resource action.
 *
 * Role checks read the Spatie pivot tables directly, so they work whether
 * or not Builder A has added the HasRoles trait to the User model yet.
 * When the trait exists, its helpers are used instead.
 */
class AdminService
{
    // ------------------------------------------------------------------
    // Role helpers (static)
    // ------------------------------------------------------------------

    /** Role names currently assigned to the user (via Spatie pivot). */
    public static function userRoles(User $user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            try {
                return $user->getRoleNames()->all();
            } catch (\Throwable) {
                // Fall through to the pivot query.
            }
        }

        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return [];
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->getKey())
            ->pluck('roles.name')
            ->all();
    }

    public static function userHasRole(User $user, string|array $roles): bool
    {
        if (method_exists($user, 'hasRole')) {
            try {
                return $user->hasRole($roles);
            } catch (\Throwable) {
                // Fall through to the pivot query.
            }
        }

        return count(array_intersect((array) $roles, static::userRoles($user))) > 0;
    }

    /** Admin or Super Admin (either may use the /admin panel). */
    public static function isAdmin(?User $user): bool
    {
        return $user !== null && static::userHasRole($user, ['admin', 'super-admin']);
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user !== null && static::userHasRole($user, 'super-admin');
    }

    /** A15: knowing the /admin URL is never enough — panel access needs a role. */
    public static function canAccessAdminPanel(?User $user): bool
    {
        return static::isAdmin($user);
    }

    public static function canApproveBooks(?User $user): bool
    {
        return static::isAdmin($user);
    }

    public static function canApproveAuthors(?User $user): bool
    {
        return static::isAdmin($user);
    }

    public static function canModerateReviews(?User $user): bool
    {
        // Spec §1.2: support may moderate too, but Support's Filament
        // access is Phase 3 — in Phase 1 the panel is admin/super-admin only.
        return static::isAdmin($user);
    }

    public static function canManageUsers(?User $user): bool
    {
        return static::isAdmin($user);
    }

    public static function canManageCategories(?User $user): bool
    {
        return static::isAdmin($user);
    }

    /** Roles & payouts are Super Admin only (roles doc). Admin can never do these. */
    public static function canManageRoles(?User $user): bool
    {
        return static::isSuperAdmin($user);
    }

    public static function canApprovePayouts(?User $user): bool
    {
        return false; // No payout flow exists in Phase 1; and Admin never may.
    }

    /**
     * Roles the acting user may grant/revoke. Admin may never grant or
     * remove `admin` / `super-admin` (roles doc: Admin cannot create or
     * remove Admins; Super Admin is the only role manager).
     */
    public static function assignableRolesFor(?User $user): array
    {
        if (! Schema::hasTable('roles')) {
            return [];
        }

        $all = Role::query()->pluck('name')->all();

        if (static::isSuperAdmin($user)) {
            return $all;
        }

        return array_values(array_diff($all, ['admin', 'super-admin']));
    }

    public static function assignRole(User $user, string $role): void
    {
        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role);

            return;
        }

        $roleModel = Role::query()->where('name', $role)->first();

        if (! $roleModel) {
            throw new InvalidArgumentException("Role [{$role}] does not exist.");
        }

        DB::table('model_has_roles')->updateOrInsert([
            'role_id' => $roleModel->getKey(),
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
    }

    public static function removeRole(User $user, string $role): void
    {
        if (method_exists($user, 'removeRole')) {
            $user->removeRole($role);

            return;
        }

        $roleModel = Role::query()->where('name', $role)->first();

        if (! $roleModel) {
            return;
        }

        DB::table('model_has_roles')
            ->where('role_id', $roleModel->getKey())
            ->where('model_type', User::class)
            ->where('model_id', $user->getKey())
            ->delete();
    }

    /**
     * Sync a user's roles from a Filament form, preserving every role the
     * actor is not allowed to manage (so an Admin can never strip — or
     * grant — `admin` / `super-admin`). The single Super Admin account
     * can never lose its role this way (roles doc: exactly one).
     */
    public static function syncRolesForUser(User $user, array $requested, ?User $actor): void
    {
        $allowed = static::assignableRolesFor($actor);
        $current = static::userRoles($user);

        $keep = array_values(array_diff($current, $allowed));
        $grant = array_values(array_intersect($requested, $allowed));
        $final = array_values(array_unique(array_merge($keep, $grant)));

        if (in_array('super-admin', $current, true)
            && ! in_array('super-admin', $final, true)
            && static::countSuperAdmins() <= 1
        ) {
            $final[] = 'super-admin';
        }

        foreach (array_diff($current, $final) as $role) {
            static::removeRole($user, $role);
        }

        foreach (array_diff($final, $current) as $role) {
            static::assignRole($user, $role);
        }
    }

    public static function countSuperAdmins(): int
    {
        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return 0;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super-admin')
            ->where('model_has_roles.model_type', User::class)
            ->count();
    }

    // ------------------------------------------------------------------
    // Book approval (spec §1.5 statuses: draft / in_review / published /
    // rejected / taken_down). No soft delete — status carries the state.
    // ------------------------------------------------------------------

    public function approveBook(Book $book, ?User $actor = null): void
    {
        $this->authorizeActor($actor, static::canApproveBooks($actor), 'books.approve');

        if (! in_array($book->status, ['draft', 'in_review', 'rejected'], true)) {
            throw new InvalidArgumentException(
                "Book [{$book->id}] with status [{$book->status}] cannot be approved."
            );
        }

        $book->status = 'published';
        $book->published_at ??= now();
        $book->save();
    }

    public function rejectBook(Book $book, ?string $reason = null, ?User $actor = null): void
    {
        $this->authorizeActor($actor, static::canApproveBooks($actor), 'books.reject');

        if (! in_array($book->status, ['draft', 'in_review', 'published'], true)) {
            throw new InvalidArgumentException(
                "Book [{$book->id}] with status [{$book->status}] cannot be rejected."
            );
        }

        // Phase 1 has no rejection_reason column on books (spec §1.5);
        // the reason is logged, not silently dropped.
        if ($reason !== null && $reason !== '') {
            Log::info('Book rejected', ['book_id' => $book->id, 'reason' => $reason]);
        }

        // Edge case 5 (roles doc): buyers keep access; new sales stop —
        // that is enforced by the catalogue only listing `published` books.
        $book->status = 'rejected';
        $book->save();
    }

    // ------------------------------------------------------------------
    // Author approval (spec §1.3 statuses: pending / approved /
    // rejected / suspended). Approval grants the `author` add-on role
    // to the linked account (roles doc: Author is an add-on role).
    // ------------------------------------------------------------------

    public function approveAuthor(Author $author, ?User $actor = null): void
    {
        $this->authorizeActor($actor, static::canApproveAuthors($actor), 'authors.approve');

        if (! in_array($author->status, ['pending', 'rejected'], true)) {
            throw new InvalidArgumentException(
                "Author [{$author->id}] with status [{$author->status}] cannot be approved."
            );
        }

        $author->status = 'approved';
        $author->save();

        if ($author->user_id !== null && Schema::hasTable('roles')) {
            $user = User::query()->find($author->user_id);

            if ($user && Role::query()->where('name', 'author')->exists()) {
                static::assignRole($user, 'author');
            }
        }
    }

    public function rejectAuthor(Author $author, ?string $reason = null, ?User $actor = null): void
    {
        $this->authorizeActor($actor, static::canApproveAuthors($actor), 'authors.reject');

        if (! in_array($author->status, ['pending', 'approved'], true)) {
            throw new InvalidArgumentException(
                "Author [{$author->id}] with status [{$author->status}] cannot be rejected."
            );
        }

        if ($reason !== null && $reason !== '') {
            Log::info('Author rejected', ['author_id' => $author->id, 'reason' => $reason]);
        }

        $author->status = 'rejected';
        $author->save();

        // A previously approved author loses the add-on role on rejection.
        if ($author->user_id !== null && Schema::hasTable('roles')) {
            $user = User::query()->find($author->user_id);

            if ($user) {
                static::removeRole($user, 'author');
            }
        }
    }

    // ------------------------------------------------------------------
    // Review moderation (spec §1.7 statuses: pending / approved /
    // rejected / hidden). Approving refreshes the book's cached rating
    // aggregates via Review::saved (Builder B, spec A8).
    // ------------------------------------------------------------------

    public function moderateReview(Review $review, string $status, ?User $actor = null): void
    {
        $this->authorizeActor($actor, static::canModerateReviews($actor), 'reviews.moderate');

        if (! in_array($status, ['pending', 'approved', 'rejected', 'hidden'], true)) {
            throw new InvalidArgumentException("Invalid review status [{$status}].");
        }

        $allowed = [
            'pending' => ['approved', 'rejected', 'hidden'],
            'approved' => ['hidden', 'rejected'],
            'hidden' => ['approved', 'rejected'],
            'rejected' => ['approved', 'hidden'],
        ];

        if ($review->status === $status) {
            return; // Idempotent no-op.
        }

        if (! in_array($status, $allowed[$review->status] ?? [], true)) {
            throw new InvalidArgumentException(
                "Review [{$review->id}] cannot move from [{$review->status}] to [{$status}]."
            );
        }

        $review->status = $status;
        $review->save();
    }

    // ------------------------------------------------------------------

    private function authorizeActor(?User $actor, bool $allowed, string $permission): void
    {
        if ($actor === null) {
            return; // Service used without an actor (seed / console): no check.
        }

        if (! $allowed) {
            throw new AuthorizationException("User lacks permission [{$permission}].");
        }
    }
}
