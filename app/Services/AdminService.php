<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\AuthorApplication;
use App\Models\Book;
use App\Models\Order;
use App\Models\Report;
use App\Models\Review;
use App\Models\TakedownRequest;
use App\Models\User;
use App\Notifications\AuthorNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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

    /** A15: knowing the /admin URL is never enough — panel access needs a role.
     *  Phase 3: Support also enters the panel (tickets + FAQs only — the
     *  resources gate everything else). Readers and authors never do. */
    public static function canAccessAdminPanel(?User $user): bool
    {
        return $user !== null && static::userHasRole($user, ['admin', 'super-admin', 'support']);
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
        // Phase 3: PayoutResource exists; approval/release is Super Admin only.
        return static::isSuperAdmin($user);
    }

    /** Support may hide a reported book/review; only Admin+ may decide. */
    public static function canHideBook(?User $user): bool
    {
        return $user !== null && static::userHasRole($user, ['admin', 'super-admin', 'support']);
    }

    public static function canDecideReports(?User $user): bool
    {
        return static::isAdmin($user);
    }

    public static function canDecideTakedowns(?User $user): bool
    {
        return static::isAdmin($user);
    }

    /** Suspend / ban / restore = Super Admin only (roles doc, spec §3.2). */
    public static function canSuspendUsers(?User $user): bool
    {
        return static::isSuperAdmin($user);
    }

    /**
     * Roles the acting user may grant/revoke. Admin may never grant or
     * remove `admin` / `super-admin` (roles doc: Admin cannot create or
     * remove Admins; Super Admin is the only role manager). The `author`
     * role is never assignable by hand — it comes only via application
     * approval (spec §4.3) — so it is excluded here, which also keeps
     * syncRolesForUser() from ever stripping it from an approved author.
     */
    public static function assignableRolesFor(?User $user): array
    {
        if (! Schema::hasTable('roles')) {
            return [];
        }

        $all = Role::query()->pluck('name')->all();

        if (static::isSuperAdmin($user)) {
            return array_values(array_diff($all, ['author']));
        }

        return array_values(array_diff($all, ['admin', 'super-admin', 'author']));
    }

    public static function assignRole(User $user, string $role): void
    {
        // Phase 3 (spec §4): exactly ONE Super Admin account, never shared.
        // A second Super Admin cannot be created. Surfaced as a friendly
        // validation error (M4) instead of a raw 500 — Filament shows
        // ValidationException messages on the form.
        if ($role === 'super-admin'
            && ! static::userHasRole($user, 'super-admin')
            && static::countSuperAdmins() >= 1
        ) {
            throw ValidationException::withMessages([
                'roles' => __('admin.users_extra.only_one_super_admin'),
            ]);
        }

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
        // Phase 3 (spec §4): the sole Super Admin can never lose the role.
        // Friendly validation error (M4), not a raw 500.
        if ($role === 'super-admin'
            && static::userHasRole($user, 'super-admin')
            && static::countSuperAdmins() <= 1
        ) {
            throw ValidationException::withMessages([
                'roles' => __('admin.users_extra.sole_super_admin_keep_role'),
            ]);
        }

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
     * Validate a requested role sync BEFORE anything is written (M4,
     * round 2). Throws a friendly ValidationException — keyed
     * `$errorKey` (default `roles`; the Filament edit page passes
     * `data.roles` so the error shows on the field) so Filament shows
     * it as a form error — instead of silently pretending success:
     *  (a) granting `super-admin` to anyone else while one exists;
     *  (b) stripping the sole Super Admin of the role.
     * Roles the actor may not manage are ignored here (they are kept
     * as-is by syncRolesForUser()), so an Admin can never trip either
     * guard by accident.
     */
    public static function validateRoleSync(User $user, array $requested, ?User $actor, string $errorKey = 'roles'): void
    {
        $allowed = static::assignableRolesFor($actor);
        $current = static::userRoles($user);

        $keep = array_values(array_diff($current, $allowed));
        $grant = array_values(array_intersect($requested, $allowed));
        $final = array_values(array_unique(array_merge($keep, $grant)));

        if (in_array('super-admin', $grant, true)
            && ! static::userHasRole($user, 'super-admin')
            && static::countSuperAdmins() >= 1
        ) {
            throw ValidationException::withMessages([
                $errorKey => __('admin.users_extra.only_one_super_admin'),
            ]);
        }

        if (in_array('super-admin', $current, true)
            && ! in_array('super-admin', $final, true)
            && static::countSuperAdmins() <= 1
        ) {
            throw ValidationException::withMessages([
                $errorKey => __('admin.users_extra.sole_super_admin_keep_role'),
            ]);
        }
    }

    /**
     * Sync a user's roles from a Filament form, preserving every role the
     * actor is not allowed to manage (so an Admin can never strip — or
     * grant — `admin` / `super-admin`). The single Super Admin account
     * can never lose its role this way (roles doc: exactly one).
     * A failed guard throws (see validateRoleSync()) — the old silent
     * re-add of the sole-SA role is gone: a refused sync must never
     * silently pretend it succeeded.
     */
    public static function syncRolesForUser(User $user, array $requested, ?User $actor): void
    {
        static::validateRoleSync($user, $requested, $actor);

        $allowed = static::assignableRolesFor($actor);
        $current = static::userRoles($user);

        $keep = array_values(array_diff($current, $allowed));
        $grant = array_values(array_intersect($requested, $allowed));
        $final = array_values(array_unique(array_merge($keep, $grant)));

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

        // M2: a taken-down book can be restored via approval — the same
        // audit (book.approved) covers the restore; no separate path.
        if (! in_array($book->status, ['draft', 'in_review', 'rejected', 'taken_down'], true)) {
            throw new InvalidArgumentException(
                "Book [{$book->id}] with status [{$book->status}] cannot be approved."
            );
        }

        $book->status = 'published';
        $book->published_at ??= now();
        $book->save();

        static::writeAudit($actor, 'book.approved', 'books', $book->getKey(), [
            'message' => 'Approved book: '.$book->title,
        ]);

        $this->notifyBookAuthor($book, 'book_approved', ['book_id' => $book->id, 'title' => $book->title]);
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

        static::writeAudit($actor, 'book.rejected', 'books', $book->getKey(), [
            'message' => 'Rejected book: '.$book->title,
            'reason' => $reason,
        ]);

        $this->notifyBookAuthor($book, 'book_rejected', [
            'book_id' => $book->id, 'title' => $book->title, 'reason' => $reason,
        ]);
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

        static::writeAudit($actor, 'author.approved', 'authors', $author->getKey(), [
            'message' => 'Approved author: '.$author->name,
        ]);

        // Minor: the Filament approve path bypasses
        // AuthorService::reviewApplication(), so the linked application row
        // must be closed here too — otherwise it stays `submitted` forever.
        $this->closeLinkedApplication($author, 'approved', $actor);

        static::notifyUser($author->user_id !== null ? User::query()->find($author->user_id) : null,
            'application_approved', ['author_id' => $author->id]);

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

        static::writeAudit($actor, 'author.rejected', 'authors', $author->getKey(), [
            'message' => 'Rejected author: '.$author->name,
            'reason' => $reason,
        ]);

        // Minor: close the linked application row here too (see approveAuthor).
        $this->closeLinkedApplication($author, 'rejected', $actor, $reason);

        static::notifyUser($author->user_id !== null ? User::query()->find($author->user_id) : null,
            'application_rejected', ['author_id' => $author->id, 'reason' => $reason]);

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

        $from = $review->status;
        $review->status = $status;
        $review->save();

        static::writeAudit($actor, 'review.moderated', 'reviews', $review->getKey(), [
            'message' => "Review {$review->getKey()} moderated to [{$status}]",
            'from' => $from,
            'to' => $status,
        ]);

        static::notifyUser($review->user, 'review_moderated', [
            'review_id' => $review->id,
            'book_id' => $review->book_id,
            'status' => $status,
        ]);
    }

    // ------------------------------------------------------------------
    // Phase 3 fix — in-app notifications (spec §6, database channel
    // only). Never carries a full bKash number or a TrxID — only ids,
    // titles and plain amounts. Notification settings are honoured by
    // the list UI; the service always records the event.
    // ------------------------------------------------------------------

    public static function notifyUser(?User $user, string $type, array $data = []): void
    {
        if ($user === null || ! Schema::hasTable('notifications')) {
            return;
        }

        $user->notify(new AuthorNotification($type, $data));
    }

    /** Notify the user who owns the book's author row (if any). */
    protected function notifyBookAuthor(Book $book, string $type, array $data = []): void
    {
        if ($book->author_id === null) {
            return;
        }

        $author = Author::query()->find($book->author_id);

        if ($author === null || $author->user_id === null) {
            return;
        }

        static::notifyUser(User::query()->find($author->user_id), $type, $data);
    }

    /**
     * Close the linked pending author application when an author is
     * approved/rejected outside AuthorService::reviewApplication()
     * (the Filament path). Idempotent: a row already decided is untouched.
     */
    protected function closeLinkedApplication(Author $author, string $status, ?User $actor = null, ?string $note = null): void
    {
        if (! Schema::hasTable('author_applications') || $author->user_id === null) {
            return;
        }

        $application = AuthorApplication::query()
            ->where('user_id', $author->user_id)
            ->whereIn('status', AuthorApplication::ACTIVE_STATUSES)
            ->latest()
            ->first();

        if ($application === null) {
            return;
        }

        $application->status = $status;
        $application->reviewed_by = $actor?->getKey();
        $application->review_note = $note;
        $application->reviewed_at = now();
        $application->save();
    }

    // ------------------------------------------------------------------
    // Phase 3 — audit log (spec §4.5, §0.8). Every admin action writes
    // one append-only row. Safe to call before the table exists (Phase 1
    // code paths / console): then it is a no-op.
    // ------------------------------------------------------------------

    public static function writeAudit(
        ?User $actor,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $details = [],
    ): ?AuditLog {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        $ip = null;
        try {
            $ip = request()->ip();
        } catch (\Throwable) {
            $ip = null;
        }

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor ? (static::userRoles($actor)[0] ?? null) : null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Phase 3 — hide / unhide a book (spec §5.2). Hidden books leave the
    // catalogue/search/book page; previous buyers KEEP access — canRead()
    // is never changed here.
    // ------------------------------------------------------------------

    public function hideBook(Book $book, ?User $actor = null, ?string $reason = null): void
    {
        $this->authorizeActor($actor, static::canHideBook($actor), 'books.hide');

        if ($book->hidden_at !== null) {
            return; // Idempotent no-op.
        }

        $book->hidden_at = now();
        $book->save();

        static::writeAudit($actor, 'book.hidden', 'books', $book->getKey(), [
            'message' => 'Hidden book: '.$book->title,
            'reason' => $reason,
        ]);
    }

    public function unhideBook(Book $book, ?User $actor = null): void
    {
        $this->authorizeActor($actor, static::canApproveBooks($actor), 'books.unhide');

        if ($book->hidden_at === null) {
            return; // Idempotent no-op.
        }

        $book->hidden_at = null;
        $book->save();

        static::writeAudit($actor, 'book.unhidden', 'books', $book->getKey(), [
            'message' => 'Unhidden book: '.$book->title,
        ]);
    }

    // ------------------------------------------------------------------
    // Phase 3 — report decisions (spec §5.2). Admin only; Support hides,
    // Admin decides. Dismiss: the book is visible again (hidden_at NULL).
    // Action: the book is taken down, or the review is rejected.
    // ------------------------------------------------------------------

    public function decideReport(Report $report, string $decision, ?User $actor = null, ?string $note = null): void
    {
        $this->authorizeActor($actor, static::canDecideReports($actor), 'reports.decide');

        // Reload: a freshly created model may not carry the DB default status.
        $report = $report->fresh() ?? $report;

        if (! in_array($decision, ['dismiss', 'action'], true)) {
            throw new InvalidArgumentException("Invalid report decision [{$decision}].");
        }

        if (! in_array($report->status, ['open', 'reviewing'], true)) {
            throw new InvalidArgumentException(
                "Report [{$report->id}] with status [{$report->status}] is already decided."
            );
        }

        if ($decision === 'dismiss') {
            $report->status = 'dismissed';

            if ($report->reportable_type === Report::TYPE_BOOK) {
                $book = Book::query()->find($report->reportable_id);
                if ($book && $book->hidden_at !== null) {
                    $book->hidden_at = null;
                    $book->save();
                }
            }
        } else {
            $report->status = 'action_taken';

            if ($report->reportable_type === Report::TYPE_BOOK) {
                $book = Book::query()->find($report->reportable_id);
                if ($book && $book->status !== 'taken_down') {
                    $book->status = 'taken_down';
                    $book->save();
                }
            } elseif ($report->reportable_type === Report::TYPE_REVIEW) {
                $review = Review::query()->find($report->reportable_id);
                if ($review && $review->status !== 'rejected') {
                    $review->status = 'rejected';
                    $review->save();
                }
            }
        }

        $report->reviewed_by = $actor?->getKey();
        $report->review_note = $note;
        $report->save();

        static::writeAudit($actor, 'report.decided', 'reports', $report->getKey(), [
            'message' => "Report {$report->getKey()} decided: {$decision}",
            'decision' => $decision,
            'note' => $note,
        ]);
    }

    // ------------------------------------------------------------------
    // Phase 3 — takedown decisions (spec §5.3). Admin only.
    // take_down: book taken down while under review.
    // extend_proof: 7 more days of proof time (deadline + 7 days).
    // reject: claim rejected, claimant told why (note).
    // close: claim closed — commissioned work verified.
    // ------------------------------------------------------------------

    public function decideTakedown(TakedownRequest $request, string $decision, ?User $actor = null, ?string $note = null): void
    {
        $this->authorizeActor($actor, static::canDecideTakedowns($actor), 'takedowns.decide');

        // Reload: a freshly created model may not carry the DB default status.
        $request = $request->fresh() ?? $request;

        if (! in_array($decision, ['take_down', 'extend_proof', 'reject', 'close'], true)) {
            throw new InvalidArgumentException("Invalid takedown decision [{$decision}].");
        }

        if (! in_array($request->status, ['open', 'awaiting_proof'], true)) {
            throw new InvalidArgumentException(
                "Takedown request [{$request->id}] with status [{$request->status}] is already decided."
            );
        }

        $request->reviewed_by = $actor?->getKey();
        $request->decision_note = $note;

        match ($decision) {
            'take_down' => (function () use ($request) {
                $request->status = 'action_taken';
                $book = $request->book;
                if ($book && $book->status !== 'taken_down') {
                    $book->status = 'taken_down';
                    $book->save();
                }
            })(),
            'extend_proof' => (function () use ($request) {
                $request->status = 'awaiting_proof';
                $request->deadline_at = ($request->deadline_at ?? now())->addDays(7);
            })(),
            'reject' => (function () use ($request) {
                $request->status = 'rejected';
            })(),
            'close' => (function () use ($request) {
                $request->status = 'closed';
            })(),
        };

        $request->save();

        static::writeAudit($actor, 'takedown.decided', 'takedown_requests', $request->getKey(), [
            'message' => "Takedown request {$request->getKey()} decided: {$decision}",
            'decision' => $decision,
            'note' => $note,
        ]);
    }

    // ------------------------------------------------------------------
    // Phase 3 — suspend / ban / restore (spec §3.2, §5.4). SUPER ADMIN
    // ONLY — enforced here and in UserPolicy::suspend/ban. The owner
    // account itself can never be suspended or banned.
    //
    // Suspend: login blocked (AuthService checks blockedStatuses);
    // an author's books stop selling (new sales); previous buyers keep
    // access; accrued earnings are still paid after review (PayoutService
    // only flags them — it never withholds on its own).
    // Ban: login + Premium blocked; no refund unless Super Admin decides
    // otherwise — the subscription is cancelled WITHOUT refund.
    // ------------------------------------------------------------------

    public function suspendUser(User $target, ?User $actor = null, ?string $reason = null): void
    {
        $this->authorizeActor($actor, static::canSuspendUsers($actor), 'users.suspend');
        $this->guardNotSuperAdminTarget($target);

        if ($target->status === User::STATUS_SUSPENDED) {
            return; // Idempotent no-op.
        }

        $target->status = User::STATUS_SUSPENDED;
        $target->save();

        static::writeAudit($actor, 'user.suspended', 'users', $target->getKey(), [
            'message' => 'Suspended user: '.$target->name,
            'reason' => $reason,
        ]);
    }

    public function banUser(User $target, ?User $actor = null, ?string $reason = null): void
    {
        $this->authorizeActor($actor, static::canSuspendUsers($actor), 'users.ban');
        $this->guardNotSuperAdminTarget($target);

        if ($target->status === User::STATUS_BANNED) {
            return; // Idempotent no-op.
        }

        $target->status = User::STATUS_BANNED;
        $target->save();

        // Premium stops with the ban — cancelled, no refund (roles doc
        // edge case 7; BillingService::cancelSubscription never refunds).
        if (class_exists(BillingService::class)) {
            $billing = app(BillingService::class);
            $subscription = $billing->currentSubscription($target);
            if ($subscription !== null) {
                $billing->cancelSubscription($subscription, 'Account banned');
            }
        }

        static::writeAudit($actor, 'user.banned', 'users', $target->getKey(), [
            'message' => 'Banned user: '.$target->name,
            'reason' => $reason,
        ]);
    }

    public function restoreUser(User $target, ?User $actor = null, ?string $reason = null): void
    {
        $this->authorizeActor($actor, static::canSuspendUsers($actor), 'users.restore');

        if (! in_array($target->status, [User::STATUS_SUSPENDED, User::STATUS_BANNED], true)) {
            throw new InvalidArgumentException(
                "User [{$target->id}] with status [{$target->status}] is not suspended or banned."
            );
        }

        $target->status = User::STATUS_ACTIVE;
        $target->save();

        static::writeAudit($actor, 'user.restored', 'users', $target->getKey(), [
            'message' => 'Restored user: '.$target->name,
            'reason' => $reason,
        ]);
    }

    private function guardNotSuperAdminTarget(User $target): void
    {
        if (static::userHasRole($target, 'super-admin')) {
            throw new InvalidArgumentException('The Super Admin account cannot be suspended or banned.');
        }
    }

    // ------------------------------------------------------------------
    // Phase 3 — refund queue (spec §3.4). Admin may take a request
    // "under review" and comment; approve / reject is Super Admin only.
    // Approval itself lives in BillingService::refundOrder().
    // ------------------------------------------------------------------

    public function markRefundUnderReview(Order $order, ?User $actor = null): void
    {
        $this->authorizeActor($actor, static::isAdmin($actor), 'orders.refund_review');

        if ($order->refund_status !== 'requested') {
            throw new InvalidArgumentException(
                "Order [{$order->id}] with refund_status [{$order->refund_status}] cannot be marked under review."
            );
        }

        $order->refund_status = 'under_review';
        $order->save();

        static::writeAudit($actor, 'refund.under_review', 'orders', $order->getKey(), [
            'message' => 'Refund request under review: '.$order->order_no,
        ]);
    }

    public function rejectRefund(Order $order, ?User $actor = null, ?string $note = null): void
    {
        $this->authorizeActor($actor, static::isSuperAdmin($actor), 'orders.refund_reject');

        if (! in_array($order->refund_status, ['requested', 'under_review'], true)) {
            throw new InvalidArgumentException(
                "Order [{$order->id}] with refund_status [{$order->refund_status}] cannot be rejected."
            );
        }

        $order->refund_status = 'rejected';
        $order->save();

        static::writeAudit($actor, 'refund.rejected', 'orders', $order->getKey(), [
            'message' => 'Refund rejected: '.$order->order_no,
            'note' => $note,
        ]);
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
