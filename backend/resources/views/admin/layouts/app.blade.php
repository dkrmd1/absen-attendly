<!doctype html>
<html lang="id" dir="ltr" data-color-theme="Blue_Theme" class="light selected" data-layout="vertical"
    data-boxed-layout="boxed" data-card="shadow">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Attendly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('themes/tailwindadmin/css/theme.css') }}">
    <style>
        html{font-family:"DM Sans",ui-sans-serif,system-ui,sans-serif;scrollbar-gutter:stable}
        body{min-width:320px}
        button,a,input,select,textarea,summary{touch-action:manipulation}
        :focus-visible{outline:3px solid rgb(72 117 223 / .55);outline-offset:2px}
        button:disabled,input:disabled,select:disabled,textarea:disabled{cursor:not-allowed;opacity:.65}
        .admin-dialog{width:min(720px,calc(100vw - 28px));max-width:calc(100vw - 28px);max-height:90vh;max-height:90dvh;margin:auto;padding:0;border:1px solid var(--color-bordergray);border-radius:var(--radius-md);overflow:auto;overscroll-behavior:contain;background:var(--color-white);color:var(--color-dark);box-shadow:var(--theme-shadow-md)}
        .admin-dialog::backdrop,.admin-confirm-dialog::backdrop{background:rgb(17 24 39 / .5)}
        .admin-dialog-head{position:sticky;top:0;z-index:3;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 22px;border-bottom:1px solid var(--color-bordergray);background:var(--color-white)}
        .admin-dialog-head h2{margin:0;font-size:1rem;font-weight:600}
        .admin-dialog-close{display:grid;place-items:center;width:34px;height:34px;border-radius:var(--radius-md);background:var(--color-lightgray);color:var(--color-link);font-size:22px}
        .admin-dialog-content{padding:22px;overflow-wrap:anywhere}
        .admin-dialog-content>.card{margin:0;padding:0;border:0;background:transparent;box-shadow:none}
        .admin-dialog-content>.card>h3{display:none}
        .admin-dialog .location-map{height:240px}
        .admin-dialog-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:16px;border-top:1px solid var(--color-bordergray)}
        .admin-confirm-dialog{width:min(420px,calc(100vw - 28px));max-width:calc(100vw - 28px);margin:auto;padding:22px;border:1px solid var(--color-bordergray);border-radius:var(--radius-md);background:var(--color-white);box-shadow:var(--theme-shadow-md)}
        .admin-confirm-dialog h2{margin:0 0 8px;font-size:1rem;font-weight:600}
        .admin-confirm-dialog p{margin:0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .admin-confirm-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}
        html.dark{color-scheme:dark;--color-bodytext:var(--color-darklink);--color-link:var(--color-darklink);--color-bordergray:var(--color-darkborder);--color-lightgray:var(--color-darkgray);--color-lightsuccess:var(--color-darksuccess);--color-lighterror:var(--color-darkerror);--color-lightwarning:var(--color-darkwarning);--color-lightinfo:var(--color-darkinfo)}
        html.dark .body-wrapper :is(h1,h2,h3,h4,h5,h6):not(.text-primary):not(.text-success):not(.text-error):not(.text-warning),
        html.dark .body-wrapper .text-dark,
        html.dark .body-wrapper td strong{color:var(--color-white)!important}
        html.dark .admin-dialog,html.dark .admin-confirm-dialog{border-color:var(--color-darkborder);background:var(--color-dark);color:var(--color-white)}
        html.dark .admin-dialog-head{border-color:var(--color-darkborder);background:var(--color-dark)}
        html.dark .admin-dialog-head h2{color:var(--color-white)}
        html.dark .admin-dialog-close{background:var(--color-darkgray);color:var(--color-white)}
        html.dark .body-wrapper .form-control{border-color:var(--color-darkborder);background-color:var(--color-darkgray);color:var(--color-white);color-scheme:dark}
        html.dark .body-wrapper .form-control::placeholder{color:var(--color-darklink);opacity:1}
        .body-wrapper .bg-lightwarning.text-warning{color:var(--color-warningemphasis)}
        html.dark .body-wrapper .admin-error-summary{border-color:var(--color-darkerror);background:var(--color-darkerror);color:var(--color-error)}
        .body-wrapper table{width:100%;border-collapse:collapse;font-size:.875rem}
        .body-wrapper th,.body-wrapper td{padding:.75rem 1rem;border-bottom:1px solid var(--color-bordergray);text-align:left;vertical-align:middle}
        .body-wrapper th{color:var(--color-bodytext);font-size:.75rem;font-weight:600;white-space:nowrap}
        .body-wrapper tbody tr:hover{background:var(--color-lightprimary)}
        .body-wrapper td small{display:block;color:var(--color-bodytext);font-size:.75rem}
        .admin-header-row{display:flex;width:100%;min-width:0;align-items:center;justify-content:space-between;gap:16px}
        .admin-header-start{display:flex;min-width:0;flex:1;align-items:center;gap:16px}
        .admin-header-brand{display:flex;flex-shrink:0;align-items:center;gap:8px}
        .admin-header-brand img{display:block;width:32px;height:32px;flex:0 0 32px;object-fit:contain}
        .admin-header-brand span{white-space:nowrap}
        .admin-header-search{position:relative;display:none;width:min(320px,100%);align-items:center}
        .admin-header-search iconify-icon{position:absolute;left:14px;z-index:1;color:var(--color-link);font-size:18px}
        .admin-header-search input{width:100%;height:42px;padding-left:42px}
        .admin-header-actions{display:flex;flex-shrink:0;align-items:center;gap:8px}
        .admin-profile-dropdown{position:relative;display:inline-flex}
        #application-sidebar-brand{flex-direction:column;height:100vh;height:100dvh;overflow:hidden}
        #application-sidebar-brand>.scroll-sidebar{position:relative!important;flex:1 1 0%!important;min-height:0!important;max-height:100%;height:auto!important;overflow:hidden}
        #application-sidebar-brand>.scroll-sidebar .simplebar-wrapper,
        #application-sidebar-brand>.scroll-sidebar .simplebar-mask,
        #application-sidebar-brand>.scroll-sidebar .simplebar-offset{height:100%!important}
        #application-sidebar-brand>.scroll-sidebar .simplebar-content-wrapper{height:100%!important;overflow-y:auto!important}
        #application-sidebar-brand>.scroll-sidebar .simplebar-content{min-height:100%;padding-bottom:16px}
        #application-sidebar-brand>.sidebar-account{position:relative!important;inset:auto!important;flex-shrink:0}
        .admin-user-menu,.admin-notifications-menu{position:absolute!important;top:calc(100% + 8px)!important;right:0!important;bottom:auto!important;left:auto!important;z-index:30!important}
        .admin-user-menu{width:208px!important;min-width:208px!important}
        .admin-user-menu-item{display:flex;width:100%;align-items:center;gap:10px;border-radius:6px;padding:10px 12px;color:var(--color-bodytext);font-size:.875rem;text-align:left}
        .admin-user-menu-item iconify-icon{color:var(--color-link);font-size:17px}
        .admin-user-menu-item-copy{display:grid;gap:2px}
        .admin-user-menu-item-copy small{color:var(--color-bodytext);font-size:.7rem;font-weight:400}
        .admin-user-menu-item:hover{background:var(--color-lightprimary);color:var(--color-primary)}
        .admin-user-menu-divider{height:1px;margin:6px -8px 8px;background:var(--color-bordergray)}
        .admin-user-menu-logout{display:flex;width:100%;height:40px;align-items:center;justify-content:flex-start;gap:10px;border-radius:6px;padding:0 12px;color:var(--color-error);font-size:.875rem;font-weight:600}
        .admin-user-menu-logout:hover{background:var(--color-lighterror)}
        .admin-user-menu-logout iconify-icon{font-size:17px}
        .admin-profile-card{margin:0 auto;max-width:768px;padding:24px}
        .admin-profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .admin-profile-grid .admin-profile-field-wide{grid-column:1/-1}
        .admin-resource-page{display:grid;gap:20px;min-width:0}
        .admin-resource-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .admin-resource-toolbar p{margin:0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .admin-resource-card{min-width:0;overflow:hidden;padding:0}
        .admin-resource-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .admin-resource-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .admin-resource-card-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem;line-height:1.55}
        .admin-resource-card-body{padding:20px 24px}
        .admin-resource-count{display:inline-flex;flex-shrink:0;align-items:center;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .admin-resource-table-wrap{overflow-x:auto;overscroll-behavior-x:contain}
        .admin-resource-table{min-width:720px}
        .admin-resource-table th{padding:13px 16px;background:var(--color-lightgray);font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .admin-resource-table td{padding:14px 16px;vertical-align:middle}
        .admin-resource-table tbody tr:last-child td{border-bottom:0}
        .admin-resource-table tbody tr:hover{background:var(--color-lightprimary)}
        .admin-resource-pagination{padding:16px 24px}
        .admin-error-summary{border:1px solid var(--color-lighterror);border-radius:var(--radius-md);background:var(--color-lighterror);padding:14px 16px;color:var(--color-error)}
        .admin-error-summary ul{display:grid;gap:4px;margin:6px 0 0;padding-left:1.25rem;list-style:disc}
        .admin-error-summary li{font-size:.875rem;line-height:1.5}
        .admin-dialog-close:hover{background:var(--color-lightprimary);color:var(--color-primary)}
        .admin-footer{padding-bottom:max(12px,env(safe-area-inset-bottom))}
        .admin-table-scroll{overflow-x:auto;overscroll-behavior-x:contain}
        @media(prefers-reduced-motion:reduce){
            *,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
        }
        .page-wrapper{display:flex;height:100vh;height:100dvh;min-height:0;min-width:0;flex-direction:column;overflow:hidden}
        .page-wrapper>.body-wrapper{min-height:0;flex:1 1 auto;overflow-y:auto}
        .body-wrapper>.container-fluid{min-width:0}
        .admin-footer{flex:0 0 auto;border-top:1px solid var(--color-bordergray);padding:12px 16px;color:var(--color-bodytext);font-size:.75rem;text-align:center}
        .admin-resource-form{display:grid;gap:16px}
        .admin-resource-form .form-control{width:100%;min-width:0}
        .admin-resource-form label{display:block;color:var(--color-link);font-size:.8125rem;font-weight:600}
        .admin-resource-form-actions{display:flex;justify-content:flex-end}
        .admin-resource-empty{padding:42px 20px!important;text-align:center!important;color:var(--color-bodytext)}
        .admin-resource-actions{white-space:nowrap}
        .admin-resource-actions>form{display:inline-flex;align-items:center;vertical-align:middle}
        .admin-notifications-menu{width:min(288px,calc(100vw - 32px))!important;min-width:0!important}
        @media(min-width:1024px){
            #application-sidebar-brand{display:flex!important;transform:translateX(0)!important;translate:0!important;visibility:visible!important}
            #main-wrapper>.page-wrapper{width:calc(100% - 270px);margin-left:270px}
            #main-wrapper .sidebartoggler{display:none}
            #main-wrapper .body-wrapper{width:100%;margin-left:0!important}
            .admin-header-brand{display:none}
            .admin-header-search{display:flex}
        }
        @media(max-width:1023px){
            #application-sidebar-brand{width:min(270px,85vw)}
            #application-sidebar-brand.open{display:flex!important}
            #main-wrapper>.page-wrapper{width:100%;margin-left:0}
            #main-wrapper .body-wrapper{width:100%;margin-left:0}
            .admin-header-row{gap:8px}
            .admin-header-actions{gap:2px}
        }
        @media(max-width:640px){
            .admin-profile-card{padding:16px}
            .admin-profile-grid{grid-template-columns:minmax(0,1fr);gap:14px}
            .admin-profile-grid .admin-profile-field-wide{grid-column:auto}
            .admin-resource-toolbar{align-items:stretch;flex-direction:column}
            .admin-resource-toolbar .btn{width:100%}
            .admin-resource-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .admin-resource-card-body{padding:16px}
            .admin-resource-count{padding:6px 10px;font-size:.75rem}
            .admin-resource-form-actions .btn{width:100%}
            .admin-resource-pagination{padding:14px 16px}
        }
        @media(max-width:640px){.admin-dialog-content{padding:16px}.admin-dialog-head{padding:14px 16px}.admin-dialog .location-map{height:210px}}
    </style>
    @stack('styles')
</head>
<body class="DEFAULT_THEME bg-white dark:bg-dark" data-sidebartype="full" data-layout="vertical" data-header-position="fixed">
@php
    $currentUser = auth()->user();
    $roleLabel = match (true) {
        $currentUser->isAdmin() => 'Administrator',
        $currentUser->isHr() => 'HR',
        $currentUser->isDirector() => 'Direktur',
        $currentUser->isCeo() => 'Direktur Utama',
        $currentUser->isCommissioner() => 'Komisaris',
        $currentUser->isGroupLead() => 'Ketua group',
        $currentUser->isDivisionHead() => 'Kepala divisi',
        default => 'Pengguna',
    };
    $homeRoute = $currentUser->isAdmin() ? route('admin.dashboard') : route('admin.leave-requests.index');
    $isActiveRoute = fn (string ...$routes): string => request()->routeIs(...$routes) ? 'active activemenu dark:text-white' : '';
@endphp
<div id="main-wrapper" class="flex">
    <aside id="application-sidebar-brand"
        class="hs-overlay hs-overlay-open:translate-x-0 -translate-x-full xl:rtl:-translate-x-0 rtl:translate-x-full left-0 rtl:left-auto rtl:right-0 transform hidden xl:block xl:translate-x-0 xl:end-auto xl:bottom-0 fixed top-0 with-vertical left-sidebar transition-all duration-300 h-screen z-20 flex-shrink-0 border-r rtl:border-l rtl:border-r-0 w-[270px] border-border dark:border-darkborder bg-white dark:bg-dark">
        <div class="py-5 px-5 flex justify-between">
            <a href="{{ $homeRoute }}" class="text-nowrap logo-img flex items-center gap-3">
                <img src="{{ asset('images/attendly-mark.svg') }}" class="h-10 w-10" alt="Attendly">
                <span class="text-lg font-semibold text-dark dark:text-white">Attendly</span>
            </a>
        </div>
        <div class="scroll-sidebar min-h-0 flex-1" data-simplebar>
            <div class="px-6 mt-8 mini-layout">
                <nav class="hs-accordion-group w-full flex flex-col" aria-label="Navigasi utama">
                    <ul id="sidebarnav">
                        <li class="caption"><span class="hide-menu">Menu utama</span></li>
                        @if ($currentUser->isAdmin())
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.dashboard') }}" href="{{ route('admin.dashboard') }}">
                                    <iconify-icon icon="solar:widget-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Dashboard</span>
                                </a>
                            </li>
                        @endif
                        @if ($currentUser->isAdmin() || $currentUser->isHr())
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.attendances.*') }}" href="{{ route('admin.attendances.index') }}">
                                    <iconify-icon icon="solar:calendar-mark-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Absensi</span>
                                </a>
                            </li>
                        @endif
                        <li class="sidebar-item">
                            <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.attendance-corrections.*') }}" href="{{ route('admin.attendance-corrections.index') }}">
                                <iconify-icon icon="solar:calendar-search-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Koreksi absensi</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.leave-requests.*') }}" href="{{ route('admin.leave-requests.index') }}">
                                <iconify-icon icon="solar:document-text-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Pengajuan izin</span>
                            </a>
                        </li>
                        @if ($currentUser->isAdmin() || $currentUser->isHr())
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.activity-reports.*') }}" href="{{ route('admin.activity-reports.index') }}">
                                    <iconify-icon icon="solar:clipboard-list-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Aktivitas karyawan</span>
                                </a>
                            </li>
                        @endif

                        @if ($currentUser->isAdmin())
                            <li class="caption mt-6"><span class="hide-menu">Manajemen</span></li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.employees.*') }}" href="{{ route('admin.employees.index') }}">
                                    <iconify-icon icon="solar:users-group-rounded-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Karyawan</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.departments.*') }}" href="{{ route('admin.departments.index') }}">
                                    <iconify-icon icon="solar:buildings-2-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Departemen</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.work-groups.*') }}" href="{{ route('admin.work-groups.index') }}">
                                    <iconify-icon icon="solar:users-group-rounded-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Group kerja</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.shifts.*') }}" href="{{ route('admin.shifts.index') }}">
                                    <iconify-icon icon="solar:clock-circle-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Shift kerja</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.locations.*') }}" href="{{ route('admin.locations.index') }}">
                                    <iconify-icon icon="solar:map-point-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Lokasi kerja</span>
                                </a>
                            </li>
                        @endif

                        @if ($currentUser->isAdmin() || $currentUser->isHr())
                            <li class="caption mt-6"><span class="hide-menu">Human resources</span></li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.leave-balances.*', 'admin.company-holidays.*') }}" href="{{ route('admin.leave-balances.index') }}">
                                    <iconify-icon icon="solar:calendar-date-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Saldo cuti & hari libur</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.payroll.*') }}" href="{{ route('admin.payroll.index') }}">
                                    <iconify-icon icon="solar:wallet-money-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Info gaji</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.tax-documents.*') }}" href="{{ route('admin.tax-documents.index') }}">
                                    <iconify-icon icon="solar:document-add-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Dokumen SPT</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.announcements.*') }}" href="{{ route('admin.announcements.index') }}">
                                    <iconify-icon icon="solar:chat-square-like-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Berita & pengumuman</span>
                                </a>
                            </li>
                        @endif

                        @if ($currentUser->isAdmin())
                            <li class="caption mt-6"><span class="hide-menu">Sistem</span></li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.audit-logs.*') }}" href="{{ route('admin.audit-logs.index') }}">
                                    <iconify-icon icon="solar:history-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Audit aktivitas</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.firebase-messaging.*') }}" href="{{ route('admin.firebase-messaging.index') }}">
                                    <iconify-icon icon="solar:bell-bing-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Notifikasi Firebase</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link dark-sidebar-link {{ $isActiveRoute('admin.backups.*') }}" href="{{ route('admin.backups.index') }}">
                                    <iconify-icon icon="solar:cloud-storage-linear" class="shrink-0 text-xl"></iconify-icon><span class="hide-menu shrink-0">Backup &amp; pemulihan</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </nav>
            </div>
        </div>
        <div class="sidebar-account border-t border-border dark:border-darkborder bg-white dark:bg-dark p-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-lightprimary text-primary font-semibold">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                <span class="min-w-0 flex-1">
                    <strong class="block truncate text-sm font-semibold text-dark dark:text-white">{{ $currentUser->name }}</strong>
                    <small class="block truncate text-xs text-bodytext">{{ $roleLabel }}{{ $currentUser->department ? ' · '.$currentUser->department->name : '' }}</small>
                </span>
            </div>
        </div>
    </aside>

    <div class="page-wrapper w-full" role="main">
        <header class="sticky top-header top-0 inset-x-0 z-10 flex flex-wrap text-sm bg-white dark:bg-dark">
            <div class="with-vertical w-full">
                <div class="w-full mx-auto px-4 lg:py-4 py-3 lg:px-4" aria-label="Global">
                    <div class="admin-header-row">
                        <div class="admin-header-start">
                            <div class="relative xl:hidden">
                                <button type="button" class="sidebartoggler text-2xl icon-hover cursor-pointer text-link dark:text-darklink h-10 w-10 hover:text-primary light-dark-hoverbg flex justify-center items-center rounded-full"
                                    data-hs-overlay="#application-sidebar-brand" aria-controls="application-sidebar-brand" aria-label="Buka navigasi">
                                    <iconify-icon icon="solar:hamburger-menu-linear" class="relative z-1"></iconify-icon>
                                </button>
                            </div>
                            <a href="{{ $homeRoute }}" class="admin-header-brand text-sm font-semibold text-dark dark:text-white">
                                <img src="{{ asset('images/attendly-mark.svg') }}" class="h-8 w-8" alt="">
                                <span>Attendly</span>
                            </a>
                            <label class="admin-header-search">
                                <iconify-icon icon="solar:magnifer-linear" aria-hidden="true"></iconify-icon>
                                <input id="admin-menu-search" class="form-control" type="search" placeholder="Cari menu..." aria-label="Cari menu navigasi" autocomplete="off">
                            </label>
                        </div>
                        <div class="admin-header-actions">
                            <div class="hs-dropdown relative inline-flex">
                                <button type="button" class="hs-dropdown-toggle relative flex h-10 w-10 items-center justify-center rounded-full text-link hover:bg-lightprimary hover:text-primary" aria-label="Notifikasi pengajuan" aria-expanded="false">
                                    <iconify-icon icon="solar:bell-linear" class="text-xl"></iconify-icon>
                                    @if (($pendingNotificationCount ?? 0) > 0)
                                        <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-error" aria-label="{{ $pendingNotificationCount }} pengajuan menunggu"></span>
                                    @endif
                                </button>
                                <div class="card hs-dropdown-menu admin-notifications-menu transition-[opacity,margin] duration opacity-0 hidden z-20 p-4" role="menu">
                                    <strong class="block text-sm font-semibold text-dark">Notifikasi</strong>
                                    <p class="mt-1 text-sm text-bodytext">{{ ($pendingNotificationCount ?? 0) > 0 ? $pendingNotificationCount.' pengajuan menunggu persetujuan.' : 'Tidak ada notifikasi baru.' }}</p>
                                    <a href="{{ route('admin.leave-requests.index') }}" class="btn mt-3 w-full">Lihat pengajuan</a>
                                </div>
                            </div>
                            <button type="button" class="hs-dark-mode-active:hidden icon-hover flex cursor-pointer hs-dark-mode group items-center font-medium hover:text-primary text-link dark:text-darklink h-10 w-10 light-dark-hoverbg justify-center rounded-full"
                                data-hs-theme-click-value="dark" aria-label="Gunakan mode gelap">
                                <iconify-icon icon="solar:moon-linear" class="text-xl"></iconify-icon>
                            </button>
                            <button type="button" class="hs-dark-mode-active:flex cursor-pointer icon-hover hidden hs-dark-mode group items-center font-medium hover:text-primary text-link dark:text-darklink h-10 w-10 light-dark-hoverbg justify-center rounded-full"
                                data-hs-theme-click-value="light" aria-label="Gunakan mode terang">
                                <iconify-icon icon="solar:sun-2-linear" class="text-xl"></iconify-icon>
                            </button>
                            <div class="admin-profile-dropdown">
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full p-0 hover:bg-lightprimary" data-profile-menu-toggle aria-controls="admin-profile-menu" aria-label="Menu profil" aria-expanded="false">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-lightprimary text-primary font-semibold">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                                </button>
                                <div id="admin-profile-menu" class="card admin-user-menu hidden z-20 p-2" role="menu" aria-label="Menu profil">
                                    <a href="{{ route('admin.profile.edit') }}" class="admin-user-menu-item" role="menuitem">
                                        <iconify-icon icon="solar:user-circle-linear" aria-hidden="true"></iconify-icon>
                                        <span class="admin-user-menu-item-copy">
                                            <span>Profil Saya</span>
                                            <small>Atur profil & kata sandi</small>
                                        </span>
                                    </a>
                                    <a href="{{ route('admin.leave-requests.index') }}" class="admin-user-menu-item" role="menuitem">
                                        <iconify-icon icon="solar:checklist-minimalistic-linear" aria-hidden="true"></iconify-icon>
                                        <span>Tugas Saya</span>
                                    </a>
                                    <div class="admin-user-menu-divider" role="separator"></div>
                                    <form method="post" action="{{ route('admin.logout') }}" class="admin-logout-form">
                                        @csrf
                                        <button type="submit" class="admin-user-menu-logout" role="menuitem" aria-label="Keluar">
                                            <iconify-icon icon="solar:logout-2-linear" aria-hidden="true"></iconify-icon>
                                            <span>Keluar</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="body-wrapper">
            <div class="container-fluid max-w-full px-4 py-6 lg:px-6 lg:py-8">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold text-dark dark:text-white">@yield('heading', 'Dashboard')</h1>
                        @hasSection('page-description')
                            <p class="mt-1 text-sm text-bodytext">@yield('page-description')</p>
                        @endif
                    </div>
                    <nav class="text-sm text-bodytext" aria-label="Breadcrumb">
                        <a href="{{ $homeRoute }}" class="text-link hover:text-primary">Attendly</a>
                        <span class="px-2">/</span>
                        <span class="text-dark dark:text-white">@yield('breadcrumb', 'Dashboard')</span>
                    </nav>
                </div>
                @if (session('success'))
                    <div class="mb-4 rounded-md border border-lightsuccess bg-lightsuccess p-4 text-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="admin-error-summary mb-4" role="alert" tabindex="-1">
                        <strong>Periksa kembali data yang dimasukkan.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
        <footer class="admin-footer">© {{ now()->year }} Drweb. Hak cipta dilindungi.</footer>
    </div>
</div>
<script src="{{ asset('themes/tailwindadmin/js/vendor.min.js') }}"></script>
<script src="{{ asset('themes/tailwindadmin/js/theme/app.init.js') }}"></script>
<script src="{{ asset('themes/tailwindadmin/js/theme/app.min.js') }}"></script>
<script src="{{ asset('themes/tailwindadmin/js/theme.js') }}"></script>
<script src="{{ asset('themes/tailwindadmin/js/libs/simplebar/dist/simplebar.min.js') }}"></script>
<script>
    window.$hsOverlayCollection = window.$hsOverlayCollection || [];
    document.addEventListener('keydown', (event) => {
        if (window.HSOverlay) window.HSOverlay.accessibility(event);
    });
</script>
<script src="{{ asset('themes/tailwindadmin/js/libs/preline/dist/preline.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
@stack('scripts')
<script>
    (() => {
        const searchInput = document.getElementById('admin-menu-search');
        const menu = document.getElementById('sidebarnav');
        const profileToggle = document.querySelector('[data-profile-menu-toggle]');
        const profileMenu = document.getElementById('admin-profile-menu');
        const activeSidebarLink = document.querySelector('#sidebarnav .sidebar-link.active');
        if (activeSidebarLink) activeSidebarLink.setAttribute('aria-current', 'page');
        if (profileToggle && profileMenu) {
            const closeProfileMenu = () => {
                profileMenu.classList.add('hidden');
                profileToggle.setAttribute('aria-expanded', 'false');
            };
            profileToggle.addEventListener('click', () => {
                const isOpen = profileToggle.getAttribute('aria-expanded') === 'true';
                profileMenu.classList.toggle('hidden', isOpen);
                profileToggle.setAttribute('aria-expanded', String(!isOpen));
            });
            document.addEventListener('click', (event) => {
                if (!event.target.closest('.admin-profile-dropdown')) closeProfileMenu();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeProfileMenu();
            });
        }
        if (searchInput && menu) {
            const menuItems = [...menu.querySelectorAll('.sidebar-item')];
            const sections = [...menu.querySelectorAll('.caption')];
            const emptyMessage = document.createElement('li');
            emptyMessage.className = 'caption';
            emptyMessage.textContent = 'Menu tidak ditemukan';
            emptyMessage.hidden = true;
            menu.append(emptyMessage);
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim().toLocaleLowerCase('id');
                let visibleCount = 0;
                menuItems.forEach((item) => {
                    item.hidden = query !== '' && !item.textContent.toLocaleLowerCase('id').includes(query);
                    if (!item.hidden) visibleCount++;
                });
                sections.forEach((section) => {
                    let hasVisibleItem = false;
                    for (let sibling = section.nextElementSibling; sibling && !sibling.classList.contains('caption'); sibling = sibling.nextElementSibling) {
                        if (sibling.classList.contains('sidebar-item') && !sibling.hidden) hasVisibleItem = true;
                    }
                    section.hidden = query !== '' && !hasVisibleItem;
                });
                emptyMessage.hidden = query === '' || visibleCount > 0;
            });
        }

        const openDialog = (dialog) => {
            if (!(dialog instanceof HTMLDialogElement) || dialog.open) return;
            dialog.showModal();
            window.dispatchEvent(new CustomEvent('attendly:modal-open', {detail: {dialog}}));
        };

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            const openDialogs = [...document.querySelectorAll('dialog[open]')]
                .filter((dialog) => dialog.matches(':modal'));
            const topDialog = openDialogs[openDialogs.length - 1];
            if (topDialog) {
                event.preventDefault();
                topDialog.close();
                return;
            }
            const openOverlays = [...document.querySelectorAll('.hs-overlay.open')];
            const topOverlay = openOverlays[openOverlays.length - 1];
            if (topOverlay) {
                event.preventDefault();
                window.HSOverlay.close(topOverlay);
            }
        });

        const closeButton = (dialog) => {
            const button = document.createElement('button');
            button.className = 'admin-dialog-close';
            button.type = 'button';
            button.setAttribute('aria-label', 'Tutup');
            button.textContent = '×';
            button.addEventListener('click', () => dialog.close());
            return button;
        };

        const makeDialog = (content, title) => {
            const dialog = document.createElement('dialog');
            dialog.className = 'admin-dialog';
            const header = document.createElement('div');
            header.className = 'admin-dialog-head';
            const heading = document.createElement('h2');
            heading.textContent = title;
            header.append(heading, closeButton(dialog));
            const body = document.createElement('div');
            body.className = 'admin-dialog-content';
            body.append(content);
            dialog.append(header, body);
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
            return dialog;
        };

        document.querySelectorAll('section.card.modal-form').forEach((section) => {
            const parent = section.parentNode;
            const next = section.nextSibling;
            const title = section.querySelector('h3, .panel-head h2')?.textContent.trim() || 'Formulir';
            const dialog = makeDialog(section, title);
            section.classList.remove('modal-form');
            section.querySelector('h3')?.remove();
            section.querySelector('.panel-head')?.remove();
            parent.insertBefore(dialog, next);
        });

        document.querySelectorAll('form[data-popup-create]').forEach((form) => {
            const parent = form.parentNode;
            const next = form.nextSibling;
            const dialog = makeDialog(form, form.dataset.dialogTitle || 'Tambah data');
            dialog.id = form.dataset.dialogId || '';
            parent.insertBefore(dialog, next);
        });

        const promoteEditForm = (form, title) => {
            const parent = form.parentNode;
            const trigger = document.createElement('button');
            trigger.className = 'btn text-xs py-1 px-3';
            trigger.type = 'button';
            trigger.textContent = form.dataset.triggerLabel || 'Ubah';
            parent.insertBefore(trigger, form);
            const dialog = makeDialog(form, title);
            parent.insertBefore(dialog, trigger.nextSibling);
            trigger.addEventListener('click', () => openDialog(dialog));
        };

        document.querySelectorAll('details').forEach((details) => {
            const form = details.querySelector('form.modal-form');
            const summary = details.querySelector('summary');
            if (!form || !summary) return;

            const dialog = makeDialog(form, form.dataset.dialogTitle || summary.textContent.trim());
            const trigger = document.createElement('button');
            trigger.className = 'btn text-xs py-1 px-3';
            trigger.type = 'button';
            trigger.textContent = summary.textContent.trim();
            trigger.addEventListener('click', () => openDialog(dialog));
            details.replaceWith(trigger, dialog);
        });

        document.querySelectorAll('table form[class*="flex flex-wrap items-center gap-2"]:not([data-popup-edit])').forEach((form) => {
            const fieldLabel = form.querySelector('[aria-label]')?.getAttribute('aria-label') || 'data';
            promoteEditForm(form, `Ubah ${fieldLabel.charAt(0).toLocaleLowerCase('id')}${fieldLabel.slice(1)}`);
        });

        document.querySelectorAll('form[data-popup-edit]').forEach((form) => {
            promoteEditForm(form, form.dataset.dialogTitle || 'Ubah data');
        });

        document.querySelectorAll('.admin-dialog form').forEach((form) => {
            const activeCheckbox = form.querySelector('input[type="checkbox"][name="is_active"]');
            const activeSelect = form.querySelector('select[name="is_active"]');
            if (!activeCheckbox && !activeSelect) return;

            const archive = document.createElement('button');
            archive.className = 'btn bg-lighterror text-error text-xs py-1 px-3';
            archive.type = 'button';
            archive.textContent = 'Arsipkan';
            archive.addEventListener('click', () => openConfirmation({
                title: 'Arsipkan data ini?',
                message: 'Data akan dinonaktifkan dan riwayatnya tetap tersimpan.',
                confirmLabel: 'Arsipkan',
                onConfirm: () => {
                    if (activeCheckbox) activeCheckbox.checked = false;
                    if (activeSelect) activeSelect.value = '0';
                    form.requestSubmit();
                },
            }));

            const actions = form.querySelector('.location-actions') || form;
            const submit = actions.querySelector('button[type="submit"]');
            if (submit) submit.parentElement.insertBefore(archive, submit);
            else actions.append(archive);
        });

        const confirmation = document.createElement('dialog');
        confirmation.className = 'admin-confirm-dialog';
        confirmation.innerHTML = '<h2></h2><p></p><div class="admin-confirm-actions"><button type="button" class="btn bg-lightprimary text-primary">Batal</button><button type="button" class="btn bg-lighterror text-error"></button></div>';
        document.body.append(confirmation);
        const confirmationTitle = confirmation.querySelector('h2');
        const confirmationMessage = confirmation.querySelector('p');
        const cancel = confirmation.querySelector('.btn.bg-lightprimary');
        const confirm = confirmation.querySelector('.btn.bg-lighterror');
        let confirmAction = null;

        const openConfirmation = ({title, message, confirmLabel, onConfirm}) => {
            confirmationTitle.textContent = title;
            confirmationMessage.textContent = message;
            confirm.textContent = confirmLabel;
            confirmAction = onConfirm;
            confirmation.showModal();
        };

        cancel.addEventListener('click', () => confirmation.close());
        confirm.addEventListener('click', () => {
            const action = confirmAction;
            confirmation.close();
            confirmAction = null;
            action?.();
        });
        confirmation.addEventListener('click', (event) => {
            if (event.target === confirmation) confirmation.close();
        });

        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href^="#"]');
            if (!link) return;
            const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
            const dialog = target?.closest('dialog');
            if (!dialog) return;
            event.preventDefault();
            openDialog(dialog);
        });

        document.querySelectorAll('form[method="post"]').forEach((form) => {
            const method = form.querySelector('input[name="_method"]')?.value?.toUpperCase();
            if ((method !== 'DELETE' && !form.dataset.confirmAction) || form.hasAttribute('data-confirmed')) return;
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === 'true') return;
                event.preventDefault();
                const isRestore = form.dataset.confirmAction === 'restore';
                const isEmployeeDeactivation = form.dataset.confirmAction === 'deactivate-employee';
                openConfirmation({
                    title: isRestore
                        ? 'Aktifkan kembali akun ini?'
                        : (isEmployeeDeactivation ? 'Nonaktifkan akun ini?' : 'Arsipkan data ini?'),
                    message: isRestore
                        ? 'Akun akan dapat digunakan kembali untuk masuk ke Attendly.'
                        : (isEmployeeDeactivation
                            ? 'Karyawan tidak dapat masuk lagi, tetapi riwayat absensi dan pengajuannya tetap disimpan.'
                            : 'Data akan diarsipkan dan riwayatnya tetap tersimpan.'),
                    confirmLabel: isRestore ? 'Aktifkan kembali' : (isEmployeeDeactivation ? 'Nonaktifkan' : 'Arsipkan'),
                    onConfirm: () => {
                        form.dataset.confirmed = 'true';
                        form.requestSubmit();
                    },
                });
            });
        });

        const validationErrorKeys = @js($errors->keys());
        const validationErrorValues = @js(collect($errors->keys())->mapWithKeys(fn ($key) => [$key => old($key)])->all());
        if (validationErrorKeys.length > 0) {
            const normalizeFieldName = (name) => name.replace(/\[(.*?)\]/g, '.$1');
            const matchingErrorFields = Array.from(document.querySelectorAll('input[name], select[name], textarea[name]'))
                .filter((field) => validationErrorKeys.includes(normalizeFieldName(field.name)));
            const errorField = matchingErrorFields.find((field) => {
                const submittedValue = validationErrorValues[normalizeFieldName(field.name)];
                return field.value === String(submittedValue ?? '');
            }) || matchingErrorFields[0];
            const errorDialog = errorField?.closest('dialog');
            if (errorDialog) {
                openDialog(errorDialog);
                errorField.focus({preventScroll: true});
            }
        }
    })();
</script>
</body>
</html>
