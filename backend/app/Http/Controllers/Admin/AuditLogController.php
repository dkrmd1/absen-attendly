<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        if (
            isset($filters['action'])
            && ! AuditLog::query()->where('action', $filters['action'])->exists()
        ) {
            throw ValidationException::withMessages([
                'action' => 'Jenis aktivitas yang dipilih tidak tersedia.',
            ]);
        }

        $logs = AuditLog::query()
            ->when(
                isset($filters['q']),
                fn ($query) => $query->where(function ($search) use ($filters): void {
                    $term = '%'.$filters['q'].'%';
                    $search->where('actor_name', 'like', $term)
                        ->orWhere('actor_email', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('module', 'like', $term);
                }),
            )
            ->when(
                isset($filters['action']),
                fn ($query) => $query->where('action', $filters['action']),
            )
            ->when(
                isset($filters['from']),
                fn ($query) => $query->whereDate('created_at', '>=', $filters['from']),
            )
            ->when(
                isset($filters['to']),
                fn ($query) => $query->whereDate('created_at', '<=', $filters['to']),
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.audit-logs.index', compact('logs', 'actions', 'filters'));
    }
}
