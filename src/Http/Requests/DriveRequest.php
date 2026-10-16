<?php

namespace OiLab\OiLaravelDrive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriveRequest extends FormRequest
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
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'owner_id' => ['nullable'],
            'props' => ['nullable', 'array'],
        ];
    }
}
