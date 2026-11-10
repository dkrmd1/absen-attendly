@extends('admin.layouts.app')

@section('title', 'Group kerja')
@section('heading', 'Group kerja')
@section('breadcrumb', 'Struktur organisasi')

@push('styles')
    <style>
        .work-group-page{display:grid;gap:20px}
        .work-group-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .work-group-toolbar p{margin:0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .work-group-card{min-width:0;overflow:hidden;padding:0}
        .work-group-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .work-group-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .work-group-card-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem}
        .work-group-count{display:inline-flex;flex-shrink:0;align-items:center;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .work-group-table-wrap{overflow-x:auto}
        .work-group-table{min-width:760px}
        .work-group-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .work-group-table td{padding:16px;vertical-align:middle}
        .work-group-table tbody tr:last-child td{border-bottom:0}
        .work-group-table tbody tr:hover{background:var(--color-lightprimary)}
        .work-group-name{min-width:175px}
        .work-group-name strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .work-group-department{min-width:180px}
        .work-group-leader{min-width:185px}
        .work-group-members{color:var(--color-dark);font-variant-numeric:tabular-nums;font-weight:600}
        .work-group-status{min-width:120px}
        .work-group-status-pill{display:block;margin-bottom:8px}
        .work-group-edit-form{display:grid;gap:14px}
        .work-group-edit-form .form-control{width:100%;min-width:0}
        .work-group-edit-form label{display:grid;gap:6px;color:var(--color-link);font-size:.8125rem;font-weight:600}
        .work-group-edit-actions{display:flex;justify-content:flex-end}
        .work-group-create-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .work-group-create-form>div{min-width:0}
        .work-group-create-wide{grid-column:1/-1}
        .work-group-create-actions{display:flex;justify-content:flex-end}
        .work-group-empty{padding:42px 20px!important;text-align:center!important;color:var(--color-bodytext)}
        @media(max-width:640px){
            .work-group-toolbar{align-items:stretch;flex-direction:column}
            .work-group-toolbar .btn{width:100%}
            .work-group-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .work-group-card-head h2{font-size:1rem}
            .work-group-count{padding:6px 10px;font-size:.75rem}
            .work-group-create-form{grid-template-columns:minmax(0,1fr)}
            .work-group-create-wide{grid-column:auto}
            .work-group-create-actions .btn{width:100%}
        }
    </style>
@endpush

@section('content')
    <div class="work-group-page">
        <div class="work-group-toolbar">
            <p>Buat group dalam departemen dan tetapkan ketua untuk tahap persetujuan pertama.</p>
            <a class="btn" href="#tambah-group">＋ Tambah group</a>
        </div>

        <section class="card work-group-card">
            <div class="work-group-card-head">
                <div>
                    <h2>Daftar group kerja</h2>
                    <p>Kelola keanggotaan dan ketua group untuk proses persetujuan.</p>
                </div>
                <span class="work-group-count">{{ $groups->count() }} group</span>
            </div>
            <div class="work-group-table-wrap">
                <table class="work-group-table">
                    <thead>
                        <tr>
                            <th scope="col">Nama group</th>
                            <th scope="col">Departemen</th>
                            <th scope="col">Ketua group</th>
                            <th scope="col">Anggota</th>
                            <th scope="col">Status & tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td class="work-group-name"><strong>{{ $group->name }}</strong></td>
                                <td class="work-group-department">{{ $group->department->name }}</td>
                                <td class="work-group-leader">{{ $group->leader?->name ?? 'Belum ditentukan' }}</td>
                                <td class="work-group-members">{{ number_format($group->employees_count) }}</td>
                                <td class="work-group-status">
                                    <span class="work-group-status-pill badge-md rounded-full text-xs font-medium {{ $group->is_active ? 'bg-lightsuccess text-success' : 'bg-lighterror text-error' }}">
                                        {{ $group->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                    <form method="post" action="{{ route('admin.work-groups.update', $group) }}"
                                        class="work-group-edit-form"
                                        data-popup-edit
                                        data-trigger-label="Kelola"
                                        data-dialog-title="Kelola group · {{ $group->name }}">
                                        @csrf
                                        @method('PUT')
                                        <label>Nama group
                                            <input class="form-control" name="name" value="{{ $group->name }}" maxlength="120" required>
                                        </label>
                                        <label>Ketua group
                                            <select class="form-control" name="leader_id">
                                                <option value="">Tanpa ketua</option>
                                                @foreach ($leaders->where('department_id', $group->department_id) as $leader)
                                                    <option value="{{ $leader->id }}" @selected($group->leader_id === $leader->id)>{{ $leader->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>Status
                                            <select class="form-control" name="is_active">
                                                <option value="1" @selected($group->is_active)>Aktif</option>
                                                <option value="0" @selected(! $group->is_active)>Nonaktif</option>
                                            </select>
                                        </label>
                                        <div class="work-group-edit-actions"><button class="btn" type="submit">Simpan perubahan</button></div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="work-group-empty">Belum ada group kerja.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card p-6 modal-form" id="tambah-group">
            <h3>Tambah group</h3>
            <form method="post" action="{{ route('admin.work-groups.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 work-group-create-form">
                @csrf
                <div><label class="block mb-1 text-sm font-medium text-link" for="department_id">Departemen</label><select class="form-control" id="department_id" name="department_id" required><option value="">Pilih departemen</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                <div><label class="block mb-1 text-sm font-medium text-link" for="name">Nama group</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="120" required></div>
                <div class="work-group-create-wide"><label class="block mb-1 text-sm font-medium text-link" for="leader_id">Ketua group (opsional)</label><select class="form-control" id="leader_id" name="leader_id"><option value="">Belum ditentukan</option>@foreach ($leaders as $leader)<option value="{{ $leader->id }}" @selected(old('leader_id') == $leader->id)>{{ $leader->name }} · {{ $leader->department?->name ?? 'Tanpa departemen' }}</option>@endforeach</select></div>
                <div class="work-group-create-wide work-group-create-actions"><button class="btn" type="submit">Simpan group</button></div>
            </form>
        </section>
    </div>
@endsection
