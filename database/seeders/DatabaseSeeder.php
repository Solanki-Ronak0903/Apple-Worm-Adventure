<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevelProgress;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Initial Release Configuration
        |--------------------------------------------------------------------------
        */

        $releaseLevelCount = (int) config('game.initial_level_count', 25);

        /*
        |--------------------------------------------------------------------------
        | Seed 25 Published Game Levels
        |--------------------------------------------------------------------------
        */

        for ($number = 1; $number <= $releaseLevelCount; $number++) {

            $config = $this->buildLevelConfig($number);

            Level::updateOrCreate(
                ['number' => $number],
                [
                    'title' => sprintf('Level %02d', $number),
                    'difficulty' => $this->difficultyFor($number),
                    'grid_rows' => $config['grid']['rows'],
                    'grid_cols' => $config['grid']['cols'],
                    'config' => $config,
                    'is_published' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Hide Future Levels
        |--------------------------------------------------------------------------
        |
        | Future levels are retained in the database.
        | They are not available in the initial release.
        |
        */

        Level::where('number', '>', $releaseLevelCount)
            ->update(['is_published' => false]);

        /*
        |--------------------------------------------------------------------------
        | Seed Achievement Definitions
        |--------------------------------------------------------------------------
        */

        $achievements = [
            [
                'first_apple',
                'First Bite',
                'Collect your first apple.',
                '🍎',
                [
                    'apples' => 1,
                    'reward_coins' => 10,
                ],
            ],
            [
                'five_levels',
                'Getting Started',
                'Complete five levels.',
                '⭐',
                [
                    'levels' => 5,
                    'reward_coins' => 40,
                ],
            ],
            [
                'star_collector',
                'Star Collector',
                'Earn ten stars.',
                '🌟',
                [
                    'stars' => 10,
                    'reward_coins' => 30,
                ],
            ],
            [
                'ad_explorer',
                'Reward Explorer',
                'Try a rewarded action.',
                '🎁',
                [
                    'rewards' => 1,
                    'reward_coins' => 15,
                ],
            ],
            [
                'golden_hunter',
                'Golden Hunter',
                'Collect five golden apples.',
                '🍏',
                [
                    'golden_apples' => 5,
                    'reward_coins' => 50,
                ],
            ],
        ];

        foreach ($achievements as [
            $code,
            $name,
            $description,
            $icon,
            $criteria
        ]) {

            Achievement::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'icon' => $icon,
                    'criteria' => $criteria,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preserve Current Administrator Account
        |--------------------------------------------------------------------------
        |
        | New admin email: ronak@gmail.com
        |
        | Do not reset an existing administrator's password whenever
        | php artisan db:seed is executed.
        |
        | If the old demo administrator exists, update that same account.
        | If no administrator exists, create the new administrator.
        |
        */

        $adminEmail = 'ronak@gmail.com';

        $admin = User::where('email', $adminEmail)->first();

        // Do not accidentally promote an existing regular player.
        if ($admin && ! $admin->is_admin) {
            throw new \RuntimeException(
                'The admin email is already assigned to a non-admin account.'
            );
        }

        if (! $admin) {

            // Reuse an existing administrator instead of creating duplicates.
            $admin = User::where('is_admin', true)
                ->orderBy('id')
                ->first();

            if ($admin) {

                // Preserve the existing account and its password.
                $admin->email = $adminEmail;

            } else {

                // Create a new admin only when no admin account exists.
                // The ADMIN_PASSWORD environment variable can override
                // this temporary local-development password.
                $admin = new User();

                $admin->name = 'Ronak Solanki';
                $admin->email = $adminEmail;

                $admin->password = Hash::make(
                    env('ADMIN_PASSWORD', 'Ronak@123')
                );
            }
        }

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Ensure Administrator Wallet Exists
        |--------------------------------------------------------------------------
        |
        | Existing wallet balances are preserved.
        |
        */

        Wallet::firstOrCreate(
            ['user_id' => $admin->id],
            ['coins' => 100]
        );

        /*
        |--------------------------------------------------------------------------
        | Unlock Level 01 For Administrator
        |--------------------------------------------------------------------------
        |
        | Existing player progress is not overwritten.
        |
        */

        $firstLevel = Level::where('number', 1)
            ->where('is_published', true)
            ->first();

        if ($firstLevel) {

            UserLevelProgress::firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'level_id' => $firstLevel->id,
                ],
                [
                    'unlocked' => true,
                    'completed' => false,
                    'best_score' => 0,
                    'stars' => 0,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Level Difficulty
    |--------------------------------------------------------------------------
    */

    private function difficultyFor(int $number): string
    {
        return match (intdiv($number - 1, 5)) {

            0 => 'tutorial',

            1 => 'easy',

            2 => 'medium',

            3 => 'hard',

            default => 'advanced',

        };
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Level Configuration
    |--------------------------------------------------------------------------
    |
    | Creates a guaranteed-clear route.
    | Obstacles are placed outside the intended route.
    |
    */

    private function buildLevelConfig(int $number): array
    {
        $stage = intdiv($number - 1, 5);

        $size = 8 + $stage;

        $rows = $size;
        $cols = $size;

        $route = [];

        /*
        |--------------------------------------------------------------------------
        | Start At Lower-Left Area
        |--------------------------------------------------------------------------
        */

        for ($x = 1; $x <= $cols - 2; $x++) {

            $route[] = [
                'x' => $x,
                'y' => $rows - 2,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Move Up Along Right Side
        |--------------------------------------------------------------------------
        */

        for ($y = $rows - 3; $y >= 1; $y--) {

            $route[] = [
                'x' => $cols - 2,
                'y' => $y,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Move Left Along Upper Row
        |--------------------------------------------------------------------------
        */

        for ($x = $cols - 3; $x >= 1; $x--) {

            $route[] = [
                'x' => $x,
                'y' => 1,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Determine Apple Count
        |--------------------------------------------------------------------------
        */

        $appleCount = match ($stage) {

            0 => 1,

            1 => 2,

            2 => 3,

            3 => 3,

            default => 4,

        };

        $lastRouteIndex = count($route) - 1;

        $apples = [];

        for ($index = 1; $index <= $appleCount; $index++) {

            $routeIndex = (int) round(
                ($index * $lastRouteIndex) / ($appleCount + 1)
            );

            $apples[] = $route[$routeIndex];
        }

        /*
        |--------------------------------------------------------------------------
        | Golden Apple Placement
        |--------------------------------------------------------------------------
        |
        | A golden apple appears every fifth level.
        | It is placed on the intended route.
        |
        */

        $goldenApples = [];

        if ($number % 5 === 0 && $lastRouteIndex > 2) {

            $used = [];

            foreach (
                array_merge(
                    [$route[0], $route[$lastRouteIndex]],
                    $apples
                ) as $point
            ) {

                $used[
                    $point['x'] . ':' . $point['y']
                ] = true;
            }

            $preferredIndex = max(
                1,
                min(
                    $lastRouteIndex - 1,
                    (int) floor($lastRouteIndex * 0.65)
                )
            );

            for (
                $offset = 0;
                $offset < $lastRouteIndex;
                $offset++
            ) {

                $candidateIndex = 1 + (
                    ($preferredIndex - 1 + $offset)
                    % ($lastRouteIndex - 1)
                );

                $candidate = $route[$candidateIndex];

                $candidateKey =
                    $candidate['x'] . ':' . $candidate['y'];

                if (! isset($used[$candidateKey])) {

                    $goldenApples[] = $candidate;

                    break;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Identify Route Cells
        |--------------------------------------------------------------------------
        */

        $routeKeys = [];

        foreach ($route as $point) {

            $routeKeys[
                $point['x'] . ':' . $point['y']
            ] = true;
        }

        /*
        |--------------------------------------------------------------------------
        | Find Obstacle Candidate Cells
        |--------------------------------------------------------------------------
        |
        | Only cells outside the guaranteed route are considered.
        |
        */

        $candidates = [];

        for ($y = 2; $y <= $rows - 3; $y++) {

            for ($x = 1; $x <= $cols - 3; $x++) {

                $key = $x . ':' . $y;

                if (! isset($routeKeys[$key])) {

                    $candidates[] = [
                        'x' => $x,
                        'y' => $y,
                    ];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Deterministic Obstacle Placement
        |--------------------------------------------------------------------------
        */

        usort(
            $candidates,
            function (
                array $left,
                array $right
            ) use ($number): int {

                $leftRank =
                    (
                        ($left['x'] * 31)
                        + ($left['y'] * 17)
                        + ($number * 13)
                    ) % 101;

                $rightRank =
                    (
                        ($right['x'] * 31)
                        + ($right['y'] * 17)
                        + ($number * 13)
                    ) % 101;

                return $leftRank <=> $rightRank
                    ?: ($left['y'] <=> $right['y'])
                    ?: ($left['x'] <=> $right['x']);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Obstacle Difficulty
        |--------------------------------------------------------------------------
        */

        $obstacleCount = match ($stage) {

            0 => $number <= 2 ? 0 : 1,

            1 => 2 + ($number % 2),

            2 => 3 + ($number % 2),

            3 => 4 + ($number % 3),

            default => 6 + ($number % 3),

        };

        $obstacles = array_slice(
            $candidates,
            0,
            min($obstacleCount, count($candidates))
        );

        /*
        |--------------------------------------------------------------------------
        | Move Limit
        |--------------------------------------------------------------------------
        */

        $requiredMoves = count($route) - 1;

        $moveLimit = max(
            $requiredMoves + 5,
            (int) ceil($requiredMoves / 0.55)
        );

        /*
        |--------------------------------------------------------------------------
        | Final Level Configuration
        |--------------------------------------------------------------------------
        */

        return [

            'grid' => [
                'rows' => $rows,
                'cols' => $cols,
            ],

            'wormStart' => $route[0],

            'wormLength' => 1,

            'apples' => $apples,

            'goldenApples' => array_values($goldenApples),

            'obstacles' => array_values($obstacles),

            'portal' => $route[$lastRouteIndex],

            'moveLimit' => $moveLimit,

            'objective' => 'collect_all_apples_and_reach_portal',

            'plannedRoute' => array_values($route),

        ];
    }
}
