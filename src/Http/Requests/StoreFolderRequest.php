<?php

namespace OiLab\OiLaravelDrive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'drive_id' => ['nullable', 'uuid'],
            'parent_id' => ['nullable', 'uuid'],
            'owner_id' => ['nullable'],
            'created_at' => ['nullable', 'date'],
            'props' => ['nullable', 'array'],
        ];
    }
}
