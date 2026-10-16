<?php

namespace OiLab\OiLaravelDrive\Data;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * The owner of a drive, a folder or a file.
 */
class UserSummaryData extends Data
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $name,
    ) {}

    public static function fromUser(Model $user): self
    {
        return new self(id: $user->getKey(), name: self::labelOf($user));
    }

    protected static function labelOf(Model $user): string
    {
        $attribute = config('oi-laravel-drive.user_name_attribute', 'name');
        $label = $attribute ? trim((string) $user->getAttribute($attribute)) : '';

        if ($label === '') {
            $label = trim($user->getAttribute('firstname').' '.$user->getAttribute('lastname'));
        }

        if ($label === '') {
            $label = (string) $user->getAttribute('email');
        }

        return $label !== '' ? $label : '#'.$user->getKey();
    }
}
