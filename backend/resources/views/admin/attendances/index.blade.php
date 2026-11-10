@extends('admin.layouts.app')

@section('title', 'Absensi')
@section('heading', 'Absensi')
@section('breadcrumb', 'Absensi')

@push('styles')
    <style>
        .attendance-page{display:grid;gap:22px}
        .attendance-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:24px}
        .attendance-intro{max-width:540px}
        .attendance-intro p{margin:0;color:var(--color-bodytext);font-size:.9375rem;line-height:1.6}
        .attendance-filter{display:grid;grid-template-columns:minmax(170px,210px) minmax(190px,260px) auto;align-items:end;gap:12px;padding:16px;border:1px solid var(--color-bordergray);border-radius:var(--radius-md);background:var(--color-white);box-shadow:var(--theme-shadow-sm)}
        .attendance-filter-actions{display:flex;align-items:center;gap:8px}
        .attendance-filter-field{display:grid;gap:6px;min-width:0}
        .attendance-filter-field label{color:var(--color-link);font-size:.8125rem;font-weight:600}
        .attendance-filter-field input,.attendance-filter-field select{width:100%;min-width:0;height:42px}
        .attendance-filter .btn{height:42px;white-space:nowrap}
        .attendance-card{min-width:0;overflow:hidden;padding:0}
        .attendance-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .attendance-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700;letter-spacing:-.01em}
        .attendance-card-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem}
        .attendance-count{display:inline-flex;align-items:center;gap:7px;flex-shrink:0;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .attendance-count svg{display:block;width:16px;height:16px;flex:0 0 16px}
        .attendance-table-wrap{overflow-x:auto}
        .attendance-table{min-width:980px}
        .attendance-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.045em;text-transform:uppercase}
        .attendance-table td{padding:15px 16px}
        .attendance-table tbody tr:last-child td{border-bottom:0}
        .attendance-employee{display:flex;min-width:175px;align-items:center;gap:11px}
        .attendance-employee .avatar{flex:0 0 auto}
        .attendance-employee strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .attendance-employee small{margin-top:3px;overflow-wrap:anywhere}
        .attendance-time{color:var(--color-dark);font-variant-numeric:tabular-nums;font-weight:600;white-space:nowrap}
        .attendance-review{display:grid;min-width:190px;gap:7px}
        .attendance-review .form-control{min-width:160px}
        .attendance-review-actions{display:flex;flex-wrap:wrap;gap:6px}
        .attendance-review-actions .btn{min-height:32px}
        .attendance-review-note{overflow-wrap:anywhere}
        .attendance-empty{padding:48px 20px!important;text-align:center!important}
        .attendance-empty-icon{display:grid;width:44px;height:44px;margin:0 auto 12px;place-items:center;border-radius:50%;background:var(--color-lightprimary);color:var(--color-primary);font-size:22px}
        .attendance-empty-icon svg{display:block;width:20px;height:20px;flex:0 0 20px}
        .attendance-empty strong{display:block;color:var(--color-dark);font-size:.9375rem;font-weight:600}
        .attendance-empty>span:not(.attendance-empty-icon){display:block;margin-top:5px;color:var(--color-bodytext);font-size:.8125rem}
        .attendance-pagination{display:flex;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--color-bordergray)}
        @media(max-width:1100px){
            .attendance-toolbar{align-items:stretch;flex-direction:column;gap:16px}
            .attendance-filter{grid-template-columns:repeat(2,minmax(0,1fr))}
            .attendance-filter-actions{grid-column:1/-1}
        }
        @media(max-width:560px){
            .attendance-page{gap:16px}
            .attendance-filter{grid-template-columns:minmax(0,1fr);padding:14px}
            .attendance-filter-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}
            .attendance-filter-actions .btn{width:100%;padding-inline:10px}
            .attendance-card-head{align-items:flex-start;padding:18px 16px}
            .attendance-card-head h2{font-size:1rem}
            .attendance-count{padding:6px 9px;font-size:.75rem}
            .attendance-pagination{padding:14px 16px}
        }
    </style>
@endpush

