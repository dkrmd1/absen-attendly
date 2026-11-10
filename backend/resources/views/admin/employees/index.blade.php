@extends('admin.layouts.app')

@section('title', 'Karyawan')
@section('heading', 'Karyawan')
@section('breadcrumb', 'Karyawan')

@push('styles')
    <style>
        .employee-page{display:grid;gap:20px}
        .employee-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .employee-toolbar p{margin:0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .employee-card{min-width:0;overflow:hidden;padding:0}
        .employee-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .employee-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .employee-card-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem}
        .employee-total{display:inline-flex;flex-shrink:0;align-items:center;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .employee-table-wrap{overflow-x:auto}
        .employee-table{min-width:1040px}
        .employee-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .employee-table td{padding:15px 16px}
        .employee-table tbody tr:last-child td{border-bottom:0}
        .employee-table tbody tr:hover{background:var(--color-lightprimary)}
        .employee-person{display:flex;min-width:195px;align-items:center;gap:10px}
        .employee-person .avatar{flex:0 0 auto}
        .employee-person strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .employee-person small{margin-top:3px;overflow-wrap:anywhere}
        .employee-role{min-width:130px}
        .employee-placement{min-width:175px}
        .employee-placement strong{display:block;color:var(--color-dark);font-size:.8125rem;font-weight:600}
        .employee-placement small{margin-top:4px;line-height:1.5}
        .employee-attendance-count{color:var(--color-dark);font-variant-numeric:tabular-nums;font-weight:600}
        .employee-status{white-space:nowrap}
        .employee-actions{display:flex;min-width:180px;flex-wrap:nowrap;align-items:center;gap:8px;white-space:nowrap}
        .employee-actions>button,.employee-actions>form{flex:0 0 auto}
        .employee-actions .btn{min-height:34px}
        .employee-actions form{margin:0}
        .employee-edit-form{display:grid;gap:18px}
        .employee-edit-section{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .employee-edit-section h3,.employee-edit-section>p{grid-column:1/-1;margin:0}
        .employee-edit-section h3{color:var(--color-dark);font-size:.9375rem;font-weight:700}
        .employee-edit-section>p{color:var(--color-bodytext);font-size:.8125rem;line-height:1.5}
        .employee-edit-field{display:grid;align-content:start;gap:6px;min-width:0}
        .employee-edit-field label{color:var(--color-link);font-size:.8125rem;font-weight:600}
        .employee-edit-field .form-control{width:100%;min-width:0}
        .employee-field-wide{grid-column:1/-1}
        .employee-role-note{border-radius:var(--radius-md);background:var(--color-lightwarning);padding:11px 12px;color:var(--color-warning);font-size:.8125rem;line-height:1.5}
        .employee-password-panel{border:1px solid var(--color-bordergray);border-radius:var(--radius-md);padding:14px}
        .employee-password-panel summary{color:var(--color-link);font-size:.875rem;font-weight:600;cursor:pointer}
        .employee-password-panel p{margin:7px 0 14px;color:var(--color-bodytext);font-size:.8125rem;line-height:1.5}
        .employee-password-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .employee-form-actions{display:flex;justify-content:flex-end;border-top:1px solid var(--color-bordergray);padding-top:16px}
        .employee-pagination{display:flex;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--color-bordergray)}
        .employee-create-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .employee-empty{padding:36px 20px!important;text-align:center!important;color:var(--color-bodytext)}
        @media(max-width:640px){
            .employee-toolbar{align-items:stretch;flex-direction:column}
            .employee-toolbar .btn{width:100%}
            .employee-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .employee-card-head h2{font-size:1rem}
            .employee-total{padding:6px 10px;font-size:.75rem}
            .employee-edit-section,.employee-password-grid,.employee-create-grid{grid-template-columns:minmax(0,1fr)}
            .employee-field-wide,.employee-edit-section h3,.employee-edit-section>p{grid-column:auto}
            .employee-pagination{padding:14px 16px}
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-employee-edit-form]').forEach((form) => {
            const role = form.querySelector('[data-employee-role]');
            const department = form.querySelector('[data-employee-department]');
            const group = form.querySelector('[data-employee-group]');

            const filterGroups = () => {
                if (!department || !group) return;
                Array.from(group.options).forEach((option) => {
                    if (!option.dataset.departmentId) return;
                    option.hidden = option.dataset.departmentId !== department.value;
                    if (option.hidden && option.selected) group.value = '';
                });
            };

            const updateAssignments = () => {
                const currentRole = role?.value || form.dataset.currentRole;
                const isExecutive = ['ceo', 'commissioner'].includes(currentRole);
                const isEmployee = currentRole === 'employee';

                form.querySelectorAll('[data-employee-role-assignment]').forEach((field) => {
                    field.style.display = isExecutive || !isEmployee ? 'none' : '';
                    field.querySelectorAll('input, select').forEach((input) => {
                        input.disabled = isExecutive || !isEmployee;
                    });
                });
                form.querySelectorAll('[data-employee-work-assignment]').forEach((field) => {
                    field.style.display = isExecutive ? 'none' : '';
                    field.querySelectorAll('input, select').forEach((input) => {
                        input.disabled = isExecutive;
                    });
                });

                if (department) department.required = isEmployee;
                const location = form.querySelector('[data-employee-location]');
                if (location) location.required = !isExecutive;
                filterGroups();
            };

            role?.addEventListener('change', updateAssignments);
            department?.addEventListener('change', filterGroups);
            updateAssignments();
        });
    </script>
