<?php

namespace OiLab\OiLaravelDrive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
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
        $maxKilobytes = max(1, intdiv((int) config('oi-laravel-drive.files.max_bytes'), 1024));

        return [
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
            'drive_id' => ['nullable', 'uuid'],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'owner_id' => ['nullable'],
            'created_at' => ['nullable', 'date'],
            'props' => ['nullable', 'array'],
        ];
    }
}
