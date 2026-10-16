<?php

namespace OiLab\OiLaravelDrive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachFoldersRequest extends FormRequest
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
            'folder_ids' => ['required', 'array', 'min:1'],
            'folder_ids.*' => ['uuid'],
        ];
    }
}
