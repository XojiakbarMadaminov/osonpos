<?php

namespace App\Filament\Admin\Resources\Devices\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Devices\DeviceResource;
use App\Models\Device;
use App\Models\Store;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    protected static ?string $title = 'Yangi qurilma';

    protected function handleRecordCreation(array $data): Model
    {
        $organization = app(TenantContext::class)->requireCurrent();
        $store = Store::query()
            ->where('organization_id', $organization->getKey())
            ->where('is_active', true)
            ->findOrFail($data['store_id']);

        abort_unless(app(StoreAccess::class)->allows(request()->user(), $store), 403);

        $code = mb_strtoupper(trim($data['code']));
        if (Device::query()->forTenant($organization)->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'data.code' => 'Bu qurilma kodi tashkilotda allaqachon mavjud.',
            ]);
        }

        $device = new Device([
            'name' => $data['name'],
            'code' => $code,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $device->organization()->associate($organization);
        $device->store()->associate($store);
        $device->save();

        return $device;
    }
}
