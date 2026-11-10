<!doctype html>
<html lang="id" dir="ltr" data-color-theme="Blue_Theme" class="light selected" data-layout="vertical"
    data-boxed-layout="boxed" data-card="shadow">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Admin · Attendly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('themes/tailwindadmin/css/theme.css') }}">
    <style>
        html{font-family:"DM Sans",ui-sans-serif,system-ui,sans-serif}
        .admin-login-main{display:flex;min-height:100vh;min-height:100dvh;align-items:center}
        .admin-login-content{display:flex;min-height:100vh;min-height:100dvh;width:100%;align-items:center;justify-content:center;padding:32px 16px}
    </style>
</head>
<body class="DEFAULT_THEME bg-white dark:bg-dark">
    <main>
        <div id="main-wrapper" class="flex">
            <main class="admin-login-main w-full bg-lightprimary dark:bg-dark">
                <div class="admin-login-content">
                    <div class="flex w-full justify-center">
                        <div class="w-full max-w-[460px]">
                            <div class="card">
                                <div class="card-body">
                                    <div class="mx-auto mb-8 text-center">
                                        <div class="flex items-center justify-center gap-3">
                                            <img src="{{ asset('images/attendly-mark.svg') }}" class="h-10 w-10" alt="">
                                            <span class="text-xl font-semibold text-dark dark:text-white">Attendly</span>
                                        </div>
                                    </div>
                                    <div class="mb-5 text-center">
                                        <h1 class="text-2xl font-semibold text-dark dark:text-white">Selamat datang</h1>
                                        <p class="mt-2 text-sm text-bodytext">Masuk untuk mengelola kehadiran dan aktivitas tim.</p>
                                    </div>
                                    @if ($errors->any())
                                        <div class="mb-4 rounded-md bg-lighterror p-3 text-sm text-error" role="alert">{{ $errors->first() }}</div>
                                    @endif
                                    <div class="flex items-center text-base text-bodytext before:me-4 before:flex-[1_1_0%] before:border-t before:border-border after:ms-4 after:flex-[1_1_0%] after:border-t after:border-border">
                                        Masuk dengan akun Attendly
                                    </div>
                                    <form method="post" action="{{ route('admin.login.store') }}">
                                        @csrf
                                        <div class="mt-4 flex flex-col gap-4">
                                            <div>
                                                <label class="mb-2 block font-semibold text-dark dark:text-darklink" for="email">Email</label>
                                                <input class="form-control py-2" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
                                            </div>
                                            <div>
                                                <label class="mb-2 block font-semibold text-dark dark:text-darklink" for="password">Kata sandi</label>
                                                <input class="form-control py-2" id="password" name="password" type="password" autocomplete="current-password" required>
                                            </div>
                                            <div class="my-2">
                                                <label class="flex cursor-pointer items-center">
                                                    <input class="h-4 w-4 rounded-md border-bordergray bg-transparent text-primary focus:ring-0 focus:ring-offset-0" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                                                    <span class="ms-2 text-sm text-bodytext">Ingat perangkat ini</span>
                                                </label>
                                            </div>
                                            <button class="btn btn-md w-full cursor-pointer py-3" type="submit">Masuk</button>
                                            <p class="mt-2 text-center text-sm text-bodytext">Akses khusus administrator, HR, dan pimpinan terdaftar.</p>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </main>
</body>
</html>
