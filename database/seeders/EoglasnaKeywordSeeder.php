<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EoglasnaKeyword;

class EoglasnaKeywordSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['query' => 'Andrija Glavaš', 'scope' => 'notice', 'deep_scan' => true, 'enabled' => true],
            ['query' => 'Andrija Glavaš', 'scope' => 'court', 'deep_scan' => true, 'enabled' => true],
            ['query' => 'Andrija Glavaš', 'scope' => 'institution', 'deep_scan' => true, 'enabled' => true],
            ['query' => 'Andrej Gungl', 'scope' => 'court', 'deep_scan' => true, 'enabled' => true],
            ['query' => 'Andrej Gungl', 'scope' => 'notice', 'deep_scan' => true, 'enabled' => true],
            ['query' => 'Slavonska 8', 'scope' => 'institution', 'deep_scan' => true, 'enabled' => true],
            ['query' => 'Slavonska 8', 'scope' => 'notice', 'deep_scan' => true, 'enabled' => true],
            ['query' => 'Slavonska 8', 'scope' => 'court', 'deep_scan' => true, 'enabled' => true],
        ];

        foreach ($defaults as $d) {
            EoglasnaKeyword::firstOrCreate(
                ['query' => $d['query'], 'scope' => $d['scope']],
                $d
            );
        }
    }
}
