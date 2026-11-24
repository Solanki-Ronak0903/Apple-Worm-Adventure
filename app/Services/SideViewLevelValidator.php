<?php

namespace App\Services;

use App\Game\SideViewLevelSolutions;
use App\Models\GameSession;
use App\Models\Level;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Validates level geometry and replays its private approved solution. */
class SideViewLevelValidator
{
    public function __construct(private readonly SideViewPuzzleService $puzzles)
    {
    }

    /**
     * @return array{valid:bool,message:string,moves?:int}
     */
    public function validate(Level $level, int $adminUserId): array
    {
        $layout = $level->config['sideView'] ?? null;
        if (! is_array($layout)) {
            return $this->invalid('This record does not contain a side-view puzzle layout.');
        }

        $solution = SideViewLevelSolutions::for((int) $level->number);
        if ($solution === null) {
            return $this->invalid('There is no approved solution fixture for this level yet. Keep it unpublished until its puzzle has been designed and verified.');
        }

        $geometryError = $this->validateGeometry($layout);
        if ($geometryError !== null) {
            return $this->invalid($geometryError);
        }

        $directions = ['r' => 'right', 'l' => 'left', 'u' => 'up', 'd' => 'down'];

        try {
            return DB::transaction(function () use ($level, $layout, $solution, $directions, $adminUserId): array {
                $session = GameSession::create([
                    'user_id' => $adminUserId,
                    'level_id' => $level->id,
                    'status' => 'active',
                    'state' => $this->initialState($layout),
                    'started_at' => now(),
                ]);

                try {
                    foreach (str_split($solution) as $symbol) {
                        if ($session->fresh()->state['won'] ?? false) {
                            break;
                        }

                        $result = $this->puzzles->move($session, $directions[$symbol]);
                        if (! empty($result['blocked'])) {
                            return $this->invalid('The approved route is blocked: ' . ($result['reason'] ?? 'unknown collision'));
                        }
                        if (! empty($result['failed']) || ($result['status'] ?? 'active') === 'failed') {
                            return $this->invalid('The approved route fails under the current puzzle rules: ' . ($result['reason'] ?? 'the worm was not safe'));
                        }
                    }

                    $finalState = $session->fresh()->state ?? [];
                    if (empty($finalState['won'])) {
                        return $this->invalid('The approved route did not reach the portal with every required collectible gathered.');
                    }

                    if (! empty($finalState['apples']) || ! empty($finalState['goldenApples']) || ! empty($finalState['switches'])) {
                        return $this->invalid('The approved route leaves a required collectible or switch unfinished.');
                    }

                    return [
                        'valid' => true,
                        'message' => 'Level structure is valid and its approved route completed successfully.',
                        'moves' => (int) ($finalState['moves'] ?? 0),
                    ];
                } finally {
                    // This is a temporary validation session, never player history.
                    $session->delete();
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->invalid('The level could not be replay-validated. Check its saved puzzle data and try again.');
        }
    }

    private function validateGeometry(array $layout): ?string
    {
        $width = (int) ($layout['width'] ?? 0);
        $height = (int) ($layout['height'] ?? 0);
        if ($width < 5 || $width > 30 || $height < 5 || $height > 20) {
            return 'Board dimensions are outside the supported range (5–30 columns and 5–20 rows).';
        }

        $start = $layout['start'] ?? null;
        if (! is_array($start) || ! isset($start['x'], $start['y'])) {
            return 'The worm starting position is missing or invalid.';
        }

        $length = (int) ($start['length'] ?? 0);
        $facing = $start['facing'] ?? 'right';
        if ($length < 2 || $length > 6 || ! in_array($facing, ['left', 'right', 'up', 'down'], true)) {
            return 'The worm starting length or direction is invalid.';
        }

        $fields = ['terrain', 'apples', 'goldenApples', 'blocks', 'spikes', 'switches', 'gates'];
        foreach ($fields as $field) {
            if (! isset($layout[$field]) || ! is_array($layout[$field])) {
                return "The {$field} map data is missing or invalid.";
            }
        }

        $allPoints = [];
        foreach ($fields as $field) {
            foreach ($layout[$field] as $point) {
                if (! $this->validPoint($point, $width, $height)) {
                    return "The {$field} list contains a coordinate outside the board.";
                }
                $key = $this->key($point);
                if (isset($allPoints[$field][$key])) {
                    return "The {$field} list contains a duplicate coordinate.";
                }
                $allPoints[$field][$key] = true;
            }
        }

        $portal = $layout['portal'] ?? null;
        if (! $this->validPoint($portal, $width, $height)) {
            return 'The portal position is missing or outside the board.';
        }

        $solid = $allPoints['terrain'] ?? [];
        $hazards = $allPoints['spikes'] ?? [];
        $blocks = $allPoints['blocks'] ?? [];
        $apples = $allPoints['apples'] ?? [];
        $golden = $allPoints['goldenApples'] ?? [];
        $switches = $allPoints['switches'] ?? [];
        $gates = $allPoints['gates'] ?? [];

        foreach ([$apples, $golden, $blocks, $hazards, $switches, $gates] as $set) {
            foreach ($set as $key => $_) {
                if (isset($solid[$key])) {
                    return 'A collectible, crate, hazard, switch or gate overlaps solid terrain.';
                }
            }
        }

        $portalKey = $this->key($portal);
        if (isset($solid[$portalKey]) || isset($hazards[$portalKey]) || isset($blocks[$portalKey]) || isset($apples[$portalKey]) || isset($golden[$portalKey]) || isset($gates[$portalKey])) {
            return 'The portal overlaps an incompatible map object.';
        }

        $head = ['x' => (int) $start['x'], 'y' => (int) $start['y']];
        [$dx, $dy] = match ($facing) {
            'left' => [1, 0], 'up' => [0, 1], 'down' => [0, -1], default => [-1, 0],
        };
        for ($segment = 0; $segment < $length; $segment++) {
            $part = ['x' => $head['x'] + ($dx * $segment), 'y' => $head['y'] + ($dy * $segment)];
            if (! $this->validPoint($part, $width, $height)) {
                return 'The starting worm body extends beyond the board.';
            }
            $key = $this->key($part);
            if (isset($solid[$key]) || isset($hazards[$key]) || isset($blocks[$key]) || isset($apples[$key]) || isset($golden[$key]) || isset($gates[$key])) {
                return 'The starting worm overlaps a solid tile, hazard or game object.';
            }
        }

        return null;
    }

    private function initialState(array $layout): array
    {
        $start = $layout['start'];
        [$dx, $dy] = match ($start['facing'] ?? 'right') {
            'left' => [1, 0], 'up' => [0, 1], 'down' => [0, -1], default => [-1, 0],
        };
        $worm = [];
        for ($segment = 0; $segment < (int) $start['length']; $segment++) {
            $worm[] = ['x' => (int) $start['x'] + ($dx * $segment), 'y' => (int) $start['y'] + ($dy * $segment)];
        }

        return [
            'mode' => 'side-view-puzzle',
            'worm' => $worm,
            'apples' => array_values($layout['apples'] ?? []),
            'goldenApples' => array_values($layout['goldenApples'] ?? []),
            'goldenApplesCollected' => 0,
            'megaApples' => [], 'mysteryApples' => [], 'keys' => [],
            'blocks' => array_values($layout['blocks'] ?? []),
            'switches' => array_values($layout['switches'] ?? []),
            'switchesActivated' => 0,
            'gatesOpen' => empty($layout['gates']),
            'applesCollected' => 0, 'growthPending' => 0, 'moves' => 0,
            'move_limit' => max(1, (int) ($layout['moveLimit'] ?? ((int) ($layout['parMoves'] ?? 10) + 5))),
            'bonus_moves' => 0, 'score' => 0,
            'pushes' => 0, 'gravitySteps' => 0, 'won' => false, 'lost' => false,
            'double_score_claimed' => false, 'double_coins_claimed' => false,
            'extra_moves_claimed' => false, 'booster_claimed' => false,
            'rewarded_hints_used' => 0, 'hint_stage' => 0,
        ];
    }

    private function validPoint(mixed $point, int $width, int $height): bool
    {
        return is_array($point)
            && isset($point['x'], $point['y'])
            && is_numeric($point['x']) && is_numeric($point['y'])
            && (int) $point['x'] >= 0 && (int) $point['x'] < $width
            && (int) $point['y'] >= 0 && (int) $point['y'] < $height;
    }

    private function key(array $point): string
    {
        return (int) $point['x'] . ':' . (int) $point['y'];
    }

    private function invalid(string $message): array
    {
        return ['valid' => false, 'message' => $message];
    }
}
