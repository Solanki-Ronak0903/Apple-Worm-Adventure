<?php

namespace App\Game;

/**
 * Server-only regression routes for the 25 released side-view puzzles.
 * These are deliberately not stored in the public level configuration sent
 * to the browser. A level can only be published when its approved route still
 * completes successfully against the production puzzle rules.
 */
class SideViewLevelSolutions
{
    public static function for(int $number): ?string
    {
        return self::all()[$number] ?? null;
    }

    public static function all(): array
    {
        return [
            1 => 'rrruurrrrrrr',
            2 => 'rruuurrrrrrrrdd',
            3 => 'uurrruurrdruurrrrdd',
            4 => 'rruurrrurrdddrrr',
            5 => 'rrrrrrrrrulullllllllddlll',
            6 => 'rruurrrrrrurrdruuluuuuurddl',
            7 => 'ruurrrrullllluurrrrrrrrrrdd',
            8 => 'lllluulllldluuurulld',
            9 => 'ruurrullluurrrrrurrrrrddd',
            10 => 'rrrrrururrrrrddllllluurrrr',
            11 => 'rruurrullluurrrrrrrrd',
            12 => 'lllulullulllddrrrrruullulldlddl',
            13 => 'rruurrullluurrrrrrrrrdd',
            14 => 'luullurrruullllllllll',
            15 => 'ruurrrrururrrrrddlulllldrrurruulllurrrd',
            16 => 'ruurrrulllluurrrrrrrrrrdd',
            17 => 'uurrullluurrrrrrrrrrrdd',
            18 => 'rrururrrddllluurrrrddrrr',
            19 => 'rrrrruldllluurrurrurrrrrrddl',
            20 => 'rrrrrururulllurrrrdrr',
            21 => 'uurrullluurrrrrurrrrrdrdd',
            22 => 'rrrrrrrullllluurrurrrurrrd',
            23 => 'luulllurrrruulllllllldldl',
            24 => 'rrrrrururullllurrrrrrr',
            25 => 'lllllululuruuldlddll',
        ];
    }
}
