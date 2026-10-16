<?php

namespace OiLab\OiLaravelDrive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `folder_ids`: exactly these folders (empty: the root); leave it out to keep
 * them. `owner_id`: null removes the owner; leave it out to keep it.
 */
class UpdateFileRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'folder_ids' => ['sometimes', 'nullable', 'array'],
            'folder_ids.*' => ['uuid'],
            'owner_id' => ['nullable'],
            'created_at' => ['nullable', 'date'],
            'props' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
