<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\AdminService;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = AdminService::userRoles($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // M4 (round 2): validate the requested roles BEFORE the record
        // is saved, so a guard failure surfaces as a form error on
        // `roles` and Filament never shows a false "Saved" toast.
        // The same check runs again inside syncRolesForUser() (afterSave)
        // as a second line of defence.
        AdminService::validateRoleSync(
            $this->record,
            $data['roles'] ?? AdminService::userRoles($this->record),
            auth()->user(),
            // The form field's state path — the error must be keyed
            // here for Filament to render it on the roles checkbox list.
            'data.roles',
        );

        return $data;
    }

    protected function afterSave(): void
    {
        AdminService::syncRolesForUser(
            $this->record,
            $this->data['roles'] ?? AdminService::userRoles($this->record),
            auth()->user(),
        );
    }
}
