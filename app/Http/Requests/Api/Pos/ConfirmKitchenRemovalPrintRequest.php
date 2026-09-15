<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmKitchenRemovalPrintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'removal_ids' => ['required', 'array', 'min:1'],
            'removal_ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