@section('content')
    <div class="attendance-page">
        <div class="attendance-toolbar">
            <div class="attendance-intro">
                <p>Riwayat check-in dan check-out karyawan. Gunakan filter untuk melihat catatan berdasarkan tanggal dan departemen.</p>
            </div>

            <form class="attendance-filter" method="get" action="{{ route('admin.attendances.index') }}">
                <div class="attendance-filter-field">
                    <label for="date">Pilih tanggal</label>
                    <input class="form-control" type="date" id="date" name="date" value="{{ $date }}">
                </div>
                <div class="attendance-filter-field">
                    <label for="department_id">Departemen</label>
                    <select class="form-control" id="department_id" name="department_id">
                        <option value="">Semua departemen</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="attendance-filter-actions">
                    <button class="btn" type="submit">Tampilkan</button>
                    <a class="btn" href="{{ route('admin.attendances.export.xlsx', ['date' => $date, 'department_id' => $filters['department_id'] ?? null]) }}">Unduh XLSX</a>
                    <a class="btn btn-outline-primary" href="{{ route('admin.attendances.export', ['date' => $date, 'department_id' => $filters['department_id'] ?? null]) }}">CSV</a>
                </div>
            </form>
        </div>

        <section class="card attendance-card">
            <div class="attendance-card-head">
                <div>
                    <h2>Absensi {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('d F Y') }}</h2>
                    <p>Daftar catatan kehadiran sesuai filter yang dipilih.</p>
                </div>
                <span class="attendance-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="5.5" y="5" width="13" height="16" rx="2"></rect>
                        <path d="M9 5V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V5M9 10h6M9 13.5h6M9 17h3"></path>
                    </svg>
                    {{ $attendances->total() }} catatan
                </span>
            </div>

            <div class="attendance-table-wrap">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th scope="col">Karyawan</th>
                            <th scope="col">Departemen</th>
                            <th scope="col">Lokasi / mode</th>
                            <th scope="col">Jam masuk</th>
                            <th scope="col">Jam pulang</th>
                            <th scope="col">Status</th>
                            <th scope="col">Peninjauan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $attendance)
                            @php($isLate = ($attendance->late_minutes ?? 0) > 0)
                            <tr>
                                <td>
                                    <div class="attendance-employee">
                                        <span class="avatar">{{ mb_strtoupper(mb_substr($attendance->user->name, 0, 1)) }}</span>
                                        <span>
                                            <strong>{{ $attendance->user->name }}</strong>
                                            <small>{{ $attendance->user->email }}</small>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $attendance->department?->name ?? 'Belum ditentukan' }}</td>
                                <td>{{ $attendance->attendance_mode === 'online_training' ? 'Diklat online' : ($attendance->workLocation?->name ?? 'Lokasi lama') }}</td>
                                <td class="attendance-time">{{ $attendance->check_in_at->format('H:i') }}</td>
                                <td class="attendance-time">{{ $attendance->check_out_at?->format('H:i') ?? '—' }}</td>
                                <td>
                                    @if ($attendance->attendance_mode === 'online_training')
                                        <span class="badge-md rounded-full text-xs font-medium {{ $attendance->verification_status === 'approved' ? 'bg-lightsuccess text-success' : ($attendance->verification_status === 'rejected' ? 'bg-lighterror text-error' : 'bg-lightwarning text-warning') }}">
                                            {{ ['pending_review' => 'Menunggu review', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$attendance->verification_status] ?? 'Diklat online' }}
                                        </span>
                                        @if ($isLate)
                                            <small class="text-xs text-bodytext">Terlambat {{ $attendance->late_minutes }} menit</small>
                                        @endif
                                    @else
                                        @if ($attendance->shift_name === null)
                                            <span class="badge-md rounded-full text-xs font-medium bg-lightgray text-link">Jadwal belum ditetapkan</span>
                                        @elseif ($attendance->is_scheduled_workday === false)
                                            <span class="badge-md rounded-full text-xs font-medium bg-lightgray text-link">Di luar hari kerja</span>
                                        @else
                                            <span class="badge-md rounded-full text-xs font-medium {{ $isLate ? 'bg-lightwarning text-warning' : 'bg-lightsuccess text-success' }}">
                                                {{ $isLate ? 'Terlambat '.$attendance->late_minutes.' menit' : 'Tepat waktu' }}
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <div class="attendance-review">
                                        @if ($attendance->verification_status === 'pending_review' && (! auth()->user()->isDepartmentSupervisor() || $attendance->user_id !== auth()->id()))
                                            <form method="post" action="{{ route('admin.attendances.review', $attendance) }}" class="attendance-review">
                                                @csrf
                                                @method('PATCH')
                                                <input class="form-control" name="review_note" maxlength="1000" placeholder="Catatan (opsional)" aria-label="Catatan peninjauan absensi">
                                                <div class="attendance-review-actions">
                                                    <button class="btn text-xs py-1 px-2" name="verification_status" value="approved" type="submit">Setujui</button>
                                                    <button class="btn text-xs py-1 px-2 bg-lighterror text-error" name="verification_status" value="rejected" type="submit">Tolak</button>
                                                </div>
                                            </form>
                                        @elseif ($attendance->verification_status === 'pending_review')
                                            <span class="text-xs text-bodytext">Tidak dapat meninjau absensi sendiri.</span>
                                        @elseif ($attendance->attendance_mode === 'online_training')
                                            <span class="text-xs text-bodytext">{{ $attendance->verification_status === 'approved' ? 'Disetujui' : 'Ditolak' }} · {{ $attendance->reviewer?->name ?? 'Admin' }}</span>
                                            @if ($attendance->review_note)
                                                <small class="attendance-review-note text-xs text-bodytext">{{ $attendance->review_note }}</small>
                                            @endif
                                        @else
                                            <span class="text-xs text-bodytext">Tercatat</span>
                                        @endif

                                        @if ($attendance->check_in_selfie_path && (! auth()->user()->isDepartmentSupervisor() || $attendance->user_id !== auth()->id()))
                                            <a class="text-primary hover:text-primaryemphasis" href="{{ route('admin.attendances.selfie', $attendance) }}" target="_blank" rel="noopener noreferrer">Lihat selfie</a>
                                            <small>Disimpan maksimal 90 hari</small>
                                        @elseif ($attendance->check_in_selfie_path)
                                            <small>Selfie disimpan maksimal 90 hari.</small>
                                        @elseif ($attendance->selfie_review_status === 'pending_review' && $attendance->attendance_mode === 'online_training')
                                            <small>Selfie tidak dikirim · absensi diklat ditinjau terpisah</small>
                                        @elseif ($attendance->selfie_review_status === 'pending_review' && (! auth()->user()->isDepartmentSupervisor() || $attendance->user_id !== auth()->id()))
                                            <form method="post" action="{{ route('admin.attendances.selfie-review', $attendance) }}" class="attendance-review">
                                                @csrf
                                                @method('PATCH')
                                                <small>Selfie tidak tersedia · review manual</small>
                                                <input class="form-control" name="selfie_review_note" maxlength="1000" placeholder="Catatan (opsional)" aria-label="Catatan review selfie">
                                                <div class="attendance-review-actions">
                                                    <button class="btn text-xs py-1 px-2" name="selfie_review_status" value="approved" type="submit">Setujui</button>
                                                    <button class="btn text-xs py-1 px-2 bg-lighterror text-error" name="selfie_review_status" value="rejected" type="submit">Tolak</button>
                                                </div>
                                            </form>
                                        @elseif ($attendance->selfie_review_status === 'pending_review')
                                            <span class="text-xs text-bodytext">Tidak dapat meninjau absensi sendiri.</span>
                                        @elseif (in_array($attendance->selfie_review_status, ['approved', 'rejected'], true))
                                            <span class="text-xs text-bodytext">Review manual {{ $attendance->selfie_review_status === 'approved' ? 'disetujui' : 'ditolak' }} · {{ $attendance->selfieReviewer?->name ?? 'Supervisor' }}</span>
                                            @if ($attendance->selfie_review_note)
                                                <small class="attendance-review-note text-xs text-bodytext">{{ $attendance->selfie_review_note }}</small>
                                            @endif
                                        @elseif ($attendance->selfie_review_status === 'expired')
                                            <small>Selfie dihapus setelah melewati masa simpan 90 hari.</small>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="attendance-empty">
                                    <span class="attendance-empty-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <rect x="3.5" y="5" width="17" height="15.5" rx="2"></rect>
                                            <path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17M7 13h4"></path>
                                            <circle cx="15.5" cy="15.5" r="2.5"></circle>
                                            <path d="m17.3 17.3 2 2"></path>
                                        </svg>
                                    </span>
                                    <strong>Tidak ada absensi pada tanggal ini</strong>
                                    <span>Coba pilih tanggal atau departemen lain.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($attendances->hasPages())
                <div class="attendance-pagination">{{ $attendances->links() }}</div>
            @endif
        </section>
    </div>
@endsection
