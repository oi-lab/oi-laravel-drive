<?php

namespace OiLab\OiLaravelDrive\Enums;

/**
 * What a drive entry points at: a folder or a file of oi-laravel-attachments.
 */
enum DriveEntryKind: string
{
    case Folder = 'folder';
    case File = 'file';
}
