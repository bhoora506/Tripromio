<?php

namespace App\Enums;

enum TravelStyle: string
{
    case Adventure   = 'adventure';
    case Backpacking = 'backpacking';
    case Budget      = 'budget';
    case Luxury      = 'luxury';
    case Relaxed     = 'relaxed';
    case RoadTrip    = 'road_trip';
    case Nature      = 'nature';
    case Cultural    = 'cultural';

    /**
     * Return all case values as a plain array for validation rules.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
