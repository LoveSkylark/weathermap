<?php

namespace Weathermap\Geometry;

/**
 * Utility 'class' for 2D points.
 *
 * we use enough points in various places to make it worth a small class to
 * save some variable-pairs.
 *
 */
class Point
{
    public $x;
    public $y;

    public function __construct($x = 0, $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function identical($point2)
    {
        if (($this->x == $point2->x) && ($this->y == $point2->y)) {
            return true;
        }
        return false;
    }

    public function set($newX, $newY)
    {
        $this->x = $newX;
        $this->y = $newY;
    }

    /**
     * round() - round the coordinates to their nearest integers, in place.
     */
    public function round()
    {
        $this->x = round($this->x);
        $this->y = round($this->y);
    }

    /**
     * Compare two points to within a few decimal places - good enough for graphics! (and unit tests)
     *
     * @param $point2
     * @return bool
     */
    public function closeEnough($point2)
    {
        if ((round($this->x, 2) == round($point2->x, 2)) && (round($this->y, 2) == round($point2->y, 2))) {
            return true;
        }
        return false;
    }


    public function vectorToPoint($p2)
    {
        $v = new Vector($p2->x - $this->x, $p2->y - $this->y);

        return $v;
    }

    public function lineToPoint($p2)
    {
        $vec = $this->vectorToPoint($p2);
        return new Line($this, $vec);
    }

    public function distanceToLine(Line $l)
    {
        $p = $l->getPoint();
        $v = $l->getVector();
        $len = $v->length();
        if ($len == 0) {
            return $this->distanceToPoint($p);
        }
        // |cross product of (this - p) and v| / |v|
        return abs($v->dx * ($p->y - $this->y) - $v->dy * ($p->x - $this->x)) / $len;
    }

    public function distanceToLineSegment(LineSegment $l)
    {
        $v = $l->vector;
        $lenSq = $v->dx * $v->dx + $v->dy * $v->dy;
        if ($lenSq == 0) {
            return $this->distanceToPoint($l->point1);
        }
        // Project this point onto the segment, clamped to [0,1]
        $t = (($this->x - $l->point1->x) * $v->dx + ($this->y - $l->point1->y) * $v->dy) / $lenSq;
        $t = max(0.0, min(1.0, $t));
        $closest = new Point($l->point1->x + $t * $v->dx, $l->point1->y + $t * $v->dy);
        return $this->distanceToPoint($closest);
    }

    public function distanceToPoint($p2)
    {
        return $this->vectorToPoint($p2)->length();
    }

    public function copy()
    {
        return new Point($this->x, $this->y);
    }

    /**
     * @param Vector $v
     * @param float $fraction
     *
     * @return $this - to allow for chaining of operations
     */
    public function addVector($v, $fraction = 1.0)
    {
        if ($fraction == 0) {
            return $this;
        }

        $this->x = $this->x + $fraction * $v->dx;
        $this->y = $this->y + $fraction * $v->dy;

        return $this;
    }

    /**
     * Linear Interpolate between two points
     *
     * @param $point2 - other point we're interpolating to
     * @param $ratio - how far (0-1) between the two
     * @return Point - a new Point
     */
    public function LERPWith($point2, $ratio)
    {
        $x = $this->x + $ratio * ($point2->x - $this->x);
        $y = $this->y + $ratio * ($point2->y - $this->y);

        $newPoint = new Point($x, $y);

        return $newPoint;
    }

    public function asString()
    {
        return $this->__toString();
    }

    public function asConfig()
    {
        return sprintf("%d %d", $this->x, $this->y);
    }

    public function __toString()
    {
        return sprintf("(%s,%s)", floatval($this->x), floatval($this->y));
    }

    public function translate($deltaX, $deltaY)
    {
        $this->x += $deltaX;
        $this->y += $deltaY;

        return $this;
    }

    public function translatePolar($angle, $distance)
    {
        $radiansAngle = deg2rad($angle);

        $this->x += $distance * sin($radiansAngle);
        $this->y += -$distance * cos($radiansAngle);

        return $this;
    }
}
