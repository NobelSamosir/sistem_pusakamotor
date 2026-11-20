<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            ['role_name' => 'pemilik'],
            [
                'description' => 'Pemilik Showroom Pusaka Motor, akses penuh ke seluruh modul',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
