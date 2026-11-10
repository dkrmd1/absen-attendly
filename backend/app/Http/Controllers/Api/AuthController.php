<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LeaveApprovalWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau kata sandi tidak sesuai.',
            ], 422);
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $user->createToken('attendly-mobile')->plainTextToken,
            'user' => $this->userData($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    /**
     * @return array{id: int, name: string, email: string, nik: ?string, phone: ?string, address: ?string, has_npwp_document: bool, npwp_document_extension: ?string, has_profile_photo: bool, can_review_leave_requests: bool}
     */
    private function userData(User $user): array
    {
        $user->loadMissing(['department:id,name', 'baseLocation:id,name']);

        return $this->profileData($user);
    }

    /**
     * @return array{id: int, name: string, email: string, nik: ?string, phone: ?string, address: ?string, has_npwp_document: bool, has_profile_photo: bool, can_review_leave_requests: bool}
     */
    public function profileData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nik' => $user->nik,
            'phone' => $user->phone,
            'address' => $user->address,
            'has_npwp_document' => $user->npwp_document_path !== null,
            'npwp_document_extension' => $user->npwp_document_path === null
                ? null
                : strtolower(pathinfo($user->npwp_document_path, PATHINFO_EXTENSION)),
            'has_profile_photo' => $user->profile_photo_path !== null,
            'department' => $user->department?->name,
            'base_location' => $user->baseLocation?->name,
            'can_review_leave_requests' => app(LeaveApprovalWorkflow::class)->canReview($user),
        ];
    }
}
