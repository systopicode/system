<?php

class calculate {

    static function sizeByMegapixels($mp, $ar) {
        $pixel = $mp * 1E+6;
        $width = sqrt($pixel * $ar);
        $height = $width / $ar;
        return (object) [
                'width' => $width,
                'height' => $height,
                'widthRounded' => round($width),
                'heightRounded' => round($height),
                'ar' => $ar
        ];
    }

    static function scaleToMegapixels($srcWidth, $srcHeight, $mp) {
        $srcPixel = $srcWidth * $srcHeight;
        $targetPixel = $mp * 1E+6;
        return sqrt($targetPixel) / sqrt($srcPixel);
    }

    static function convert(...$args) {
        $from = array_shift($args);
        $to = array_shift($args);
        return match ("$from->$to") {
            "px->px" => reset($args),
            "mm->px" => self::mm2px(...$args),
            default => d(static::class . "::convert unsupported conversion $from->$to")
        };
    }

    static function mm2px($mm, $dpi) {
        return $mm / 25.4 * $dpi;
    }

    static function crop($source, $canvas, $cropData) {
        $sourceAr = $source->width / $source->height;
        $canvasAr = $canvas->width / $canvas->height;
        $fit = $cropData->fit ?: 'cover'; // legacy crop data comes without fit
        $fitInHeight = $canvasAr < $sourceAr; // cover, also used by area and paint
        $scale = 1;
        switch ($fit) {
            case 'cover':
                $fitInHeight = $canvasAr < $sourceAr;
                break;
            case 'contain':
                $fitInHeight = $canvasAr > $sourceAr;
                break;
            case 'width':
                $fitInHeight = FALSE;
                break;
            case 'height':
                $fitInHeight = TRUE;
                break;
        }
        switch ($fit) {
            case 'cover':
            case 'contain':
            case 'width':
            case 'height':
                if ($fitInHeight) {
                    $scale = $cropData->scale * $canvas->height / $source->height;
                } else { // fitInWidth
                    $scale = $cropData->scale * $canvas->width / $source->width;
                }
                break;
            case 'area':
                // konstante fläche
                $scale = 1;
                break;
            case 'paint':
                // konstante schwarzfläche
                $scale = 1;
                break;
        }

        $calc = (object) [
                'sourceWidth' => $source->width,
                'sourceHeight' => $source->height,
                'method' => $fit,
                'fitin' => $fitInHeight ? 'height' : 'width',
                'scale' => $scale,
                'sourceScaledWidth' => $source->width * $scale,
                'sourceScaledHeight' => $source->height * $scale,
                'sourceHeight' => $source->height * $scale,
                'canvasWidth' => $canvas->width,
                'canvasHeight' => $canvas->height,
                'sourceScaledCenterX' => $source->width * $scale / 2,
                'sourceScaledCenterY' => $source->height * $scale / 2,
                'cropCenterX' => $canvas->width / 2,
                'cropCenterY' => $canvas->height / 2,
        ];
        $spaceX = $calc->cropCenterX - $calc->sourceScaledCenterX; // space to move 
        $spaceY = $calc->cropCenterY - $calc->sourceScaledCenterY; // space to move 

        return (object) array_merge((array) $calc, [
                'scale' => $scale,
                'x' => $spaceX,
                'y' => $spaceY,
        ]);
    }

    static function getCropData() { // default ... kopiert aus pwk fileFormat
        return (object) [
                'fit' => http::request('fit', 'contain'), // poster,width,height,area, paint ...
                'scale' => http::request('scale', 1),
                'x' => http::request('x', 0.5),
                'y' => http::request('y', 0.5),
        ];
    }

    static function secondsToFfmpegtimecode($t) {
        $tc = (object) [
                'h' => $t / 3600,
                'm' => fmod($t / 60, 60),
                's' => fmod($t, 60),
                'dec' => substr($t - floor($t), 1)
        ];
        // $tc->h twice was a typo - the minutes slot got the hours, so every
        // position past a minute was off (90s came out as 00:00:30).
        return sprintf('%02d:%02d:%02d', $tc->h, $tc->m, $tc->s) . $tc->dec;
    }

