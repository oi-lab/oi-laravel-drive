<?php

namespace OiLab\OiLaravelDrive\Enums;

/**
 * An upload by link: opened (`Awaiting`), turned into a drive file
 * (`Completed`), expired without a file (`Expired`), or refused (`Failed`).
 */
enum DriveUploadStatus: string
{
    case Awaiting = 'awaiting';
    case Completed = 'completed';
    case Expired = 'expired';
    case Failed = 'failed';
}
