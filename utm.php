<?php
class OutOfRangeError extends Exception {}

class UTMConverter {
    private const K0 = 0.9996;
    private const E = 0.00669438;
    private const E2 = 0.00669438 * 0.00669438;
    private const E3 = 0.00669438 * 0.00669438 * 0.00669438;
    private const E_P2 = 0.00669438 / (1 - 0.00669438);
    private const R = 6378137;
    private const ZONE_LETTERS = "CDEFGHJKLMNPQRSTUVWXX";

    // Pre-calculated constants
    private const M1 = (1 - 0.00669438 / 4 - 3 * 0.00669438 * 0.00669438 / 64 - 5 * 0.00669438 * 0.00669438 * 0.00669438 / 256);
    private const M2 = (3 * 0.00669438 / 8 + 3 * 0.00669438 * 0.00669438 / 32 + 45 * 0.00669438 * 0.00669438 * 0.00669438 / 1024);
    private const M3 = (15 * 0.00669438 * 0.00669438 / 256 + 45 * 0.00669438 * 0.00669438 * 0.00669438 / 1024);
    private const M4 = (35 * 0.00669438 * 0.00669438 * 0.00669438 / 3072);

    private static $_E;
    private static $_E2;
    private static $_E3;
    private static $_E4;
    private static $_E5;
    private static $P2;
    private static $P3;
    private static $P4;
    private static $P5;

    public static function init() {
        $SQRT_E = sqrt(1 - self::E);
        self::$_E = (1 - $SQRT_E) / (1 + $SQRT_E);
        self::$_E2 = self::$_E * self::$_E;
        self::$_E3 = self::$_E2 * self::$_E;
        self::$_E4 = self::$_E3 * self::$_E;
        self::$_E5 = self::$_E4 * self::$_E;

        self::$P2 = (3 / 2 * self::$_E - 27 / 32 * self::$_E3 + 269 / 512 * self::$_E5);
        self::$P3 = (21 / 16 * self::$_E2 - 55 / 32 * self::$_E4);
        self::$P4 = (151 / 96 * self::$_E3 - 417 / 128 * self::$_E5);
        self::$P5 = (1097 / 512 * self::$_E4);
    }

    private static function inBounds($x, $lower, $upper, $upperStrict = false) {
        if ($upperStrict) {
            return $lower <= $x && $x < $upper;
        }
        return $lower <= $x && $x <= $upper;
    }

    private static function checkValidZoneLetter($zoneLetter) {
        $zoneLetter = strtoupper($zoneLetter);
        if (!('C' <= $zoneLetter && $zoneLetter <= 'X') || $zoneLetter == 'I' || $zoneLetter == 'O') {
            throw new OutOfRangeError('zone letter out of range (must be between C and X)');
        }
    }

    private static function checkValidZoneNumber($zoneNumber) {
        if (!(1 <= $zoneNumber && $zoneNumber <= 60)) {
            throw new OutOfRangeError('zone number out of range (must be between 1 and 60)');
        }
    }

    public static function checkValidZone($zoneNumber, $zoneLetter) {
        self::checkValidZoneNumber($zoneNumber);
        if ($zoneLetter) {
            self::checkValidZoneLetter($zoneLetter);
        }
    }

    private static function modAngle($value) {
        return fmod($value + M_PI, 2 * M_PI) - M_PI;
    }

    public static function toLatLon($easting, $northing, $zoneNumber, $zoneLetter = null, $northern = null, $strict = true) {
        if (!$zoneLetter && $northern === null) {
            throw new InvalidArgumentException('either zone_letter or northern needs to be set');
        } elseif ($zoneLetter && $northern !== null) {
            throw new InvalidArgumentException('set either zone_letter or northern, but not both');
        }

        if ($strict) {
            if (!self::inBounds($easting, 100000, 1000000, true)) {
                throw new OutOfRangeError('easting out of range (must be between 100,000 m and 999,999 m)');
            }
            if (!self::inBounds($northing, 0, 10000000)) {
                throw new OutOfRangeError('northing out of range (must be between 0 m and 10,000,000 m)');
            }
        }

        self::checkValidZone($zoneNumber, $zoneLetter);

        if ($zoneLetter) {
            $zoneLetter = strtoupper($zoneLetter);
            $northern = ($zoneLetter >= 'N');
        }

        $x = $easting - 500000;
        $y = $northern ? $northing : $northing - 10000000;

        $m = $y / self::K0;
        $mu = $m / (self::R * self::M1);

        $p_rad = ($mu +
                 self::$P2 * sin(2 * $mu) +
                 self::$P3 * sin(4 * $mu) +
                 self::$P4 * sin(6 * $mu) +
                 self::$P5 * sin(8 * $mu));

        $p_sin = sin($p_rad);
        $p_sin2 = $p_sin * $p_sin;

        $p_cos = cos($p_rad);

        $p_tan = $p_sin / $p_cos;
        $p_tan2 = $p_tan * $p_tan;
        $p_tan4 = $p_tan2 * $p_tan2;

        $ep_sin = 1 - self::E * $p_sin2;
        $ep_sin_sqrt = sqrt(1 - self::E * $p_sin2);

        $n = self::R / $ep_sin_sqrt;
        $r = (1 - self::E) / $ep_sin;

        $c = self::E_P2 * $p_cos**2;
        $c2 = $c * $c;

        $d = $x / ($n * self::K0);
        $d2 = $d * $d;
        $d3 = $d2 * $d;
        $d4 = $d3 * $d;
        $d5 = $d4 * $d;
        $d6 = $d5 * $d;

        $latitude = $p_rad - ($p_tan / $r) * (
                     $d2 / 2 -
                     $d4 / 24 * (5 + 3 * $p_tan2 + 10 * $c - 4 * $c2 - 9 * self::E_P2) +
                     $d6 / 720 * (61 + 90 * $p_tan2 + 298 * $c + 45 * $p_tan4 - 252 * self::E_P2 - 3 * $c2));

        $longitude = ($d -
                     $d3 / 6 * (1 + 2 * $p_tan2 + $c) +
                     $d5 / 120 * (5 - 2 * $c + 28 * $p_tan2 - 3 * $c2 + 8 * self::E_P2 + 24 * $p_tan4)) / $p_cos;

        $longitude = self::modAngle($longitude + deg2rad(self::zoneNumberToCentralLongitude($zoneNumber)));

        return [rad2deg($latitude), rad2deg($longitude)];
    }

