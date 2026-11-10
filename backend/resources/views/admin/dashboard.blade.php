@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/tailwindadmin/css/owl.carousel.min.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/tailwindadmin/css/owl.theme.default.min.css') }}">
    <style>
        .dashboard-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;padding:24px;border-radius:12px}
        .dashboard-hero-copy{display:flex;min-width:0;align-items:center;gap:14px}
        .dashboard-stat-carousel .item,.dashboard-stat-carousel .card{width:100%;height:100%}
        .dashboard-panel-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(410px,1fr);gap:24px;margin-bottom:24px}
        .dashboard-panel-grid:last-child{margin-bottom:0}
        .dashboard-panel-grid .card{height:100%;margin-bottom:0}
        .dashboard-status-content{display:grid;grid-template-columns:160px minmax(0,1fr);align-items:center;gap:16px}
        .dashboard-status-total{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
        .dashboard-chart-viewport{overflow-x:auto}
        .dashboard-chart-viewport svg{display:block;width:100%;min-width:520px;height:auto}
        @media(max-width:1279px){
            .dashboard-panel-grid{grid-template-columns:minmax(0,1fr)}
        }
        @media(max-width:640px){
            .dashboard-hero{align-items:flex-start;padding:18px}
            .dashboard-hero-copy{align-items:flex-start}
            .dashboard-panel-grid{gap:14px;margin-bottom:14px}
            .dashboard-panel-grid .card-body{padding:18px}
            .dashboard-status-content{grid-template-columns:minmax(0,1fr);justify-items:center}
            .dashboard-status-legend{width:100%}
        }
    </style>
@endpush

@section('content')
    @php
        $plotLeft = 42;
        $plotTop = 24;
        $plotWidth = 570;
        $plotHeight = 126;
        $lastIndex = max(1, $chartDays->count() - 1);
        $chartPoints = $chartDays->values()->map(function (array $day, int $index) use ($plotLeft, $plotTop, $plotWidth, $plotHeight, $lastIndex): array {
            return [
                'x' => $plotLeft + ($index / $lastIndex * $plotWidth),
                'y' => $plotTop + ((100 - $day['percentage']) / 100 * $plotHeight),
                ...$day,
            ];
        });
        $linePath = $chartPoints->map(fn (array $point) => number_format($point['x'], 1, '.', '').','.number_format($point['y'], 1, '.', ''))->implode(' ');
        $areaPath = 'M '.$plotLeft.','.($plotTop + $plotHeight).' L '.$linePath.' L '.($plotLeft + $plotWidth).','.($plotTop + $plotHeight).' Z';
        $circumference = 251.33;
        $presentLength = $employeeCount > 0 ? $presentCount / $employeeCount * $circumference : 0;
        $leaveLength = $employeeCount > 0 ? $approvedLeaveCount / $employeeCount * $circumference : 0;
        $notCheckedLength = max(0, $circumference - $presentLength - $leaveLength);
    @endphp

    <section class="dashboard-hero relative overflow-hidden bg-lightsecondary">
        <div class="dashboard-hero-copy">
            <span class="flex h-[50px] w-[50px] items-center justify-center rounded-full bg-white text-xl font-semibold text-primary shadow-sm">
                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-dark">Selamat datang, {{ auth()->user()->name }}!</h2>
                <p class="text-link">Ringkasan kehadiran tim · {{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>
        <iconify-icon icon="solar:calendar-mark-linear" class="hidden text-5xl text-secondary sm:block" aria-hidden="true"></iconify-icon>
    </section>

    <section class="mb-6">
        <div class="owl-carousel owl-theme counter-carousel dashboard-stat-carousel">
            <div class="item">
                <div class="card mb-0 shadow-none bg-lightprimary dark:bg-lightprimary">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="mb-3 flex justify-center">
                                <iconify-icon icon="solar:users-group-rounded-linear" class="text-5xl text-primary" aria-hidden="true"></iconify-icon>
                            </div>
                            <p class="mb-1 font-semibold text-primary">Total karyawan</p>
                            <h2 class="mb-0 text-xl font-semibold text-primary" data-stat="employees" data-value="{{ $employeeCount }}">{{ number_format($employeeCount) }}</h2>
                            <p class="mt-1 text-sm text-link">Akun aktif terdaftar</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="item">
                <div class="card mb-0 shadow-none bg-lightsuccess dark:bg-darksuccess">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="mb-3 flex justify-center">
                                <iconify-icon icon="solar:check-circle-linear" class="text-5xl text-success" aria-hidden="true"></iconify-icon>
                            </div>
                            <p class="mb-1 font-semibold text-success">Hadir hari ini</p>
                            <h2 class="mb-0 text-xl font-semibold text-success" data-stat="present" data-value="{{ $presentCount }}">{{ number_format($presentCount) }}</h2>
                            <p class="mt-1 text-sm text-link">{{ $employeeCount > 0 ? number_format($presentCount / $employeeCount * 100, 1) : '0.0' }}% dari total karyawan</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="item">
                <div class="card mb-0 shadow-none bg-lightwarning dark:bg-darkwarning">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="mb-3 flex justify-center">
                                <iconify-icon icon="solar:calendar-date-linear" class="text-5xl text-warning" aria-hidden="true"></iconify-icon>
                            </div>
                            <p class="mb-1 font-semibold text-warning">Izin / cuti aktif</p>
                            <h2 class="mb-0 text-xl font-semibold text-warning" data-stat="approved-leave" data-value="{{ $approvedLeaveCount }}">{{ number_format($approvedLeaveCount) }}</h2>
                            <p class="mt-1 text-sm text-link">Pengajuan disetujui hari ini</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="item">
                <div class="card mb-0 shadow-none bg-lighterror dark:bg-darkerror">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="mb-3 flex justify-center">
                                <iconify-icon icon="solar:clock-circle-linear" class="text-5xl text-error" aria-hidden="true"></iconify-icon>
                            </div>
                            <p class="mb-1 font-semibold text-error">Belum check-in</p>
                            <h2 class="mb-0 text-xl font-semibold text-error" data-stat="not-checked-in" data-value="{{ $notCheckedInCount }}">{{ number_format($notCheckedInCount) }}</h2>
                            <p class="mt-1 text-sm text-link">Di luar izin yang disetujui</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-panel-grid">

        <article>
            <div class="card h-full">
                <div class="card-body">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="card-title">Grafik Kehadiran 7 Hari Terakhir</h2>
                            <p class="card-subtitle">Persentase karyawan yang hadir per hari</p>
                        </div>
                        <a class="btn" href="{{ route('admin.attendances.index') }}">Lihat absensi</a>
                    </div>
                    <div class="dashboard-chart-viewport">
                        <svg viewBox="0 0 640 196" role="img" aria-label="Persentase kehadiran karyawan dalam tujuh hari terakhir">
                            <defs>
                                <linearGradient id="attendance-area" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#4875df" stop-opacity=".2"/>
                                    <stop offset="100%" stop-color="#4875df" stop-opacity=".01"/>
                                </linearGradient>
                            </defs>
                            @foreach ([24, 55.5, 87, 118.5, 150] as $gridY)
                                <line x1="42" y1="{{ $gridY }}" x2="612" y2="{{ $gridY }}" stroke="#e5eaf2" stroke-width="1"/>
                            @endforeach
                            @foreach ([100, 75, 50, 25, 0] as $index => $percentage)
                                <text x="32" y="{{ 28 + ($index * 31.5) }}" text-anchor="end" fill="#8491a8" font-size="10">{{ $percentage }}%</text>
                            @endforeach
                            <path d="{{ $areaPath }}" fill="url(#attendance-area)"/>
                            <polyline points="{{ $linePath }}" fill="none" stroke="#4875df" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            @foreach ($chartPoints as $point)
                                <circle cx="{{ number_format($point['x'], 1, '.', '') }}" cy="{{ number_format($point['y'], 1, '.', '') }}" r="4" fill="#fff" stroke="#4875df" stroke-width="2">
                                    <title>{{ $point['date_label'] }}: {{ $point['percentage'] }}% ({{ $point['total'] }} karyawan)</title>
                                </circle>
                                <text x="{{ number_format($point['x'], 1, '.', '') }}" y="{{ max(15, $point['y'] - 11) }}" text-anchor="middle" fill="#475569" font-size="9">{{ $point['percentage'] }}%</text>
                                <text x="{{ number_format($point['x'], 1, '.', '') }}" y="176" text-anchor="middle" fill="#8491a8" font-size="10">{{ $point['date_label'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                </div>
            </div>
        </article>

        <article>
            <div class="card h-full">
                <div class="card-body">
                    <h2 class="card-title">Status kehadiran</h2>
                    <p class="card-subtitle mb-5">Ringkasan hari ini</p>
                    <div class="dashboard-status-content">
                        <div class="relative shrink-0" style="width:160px;height:160px">
                            <svg viewBox="0 0 100 100" role="img" aria-label="Distribusi status kehadiran hari ini" class="h-full w-full">
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#edf0f5" stroke-width="12"/>
                                <g transform="rotate(-90 50 50)">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#22a06b" stroke-width="12" stroke-dasharray="{{ number_format($presentLength, 2, '.', '') }} {{ number_format($circumference - $presentLength, 2, '.', '') }}"/>
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#f3a43b" stroke-width="12" stroke-dasharray="{{ number_format($leaveLength, 2, '.', '') }} {{ number_format($circumference - $leaveLength, 2, '.', '') }}" stroke-dashoffset="-{{ number_format($presentLength, 2, '.', '') }}"/>
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#4875df" stroke-width="12" stroke-dasharray="{{ number_format($notCheckedLength, 2, '.', '') }} {{ number_format($circumference - $notCheckedLength, 2, '.', '') }}" stroke-dashoffset="-{{ number_format($presentLength + $leaveLength, 2, '.', '') }}"/>
                                </g>
                            </svg>
                            <div class="dashboard-status-total">
                                <strong class="text-2xl font-semibold text-dark">{{ number_format($employeeCount) }}</strong>
                                <span class="text-sm text-link">Total</span>
                            </div>
                        </div>
                        <div class="dashboard-status-legend min-w-0">
                            <div class="flex items-center justify-between border-b border-border py-3">
                                <span class="flex items-center gap-2 text-sm text-link"><i class="h-2.5 w-2.5 rounded-full bg-success"></i>Hadir</span>
                                <strong>{{ number_format($presentCount) }}</strong>
                            </div>
                            <div class="flex items-center justify-between border-b border-border py-3">
                                <span class="flex items-center gap-2 text-sm text-link"><i class="h-2.5 w-2.5 rounded-full bg-warning"></i>Izin / cuti</span>
                                <strong>{{ number_format($approvedLeaveCount) }}</strong>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="flex items-center gap-2 text-sm text-link"><i class="h-2.5 w-2.5 rounded-full bg-primary"></i>Belum check-in</span>
                                <strong>{{ number_format($notCheckedInCount) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>

    </section>

    <section class="dashboard-panel-grid">
        <article>
            <div class="card h-full">
                <div class="card-body">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="card-title">Aktivitas terbaru</h2>
                            <p class="card-subtitle">Karyawan yang check-in hari ini</p>
                        </div>
                        <a class="text-primary hover:text-primaryemphasis" href="{{ route('admin.attendances.index') }}">Semua aktivitas <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="divide-y divide-border">
                        @forelse ($attendances->take(5) as $attendance)
                            @php($isLate = ($attendance->late_minutes ?? 0) > 0)
                            <div class="flex flex-wrap items-center gap-3 py-4">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full {{ $isLate ? 'bg-lightwarning text-warning' : 'bg-lightsuccess text-success' }}">
                                    <iconify-icon icon="{{ $isLate ? 'solar:clock-circle-linear' : 'solar:check-circle-linear' }}"></iconify-icon>
                                </span>
                                <span class="text-sm font-medium text-dark">{{ $attendance->check_in_at->format('H:i') }}</span>
                                <span class="min-w-[150px] flex-1">
                                    <strong class="block text-sm font-semibold text-dark">{{ $attendance->user->name }}</strong>
                                    <small class="text-xs text-bodytext">{{ $attendance->user->email }}</small>
                                </span>
                                <span class="badge-md rounded-full {{ $isLate ? 'bg-lightwarning text-warning' : 'bg-lightprimary text-primary' }}">
                                    {{ $isLate ? 'Terlambat '.$attendance->late_minutes.' menit' : 'Check-in' }}
                                </span>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-bodytext">Belum ada aktivitas check-in hari ini.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </article>

        <article>
            <div class="card h-full">
                <div class="card-body">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="card-title">Menunggu persetujuan</h2>
                            <p class="card-subtitle">Pengajuan izin dari karyawan</p>
                        </div>
                        <a class="text-primary hover:text-primaryemphasis" href="{{ route('admin.leave-requests.index') }}">Lihat semua <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="divide-y divide-border">
                        @forelse ($pendingRequests as $request)
                            <div class="flex items-center gap-3 py-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-lightsecondary text-secondary font-semibold">{{ mb_strtoupper(mb_substr($request->user->name, 0, 1)) }}</span>
                                <span class="min-w-0 flex-1">
                                    <strong class="block truncate text-sm font-semibold text-dark">{{ $request->user->name }}</strong>
                                    <small class="block text-xs text-bodytext">{{ ['perjalanan_dinas' => 'Perjalanan Dinas', 'diklat' => 'Diklat'][$request->type] ?? ucfirst($request->type) }} · {{ $request->start_date->translatedFormat('d M') }} – {{ $request->end_date->translatedFormat('d M Y') }}</small>
                                </span>
                                <span class="badge-md rounded-full bg-lightwarning text-warning">Menunggu</span>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-bodytext">Tidak ada pengajuan yang menunggu.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </article>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('themes/tailwindadmin/js/libs/owl.carousel/owl.carousel.min.js') }}"></script>
    <script>
        jQuery(function ($) {
            $('.dashboard-stat-carousel').owlCarousel({
                loop: false,
                margin: 24,
                mouseDrag: true,
                autoplay: false,
                nav: false,
                dots: false,
                responsive: {
                    0: { items: 2 },
                    576: { items: 2 },
                    768: { items: 3 },
                    1200: { items: 4 },
                },
            });
        });
    </script>
@endpush
