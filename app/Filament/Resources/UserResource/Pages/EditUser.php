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

    protected function afterSave(): void
    {
        AdminService::syncRolesForUser(
            $this->record,
            $this->data['roles'] ?? AdminService::userRoles($this->record),
            auth()->user(),
        );
    }
}
