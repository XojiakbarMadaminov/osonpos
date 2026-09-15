<?php

namespace App\Filament\Admin\Resources\Devices\Pages;

use App\Actions\Devices\SetDeviceActivationCode;
use App\Filament\Admin\Resources\Devices\DeviceResource;
use App\Models\Device;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    protected static ?string $title = 'Yangi qurilma';

    protected function handleRecordCreation(array $data): Model
    {
        $organization = app(TenantContext::class)->requireCurrent();
        $store = app(StoreContext::class)->requireCurrent();

        do {
            $code = 'POS-'.mb_strtoupper(Str::random(8));
        } while (Device::query()->forTenant($organization)->where('code', $code)->exists());

        $device = new Device([
            'name' => $data['name'],
            'code' => $code,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $device->organization()->associate($organization);
        $device->store()->associate($store);
        $device->save();

        app(SetDeviceActivationCode::class)->execute($device, $data['activation_code']);

        return $device;
    }
}
