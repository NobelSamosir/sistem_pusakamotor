<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun login pemilik. Email dan password bisa diatur lewat .env:
 *   SEED_OWNER_EMAIL=...
 *   SEED_OWNER_PASSWORD=...
 * Ganti password setelah login pertama.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = DB::table('roles')->where('role_name', 'pemilik')->value('id');

        DB::table('users')->updateOrInsert(
            ['email' => env('SEED_OWNER_EMAIL', 'pemilik@pusakamotor.test')],
            [
                'role_id' => $roleId,
                'name' => 'Pemilik Pusaka Motor',
                'password' => Hash::make(env('SEED_OWNER_PASSWORD', 'PusakaMotor#2026')),
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
