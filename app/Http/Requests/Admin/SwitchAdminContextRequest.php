<?php

namespace App\Http\Requests\Admin;

use App\Domain\Platform\PlatformOrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;

class SwitchAdminContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->organizations()->exists()
            || app(PlatformOrganizationAccess::class)->isActiveFor($user));
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Faol filialni tanlang.',
            'store_id.integer' => 'Tanlangan filial noto‘g‘ri.',
        ];
    }
}
