<?php

namespace App\Enums;

enum CaptureSource: string
{
    case Upload = 'upload';
    case MultiUpload = 'multi_upload';
    case Camera = 'camera';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Upload',
            self::MultiUpload => 'Multi-file upload',
            self::Camera => 'Camera capture',
        };
    }

    public static function forCount(int $count, bool $fromCamera = false): self
    {
        if ($fromCamera) {
            return self::Camera;
        }

        return $count > 1 ? self::MultiUpload : self::Upload;
    }
}
