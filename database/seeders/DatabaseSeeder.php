<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevelProgress;
use App\Game\SideViewLevelCatalog;
use App\Services\WalletService;
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
        $sideViewLevels = SideViewLevelCatalog::all();

        /*
        |--------------------------------------------------------------------------
        | Seed 25 Published Game Levels
        |--------------------------------------------------------------------------
        */

        for ($number = 1; $number <= $releaseLevelCount; $number++) {

            $config = $this->buildLevelConfig($number);
            $sideView = $sideViewLevels[$number] ?? null;
            if ($sideView !== null) {
                // Preserve legacy metadata for older records/tests while
                // attaching the side-view puzzle definition as active gameplay.
                $config['sideView'] = $sideView;
                $config['world'] = $sideView['world'];
                $config['boardMode'] = 'side_view_puzzle';
                $config['layout'] = [
                    'pattern' => 'hand_authored_side_view',
                    'label' => $sideView['lesson'],
                ];
                $config['title'] = sprintf('Level %02d · %s', $number, $sideView['title']);
            }

            // Seed each shipped level once. Re-running DatabaseSeeder must not
            // overwrite level names, hints, maps or publication choices made by
            // an administrator in the management workspace.
            Level::firstOrCreate(
                ['number' => $number],
                [
                    'title' => $config['title'],
                    'difficulty' => $sideView['difficulty'] ?? $this->difficultyFor($number),
                    'grid_rows' => $config['grid']['rows'],
                    'grid_cols' => $config['grid']['cols'],
                    'config' => $config,
                    'is_published' => true,
                ]
            );
        }

        // Future-level publication is controlled by the admin workspace.
        // The release migration hides pre-existing future rows once; seeding
        // must never silently republish or unpublish an administrator choice.

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
                'Collect golden apples across five completed levels.',
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

            Achievement::firstOrCreate(
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
        | Preserve or Create Administrator Account
        |--------------------------------------------------------------------------
        |
        | Production credentials come from ADMIN_EMAIL and ADMIN_PASSWORD.
        | On an already-installed database, an existing administrator is reused
        | when those variables are not set. Test runs use isolated test-only
        | credentials and never affect the real local administrator.
        |
        */

        $isTesting = app()->environment('testing');
        $adminEmail = $isTesting
            ? 'admin@example.test'
            : trim((string) config('game.admin_email', ''));

        $admin = null;

        if ($adminEmail !== '') {
            $admin = User::where('email', $adminEmail)->first();

            // Never promote an existing player just because ADMIN_EMAIL points
            // to their account by mistake.
            if ($admin && ! $admin->is_admin) {
                throw new \RuntimeException(
                    'ADMIN_EMAIL belongs to a non-admin account. Choose a dedicated admin email.'
                );
            }
        }

        if (! $admin) {
            $admin = User::where('is_admin', true)->orderBy('id')->first();
        }

        if (! $admin) {
            $adminPassword = $isTesting
                ? 'testing-admin-password'
                : config('game.admin_password');

            if ($adminEmail === '' || ! is_string($adminPassword) || trim($adminPassword) === '') {
                throw new \RuntimeException(
                    'No administrator exists. Set ADMIN_EMAIL and ADMIN_PASSWORD in your local .env before seeding a fresh installation.'
                );
            }

            $admin = new User();
            $admin->name = $isTesting
                ? 'Test Administrator'
                : (string) config('game.admin_name', 'Game Administrator');
            $admin->email = $adminEmail;
            $admin->password = Hash::make($adminPassword);
        } elseif ($adminEmail !== '' && $admin->email !== $adminEmail) {
            // Update the address only when explicitly configured. Keep the
            // existing password and all admin-related records unchanged.
            $emailInUse = User::where('email', $adminEmail)
                ->where('id', '!=', $admin->id)
                ->exists();

            if ($emailInUse) {
                throw new \RuntimeException('ADMIN_EMAIL is already assigned to another account.');
            }

            $admin->email = $adminEmail;
        }

        $admin->forceFill(['is_admin' => true])->save();

        /*
        |--------------------------------------------------------------------------
        | Ensure Administrator Wallet Exists
        |--------------------------------------------------------------------------
        |
        | Existing wallet balances are preserved.
        |
        */

        app(WalletService::class)->getOrCreate($admin);

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

    /**
     * Build the legacy board metadata retained for compatibility with older
     * progress/tests. run() attaches the hand-authored side-view puzzle map
     * as the active board for every published level in this release.
     */
    private function buildLevelConfig(int $number): array
    {
        $stage = intdiv($number - 1, 5);
        // Legacy grid dimensions remain in the config for older records and
        // compatibility checks; the active side-view board is 14×8.
        $rows = 8 + intdiv($stage + 1, 2);
        $cols = 8 + intdiv($stage + 1, 2);

        $worlds = [
            ['code' => 'meadow', 'name' => 'Green Meadow', 'theme' => 'meadow', 'description' => 'A bright place to learn the paths.'],
            ['code' => 'forest', 'name' => 'Enchanted Forest', 'theme' => 'forest', 'description' => 'Follow winding trails through the forest.'],
            ['code' => 'ruins', 'name' => 'Ancient Ruins', 'theme' => 'ruins', 'description' => 'Find the key and pass the old stone gates.'],
            ['code' => 'crystal', 'name' => 'Crystal Caves', 'theme' => 'crystal', 'description' => 'Navigate shimmering crystal corridors.'],
            ['code' => 'sky', 'name' => 'Sky Temple', 'theme' => 'sky', 'description' => 'Master the final floating-island trials.'],
        ];

        $world = $worlds[$stage];
        $levelNames = [
            1 => 'First Nibble', 2 => 'Sunny Switchback', 3 => 'Apple Trail', 4 => 'Rocky Bend', 5 => 'Golden Grove',
            6 => 'Fern Maze', 7 => 'Mossy Zigzag', 8 => 'Bramble Crossing', 9 => 'Forest Fork', 10 => 'Emerald Cache',
            11 => 'Ruins Entrance', 12 => 'Keykeeper Hall', 13 => 'Broken Columns', 14 => 'The Locked Gallery', 15 => 'Guardian Gate',
            16 => 'Crystal Crossing', 17 => 'Prism Path', 18 => 'Gemstone Spiral', 19 => 'Blue Quartz Run', 20 => 'Crystal Vault',
            21 => 'Cloudstep', 22 => 'Skyward Switchback', 23 => 'Floating Maze', 24 => 'Storm Path', 25 => 'Sky Temple Trial',
        ];

        $patternIndex = ($number + $stage) % 5;
        $patterns = [
            'horizontal_serpentine',
            'vertical_serpentine',
            'inward_spiral',
            'reverse_horizontal',
            'reverse_vertical',
        ];
        $pattern = $patterns[$patternIndex];
        $route = $this->buildPlannedRoute($pattern, $rows, $cols, $number);
        $lastRouteIndex = count($route) - 1;

        // Later worlds introduce a real key-and-gate objective. The key is
        // always placed earlier on the guaranteed route than the gate.
        $keys = [];
        $switches = [];
        $gates = [];
        $reserved = [];
        $hasKeyGate = $number >= 11 && in_array($number, [11, 13, 15, 17, 19, 21, 23, 25], true);
        $hasSwitchGate = $number >= 12 && in_array($number, [12, 14, 16, 18, 20, 22, 24], true);

        if (($hasKeyGate || $hasSwitchGate) && $lastRouteIndex >= 8) {
            $triggerIndex = max(2, (int) floor($lastRouteIndex * 0.28));
            $gateIndex = max($triggerIndex + 2, (int) floor($lastRouteIndex * 0.68));
            $trigger = $route[$triggerIndex];
            $gate = $route[$gateIndex];
            if ($hasKeyGate) {
                $keys[] = $trigger;
            } else {
                $switches[] = $trigger;
            }
            $gates[] = $gate;
            $reserved[$this->pointKey($trigger)] = true;
            $reserved[$this->pointKey($gate)] = true;
        }

        $appleCount = match ($stage) {
            0 => $number <= 2 ? 1 : 1 + ($number % 2),
            1 => 2 + ($number % 2),
            2 => 3 + ($number % 2),
            3 => 3 + ($number % 2),
            default => 4 + ($number % 2),
        };

        $apples = [];
        for ($index = 1; $index <= $appleCount; $index++) {
            $preferred = (int) round(($index * $lastRouteIndex) / ($appleCount + 1));
            $routeIndex = $this->findFreeRouteIndex($route, $preferred, $reserved);
            if ($routeIndex !== null) {
                $point = $route[$routeIndex];
                $apples[] = $point;
                $reserved[$this->pointKey($point)] = true;
            }
        }

        $goldenApples = [];
        if ($number % 5 === 0 && $lastRouteIndex > 2) {
            $preferred = max(1, min($lastRouteIndex - 1, (int) floor($lastRouteIndex * 0.82)));
            $goldenIndex = $this->findFreeRouteIndex($route, $preferred, $reserved);
            if ($goldenIndex !== null) {
                $golden = $route[$goldenIndex];
                $goldenApples[] = $golden;
                $reserved[$this->pointKey($golden)] = true;
            }
        }

        // Special fruit is introduced gradually. Positions remain on the
        // guaranteed route so every item is reachable without relying on luck.
        $megaApples = [];
        if (in_array($number, [7, 14, 21], true)) {
            $megaIndex = $this->findFreeRouteIndex($route, (int) floor($lastRouteIndex * 0.48), $reserved);
            if ($megaIndex !== null) {
                $megaApples[] = $route[$megaIndex];
                $reserved[$this->pointKey($route[$megaIndex])] = true;
            }
        }

        $mysteryApples = [];
        if (in_array($number, [4, 8, 12, 16, 20, 24], true)) {
            $mysteryIndex = $this->findFreeRouteIndex($route, (int) floor($lastRouteIndex * 0.58), $reserved);
            if ($mysteryIndex !== null) {
                $effects = ['bonus_score', 'extra_growth', 'bonus_moves'];
                $mysteryApples[] = array_merge($route[$mysteryIndex], [
                    'effect' => $effects[$number % count($effects)],
                ]);
                $reserved[$this->pointKey($route[$mysteryIndex])] = true;
            }
        }

        $routeKeys = [];
        foreach ($route as $point) {
            $routeKeys[$this->pointKey($point)] = true;
        }

        $candidates = [];
        for ($y = 1; $y < $rows - 1; $y++) {
            for ($x = 1; $x < $cols - 1; $x++) {
                $point = ['x' => $x, 'y' => $y];
                $key = $this->pointKey($point);
                if (isset($routeKeys[$key]) || isset($reserved[$key])) {
                    continue;
                }
                $candidates[] = $point;
            }
        }

        // Deterministic, level-specific obstacle placement keeps seed runs
        // reproducible while producing different rock clusters per board.
        usort($candidates, function (array $left, array $right) use ($number): int {
            $leftRank = (($left['x'] * 31) + ($left['y'] * 17) + ($number * 13) + (($left['x'] * $left['y'] + $number * 7) % 97)) % 211;
            $rightRank = (($right['x'] * 31) + ($right['y'] * 17) + ($number * 13) + (($right['x'] * $right['y'] + $number * 7) % 97)) % 211;
            return $leftRank <=> $rightRank ?: ($left['y'] <=> $right['y']) ?: ($left['x'] <=> $right['x']);
        });

        // Hazards are placed away from the guaranteed route. Mud is a
        // non-lethal blocked tile; thorns are lethal, matching the mission guide.
        $thorns = [];
        $mudPits = [];
        $featureOffset = 0;
        if (in_array($number, [6, 9, 13, 17, 21, 24], true) && isset($candidates[$featureOffset])) {
            $thorns[] = $candidates[$featureOffset++];
        }
        if (in_array($number, [7, 10, 14, 18, 22, 25], true) && isset($candidates[$featureOffset])) {
            $mudPits[] = $candidates[$featureOffset++];
        }
        $obstacleCount = min(max(0, count($candidates) - $featureOffset), 3 + ($stage * 2) + ($number % 3));
        $obstacles = array_slice($candidates, $featureOffset, $obstacleCount);

        $moveLimit = max($lastRouteIndex + 8, (int) ceil($lastRouteIndex / 0.62));
        $requiredLength = match ($number) {
            5 => 4,
            10 => 4,
            15 => 6,
            20 => 5,
            25 => 7,
            default => null,
        };
        $missions = [
            'Collect every apple. Learn the turns and find the portal.',
            'Follow the winding trail, collect every apple and reach the portal.',
            'Find the key, unlock the stone gate, collect every apple and escape.',
            'Navigate the crystal corridors and reach the portal with every apple.',
            'Master the final route, collect the treasure and reach the sky portal.',
        ];

        return [
            'title' => sprintf('Level %02d · %s', $number, $levelNames[$number] ?? 'Adventure'),
            'world' => $world,
            'mission' => [
                'title' => $levelNames[$number] ?? 'Adventure',
                'description' => $requiredLength
                    ? "Grow to {$requiredLength} segments, collect the fruit and reach the portal."
                    : $missions[$stage],
                'requiredLength' => $requiredLength,
            ],
            'layout' => [
                'pattern' => $pattern,
                'label' => str_replace('_', ' ', ucfirst($pattern)),
            ],
            'grid' => ['rows' => $rows, 'cols' => $cols],
            'boardMode' => 'top_down_grid',
            'wormStart' => $route[0],
            'wormLength' => 1,
            'apples' => array_values($apples),
            'goldenApples' => array_values($goldenApples),
            'megaApples' => array_values($megaApples),
            'mysteryApples' => array_values($mysteryApples),
            'keys' => array_values($keys),
            'switches' => array_values($switches),
            'gates' => array_values($gates),
            'obstacles' => array_values($obstacles),
            'thorns' => array_values($thorns),
            'mudPits' => array_values($mudPits),
            'portal' => $route[$lastRouteIndex],
            'moveLimit' => $moveLimit,
            'objective' => 'collect_all_apples_and_reach_portal',
            'plannedRoute' => array_values($route),
        ];
    }

    private function buildPlannedRoute(string $pattern, int $rows, int $cols, int $number): array
    {
        return match ($pattern) {
            'horizontal_serpentine' => $this->horizontalSerpentine($rows, $cols, false, false),
            'vertical_serpentine' => $this->verticalSerpentine($rows, $cols, false, false),
            'inward_spiral' => $this->inwardSpiral($rows, $cols, $number % 2 === 0),
            'reverse_horizontal' => $this->horizontalSerpentine($rows, $cols, true, true),
            'reverse_vertical' => $this->verticalSerpentine($rows, $cols, true, true),
            default => $this->horizontalSerpentine($rows, $cols, false, false),
        };
    }

    private function horizontalSerpentine(int $rows, int $cols, bool $fromTop, bool $startRight): array
    {
        $lanes = [];
        if ($fromTop) {
            for ($y = 1; $y <= $rows - 2; $y += 2) $lanes[] = $y;
        } else {
            for ($y = $rows - 2; $y >= 1; $y -= 2) $lanes[] = $y;
        }

        $route = [];
        $rightToLeft = $startRight;
        foreach ($lanes as $laneIndex => $y) {
            $xs = $rightToLeft
                ? range($cols - 2, 1)
                : range(1, $cols - 2);
            foreach ($xs as $x) $this->appendPoint($route, $x, $y);

            if (isset($lanes[$laneIndex + 1])) {
                $nextY = $lanes[$laneIndex + 1];
                $connectorY = (int) (($y + $nextY) / 2);
                $this->appendPoint($route, $rightToLeft ? 1 : $cols - 2, $connectorY);
            }
            $rightToLeft = ! $rightToLeft;
        }
        return $route;
    }

    private function verticalSerpentine(int $rows, int $cols, bool $fromRight, bool $startBottom): array
    {
        $lanes = [];
        if ($fromRight) {
            for ($x = $cols - 2; $x >= 1; $x -= 2) $lanes[] = $x;
        } else {
            for ($x = 1; $x <= $cols - 2; $x += 2) $lanes[] = $x;
        }

        $route = [];
        $bottomToTop = ! $startBottom;
        foreach ($lanes as $laneIndex => $x) {
            $ys = $bottomToTop
                ? range($rows - 2, 1)
                : range(1, $rows - 2);
            foreach ($ys as $y) $this->appendPoint($route, $x, $y);

            if (isset($lanes[$laneIndex + 1])) {
                $nextX = $lanes[$laneIndex + 1];
                $connectorX = (int) (($x + $nextX) / 2);
                $this->appendPoint($route, $connectorX, $bottomToTop ? 1 : $rows - 2);
            }
            $bottomToTop = ! $bottomToTop;
        }
        return $route;
    }

    private function inwardSpiral(int $rows, int $cols, bool $reverse): array
    {
        $route = [];
        $left = 1;
        $right = $cols - 2;
        $top = 1;
        $bottom = $rows - 2;

        while ($left <= $right && $top <= $bottom) {
            for ($x = $left; $x <= $right; $x++) $this->appendPoint($route, $x, $top);
            if ($top === $bottom) break;

            for ($y = $top + 1; $y <= $bottom; $y++) $this->appendPoint($route, $right, $y);
            if ($left === $right) break;

            for ($x = $right - 1; $x >= $left; $x--) $this->appendPoint($route, $x, $bottom);
            for ($y = $bottom - 1; $y >= $top + 2; $y--) $this->appendPoint($route, $left, $y);

            $left++;
            $right--;
            $top += 2;
            $bottom -= 2;
        }

        if ($reverse) $route = array_reverse($route);

        return count($route) >= 2 ? $route : $this->horizontalSerpentine($rows, $cols, false, false);
    }

    private function appendPoint(array &$route, int $x, int $y): void
    {
        $point = ['x' => $x, 'y' => $y];
        $last = $route[count($route) - 1] ?? null;
        if ($last !== null && $last['x'] === $x && $last['y'] === $y) return;
        $route[] = $point;
    }

    private function findFreeRouteIndex(array $route, int $preferred, array $reserved): ?int
    {
        $last = count($route) - 1;
        if ($last < 2) return null;
        for ($offset = 0; $offset < $last - 1; $offset++) {
            $index = 1 + (($preferred - 1 + $offset) % ($last - 1));
            if (! isset($reserved[$this->pointKey($route[$index])])) return $index;
        }
        return null;
    }

    private function pointKey(array $point): string
    {
        return (int) $point['x'] . ':' . (int) $point['y'];
    }

}