    public static function fromLatLon($latitude, $longitude, $forceZoneNumber = null, $forceZoneLetter = null, $forceNorthern = null) {
        if (!self::inBounds($latitude, -80, 84)) {
            throw new OutOfRangeError('latitude out of range (must be between 80 deg S and 84 deg N)');
        }
        if (!self::inBounds($longitude, -180, 180)) {
            throw new OutOfRangeError('longitude out of range (must be between 180 deg W and 180 deg E)');
        }
        if ($forceZoneLetter && $forceNorthern !== null) {
            throw new InvalidArgumentException('set either force_zone_letter or force_northern, but not both');
        }
        if ($forceZoneNumber !== null) {
            self::checkValidZone($forceZoneNumber, $forceZoneLetter);
        }

        $lat_rad = deg2rad($latitude);
        $lat_sin = sin($lat_rad);
        $lat_cos = cos($lat_rad);

        $lat_tan = $lat_sin / $lat_cos;
        $lat_tan2 = $lat_tan * $lat_tan;
        $lat_tan4 = $lat_tan2 * $lat_tan2;

        if ($forceZoneNumber === null) {
            $zoneNumber = self::latLonToZoneNumber($latitude, $longitude);
        } else {
            $zoneNumber = $forceZoneNumber;
        }

        if ($forceZoneLetter === null && $forceNorthern === null) {
            $zoneLetter = self::latitudeToZoneLetter($latitude);
        } else {
            $zoneLetter = $forceZoneLetter;
        }

        if ($forceNorthern === null) {
            $northern = ($zoneLetter >= 'N');
        } else {
            $northern = $forceNorthern;
        }

        $lon_rad = deg2rad($longitude);
        $central_lon = self::zoneNumberToCentralLongitude($zoneNumber);
        $central_lon_rad = deg2rad($central_lon);

        $n = self::R / sqrt(1 - self::E * $lat_sin**2);
        $c = self::E_P2 * $lat_cos**2;

        $a = $lat_cos * self::modAngle($lon_rad - $central_lon_rad);
        $a2 = $a * $a;
        $a3 = $a2 * $a;
        $a4 = $a3 * $a;
        $a5 = $a4 * $a;
        $a6 = $a5 * $a;

        $m = self::R * (self::M1 * $lat_rad -
                 self::M2 * sin(2 * $lat_rad) +
                 self::M3 * sin(4 * $lat_rad) -
                 self::M4 * sin(6 * $lat_rad));

        $easting = self::K0 * $n * ($a +
                        $a3 / 6 * (1 - $lat_tan2 + $c) +
                        $a5 / 120 * (5 - 18 * $lat_tan2 + $lat_tan4 + 72 * $c - 58 * self::E_P2)) + 500000;

        $northing = self::K0 * ($m + $n * $lat_tan * ($a2 / 2 +
                                        $a4 / 24 * (5 - $lat_tan2 + 9 * $c + 4 * $c**2) +
                                        $a6 / 720 * (61 - 58 * $lat_tan2 + $lat_tan4 + 600 * $c - 330 * self::E_P2)));

        if (!$northern) {
            $northing += 10000000;
        }

        return [$easting, $northing, $zoneNumber, $zoneLetter];
    }

    public static function latitudeToZoneLetter($latitude) {
        if (!is_numeric($latitude)) {
            throw new InvalidArgumentException('Latitude must be numeric');
        }

        if (-80 <= $latitude && $latitude <= 84) {
            $index = (int)(($latitude + 80) / 8);
            return substr(self::ZONE_LETTERS, $index, 1);
        }
        return null;
    }

    public static function latLonToZoneNumber($latitude, $longitude) {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            throw new InvalidArgumentException('Latitude and longitude must be numeric');
        }

