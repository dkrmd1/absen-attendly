@extends('admin.layouts.app')

@section('title', 'Departemen')
@section('heading', 'Departemen')
@section('breadcrumb', 'Departemen')

@push('styles')
    <style>
        .department-page{display:grid;gap:20px}
        .department-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .department-toolbar p{margin:0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .department-card{min-width:0;overflow:hidden;padding:0}
        .department-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .department-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .department-card-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem}
        .department-total{display:inline-flex;flex-shrink:0;align-items:center;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .department-table-wrap{overflow-x:auto}
        .department-table{min-width:850px}
        .department-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .department-table td{padding:16px;vertical-align:middle}
        .department-table tbody tr:last-child td{border-bottom:0}
        .department-table tbody tr:hover{background:var(--color-lightprimary)}
        .department-name{min-width:190px}
        .department-name strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .department-description{min-width:210px;max-width:360px;white-space:normal;overflow-wrap:anywhere;line-height:1.55}
        .department-structure{display:grid;min-width:210px;gap:5px}
        .department-structure small{line-height:1.45}
        .department-count{color:var(--color-dark);font-variant-numeric:tabular-nums;font-weight:600;white-space:nowrap}
        .department-status{white-space:nowrap}
        .department-actions{min-width:110px;text-align:right}
        .department-actions .btn{min-height:34px}
        .department-edit-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .department-edit-form h3,.department-edit-form>p,.department-edit-actions{grid-column:1/-1;margin:0}
        .department-edit-form h3{color:var(--color-dark);font-size:.9375rem;font-weight:700}
        .department-edit-form>p{color:var(--color-bodytext);font-size:.8125rem;line-height:1.5}
        .department-edit-field{display:grid;align-content:start;gap:6px;min-width:0}
        .department-edit-field label{color:var(--color-link);font-size:.8125rem;font-weight:600}
        .department-edit-field .form-control{width:100%;min-width:0}
        .department-edit-field-wide{grid-column:1/-1}
        .department-edit-actions{display:flex;justify-content:flex-end;border-top:1px solid var(--color-bordergray);padding-top:16px}
        .department-create-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .department-create-form>div{min-width:0}
        .department-create-form .department-create-wide{grid-column:1/-1}
        .department-create-form .department-create-actions{display:flex;justify-content:flex-end}
        .department-empty{padding:42px 20px!important;text-align:center!important;color:var(--color-bodytext)}
        @media(max-width:640px){
            .department-toolbar{align-items:stretch;flex-direction:column}
            .department-toolbar .btn{width:100%}
            .department-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .department-card-head h2{font-size:1rem}
            .department-total{padding:6px 10px;font-size:.75rem}
            .department-edit-form,.department-create-form{grid-template-columns:minmax(0,1fr)}
            .department-edit-form h3,.department-edit-form>p,.department-edit-actions,.department-edit-field-wide,.department-create-form .department-create-wide{grid-column:auto}
            .department-edit-actions .btn,.department-create-actions .btn{width:100%}
        }
    </style>
@endpush

@section('content')
    <div class="department-page">
        <div class="department-toolbar">
            <p>Kelompokkan karyawan untuk administrasi dan laporan. GPS tetap mengikuti lokasi atau cabang kerja.</p>
            <a class="btn" href="#tambah-departemen">＋ Tambah departemen</a>
        </div>

        <section class="card department-card">
            <div class="department-card-head">
                <div>
                    <h2>Daftar departemen</h2>
                    <p>Kelola struktur, pimpinan, dan status departemen.</p>
                </div>
                <span class="department-total">{{ $departments->count() }} departemen</span>
            </div>

            <div class="department-table-wrap">
                <table class="department-table">
                    <thead>
                        <tr>
                            <th scope="col">Departemen</th>
                            <th scope="col">Struktur penanggung jawab</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Karyawan</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td class="department-name">
                                    <strong>{{ $department->name }}</strong>
                                </td>
                                <td class="department-structure">
                                    <small><strong>Kepala divisi:</strong> {{ $department->supervisor?->name ?? 'Belum ditetapkan' }}</small>
                                    <small><strong>Direktur:</strong> {{ $department->director?->name ?? 'Belum ditetapkan' }}</small>
                                </td>
                                <td class="department-description">{{ $department->description ?: 'Belum ada deskripsi.' }}</td>
                                <td class="department-count">{{ number_format($department->employees_count) }}</td>
                                <td class="department-status">
                                    <span class="badge-md rounded-full text-xs font-medium {{ $department->is_active ? 'bg-lightsuccess text-success' : 'bg-lighterror text-error' }}">
                                        {{ $department->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="department-actions">
                                    <form method="post" action="{{ route('admin.departments.update', $department) }}"
                                        class="department-edit-form"
                                        data-popup-edit
                                        data-trigger-label="Kelola"
                                        data-dialog-title="Kelola departemen · {{ $department->name }}">
                                        @csrf
                                        @method('PUT')

                                        <h3>Informasi departemen</h3>
                                        <p>Atur nama, penanggung jawab, dan status departemen.</p>

                                        <div class="department-edit-field">
                                            <label for="department-name-{{ $department->id }}">Nama departemen</label>
                                            <input class="form-control" id="department-name-{{ $department->id }}" name="name" value="{{ $department->name }}" maxlength="255" required>
                                        </div>
                                        <div class="department-edit-field">
                                            <label for="department-description-{{ $department->id }}">Deskripsi</label>
                                            <input class="form-control" id="department-description-{{ $department->id }}" name="description" value="{{ $department->description }}" maxlength="1000">
                                        </div>
                                        <div class="department-edit-field">
                                            <label for="department-supervisor-{{ $department->id }}">Kepala divisi</label>
                                            <select class="form-control" id="department-supervisor-{{ $department->id }}" name="supervisor_id">
                                                <option value="">Belum ditetapkan</option>
                                                @foreach ($supervisorsByDepartment[$department->id] as $candidate)
                                                    <option value="{{ $candidate->id }}" @selected($department->supervisor_id === $candidate->id)>{{ $candidate->name }} · {{ $candidate->email }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="department-edit-field">
                                            <label for="department-director-{{ $department->id }}">Direktur penanggung jawab</label>
                                            <select class="form-control" id="department-director-{{ $department->id }}" name="director_id">
                                                <option value="">Belum ditetapkan</option>
                                                @foreach ($directors as $director)
                                                    <option value="{{ $director->id }}" @selected($department->director_id === $director->id)>{{ $director->name }} · {{ $director->email }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="department-edit-field-wide text-xs text-bodytext">
                                            <input type="checkbox" name="is_active" value="1" @checked($department->is_active)>
                                            Departemen aktif
                                        </label>
                                        <div class="department-edit-actions">
                                            <button class="btn" type="submit">Simpan perubahan</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="department-empty">Belum ada departemen. Tambahkan departemen sebelum mengelompokkan karyawan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card p-6 modal-form" id="tambah-departemen">
            <h3>Tambah departemen</h3>
            <form method="post" action="{{ route('admin.departments.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 department-form department-create-form">
                @csrf
                <div>
                    <label class="block mb-1 text-sm font-medium text-link" for="new-department-name">Nama departemen</label>
                    <input class="form-control" id="new-department-name" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="Contoh: Operasional">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-link" for="new-department-description">Deskripsi (opsional)</label>
                    <input class="form-control" id="new-department-description" name="description" value="{{ old('description') }}" maxlength="1000">
                </div>
                <div class="department-create-wide">
                    <label class="block mb-1 text-sm font-medium text-link" for="new-department-director">Direktur penanggung jawab (opsional)</label>
                    <select class="form-control" id="new-department-director" name="director_id">
                        <option value="">Belum ditetapkan</option>
                        @foreach ($directors as $director)
                            <option value="{{ $director->id }}" @selected(old('director_id') == $director->id)>{{ $director->name }} · {{ $director->email }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="is_active" value="1">
                <div class="department-create-actions department-create-wide"><button class="btn" type="submit">Simpan departemen</button></div>
            </form>
        </section>
    </div>
@endsection
