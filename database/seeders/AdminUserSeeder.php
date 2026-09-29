<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            [
                'email' => 'admin@marketplaceblose.com',
            ],
            [
                'name' => 'Marketplace Admin',
                'phone' => '03000000000',
                'password' => Hash::make('Admin@12345'),
            ]
        );

        // Admin role assign
        $admin->syncRoles(['admin']);
    }
}
