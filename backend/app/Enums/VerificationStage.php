<?php

namespace App\Enums;

enum VerificationStage: string
{
    case LOCATION = 'location';
    case QR = 'qr';
    case FACE = 'face';
    case TIME = 'time';

    public function label(): string
    {
        return match ($this) {
            self::LOCATION => 'Lokasi',
            self::QR => 'QR',
            self::FACE => 'Wajah',
            self::TIME => 'Waktu',
        };
    }
}
