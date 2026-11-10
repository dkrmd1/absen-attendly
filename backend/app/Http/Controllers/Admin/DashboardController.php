<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();
        $employeeCount = User::query()->staff()->count();
        $attendances = Attendance::query()
            ->with('user:id,name,email')
            ->whereHas('user', fn ($query) => $query->staff())
            ->whereDate('work_date', $today)
            ->where('verification_status', '!=', 'rejected')
            ->latest('check_in_at')
            ->get();
        $approvedLeaveCount = LeaveRequest::query()
            ->whereHas('user', fn ($query) => $query->staff())
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereNotIn('user_id', $attendances->pluck('user_id'))
            ->distinct('user_id')
            ->count('user_id');

        $days = collect(range(6, 0))->map(fn (int $offset) => today()->subDays($offset));
        $attendanceCounts = Attendance::query()
            ->selectRaw('work_date, count(*) as total')
            ->whereHas('user', fn ($query) => $query->staff())
            ->where('verification_status', '!=', 'rejected')
            ->whereBetween('work_date', [$days->first(), $today])
            ->groupBy('work_date')
            ->pluck('total', 'work_date');

        return view('admin.dashboard', [
            'employeeCount' => $employeeCount,
            'presentCount' => $attendances->count(),
            'approvedLeaveCount' => $approvedLeaveCount,
            'notCheckedInCount' => max(0, $employeeCount - $attendances->count() - $approvedLeaveCount),
            'lateCount' => $attendances->filter(
                fn (Attendance $attendance) => ($attendance->late_minutes ?? 0) > 0
            )->count(),
            'pendingCount' => LeaveRequest::query()
                ->whereHas('user', fn ($query) => $query->staff())
                ->whereIn('status', ['pending', 'pending_group', 'pending_division', 'pending_director', 'pending_ceo', 'pending_commissioner', 'pending_hr'])
                ->count(),
            'attendances' => $attendances->take(8),
            'pendingRequests' => LeaveRequest::query()
                ->with('user:id,name,email')
                ->whereHas('user', fn ($query) => $query->staff())
                ->whereIn('status', ['pending', 'pending_group', 'pending_division', 'pending_director', 'pending_ceo', 'pending_commissioner', 'pending_hr'])
                ->latest()
                ->take(5)
                ->get(),
            'chartDays' => $days->map(fn (Carbon $date) => [
                'label' => $date->translatedFormat('D'),
                'date_label' => $date->translatedFormat('d M'),
                'date' => $date->toDateString(),
                'total' => (int) ($attendanceCounts[$date->toDateString()] ?? 0),
                'percentage' => $employeeCount > 0
                    ? (int) round(min(100, ($attendanceCounts[$date->toDateString()] ?? 0) / $employeeCount * 100))
                    : 0,
            ]),
        ]);
    }
}
