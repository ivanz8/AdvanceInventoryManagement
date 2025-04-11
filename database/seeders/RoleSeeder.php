<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'description' => 'System Administrator'],
            ['name' => 'Manager', 'description' => 'Branch Manager'],
            ['name' => 'Staff', 'description' => 'Regular Staff']
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
