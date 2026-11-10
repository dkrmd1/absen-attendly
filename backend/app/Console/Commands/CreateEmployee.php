<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateEmployee extends Command
{
    protected $signature = 'attendly:create-employee';

    protected $description = 'Buat akun karyawan Attendly dengan password tersembunyi';

    public function handle(): int
    {
        $attributes = [
            'name' => trim((string) $this->ask('Nama karyawan')),
            'email' => mb_strtolower(trim((string) $this->ask('Email'))),
        ];
        $password = (string) $this->secret('Kata sandi (minimal 12 karakter)');
        $passwordConfirmation = (string) $this->secret('Ulangi kata sandi');

        $validator = Validator::make([
            ...$attributes,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'same:password_confirmation'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::query()->create([
            ...$attributes,
            'password' => Hash::make($password),
        ]);

        $this->info('Akun karyawan berhasil dibuat.');

        return self::SUCCESS;
    }
}
