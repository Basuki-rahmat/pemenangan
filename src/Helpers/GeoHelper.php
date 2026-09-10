<?php

declare(strict_types=1);

namespace App\Helpers;

class GeoHelper
{
    /**
     * Calculate distance between two points using Haversine formula
     * @return float Distance in meters
     */
    public static function haversineDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000; // Earth radius in meters

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if witness is within allowed radius of TPS
     */
    public static function isWithinRadius(
        float $witnessLat,
        float $witnessLon,
        float $tpsLat,
        float $tpsLon,
        float $allowedRadius = 500.0
    ): bool {
        $distance = self::haversineDistance($witnessLat, $witnessLon, $tpsLat, $tpsLon);
        return $distance <= $allowedRadius;
    }

    /**
     * Get distance description in Indonesian
     */
    public static function formatDistance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters) . ' meter';
        }
        return round($meters / 1000, 2) . ' km';
    }
}
