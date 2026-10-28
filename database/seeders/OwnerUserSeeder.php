<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class OwnerUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('JOOAL_OWNER_EMAIL');
        $password = env('JOOAL_OWNER_PASSWORD');

        if (! $email || ! $password) {
            if ($this->command) {
                $this->command->warn('Owner seeder dilewati: JOOAL_OWNER_EMAIL dan JOOAL_OWNER_PASSWORD harus disediakan.');
            }

            return;
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('JOOAL_OWNER_PASSWORD harus memiliki minimal 8 karakter.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = env('JOOAL_OWNER_NAME', 'JOOAL Owner');
        $user->role = User::ROLE_OWNER;
        $user->password = Hash::make($password);
        $user->save();

        if ($this->command) {
            $this->command->info('Akun owner JOOAL siap digunakan.');
        }
    }
}