        // Normalize longitude to be in the range [-180, 180)
        $longitude = fmod($longitude, 360);
        if ($longitude >= 180) $longitude -= 360;
        elseif ($longitude < -180) $longitude += 360;

        // Special zone for Norway
        if (56 <= $latitude && $latitude < 64 && 3 <= $longitude && $longitude < 12) {
            return 32;
        }
        // Special zones for Svalbard
        if (72 <= $latitude && $latitude <= 84 && $longitude >= 0) {
            if ($longitude < 9) {
                return 31;
            } elseif ($longitude < 21) {
                return 33;
            } elseif ($longitude < 33) {
                return 35;
            } elseif ($longitude < 42) {
                return 37;
            }
        }

        return (int)(($longitude + 180) / 6) + 1;
    }

    public static function zoneNumberToCentralLongitude($zoneNumber) {
        self::checkValidZoneNumber($zoneNumber);
        return ($zoneNumber - 1) * 6 - 180 + 3;
    }

    public static function zoneLetterToCentralLatitude($zoneLetter) {
        self::checkValidZoneLetter($zoneLetter);
        $zoneLetter = strtoupper($zoneLetter);
        if ($zoneLetter == 'X') {
            return 78;
        } else {
            return -76 + (strpos(self::ZONE_LETTERS, $zoneLetter) * 8);
        }
    }




    public static function generatePrecalibratedMatrix($eastingNorthingMatrix) {
        if (!is_array($eastingNorthingMatrix) || empty($eastingNorthingMatrix)) {
            throw new InvalidArgumentException('Input matrix must be a non-empty array');
        }
    
        // Convert input to array of arrays if it isn't already
        $matrix = [];
        foreach ($eastingNorthingMatrix as $point) {
            if (!is_array($point)) {
                throw new InvalidArgumentException('Each point must be an array [easting, northing]');
            }
            if (count($point) != 2) {
                throw new InvalidArgumentException('Each point must have exactly two values: easting and northing');
            }
            $matrix[] = [(float)$point[0], (float)$point[1]];
        }
    
        // Extract eastings and northings
        $eastings = array_column($matrix, 0);
        $northings = array_column($matrix, 1);
    
        // Calibration reference points (from Python version)
        $givenEasting = [477504.6975, 482977.07875, 487741.8536];
        $givenNorthing = [980922.813, 992734.94275, 993586.1784];
        $eastingCalibReq = [90.6484, 92.6484, 94.6484];
        $northingCalibReq = [204.2779, 210.2779, 208.2779];
    
        // Compute mean values for interpolation
        $thisMeanEasting = array_sum($eastings) / count($eastings);
        $thisMeanNorthing = array_sum($northings) / count($northings);
    
        // Combine given_easting and given_northing as input data points
        $points = [
            [$givenEasting[0], $givenNorthing[0]],
            [$givenEasting[1], $givenNorthing[1]],
            [$givenEasting[2], $givenNorthing[2]]
        ];
    
        // Simple linear interpolation implementation
        // Note: In a production environment, you might want to implement a proper RBF interpolation
        // This is a simplified version for demonstration
        
        // Find the two closest reference points
        $distances = [];
        foreach ($points as $i => $point) {
            $distances[$i] = sqrt(pow($point[0] - $thisMeanEasting, 2) + pow($point[1] - $thisMeanNorthing, 2));
        }
        
        asort($distances);
        $closestIndices = array_slice(array_keys($distances), 0, 2, true);
        
        // Calculate weights based on inverse distance
        $totalInvDistance = 0;
        $weights = [];
        foreach ($closestIndices as $i) {
            $weights[$i] = 1 / ($distances[$i] + 0.0001); // Small epsilon to avoid division by zero
            $totalInvDistance += $weights[$i];
        }
        
        // Normalize weights
        foreach ($weights as &$w) {
            $w /= $totalInvDistance;
        }
        
        // Calculate calibration values
        $calibValueEastings = 0;
        $calibValueNorthings = 0;
        foreach ($weights as $i => $weight) {
            $calibValueEastings += $eastingCalibReq[$i] * $weight;
            $calibValueNorthings += $northingCalibReq[$i] * $weight;
        }
    
        // Apply calibration
        $precalibEastings = [];
        $precalibNorthings = [];
        foreach ($matrix as $point) {
            $precalibEastings[] = $point[0] + $calibValueEastings;
            $precalibNorthings[] = $point[1] + $calibValueNorthings;
        }
    
        // Return the precalibrated matrix
        $result = [];
        foreach ($precalibEastings as $i => $easting) {
            $result[] = [$easting, $precalibNorthings[$i]];
        }
        
        return $result;
    }






}

// Initialize static properties
UTMConverter::init();

// // This file is part of the UTMConverter library.
// // Example uasege
// require_once 'utm.php';

// // Convert from Lat/Lon to UTM
// list($easting, $northing, $zoneNumber, $zoneLetter) = UTMConverter::fromLatLon(9.1234, 38.5678);

// // Convert from UTM to Lat/Lon
// list($latitude, $longitude) = UTMConverter::toLatLon(500000, 1000000, 37, 'N');

