<?php

namespace App\Services\Game;

use App\Models\Level;

/**
 * Produces stable, replayable side-view courses for the published level set.
 * The layout is derived from the level number, so players receive the same
 * course on every replay without storing a second copy of level content.
 */
class PlatformerLevelFactory
{
    private const PLAYER_RADIUS = 19;

    private const WORLDS = [
        [
            'code' => 'meadow', 'name' => 'Green Meadow', 'title' => 'The Green Meadows',
            'skyTop' => '#a9dff0', 'skyBottom' => '#edf4d9', 'far' => '#a5d4b0', 'near' => '#91c89f',
            'ground' => '#b1dca2', 'grass' => '#68b95d', 'grassLight' => '#d0ee91',
            'dirtTop' => '#b77c4d', 'dirtMid' => '#9c6542', 'dirtBottom' => '#744a37',
            'accent' => '#8d5ce3', 'hazard' => '#d87563', 'sun' => '#fff3b6',
        ],
        [
            'code' => 'forest', 'name' => 'Enchanted Forest', 'title' => 'The Enchanted Forest',
            'skyTop' => '#9ed8c9', 'skyBottom' => '#e6f0c9', 'far' => '#86bd91', 'near' => '#5f9e78',
            'ground' => '#a7cd89', 'grass' => '#4f9858', 'grassLight' => '#c9e68c',
            'dirtTop' => '#916647', 'dirtMid' => '#744f3c', 'dirtBottom' => '#4e3c35',
            'accent' => '#65c9a3', 'hazard' => '#d87963', 'sun' => '#f9f0a5',
        ],
        [
            'code' => 'ruins', 'name' => 'Ancient Ruins', 'title' => 'The Ancient Ruins',
            'skyTop' => '#f4c99e', 'skyBottom' => '#f4e4bb', 'far' => '#d8a977', 'near' => '#b98664',
            'ground' => '#d9b37e', 'grass' => '#9a9a54', 'grassLight' => '#e1d38c',
            'dirtTop' => '#a87852', 'dirtMid' => '#81583f', 'dirtBottom' => '#563e35',
            'accent' => '#e19a55', 'hazard' => '#bd6251', 'sun' => '#ffe2a2',
        ],
        [
            'code' => 'crystal', 'name' => 'Crystal Caves', 'title' => 'The Crystal Caves',
            'skyTop' => '#a8c7ed', 'skyBottom' => '#e6ddf4', 'far' => '#9aa8d4', 'near' => '#7889bd',
            'ground' => '#b4c3dc', 'grass' => '#68a4bd', 'grassLight' => '#c6f0ec',
            'dirtTop' => '#777da9', 'dirtMid' => '#555d83', 'dirtBottom' => '#383d61',
            'accent' => '#a47bfa', 'hazard' => '#f08fba', 'sun' => '#e6dcff',
        ],
        [
            'code' => 'sky', 'name' => 'Sky Temple', 'title' => 'The Sky Temple',
            'skyTop' => '#8fc6ed', 'skyBottom' => '#f5e5c9', 'far' => '#a4bfd9', 'near' => '#849fbf',
            'ground' => '#c4d9c0', 'grass' => '#78a96d', 'grassLight' => '#e3e9a8',
            'dirtTop' => '#b68a64', 'dirtMid' => '#8d6d59', 'dirtBottom' => '#5c4b4b',
            'accent' => '#f2bd63', 'hazard' => '#e37e76', 'sun' => '#fff0bd',
        ],
    ];

    private const LEVEL_NAMES = [
        1 => 'First Nibble', 2 => 'Sunny Switchback', 3 => 'Apple Trail', 4 => 'Rocky Bend', 5 => 'Golden Grove',
        6 => 'Fern Maze', 7 => 'Mossy Zigzag', 8 => 'Bramble Crossing', 9 => 'Forest Fork', 10 => 'Emerald Cache',
        11 => 'Ruins Entrance', 12 => 'Keykeeper Hall', 13 => 'Broken Columns', 14 => 'The Locked Gallery', 15 => 'Guardian Gate',
        16 => 'Crystal Crossing', 17 => 'Prism Path', 18 => 'Gemstone Spiral', 19 => 'Blue Quartz Run', 20 => 'Crystal Vault',
        21 => 'Cloudstep', 22 => 'Skyward Switchback', 23 => 'Floating Maze', 24 => 'Storm Path', 25 => 'Sky Temple Trial',
    ];

