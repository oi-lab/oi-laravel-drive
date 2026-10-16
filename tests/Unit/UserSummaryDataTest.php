<?php

use OiLab\OiLaravelDrive\Data\UserSummaryData;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;

it('labels an owner with the configured attribute, then the full name, then the email', function () {
    $user = new User(['id' => 7, 'name' => 'Ada Lovelace', 'firstname' => 'Ada', 'lastname' => 'Byron', 'email' => 'ada@example.com']);
    $user->id = 7;

    expect(UserSummaryData::fromUser($user)->name)->toBe('Ada Lovelace');

    config(['oi-laravel-drive.user_name_attribute' => 'nickname']);
    expect(UserSummaryData::fromUser($user)->name)->toBe('Ada Byron');

    $user->firstname = null;
    $user->lastname = null;
    expect(UserSummaryData::fromUser($user)->name)->toBe('ada@example.com');
});

it('gives every rule violation a stable code and an HTTP status', function () {
    expect(DriveException::uploadClosed())->rule->toBe('upload_closed')->status->toBe(409)
        ->and(DriveException::noDriveable())->status->toBe(403)
        ->and(DriveException::fileTooLarge(5 * 1_048_576)->getMessage())->toContain('5 Mo');
});
