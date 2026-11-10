<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'digits:16', Rule::unique('users', 'nik')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'npwp_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'nik.digits' => 'NIK harus terdiri dari 16 angka.',
            'nik.unique' => 'NIK sudah digunakan akun lain.',
            'npwp_document.mimes' => 'Lampiran NPWP harus berupa PDF atau gambar JPG/PNG.',
            'npwp_document.max' => 'Ukuran lampiran NPWP maksimal 5 MB.',
            'profile_photo.mimes' => 'Foto profil harus berupa gambar JPG atau PNG.',
            'profile_photo.max' => 'Ukuran foto profil maksimal 5 MB.',
        ]);

        $disk = Storage::disk('local');
        $newFiles = [];
        $oldFiles = [];
        foreach ([
            'npwp_document' => 'private/employee-documents/npwp',
            'profile_photo' => 'private/employee-photos',
        ] as $field => $directory) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $path = $request->file($field)->store($directory, 'local');
            if ($path === false) {
                throw new RuntimeException("Gagal menyimpan berkas {$field}.");
            }
            $newFiles[$field] = $path;
        }

        try {
            DB::transaction(function () use ($user, $attributes, $newFiles, &$oldFiles): void {
                $user->name = $attributes['name'];
                $user->nik = $attributes['nik'] ?? null;
                $user->phone = $attributes['phone'] ?? null;
                $user->address = $attributes['address'] ?? null;

                foreach ([
                    'npwp_document' => 'npwp_document_path',
                    'profile_photo' => 'profile_photo_path',
                ] as $field => $column) {
                    if (isset($newFiles[$field])) {
                        if ($user->{$column} !== null) {
                            $oldFiles[] = $user->{$column};
                        }
                        $user->{$column} = $newFiles[$field];
                    }
                }

                $user->save();
            });
        } catch (Throwable $error) {
            foreach ($newFiles as $path) {
                $disk->delete($path);
            }

            throw $error;
        }

        foreach ($oldFiles as $path) {
            if (! $disk->delete($path)) {
                report(new RuntimeException("Gagal menghapus berkas profil lama: {$path}"));
            }
        }

        $user->loadMissing(['department:id,name', 'baseLocation:id,name']);

        return response()->json(['data' => app(AuthController::class)->profileData($user)]);
    }

    public function npwpDocument(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->npwp_document_path !== null
                && Storage::disk('local')->exists($user->npwp_document_path),
            404,
            'Lampiran NPWP tidak ditemukan.',
        );

        return Storage::disk('local')->download(
            $user->npwp_document_path,
            'lampiran-npwp'.'.'.pathinfo($user->npwp_document_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function photo(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->profile_photo_path !== null
                && Storage::disk('local')->exists($user->profile_photo_path),
            404,
            'Foto profil tidak ditemukan.',
        );

        return Storage::disk('local')->download(
            $user->profile_photo_path,
            'foto-profil'.'.'.pathinfo($user->profile_photo_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
