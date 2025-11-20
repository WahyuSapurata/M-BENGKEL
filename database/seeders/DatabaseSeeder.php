<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Coa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $user = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'nama' => 'Admin',
                'role' => 'admin',
                'password_hash' => '<>password',
                'password' => Hash::make('<>password'),
            ]
        );

        $coas = [
            // Aset
            ['kode' => '101', 'nama' => 'Kas', 'tipe' => 'aset'],
            ['kode' => '102', 'nama' => 'Bank', 'tipe' => 'aset'],

            // Kewajiban
            ['kode' => '201', 'nama' => 'Hutang Usaha', 'tipe' => 'kewajiban'],

            // Modal
            ['kode' => '301', 'nama' => 'Persediaan (Modal)', 'tipe' => 'modal'],

            // Pendapatan
            ['kode' => '401', 'nama' => 'Pendapatan Jasa', 'tipe' => 'pendapatan'],
            ['kode' => '402', 'nama' => 'Pendapatan Sparepart', 'tipe' => 'pendapatan'],

            // Beban
            ['kode' => '504', 'nama' => 'Beban Selisih Persediaan / HPP', 'tipe' => 'beban'],
        ];

        foreach ($coas as $coa) {
            Coa::updateOrCreate(
                ['kode' => $coa['kode']], // cari berdasarkan kode (unique)
                [
                    'nama' => $coa['nama'],
                    'tipe' => $coa['tipe'],
                ]
            );
        }
    }
}