    public function build(Level|int $level, array $legacyConfig = []): array
    {
        $number = $level instanceof Level ? (int) $level->number : $level;
        if ($level instanceof Level) {
            $legacyConfig = $level->config ?? [];
        }

        $number = max(1, min(25, $number));
        $worldIndex = intdiv($number - 1, 5);
        $world = self::WORLDS[$worldIndex];
        $platformCount = 5 + min(3, intdiv($number - 1, 7));

        $platforms = [[
            'x' => -80, 'y' => 520, 'w' => 480, 'h' => 100, 'kind' => 'soil',
        ]];

        $previous = $platforms[0];
        for ($index = 1; $index < $platformCount; $index++) {
            $gap = 64 + ($this->pick($number, $index, 2, 5) * 10);
            $width = 260 + ($this->pick($number, $index, 4, 5) * 24);
            $heightChanges = [-48, -24, 0, 25, 48];
            $deltaY = $heightChanges[$this->pick($number, $index, 6, count($heightChanges))];
            $y = max(390, min(520, $previous['y'] + $deltaY));
            $x = $previous['x'] + $previous['w'] + $gap;
            $platforms[] = [
                'x' => $x, 'y' => $y, 'w' => $width, 'h' => 100,
                'kind' => $index % 3 === 0 ? 'stone' : 'soil',
            ];
            $previous = end($platforms);
        }

        $lastPlatform = end($platforms);
        $worldWidth = $lastPlatform['x'] + $lastPlatform['w'] + 120;
        $start = [
            'x' => 105,
            'y' => $platforms[0]['y'] - self::PLAYER_RADIUS,
        ];

        $regularAppleCount = min(5, 2 + intdiv($number - 1, 8));
        $apples = [];
        $availablePlatforms = max(1, $platformCount - 2);
        for ($index = 0; $index < $regularAppleCount; $index++) {
            $platformIndex = 1 + min(
                $availablePlatforms - 1,
                (int) floor(($index * $availablePlatforms) / max(1, $regularAppleCount))
            );
            $platform = $platforms[$platformIndex];
            $fraction = 0.36 + ($this->pick($number, $index, 11, 3) * 0.08);
            $apples[] = [
                'x' => (int) round($platform['x'] + $platform['w'] * $fraction),
                'y' => $platform['y'] - 43,
                'kind' => 'regular',
            ];
        }

        $legacyGoldenCount = count($legacyConfig['goldenApples'] ?? []);
        $goldenApples = [];
        for ($index = 0; $index < $legacyGoldenCount; $index++) {
            $platformIndex = max(1, min($platformCount - 2, $platformCount - 2 - $index));
            $platform = $platforms[$platformIndex];
            $goldenApples[] = [
                'x' => (int) round($platform['x'] + $platform['w'] * 0.76),
                'y' => $platform['y'] - 47,
                'kind' => 'golden',
            ];
        }

        $spikes = [];
        $spikeCount = min(5, 1 + intdiv($number - 1, 6));
        for ($index = 0; $index < $spikeCount; $index++) {
            $platformIndex = 1 + min(
                $availablePlatforms - 1,
                (int) floor(($index * $availablePlatforms) / max(1, $spikeCount))
            );
            $platform = $platforms[$platformIndex];
            $spikes[] = [
                'x' => (int) round($platform['x'] + $platform['w'] * (0.78 + ($this->pick($number, $index, 19, 2) * 0.04))),
                'y' => $platform['y'],
                'w' => 42 + ($this->pick($number, $index, 23, 2) * 8),
                'h' => 26,
            ];
        }

        $checkpointPlatformIndex = max(1, min($platformCount - 2, (int) floor($platformCount / 2)));
        $checkpointPlatform = $platforms[$checkpointPlatformIndex];
        $checkpoint = [
            'x' => (int) round($checkpointPlatform['x'] + 34),
            'y' => $checkpointPlatform['y'] - self::PLAYER_RADIUS,
        ];

        $portal = [
            'x' => (int) round($lastPlatform['x'] + $lastPlatform['w'] - 64),
            'y' => $lastPlatform['y'] - self::PLAYER_RADIUS,
        ];

        $difficulty = match ($worldIndex) {
            0 => 'TUTORIAL', 1 => 'EASY', 2 => 'MEDIUM', 3 => 'HARD', default => 'ADVANCED',
        };

        return [
            'level' => $number,
            'title' => self::LEVEL_NAMES[$number] ?? "Adventure {$number}",
            'world' => $world,
            'worldIndex' => $worldIndex + 1,
            'difficulty' => $difficulty,
            'worldWidth' => (int) $worldWidth,
            'canvas' => ['width' => 1100, 'height' => 620],
            'player' => ['radius' => self::PLAYER_RADIUS, 'initialSegments' => 5, 'lives' => 3],
            'physics' => ['gravity' => 1680, 'moveAcceleration' => 1850, 'maxSpeed' => 300, 'jumpSpeed' => 640],
            'start' => $start,
            'checkpoint' => $checkpoint,
            'platforms' => $platforms,
            'apples' => $apples,
            'goldenApples' => $goldenApples,
            'spikes' => $spikes,
            'portal' => $portal,
            // Par is tuned against the course length so three stars require a clean, fast run.
            'parFrames' => 255 + (($platformCount - 5) * 68) + (($number % 5) * 4),
            'maxFrames' => 18000,
        ];
    }

    private function pick(int $level, int $index, int $salt, int $modulus): int
    {
        return (int) abs(($level * 73 + $index * 37 + $salt * 19 + ($level * $index * 11)) % $modulus);
    }
}
