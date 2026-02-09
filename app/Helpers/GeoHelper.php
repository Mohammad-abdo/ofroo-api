<?php

namespace App\Helpers;

class GeoHelper
{
    /**
     * Calculate distance between two points using Haversine formula
     * Returns distance in kilometers
     */
    public static function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Earth radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Calculate distance in meters
     */
    public static function calculateDistanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return self::calculateDistance($lat1, $lng1, $lat2, $lng2) * 1000;
    }

    /**
     * Get SQL query for nearby locations (Haversine)
     * Returns distance in kilometers
     */
    public static function getNearbyQuery(float $lat, float $lng, float $maxDistanceKm = 10): string
    {
        return "(
            6371 * acos(
                cos(radians({$lat}))
                * cos(radians(lat))
                * cos(radians(lng) - radians({$lng}))
                + sin(radians({$lat}))
                * sin(radians(lat))
            )
        ) <= {$maxDistanceKm}";
    }
}