@endpush

@section('content')
    @php
        $roleLabels = [
            'employee' => 'Staf',
            'hr' => 'HRD',
            'director' => 'Direktur',
            'ceo' => 'Direktur Utama',
            'commissioner' => 'Komisaris',
            'group_lead' => 'Ketua tim',
            'division_head' => 'Kepala divisi',
        ];
    @endphp

    <div class="employee-page">
        <div class="employee-toolbar">
            <p>Kelola akun, penempatan, dan akses karyawan. Akun nonaktif tetap menyimpan riwayatnya.</p>
            <a class="btn" href="#tambah-karyawan">＋ Tambah karyawan</a>
        </div>

        <section class="card employee-card">
            <div class="employee-card-head">
                <div>
                    <h2>Daftar karyawan</h2>
                    <p>{{ $employees->total() }} akun terdaftar · pilih Kelola untuk mengubah data atau reset kata sandi.</p>
                </div>
                <span class="employee-total">{{ $employees->total() }} akun</span>
            </div>

            <div class="employee-table-wrap">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th scope="col">Karyawan</th>
                            <th scope="col">Jabatan</th>
                            <th scope="col">Departemen / tim</th>
                            <th scope="col">Cabang / shift</th>
                            <th scope="col">Absensi</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php
                                $hasManagedStructure = $employee->supervisedDepartment
                                    || in_array($employee->role, ['division_head', 'group_lead'], true);
                                $canChangeRole = in_array($employee->role, ['employee', 'hr', 'director', 'ceo', 'commissioner'], true)
                                    && ! $employee->supervisedDepartment
                                        && $employee->directedDepartments->isEmpty();
                                $canEditStructure = $canChangeRole
                                    || ($employee->role === 'employee' && ! $employee->supervisedDepartment);
                            @endphp
                            <tr>
                                <td>
                                    <div class="employee-person">
                                        <span class="avatar">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span>
                                        <span>
                                            <strong>{{ $employee->name }}</strong>
                                            <small>{{ $employee->email }}</small>
                                        </span>
                                    </div>
                                </td>
                                <td class="employee-role">
                                    <span class="badge-md rounded-full text-xs font-medium {{ $hasManagedStructure ? 'bg-lightwarning text-warning' : 'bg-lightprimary text-primary' }}">
                                        {{ $roleLabels[$employee->role] ?? $employee->role }}
                                    </span>
                                </td>
                                <td class="employee-placement">
                                    <strong>{{ $employee->department?->name ?? 'Belum ditentukan' }}</strong>
                                    <small>{{ $employee->workGroup?->name ?? 'Tanpa tim' }}</small>
                                </td>
                                <td class="employee-placement">
                                    <strong>{{ $employee->baseLocation?->name ?? 'Belum ditentukan' }}</strong>
                                    <small>{{ $employee->workShift?->name ?? 'Shift belum ditentukan' }}</small>
                                </td>
                                <td class="employee-attendance-count">{{ number_format($employee->attendances_count) }}</td>
                                <td class="employee-status">
                                    <span class="badge-md rounded-full text-xs font-medium {{ $employee->is_active ? 'bg-lightsuccess text-success' : 'bg-lighterror text-error' }}">
                                        {{ $employee->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="employee-actions">
                                        @if ($employee->is_active)
                                            <form method="post" action="{{ route('admin.employees.update', $employee) }}"
                                                class="employee-edit-form"
                                                data-popup-edit
                                                data-trigger-label="Kelola"
                                                data-dialog-title="Kelola akun · {{ $employee->name }}"
                                                data-employee-edit-form
                                                data-current-role="{{ $employee->role }}">
                                                @csrf
                                                @method('PUT')

                                                <div class="employee-edit-section">
                                                    <h3>Informasi akun</h3>
                                                    <div class="employee-edit-field">
                                                        <label for="employee-name-{{ $employee->id }}">Nama lengkap</label>
                                                        <input class="form-control" id="employee-name-{{ $employee->id }}" name="name" value="{{ $employee->name }}" maxlength="255" required>
                                                    </div>
                                                    <div class="employee-edit-field">
                                                        <label for="employee-email-{{ $employee->id }}">Email</label>
                                                        <input class="form-control" id="employee-email-{{ $employee->id }}" name="email" type="email" value="{{ $employee->email }}" required>
                                                    </div>
                                                </div>

                                                <div class="employee-edit-section">
                                                    <h3>Jabatan dan penempatan</h3>
                                                    @if ($canChangeRole)
                                                        <div class="employee-edit-field" data-employee-role-field>
                                                            <label for="employee-role-{{ $employee->id }}">Jabatan</label>
                                                            <select class="form-control" id="employee-role-{{ $employee->id }}" name="role" data-employee-role>
                                                                @foreach (['employee' => 'Staf', 'hr' => 'HRD', 'director' => 'Direktur', 'ceo' => 'Direktur Utama', 'commissioner' => 'Komisaris'] as $role => $label)
                                                                    <option value="{{ $role }}" @selected($employee->role === $role)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @else
                                                        <div class="employee-role-note employee-field-wide">
                                                            Jabatan struktural ini dikelola dari menu Departemen atau Group kerja agar alur persetujuan tetap benar.
                                                        </div>
                                                    @endif

                                                    @if ($canEditStructure && $departments->isNotEmpty())
                                                        <div class="employee-edit-field" data-employee-role-assignment>
                                                            <label for="employee-department-{{ $employee->id }}">Departemen</label>
                                                            <select class="form-control" id="employee-department-{{ $employee->id }}" name="department_id" data-employee-department>
                                                                <option value="">Pilih departemen</option>
                                                                @foreach ($departments as $department)
                                                                    <option value="{{ $department->id }}" @selected($employee->department_id === $department->id)>{{ $department->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @else
                                                        <div class="employee-edit-field">
                                                            <label>Departemen</label>
                                                            <div class="form-control">{{ $employee->department?->name ?? 'Belum ditentukan' }}</div>
                                                        </div>
                                                    @endif

                                                    @if ($canEditStructure && $groups->isNotEmpty())
                                                        <div class="employee-edit-field" data-employee-role-assignment>
                                                            <label for="employee-group-{{ $employee->id }}">Group kerja</label>
                                                            <select class="form-control" id="employee-group-{{ $employee->id }}" name="work_group_id" data-employee-group>
                                                                <option value="">Tanpa group</option>
                                                                @foreach ($groups as $group)
                                                                    <option value="{{ $group->id }}" data-department-id="{{ $group->department_id }}" @selected($employee->work_group_id === $group->id)>
                                                                        {{ $group->name }} · {{ $departments->firstWhere('id', $group->department_id)?->name ?? 'Departemen' }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @else
                                                        <div class="employee-edit-field">
                                                            <label>Group kerja</label>
                                                            <div class="form-control">{{ $employee->workGroup?->name ?? 'Tanpa group' }}</div>
                                                        </div>
                                                    @endif

                                                    @if ($locations->isNotEmpty())
                                                        <div class="employee-edit-field" data-employee-work-assignment>
                                                            <label for="employee-location-{{ $employee->id }}">Cabang utama</label>
                                                            <select class="form-control" id="employee-location-{{ $employee->id }}" name="base_location_id" data-employee-location>
                                                                <option value="">Pilih cabang</option>
                                                                @foreach ($locations as $location)
                                                                    <option value="{{ $location->id }}" @selected($employee->base_location_id === $location->id)>{{ $location->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @endif

                                                    @if ($shifts->isNotEmpty())
                                                        <div class="employee-edit-field" data-employee-work-assignment>
                                                            <label for="employee-shift-{{ $employee->id }}">Shift kerja</label>
                                                            <select class="form-control" id="employee-shift-{{ $employee->id }}" name="work_shift_id">
                                                                <option value="">Belum ditentukan</option>
                                                                @foreach ($shifts as $shift)
                                                                    <option value="{{ $shift->id }}" @selected($employee->work_shift_id === $shift->id)>{{ $shift->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @endif
                                                </div>

                                                <details class="employee-password-panel">
                                                    <summary>Reset kata sandi (opsional)</summary>
                                                    <p>Kosongkan jika tidak ingin mengganti kata sandi. Minimal 12 karakter. Reset akan mengeluarkan sesi dan perangkat akun ini.</p>
                                                    <div class="employee-password-grid">
                                                        <div class="employee-edit-field">
                                                            <label for="employee-password-{{ $employee->id }}">Kata sandi baru</label>
                                                            <input class="form-control" id="employee-password-{{ $employee->id }}" name="password" type="password" minlength="12" autocomplete="new-password">
                                                        </div>
                                                        <div class="employee-edit-field">
                                                            <label for="employee-password-confirmation-{{ $employee->id }}">Ulangi kata sandi baru</label>
                                                            <input class="form-control" id="employee-password-confirmation-{{ $employee->id }}" name="password_confirmation" type="password" minlength="12" autocomplete="new-password">
                                                        </div>
                                                    </div>
                                                </details>

                                                <div class="employee-form-actions">
                                                    <button class="btn" type="submit">Simpan perubahan</button>
                                                </div>
                                            </form>

                                            <form method="post" action="{{ route('admin.employees.archive', $employee) }}" data-confirm-action="deactivate-employee">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn bg-lighterror text-error text-xs py-1 px-2" type="submit">Nonaktifkan</button>
                                            </form>
                                        @else
                                            <form method="post" action="{{ route('admin.employees.restore', $employee) }}" data-confirm-action="restore">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn bg-lightprimary text-primary text-xs py-1 px-2" type="submit">Aktifkan kembali</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="employee-empty">Belum ada akun karyawan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($employees->hasPages())
                <div class="employee-pagination">{{ $employees->links() }}</div>
            @endif
        </section>

        <section class="card p-6 modal-form" id="tambah-karyawan">
            <h3>Tambah akun karyawan</h3>
            <form method="post" action="{{ route('admin.employees.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 employee-create-grid">
                @csrf
                <div><label class="block mb-1 text-sm font-medium text-link" for="name">Nama lengkap</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="255"></div>
                <div><label class="block mb-1 text-sm font-medium text-link" for="email">Email</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required></div>
                <div><label class="block mb-1 text-sm font-medium text-link" for="password">Kata sandi (minimal 12 karakter)</label><input class="form-control" id="password" name="password" type="password" minlength="12" required></div>
                <div><label class="block mb-1 text-sm font-medium text-link" for="password_confirmation">Ulangi kata sandi</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" required></div>
                @if ($locations->isNotEmpty())
                    <div><label class="block mb-1 text-sm font-medium text-link" for="base_location_id">Cabang utama</label><select class="form-control" id="base_location_id" name="base_location_id" required><option value="">Pilih cabang</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected(old('base_location_id') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
                @else
                    <div><p class="text-sm text-bodytext">Tambahkan lokasi kerja terlebih dahulu. Karyawan belum dapat melakukan absensi sampai cabangnya ditentukan.</p></div>
                @endif
                @if ($departments->isNotEmpty())
                    <div><label class="block mb-1 text-sm font-medium text-link" for="department_id">Departemen</label><select class="form-control" id="department_id" name="department_id" required><option value="">Pilih departemen</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                @else
                    <div><p class="text-sm text-bodytext">Belum ada departemen aktif. Kamu dapat menambahkannya sekarang atau mengatur departemen karyawan nanti.</p></div>
                @endif
                @if ($shifts->isNotEmpty())
                    <div><label class="block mb-1 text-sm font-medium text-link" for="work_shift_id">Shift kerja (opsional)</label><select class="form-control" id="work_shift_id" name="work_shift_id"><option value="">Belum ditentukan</option>@foreach ($shifts as $shift)<option value="{{ $shift->id }}" @selected(old('work_shift_id') == $shift->id)>{{ $shift->name }}</option>@endforeach</select></div>
                @endif
                <div><label class="block mb-1 text-sm font-medium text-link" for="role">Jabatan awal</label><select class="form-control" id="role" name="role"><option value="employee" @selected(old('role', 'employee') === 'employee')>Staf</option><option value="hr" @selected(old('role') === 'hr')>HRD</option><option value="director" @selected(old('role') === 'director')>Direktur</option><option value="ceo" @selected(old('role') === 'ceo')>Direktur Utama</option><option value="commissioner" @selected(old('role') === 'commissioner')>Komisaris</option></select></div>
                <div class="employee-field-wide flex justify-end"><button class="btn" type="submit">Simpan karyawan</button></div>
            </form>
        </section>
    </div>
@endsection
