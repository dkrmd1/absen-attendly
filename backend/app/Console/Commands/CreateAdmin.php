<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'attendly:create-admin';

    protected $description = 'Buat akun administrator dashboard Attendly';

    public function handle(): int
    {
        $attributes = [
            'name' => trim((string) $this->ask('Nama administrator')),
            'email' => mb_strtolower(trim((string) $this->ask('Email administrator'))),
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

        $admin = User::query()->create([
            ...$attributes,
            'password' => Hash::make($password),
        ]);
        $admin->forceFill(['role' => 'admin'])->save();

        $this->info('Administrator berhasil dibuat.');

        return self::SUCCESS;
    }
}
