<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Main Branch',
                'location' => 'City Center',
                'contact_number' => '+1 555-0101'
            ],
            [
                'name' => 'North Branch', 
                'location' => 'Northern District',
                'contact_number' => '+1 555-0102'
            ],
            [
                'name' => 'South Branch',
                'location' => 'Southern Plaza',
                'contact_number' => '+1 555-0103'
            ]
            
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }
    }
}
