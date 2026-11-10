<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResetAdminPassword extends Command
{
    protected $signature = 'attendly:reset-admin-password {email : Email akun administrator}';

    protected $description = 'Atur ulang password akun administrator dashboard Attendly';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $admin = User::query()
            ->where('email', $email)
            ->where('role', 'admin')
            ->first();

        if (! $admin) {
            $this->error('Akun administrator dengan email tersebut tidak ditemukan.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Kata sandi baru (minimal 12 karakter)');
        $passwordConfirmation = (string) $this->secret('Ulangi kata sandi baru');
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'password' => ['required', 'string', 'min:12', 'same:password_confirmation'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $admin->forceFill(['password' => Hash::make($password)])->save();

        $this->info('Kata sandi administrator berhasil diperbarui.');

        return self::SUCCESS;
    }
}
