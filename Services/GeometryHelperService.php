<?php

namespace App\Plugins\Weathermap\Services;

/**
 * GeometryHelperService - Geometric calculations for map editor
 * 
 * Extracted from lib/editor/EditorFunctions.php
 * Provides geometry utilities for snap-to-grid, distance calculations, range operations
 */
class GeometryHelperService
{
    /**
     * Snap coordinate to grid
     */
    public function snap(float $coord, int $gridsnap = 0): float
    {
        if ($gridsnap === 0) {
            return $coord;
        }

        $rest = fmod($coord, $gridsnap);
        return $coord - $rest + round($rest / $gridsnap) * $gridsnap;
    }

    /**
     * Check if two ranges overlap
     */
    public function rangeOverlaps(float $a_min, float $a_max, float $b_min, float $b_max): bool
    {
        if ($a_min > $b_max) {
            return false;
        }
        if ($b_min > $a_max) {
            return false;
        }

        return true;
    }

    /**
     * Find the common/overlapping part of two ranges
     */
    public function commonRange(float $a_min, float $a_max, float $b_min, float $b_max): array
    {
        $min_overlap = max($a_min, $b_min);
        $max_overlap = min($a_max, $b_max);

        return [$min_overlap, $max_overlap];
    }

    /**
     * Calculate distance between two points
     */
    public function distance(float $ax, float $ay, float $bx, float $by): float
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;
        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Find the closest cardinal direction (N, S, E, W, NE, NW, SE, SW)
     */
    public function closestCompassDirection(float $ax, float $ay, float $bx, float $by): string
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;

        $abs_dx = abs($dx);
        $abs_dy = abs($dy);

        // Determine primary direction
        if ($abs_dx > $abs_dy) {
            // More horizontal
            return $dx > 0 ? 'E' : 'W';
        } elseif ($abs_dy > $abs_dx) {
            // More vertical
            return $dy > 0 ? 'S' : 'N';
        } else {
            // Diagonal - determine which diagonal
            $northeast = ($dx > 0 && $dy < 0);
            $northwest = ($dx < 0 && $dy < 0);
            $southeast = ($dx > 0 && $dy > 0);

            if ($northeast) {
                return 'NE';
            } elseif ($northwest) {
                return 'NW';
            } elseif ($southeast) {
                return 'SE';
            } else {
                return 'SW';
            }
        }
    }
}
