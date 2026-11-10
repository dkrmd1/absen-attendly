<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkLocationController extends Controller
{
    public function index(): View
    {
        $locations = WorkLocation::query()
            ->withCount(['employees as employees_count' => fn ($query) => $query->staff()])
            ->orderBy('name')
            ->get();

        return view('admin.locations.index', compact('locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        WorkLocation::query()->create($this->validatedAttributes($request));

        return redirect()->route('admin.locations.index')->with('success', 'Lokasi kerja berhasil ditambahkan.');
    }

    public function update(Request $request, WorkLocation $workLocation): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request);

        if (! $attributes['is_active'] && $workLocation->is_active) {
            $hasEmployees = $workLocation->employees()->staff()->exists();
            $hasAssignments = $workLocation->assignments()->whereDate('end_date', '>=', today())->exists();

            if ($hasEmployees || $hasAssignments) {
                throw ValidationException::withMessages([
                    'is_active' => 'Pindahkan karyawan dan penugasan yang masih berlaku sebelum menonaktifkan lokasi ini.',
                ]);
            }
        }

        $workLocation->update($attributes);

        return redirect()->route('admin.locations.index')->with('success', 'Lokasi kerja berhasil diperbarui.');
    }

    /**
     * @return array{name: string, address: ?string, latitude: ?string, longitude: ?string, radius_meters: int, is_active: bool}
     */
    private function validatedAttributes(Request $request): array
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:20,2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (
            (($attributes['latitude'] ?? null) === null)
            !== (($attributes['longitude'] ?? null) === null)
        ) {
            throw ValidationException::withMessages([
                'latitude' => 'Latitude dan longitude harus diisi bersama atau dikosongkan bersama.',
            ]);
        }

        return $attributes;
    }
}