    static function minmax($min, $val, $max) {
        return $val > $max ? $max : ($val < $min ? $min : $val);
    }

    static function getColorDistance($color, $color2 = array('r' => 255, 'g' => 255, 'b' => 255)) {
        return sqrt(pow($color['r'] - $color2['r'], 2) + pow($color['g'] - $color2['g'], 2) + pow($color['b'] - $color2['b'], 2));
    }

    /**
     * Convert between px, in, cm, mm units, using resolution only when px is involved.
     *
     * @param float  $value          The numeric value to convert.
     * @param string $fromUnit       Unit of the input value (px|in|inch|cm|mm).
     * @param string $toUnit         Desired output unit (px|in|inch|cm|mm).
     * @param float  $resolution     Resolution in given unit.
     * @param string $resolutionUnit Resolution unit: dpi, dpcm, dpmm.
     *
     * @return float
     * @throws \InvalidArgumentException
     */
    public static function convertValue(
        float $value,
        string $fromUnit,
        string $toUnit,
        float $resolution,
        string $resolutionUnit
    ): float {
        // p("convertValue:", [
        //    '$value' => $value,
        //    '$fromUnit' => $fromUnit,
        //    '$toUnit' => $toUnit,
        //    '$resolution' => $resolution,
        //    '$resolutionUnit' => $resolutionUnit
        // ]);
        $from = strtolower($fromUnit);
        $to = strtolower($toUnit);

        if ($from === $to) {
            return $value;
        }

        $needsDpi = ($from === 'px' || $to === 'px');

        $dpi = null;
        if ($needsDpi) {
            $dpi = self::normalizeDpi($resolution, $resolutionUnit);
        }

        // Purely physical conversion
        if (!$needsDpi) {
            $inches = self::toInches($value, $from);
            return self::fromInches($inches, $to);
        }

        // px -> physical
        if ($from === 'px') {
            $inches = $value / $dpi;
            return self::fromInches($inches, $to);
        }

        // physical -> px
        if ($to === 'px') {
            $inches = self::toInches($value, $from);
            return $inches * $dpi;
        }

        throw new \LogicException("Conversion from {$from} to {$to} not supported.");
    }

    /**
     * Normalize a resolution into dots-per-inch (dpi).
     *
     * @param float  $resolution
     * @param string $unit       dpi|dpcm|dpmm
     *
     * @return float
     * @throws \InvalidArgumentException
     */
    private static function normalizeDpi(float $resolution, string $unit): float {
        return match (strtolower($unit)) {
            'dpi' => $resolution,
            'dpcm' => $resolution * 2.54,
            'dpmm' => $resolution * 25.4,
            default => throw new \InvalidArgumentException("Unknown resolution unit \"{$unit}\"."),
        };
    }

    /**
     * Convert a physical measurement to inches.
     *
     * @param float  $value
     * @param string $unit  in|inch|cm|mm
     *
     * @return float
     * @throws \InvalidArgumentException
     */
    private static function toInches(float $value, string $unit): float {
        return match (strtolower($unit)) {
            'in', 'inch' => $value,
            'cm' => $value / 2.54,
            'mm' => $value / 25.4,
            default => throw new \InvalidArgumentException("Unknown unit \"{$unit}\"."),
        };
    }

    /**
     * Convert inches to a physical measurement.
     *
     * @param float  $inches
     * @param string $unit   in|inch|cm|mm
     *
     * @return float
     * @throws \InvalidArgumentException
     */
    private static function fromInches(float $inches, string $unit): float {
        return match (strtolower($unit)) {
            'in', 'inch' => $inches,
            'cm' => $inches * 2.54,
            'mm' => $inches * 25.4,
            default => throw new \InvalidArgumentException("Unknown unit \"{$unit}\"."),
        };
    }

}
