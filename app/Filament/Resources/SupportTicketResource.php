<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Builder C — adminSupport / support / supportEscalations (spec §3.5).
 *
 * One resource, role-scoped: Support, Admin and Super Admin all see every
 * ticket and may reply; the reader side lives in SupportChatController +
 * the TicketThread Livewire component. Escalation sets escalated_to /
 * escalated_at and moves the ticket to `escalated`.
 */
class SupportTicketResource extends BaseAdminResource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'ticket_no';

    protected static function resourceAllowed(): bool
    {
        $user = static::currentUser();

        return $user !== null && AdminService::userHasRole($user, ['support', 'admin', 'super-admin']);
    }

    public static function getModelLabel(): string
    {
        return __('support.tickets.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support.tickets.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('support.tickets.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.manage');
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny(); // Tickets are opened by readers, not staff.
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function statusOptions(): array
    {
        return collect(['open', 'in_progress', 'waiting_reply', 'resolved', 'closed', 'escalated'])
            ->mapWithKeys(fn (string $s) => [$s => __('support.tickets.status_'.$s)])
            ->all();
    }

    public static function categoryOptions(): array
    {
        return collect(['payment', 'book', 'technical', 'account', 'other'])
            ->mapWithKeys(fn (string $s) => [$s => __('support.tickets.category_'.$s)])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('ticket_no')->label(__('support.tickets.ticket_no')),
            TextEntry::make('user.name')->label(__('support.tickets.user')),
            TextEntry::make('subject')->label(__('support.tickets.subject')),
            TextEntry::make('category')
                ->label(__('support.tickets.category'))
                ->formatStateUsing(fn (string $state) => __('support.tickets.category_'.$state)),
            TextEntry::make('status')
                ->label(__('support.tickets.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('support.tickets.status_'.$state)),
            TextEntry::make('priority')
                ->label(__('support.tickets.priority'))
                ->formatStateUsing(fn (string $state) => __('support.tickets.priority_'.$state)),
            TextEntry::make('assignee.name')
                ->label(__('support.tickets.assigned_to'))
                ->placeholder('—'),
            TextEntry::make('escalated_to')
                ->label(__('support.tickets.escalated_to'))
                ->formatStateUsing(fn (?string $state) => $state ? __('support.tickets.escalated_to_'.$state) : '—')
                ->visible(fn (SupportTicket $record) => $record->escalated_to !== null),
            TextEntry::make('escalated_at')
                ->label(__('support.tickets.escalated_at'))
                ->dateTime()
                ->visible(fn (SupportTicket $record) => $record->escalated_at !== null),
            RepeatableEntry::make('messages')
                ->label(__('support.tickets.messages'))
                ->state(fn (SupportTicket $record) => $record->messages->map(fn ($m) => [
                    'user' => $m->user?->name ?? '—',
                    'body' => $m->body,
                    'internal' => $m->is_internal ? __('support.tickets.internal_note') : '',
                    'created_at' => $m->created_at?->format('Y-m-d H:i') ?? '',
                ])->all())
                ->schema([
                    TextEntry::make('user')->label(__('support.tickets.user')),
                    TextEntry::make('body')->label(__('support.tickets.message')),
                    TextEntry::make('internal')->label(''),
                    TextEntry::make('created_at')->label(__('support.tickets.created_at')),
                ]),
            TextEntry::make('created_at')
                ->label(__('support.tickets.created_at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ticket_no')
                    ->label(__('support.tickets.ticket_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('support.tickets.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->label(__('support.tickets.subject'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('category')
                    ->label(__('support.tickets.category'))
                    ->formatStateUsing(fn (string $state) => __('support.tickets.category_'.$state)),
                TextColumn::make('status')
                    ->label(__('support.tickets.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'resolved', 'closed' => 'success',
                        'open' => 'danger',
                        'escalated' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('support.tickets.status_'.$state))
                    ->sortable(),
                TextColumn::make('priority')
                    ->label(__('support.tickets.priority'))
                    ->badge()
                    ->color(fn (string $state) => $state === 'urgent' ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state) => __('support.tickets.priority_'.$state)),
                TextColumn::make('escalated_to')
                    ->label(__('support.tickets.escalated_to'))
                    ->formatStateUsing(fn (?string $state) => $state ? __('support.tickets.escalated_to_'.$state) : '—'),
                TextColumn::make('created_at')
                    ->label(__('support.tickets.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('support.tickets.status'))
                    ->options(static::statusOptions()),
                SelectFilter::make('category')
                    ->label(__('support.tickets.category'))
                    ->options(static::categoryOptions()),
                SelectFilter::make('escalated_to')
                    ->label(__('support.tickets.escalated_to'))
                    ->options([
                        'admin' => __('support.tickets.escalated_to_admin'),
                        'super_admin' => __('support.tickets.escalated_to_super_admin'),
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    /**
     * Shared staff reply action (table + view page): writes a message and
     * moves the ticket to in_progress unless it is resolved/closed.
     */
    public static function replyAction(): Action
    {
        return Action::make('reply')
            ->label(__('support.tickets.reply'))
            ->icon('heroicon-o-chat-bubble-left')
            ->color('primary')
            ->form([
                Textarea::make('body')
                    ->label(__('support.tickets.message'))
                    ->required()
                    ->rows(4),
                Checkbox::make('is_internal')
                    ->label(__('support.tickets.internal_note')),
            ])
            ->action(function (SupportTicket $record, array $data) {
                $record->messages()->create([
                    'user_id' => auth()->id(),
                    'body' => $data['body'],
                    'is_internal' => (bool) ($data['is_internal'] ?? false),
                ]);

                if (in_array($record->status, ['open', 'waiting_reply', 'escalated'], true)) {
                    $record->status = 'in_progress';
                    $record->save();
                }

                AdminService::writeAudit(auth()->user(), 'ticket.replied', 'support_tickets', $record->getKey(), [
                    'message' => 'Replied to ticket '.$record->ticket_no,
                ]);
            })
            ->successNotificationTitle(__('support.tickets.replied'));
    }

    /** Shared escalation action: Support escalates up (spec §3.5). */
    public static function escalateAction(): Action
    {
        return Action::make('escalate')
            ->label(__('support.tickets.escalate'))
            ->icon('heroicon-o-arrow-up-circle')
            ->color('warning')
            ->form([
                Select::make('escalated_to')
                    ->label(__('support.tickets.escalate_to'))
                    ->options([
                        'admin' => __('support.tickets.escalated_to_admin'),
                        'super_admin' => __('support.tickets.escalated_to_super_admin'),
                    ])
                    ->required(),
            ])
            ->action(function (SupportTicket $record, array $data) {
                $record->status = 'escalated';
                $record->escalated_to = $data['escalated_to'];
                $record->escalated_at = now();
                $record->save();

                AdminService::writeAudit(auth()->user(), 'ticket.escalated', 'support_tickets', $record->getKey(), [
                    'message' => 'Escalated ticket '.$record->ticket_no.' to '.$data['escalated_to'],
                ]);
            })
            ->successNotificationTitle(__('support.tickets.escalated'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
