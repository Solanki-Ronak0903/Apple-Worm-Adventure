<?php

namespace App\Services\Game;

/**
 * Replays a fixed-step input log on the server. Client-reported positions,
 * collisions, collectible totals and scores are never trusted for rewards.
 */
class PlatformerSimulation
{
    private const STEP = 1 / 60;

    public function replay(array $course, string $inputLog): array
    {
        $radius = (float) ($course['player']['radius'] ?? 19);
        $gravity = (float) ($course['physics']['gravity'] ?? 1680);
        $acceleration = (float) ($course['physics']['moveAcceleration'] ?? 1850);
        $maxSpeed = (float) ($course['physics']['maxSpeed'] ?? 300);
        $jumpSpeed = (float) ($course['physics']['jumpSpeed'] ?? 640);
        $width = (float) ($course['worldWidth'] ?? 2380);
        $height = (float) ($course['canvas']['height'] ?? 620);

        $start = $course['start'];
        $checkpoint = $start;
        $courseCheckpoint = $course['checkpoint'] ?? $start;
        $x = (float) $start['x'];
        $y = (float) $start['y'];
        $vx = 0.0;
        $vy = 0.0;
        $grounded = true;
        $coyote = 0.12;
        $lives = (int) ($course['player']['lives'] ?? 3);
        $collected = [];
        $goldenCollected = [];
        $score = 0;
        $jumps = 0;
        $checkpointReached = false;
        $completed = false;
        $frames = 0;
        $lastDirection = 0;
        $directionChanges = 0;

        foreach (str_split($inputLog) as $character) {
            if ($completed || $lives <= 0) {
                break;
            }

            $bits = (int) $character;
            $left = ($bits & 1) === 1;
            $right = ($bits & 2) === 2;
            $jump = ($bits & 4) === 4;
            $direction = ($right ? 1 : 0) - ($left ? 1 : 0);

            if ($direction !== 0) {
                $vx += $direction * $acceleration * self::STEP;
                if ($direction !== $lastDirection && $lastDirection !== 0) {
                    $directionChanges++;
                }
                $lastDirection = $direction;
            } else {
                $friction = $grounded ? 2100 : 520;
                if (abs($vx) <= $friction * self::STEP) {
                    $vx = 0.0;
                } else {
                    $vx -= ($vx <=> 0) * $friction * self::STEP;
                }
                $lastDirection = 0;
            }
            $vx = max(-$maxSpeed, min($maxSpeed, $vx));

            if ($jump && ($grounded || $coyote > 0)) {
                $vy = -$jumpSpeed;
                $grounded = false;
                $coyote = 0.0;
                $jumps++;
            }

            $previousY = $y;
            $x += $vx * self::STEP;
            $x = max($radius, min($width - $radius, $x));

            $vy += $gravity * self::STEP;
            $y += $vy * self::STEP;
            $grounded = false;

            if ($vy >= 0) {
                foreach ($course['platforms'] as $platform) {
                    $horizontalOverlap = $x + ($radius * 0.7) > $platform['x']
                        && $x - ($radius * 0.7) < ($platform['x'] + $platform['w']);
                    $crossedTop = $previousY + $radius <= ($platform['y'] + 8)
                        && $y + $radius >= $platform['y'];
                    if ($horizontalOverlap && $crossedTop) {
                        $y = (float) $platform['y'] - $radius;
                        $vy = 0.0;
                        $grounded = true;
                        break;
                    }
                }
            }

            $coyote = $grounded ? 0.12 : max(0.0, $coyote - self::STEP);

            foreach ($course['apples'] as $index => $apple) {
                if (isset($collected[$index])) {
                    continue;
                }
                if (hypot($x - $apple['x'], $y - $apple['y']) < 35) {
                    $collected[$index] = true;
                    $score += 100;
                }
            }

            foreach ($course['goldenApples'] as $index => $apple) {
                if (isset($goldenCollected[$index])) {
                    continue;
                }
                if (hypot($x - $apple['x'], $y - $apple['y']) < 36) {
                    $goldenCollected[$index] = true;
                    $score += 500;
                }
            }

            $hitHazard = false;
            foreach ($course['spikes'] as $spike) {
                $horizontal = $x + ($radius * 0.7) > $spike['x']
                    && $x - ($radius * 0.7) < ($spike['x'] + $spike['w']);
                $vertical = $y + $radius > ($spike['y'] - $spike['h'] + 4)
                    && $y + ($radius * 0.55) < ($spike['y'] + 7);
                if ($horizontal && $vertical) {
                    $hitHazard = true;
                    break;
                }
            }

            if (!$hitHazard && $y > $height + 120) {
                $hitHazard = true;
            }

            if ($hitHazard) {
                $lives--;
                if ($lives <= 0) {
                    break;
                }
                $x = (float) $checkpoint['x'];
                $y = (float) $checkpoint['y'];
                $vx = 0.0;
                $vy = 0.0;
                $grounded = true;
                $coyote = 0.12;
                $frames++;
                continue;
            }

            if (!$checkpointReached && $x > $courseCheckpoint['x'] && $grounded) {
                $checkpoint = $courseCheckpoint;
                $checkpointReached = true;
            }

            $allApplesCollected = count($collected) === count($course['apples']);
            if ($allApplesCollected && hypot($x - $course['portal']['x'], $y - $course['portal']['y']) < 56) {
                $completed = true;
                $score += 250;
            }

            $frames++;
        }

        $regularCount = count($course['apples']);
        $goldenCount = count($goldenCollected);
        $parFrames = max(1, (int) ($course['parFrames'] ?? 3000));
        $stars = $completed
            ? (($frames <= $parFrames && $lives === (int) ($course['player']['lives'] ?? 3))
                ? 3
                : (($frames <= (int) ($parFrames * 1.6) || $lives >= 2) ? 2 : 1))
            : 0;

        return [
            'completed' => $completed,
            'lives' => $lives,
            'frames' => $frames,
            'jumps' => $jumps,
            'directionChanges' => $directionChanges,
            'applesCollected' => count($collected),
            'applesRequired' => $regularCount,
            'goldenApplesCollected' => $goldenCount,
            'score' => min(100000, $score),
            'stars' => $stars,
            'checkpointReached' => $checkpointReached,
        ];
    }
}
