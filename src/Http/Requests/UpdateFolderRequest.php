<?php

namespace OiLab\OiLaravelDrive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `parent_id`: null moves the folder to the root; leave it out to keep it.
 * `owner_id`: null removes the owner; leave it out to keep it.
 */
class UpdateFolderRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            'owner_id' => ['nullable'],
            'created_at' => ['nullable', 'date'],
            'props' => ['nullable', 'array'],
        ];
    }
}
