import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter/services.dart'
    show PlatformException, SystemUiOverlayStyle;
import 'package:attendly_mobile/attendly_api.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart' as latlong;
import 'package:attendly_mobile/play_integrity.dart';
import 'package:attendly_mobile/push_notifications.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';

void main() {
  runApp(const AttendlyApp());
}

class AttendanceCoordinates {
  const AttendanceCoordinates({
    required this.latitude,
    required this.longitude,
    this.isMocked = false,
    this.accuracyMeters,
  });

  final double latitude;
  final double longitude;
  final bool isMocked;
  final double? accuracyMeters;
}

abstract final class AppColors {
  static const navy = Color(0xFF062C68);
  static const blue = Color(0xFF0875D1);
  static const ink = Color(0xFF183B67);
  static const muted = Color(0xFF657B93);
  static const background = Color(0xFFF3F7FD);
  static const line = Color(0xFFDCE9F8);
  static const green = Color(0xFF287A5D);
  static const orange = Color(0xFF9A681F);
}

class AttendlyApp extends StatefulWidget {
  const AttendlyApp({
    super.key,
    this.api,
    this.getCurrentLocation,
    this.captureSelfie,
  });

  final AttendlyApi? api;
  final Future<AttendanceCoordinates> Function()? getCurrentLocation;
  final Future<XFile?> Function()? captureSelfie;

  @override
  State<AttendlyApp> createState() => _AttendlyAppState();
}

class _AttendlyAppState extends State<AttendlyApp> {
  late final AttendlyApi _api = widget.api ?? HttpAttendlyApi();
  late final AttendlyPushNotifications? _push = widget.api == null
      ? AttendlyPushNotifications(_api)
      : null;
  final _messengerKey = GlobalKey<ScaffoldMessengerState>();
  final _attendanceShellKey = GlobalKey<_AttendanceShellState>();
  StreamSubscription<Map<String, dynamic>>? _pushTapSubscription;
  Employee? _employee;
  bool _checkingSession = true;
  String? _sessionError;
  Map<String, dynamic>? _pendingPushData;

  @override
  void initState() {
    super.initState();
    _pushTapSubscription = _push?.openedNotifications.listen(_handlePushTap);
    unawaited(_initializePush());
    _restoreSession();
  }

  Future<void> _initializePush() async {
    try {
      await _push?.initialize();
    } catch (error) {
      debugPrint('Attendly FCM initialization failed: $error');
    }
  }

  Future<void> _registerPushDevice() async {
    try {
      await _push?.registerDevice();
    } catch (error) {
      debugPrint('Attendly FCM device registration failed: $error');
    }
  }

  void _handlePushTap(Map<String, dynamic> data) {
    if (_employee == null) {
      _pendingPushData = data;
      return;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final shell = _attendanceShellKey.currentState;
      if (shell == null) {
        _pendingPushData = data;
      } else {
        shell.openNotification(data);
      }
    });
  }

  void _openPendingPush() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final data = _pendingPushData;
      final shell = _attendanceShellKey.currentState;
      if (data != null && shell != null) {
        _pendingPushData = null;
        shell.openNotification(data);
      }
    });
  }

  Future<void> _restoreSession() async {
    setState(() {
      _checkingSession = true;
      _sessionError = null;
    });
    try {
      final token = await _api.readToken();
      if (token != null && token.isNotEmpty) {
        _employee = await _api.me();
        unawaited(_registerPushDevice());
      }
    } on ApiException catch (error) {
      if (error.statusCode == 401) {
        await _api.clearToken();
        _employee = null;
      } else {
        _sessionError = error.message;
      }
    } catch (error) {
      _sessionError = 'Tidak dapat memeriksa sesi: $error';
    } finally {
      if (mounted) {
        setState(() => _checkingSession = false);
        _openPendingPush();
      }
    }
  }

  void _signIn(Employee employee) {
    setState(() {
      _employee = employee;
      _sessionError = null;
    });
    unawaited(_registerPushDevice());
    _openPendingPush();
  }

  Future<void> _signOut() async {
    String? errorMessage;
    try {
      await _push?.unregisterDevice();
    } catch (error) {
      debugPrint('Attendly FCM device unregister failed: $error');
    }
    try {
      await _api.logout();
    } catch (error) {
      await _api.clearToken();
      errorMessage = error is ApiException
          ? error.message
          : 'Token lokal dihapus, tetapi server tidak dapat dihubungi: $error';
    } finally {
      if (mounted) setState(() => _employee = null);
    }
    if (errorMessage != null) {
      _messengerKey.currentState?.showSnackBar(
        SnackBar(
          content: Text('Sesi lokal diakhiri. $errorMessage'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  void dispose() {
    unawaited(_pushTapSubscription?.cancel());
    unawaited(_push?.dispose());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      scaffoldMessengerKey: _messengerKey,
      title: 'Attendly',
      debugShowCheckedModeBanner: false,
      locale: const Locale('id', 'ID'),
      supportedLocales: const [Locale('id', 'ID')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: ThemeData(
        useMaterial3: true,
        scaffoldBackgroundColor: AppColors.background,
        colorScheme: ColorScheme.fromSeed(
          seedColor: AppColors.blue,
          primary: AppColors.blue,
          surface: Colors.white,
        ),
        textTheme: const TextTheme(
          titleLarge: TextStyle(
            color: AppColors.ink,
            fontSize: 21,
            fontWeight: FontWeight.w700,
            letterSpacing: -0.25,
          ),
          titleMedium: TextStyle(
            color: AppColors.ink,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
          bodyMedium: TextStyle(color: AppColors.ink, fontSize: 14),
          bodySmall: TextStyle(color: AppColors.muted, fontSize: 12),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: const Color(0xFFF7FAFE),
          isDense: true,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 13,
            vertical: 13,
          ),
          hintStyle: const TextStyle(
            color: AppColors.muted,
            fontSize: 13,
            fontWeight: FontWeight.w400,
          ),
          counterStyle: const TextStyle(color: AppColors.muted, fontSize: 10),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.line),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.line),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.blue, width: 1.4),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFB54747)),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFB54747), width: 1.4),
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            backgroundColor: AppColors.blue,
            foregroundColor: Colors.white,
            minimumSize: const Size(48, 46),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
            textStyle: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.blue,
            minimumSize: const Size(44, 44),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            side: const BorderSide(color: AppColors.line),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
            textStyle: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        navigationBarTheme: NavigationBarThemeData(
          backgroundColor: Colors.white,
          indicatorColor: const Color(0xFFE7EEF1),
          elevation: 0,
          height: 66,
          labelTextStyle: WidgetStateProperty.resolveWith((states) {
            return TextStyle(
              color: states.contains(WidgetState.selected)
                  ? AppColors.blue
                  : AppColors.muted,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            );
          }),
        ),
      ),
      home: _checkingSession
          ? const _SessionLoadingScreen()
          : _sessionError != null
          ? _SessionErrorScreen(
              message: _sessionError!,
              onRetry: _restoreSession,
              onSignOut: _signOut,
            )
          : _employee == null
          ? _LoginScreen(api: _api, onSignedIn: _signIn)
          : AttendanceShell(
              key: _attendanceShellKey,
              api: _api,
              employee: _employee!,
              onSignOut: _signOut,
              getCurrentLocation: widget.getCurrentLocation,
              captureSelfie: widget.captureSelfie,
            ),
    );
  }
}

class _SessionLoadingScreen extends StatelessWidget {
  const _SessionLoadingScreen();

  @override
  Widget build(BuildContext context) => Scaffold(
    key: const Key('session.loading'),
    body: DecoratedBox(
      decoration: const BoxDecoration(color: Colors.white),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            children: [
              const SizedBox(height: 24),
              const Center(child: _BrandMark(onLight: true)),
              const Spacer(),
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF0F2),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.fingerprint_rounded,
                  color: AppColors.navy,
                  size: 30,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'Menyiapkan ruang kerjamu',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontSize: 20,
                  letterSpacing: -0.25,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Sebentar ya, kami sedang memeriksa sesi akun.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AppColors.muted,
                  fontSize: 13,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 24),
              const SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(
                  key: Key('session.loading.progress'),
                  strokeWidth: 2.2,
                  color: AppColors.blue,
                ),
              ),
              const Spacer(),
              const Text(
                'Attendly · Absensi karyawan',
                style: TextStyle(
                  color: AppColors.muted,
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 12),
            ],
          ),
        ),
      ),
    ),
  );
}

class _SessionErrorScreen extends StatelessWidget {
  const _SessionErrorScreen({
    required this.message,
    required this.onRetry,
    required this.onSignOut,
  });

  final String message;
  final VoidCallback onRetry;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off_outlined,
              size: 42,
              color: AppColors.blue,
            ),
            const SizedBox(height: 16),
            const Text(
              'Belum bisa terhubung ke server',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 18),
            FilledButton(onPressed: onRetry, child: const Text('Coba lagi')),
            TextButton(
              onPressed: onSignOut,
              child: const Text('Kembali ke login'),
            ),
          ],
        ),
      ),
    ),
  );
}

class _LoginScreen extends StatefulWidget {
  const _LoginScreen({required this.api, required this.onSignedIn});

  final AttendlyApi api;
  final ValueChanged<Employee> onSignedIn;

  @override
  State<_LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<_LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _submitting = false;
  bool _hidePassword = true;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final employee = await widget.api.login(
        _emailController.text.trim(),
        _passwordController.text,
      );
      if (mounted) widget.onSignedIn(employee);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) {
        setState(() => _error = 'Tidak dapat login: $error');
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(child: _BrandMark(onLight: true)),
                    const SizedBox(height: 30),
                    Text(
                      'Selamat datang',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 7),
                    const Text(
                      'Masuk dengan akun karyawan untuk melanjutkan.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.muted),
                    ),
                    const SizedBox(height: 28),
                    const _FieldLabel('Email'),
                    TextFormField(
                      key: const Key('login.email'),
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(
                        hintText: 'nama@perusahaan.co.id',
                        prefixIcon: Icon(Icons.email_outlined),
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        final email = value?.trim() ?? '';
                        if (email.isEmpty || !email.contains('@')) {
                          return 'Masukkan email yang valid.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    const _FieldLabel('Kata sandi'),
                    TextFormField(
                      key: const Key('login.password'),
                      controller: _passwordController,
                      obscureText: _hidePassword,
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        hintText: 'Masukkan kata sandi',
                        prefixIcon: const Icon(Icons.lock_outline_rounded),
                        suffixIcon: IconButton(
                          onPressed: () =>
                              setState(() => _hidePassword = !_hidePassword),
                          icon: Icon(
                            _hidePassword
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                          ),
                        ),
                        border: const OutlineInputBorder(),
                      ),
                      validator: (value) => value == null || value.isEmpty
                          ? 'Kata sandi wajib diisi.'
                          : null,
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        _error!,
                        key: const Key('login.error'),
                        style: const TextStyle(
                          color: Color(0xFFB42338),
                          fontSize: 13,
                        ),
                      ),
                    ],
                    const SizedBox(height: 18),
                    FilledButton(
                      key: const Key('login.submit'),
                      onPressed: _submitting ? null : _submit,
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                      ),
                      child: _submitting
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Masuk'),
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'Gunakan akun yang dibuat oleh administrator.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.muted, fontSize: 11),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class AttendanceShell extends StatefulWidget {
  const AttendanceShell({
    super.key,
    required this.api,
    required this.employee,
    required this.onSignOut,
    this.getCurrentLocation,
    this.captureSelfie,
  });

  final AttendlyApi api;
  final Employee employee;
  final Future<void> Function() onSignOut;
  final Future<AttendanceCoordinates> Function()? getCurrentLocation;
  final Future<XFile?> Function()? captureSelfie;

  @override
  State<AttendanceShell> createState() => _AttendanceShellState();
}

class _AttendanceShellState extends State<AttendanceShell> {
  int _selectedTab = 0;
  late Employee _employee;
  DateTime _now = DateTime.now();
  AttendanceRecord? _todayAttendance;
  LeaveBalanceRecord? _leaveBalance;
  AttendanceSettings? _attendanceSettings;
  List<AttendanceRecord> _attendanceHistory = [];
  List<AttendanceCorrectionRecord> _attendanceCorrections = [];
  List<LeaveRequestRecord> _requests = [];
  List<LeaveRequestReviewItem> _reviewQueue = [];
  List<ReviewWorkLocation> _reviewLocations = [];
  List<AppNotification> _notifications = [];
  int _unreadNotifications = 0;
  bool _loading = true;
  String? _loadError;
  late final Timer _clock;

  @override
  void initState() {
    super.initState();
    _employee = widget.employee;
    _loadDashboard();
    _clock = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
  }

  @override
  void dispose() {
    _clock.cancel();
    super.dispose();
  }

  Future<bool> _loadDashboard({bool showLoading = true}) async {
    if (showLoading && mounted) {
      setState(() {
        _loading = true;
        _loadError = null;
      });
    }
    try {
      final results = await Future.wait<dynamic>([
        widget.api.todayAttendance(),
        widget.api.attendances(),
        widget.api.attendanceCorrections(),
        widget.api.leaveRequests(),
        widget.api.notifications(),
        widget.api.attendanceSettings(),
        widget.api.leaveBalance(),
        _employee.canReviewLeaveRequests
            ? widget.api.reviewQueue()
            : Future.value(
                const LeaveRequestReviewQueue(requests: [], workLocations: []),
              ),
      ]);
      if (!mounted || !context.mounted) return false;
      setState(() {
        _todayAttendance = results[0] as AttendanceRecord?;
        _attendanceHistory = results[1] as List<AttendanceRecord>;
        _attendanceCorrections = results[2] as List<AttendanceCorrectionRecord>;
        _requests = results[3] as List<LeaveRequestRecord>;
        final inbox = results[4] as NotificationInbox;
        _notifications = inbox.notifications;
        _unreadNotifications = inbox.unreadCount;
        _attendanceSettings = results[5] as AttendanceSettings;
        _leaveBalance = results[6] as LeaveBalanceRecord;
        final reviewQueue = results[7] as LeaveRequestReviewQueue;
        _reviewQueue = reviewQueue.requests;
        _reviewLocations = reviewQueue.workLocations;
        _loadError = null;
      });
      return true;
    } on ApiException catch (error) {
      if (error.statusCode == 401) {
        await widget.onSignOut();
        return false;
      }
      if (mounted) setState(() => _loadError = error.message);
      return false;
    } catch (error) {
      if (mounted) setState(() => _loadError = 'Gagal memuat data: $error');
      return false;
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showError(Object error) {
    if (!mounted) return;
    final message = error is ApiException
        ? error.message
        : 'Terjadi kesalahan: $error';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  void openNotification(Map<String, dynamic> data) {
    final kind = data['kind'];
    setState(() {
      _selectedTab = switch (kind) {
        'leave_request_review' => _employee.canReviewLeaveRequests ? 4 : 3,
        'leave_request_update' => 3,
        'attendance_correction_review' || 'attendance_correction_update' => 2,
        'attendance_late_check_in' || 'attendance_missing_check_in' => 1,
        _ => 0,
      };
    });
  }

  Future<void> _showAttendanceSelfie(int attendanceId, bool checkOut) async {
    try {
      final bytes = await widget.api.attendanceSelfieBytes(
        attendanceId: attendanceId,
        checkOut: checkOut,
      );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => Dialog(
          insetPadding: const EdgeInsets.all(20),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        checkOut ? 'Selfie absen pulang' : 'Selfie absen masuk',
                        style: const TextStyle(
                          color: AppColors.ink,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    IconButton(
                      key: const Key('history.selfie.close'),
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                Flexible(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.memory(
                      bytes,
                      key: const Key('history.selfie.image'),
                      fit: BoxFit.contain,
                      errorBuilder: (context, error, stackTrace) =>
                          const Padding(
                            padding: EdgeInsets.all(24),
                            child: Text('Foto selfie tidak dapat ditampilkan.'),
                          ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    } catch (error) {
      _showError(error);
    }
  }

  Future<void> _reviewLeaveRequest(
    LeaveRequestReviewItem request,
    String decision,
  ) async {
    var reviewNote = '';
    int? workLocationId;
    var isOnline = false;
    var submitting = false;
    var locationRequiredError = false;
    final needsLocation =
        request.isFinalReviewer &&
        (request.type == 'perjalanan_dinas' || request.type == 'diklat');

    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(
            decision == 'approved' ? 'Setujui pengajuan?' : 'Tolak pengajuan?',
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  decision == 'approved'
                      ? 'Persetujuan akan diteruskan ke tahap berikutnya bila masih diperlukan.'
                      : 'Penolakan akan mengakhiri proses pengajuan.',
                ),
                if (needsLocation && request.type == 'diklat') ...[
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Diklat online'),
                    value: isOnline,
                    onChanged: submitting
                        ? null
                        : (value) => setDialogState(() {
                            isOnline = value ?? false;
                            locationRequiredError = false;
                          }),
                  ),
                ],
                if (needsLocation && !isOnline) ...[
                  const SizedBox(height: 8),
                  DropdownButtonFormField<int>(
                    initialValue: workLocationId,
                    decoration: InputDecoration(
                      labelText: request.type == 'diklat'
                          ? 'Lokasi diklat'
                          : 'Lokasi perjalanan dinas',
                      errorText: locationRequiredError
                          ? 'Pilih lokasi terlebih dahulu.'
                          : null,
                    ),
                    items: _reviewLocations
                        .map(
                          (location) => DropdownMenuItem(
                            value: location.id,
                            child: Text(location.name),
                          ),
                        )
                        .toList(),
                    onChanged: submitting
                        ? null
                        : (value) => setDialogState(() {
                            workLocationId = value;
                            locationRequiredError = false;
                          }),
                  ),
                ],
                const SizedBox(height: 12),
                TextField(
                  maxLines: 3,
                  maxLength: 1000,
                  onChanged: (value) => reviewNote = value,
                  decoration: const InputDecoration(
                    labelText: 'Catatan (opsional)',
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: submitting
                  ? null
                  : () => Navigator.of(dialogContext).pop(),
              child: const Text('Batal'),
            ),
            FilledButton(
              key: const Key('approvals.confirm'),
              onPressed: submitting
                  ? null
                  : () async {
                      if (decision == 'approved' &&
                          needsLocation &&
                          !isOnline &&
                          workLocationId == null) {
                        setDialogState(() => locationRequiredError = true);
                        return;
                      }
                      setDialogState(() => submitting = true);
                      try {
                        await widget.api.reviewLeaveRequest(
                          requestId: request.id,
                          decision: decision,
                          note: reviewNote.trim().isEmpty
                              ? null
                              : reviewNote.trim(),
                          workLocationId: isOnline ? null : workLocationId,
                          isOnline: isOnline,
                        );
                        if (!mounted || !dialogContext.mounted) return;
                        Navigator.of(dialogContext).pop();
                        await _loadDashboard(showLoading: false);
                        if (!mounted) return;
                        ScaffoldMessenger.of(this.context).showSnackBar(
                          SnackBar(
                            content: Text(
                              decision == 'approved'
                                  ? 'Persetujuan berhasil diproses.'
                                  : 'Pengajuan berhasil ditolak.',
                            ),
                            behavior: SnackBarBehavior.floating,
                          ),
                        );
                      } catch (error) {
                        if (dialogContext.mounted) {
                          setDialogState(() => submitting = false);
                        }
                        _showError(error);
                      }
                    },
              child: submitting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(decision == 'approved' ? 'Setujui' : 'Tolak'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openNotifications() async {
    try {
      final inbox = await widget.api.notifications();
      if (!mounted) return;
      setState(() {
        _notifications = inbox.notifications;
        _unreadNotifications = inbox.unreadCount;
      });
      await Navigator.of(context).push<void>(
        MaterialPageRoute(
          builder: (_) => _NotificationsPage(
            api: widget.api,
            notifications: _notifications,
            unreadCount: _unreadNotifications,
          ),
        ),
      );
      await _loadDashboard(showLoading: false);
    } catch (error) {
      _showError(error);
    }
  }

  Future<void> _openFeature(String feature) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => _FeaturePage(
          api: widget.api,
          feature: feature,
          leaveBalance: feature == 'izin' ? _leaveBalance : null,
          onCreateRequest: (type, allowedTypes) =>
              _openRequestForm(initialType: type, allowedTypes: allowedTypes),
          onCreateActivity: _openActivityReportForm,
        ),
      ),
    );
    if (mounted) await _loadDashboard(showLoading: false);
  }

  Future<void> _openActivityReportForm() async {
    final titleController = TextEditingController();
    final descriptionController = TextEditingController();
    final hoursController = TextEditingController();
    var saving = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Container(
            padding: const EdgeInsets.fromLTRB(22, 12, 22, 24),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
            ),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const _SheetHandle(),
                  Text(
                    'Lapor aktivitas',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 16),
                  const _FieldLabel('Judul aktivitas'),
                  TextField(
                    controller: titleController,
                    maxLength: 160,
                    decoration: const InputDecoration(
                      hintText: 'Contoh: Rekonsiliasi data absensi',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const _FieldLabel('Uraian hasil pekerjaan'),
                  TextField(
                    controller: descriptionController,
                    minLines: 3,
                    maxLines: 5,
                    maxLength: 5000,
                    decoration: const InputDecoration(
                      hintText: 'Jelaskan pekerjaan dan hasilnya...',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const _FieldLabel('Durasi (jam, opsional)'),
                  TextField(
                    controller: hoursController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      hintText: 'Contoh: 2.5',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: saving
                          ? null
                          : () async {
                              final hours = double.tryParse(
                                hoursController.text.trim(),
                              );
                              if (titleController.text.trim().isEmpty ||
                                  descriptionController.text.trim().isEmpty ||
                                  (hoursController.text.trim().isNotEmpty &&
                                      (hours == null ||
                                          hours <= 0 ||
                                          hours > 24))) {
                                _showError(
                                  const ApiException(
                                    'Lengkapi judul, uraian, dan durasi yang valid.',
                                  ),
                                );
                                return;
                              }
                              setSheetState(() => saving = true);
                              try {
                                await widget.api.createActivityReport(
                                  activityDate: DateTime.now(),
                                  title: titleController.text.trim(),
                                  description: descriptionController.text
                                      .trim(),
                                  hours: hours,
                                );
                                if (sheetContext.mounted) {
                                  Navigator.of(sheetContext).pop();
                                }
                                await _loadDashboard(showLoading: false);
                                if (mounted) {
                                  ScaffoldMessenger.of(
                                    this.context,
                                  ).showSnackBar(
                                    const SnackBar(
                                      content: Text(
                                        'Aktivitas berhasil dikirim.',
                                      ),
                                      behavior: SnackBarBehavior.floating,
                                    ),
                                  );
                                }
                              } catch (error) {
                                _showError(error);
                                if (sheetContext.mounted) {
                                  setSheetState(() => saving = false);
                                }
                              }
                            },
                      child: Text(saving ? 'Mengirim...' : 'Kirim aktivitas'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    titleController.dispose();
    descriptionController.dispose();
    hoursController.dispose();
  }

  Future<AttendanceCoordinates> _deviceLocation() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const ApiException(
        'Aktifkan layanan lokasi/GPS untuk melakukan absensi.',
      );
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const ApiException(
        'Izin lokasi diperlukan untuk mencatat absensi. Aktifkan izin lokasi aplikasi.',
      );
    }

    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );
      return AttendanceCoordinates(
        latitude: position.latitude,
        longitude: position.longitude,
        isMocked: position.isMocked,
        accuracyMeters: position.accuracy,
      );
    } on LocationServiceDisabledException {
      throw const ApiException('Layanan lokasi/GPS tidak aktif.');
    } on TimeoutException {
      throw const ApiException(
        'Lokasi belum berhasil didapat. Coba lagi di area dengan sinyal GPS yang baik.',
      );
    }
  }

  Future<XFile?> _captureSelfie() {
    if (kIsWeb) {
      throw const ApiException(
        'Preview web tidak menerima unggahan galeri. Gunakan aplikasi Attendly di Android/iOS untuk mengambil selfie dari kamera.',
      );
    }

    return ImagePicker().pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.front,
      maxWidth: 1280,
      imageQuality: 80,
    );
  }

  Future<void> _openAttendanceMap({required bool checkOut}) async {
    final settings = _attendanceSettings;
    if (settings == null) {
      _showError(const ApiException('Pengaturan absensi belum tersedia.'));
      return;
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => _AttendanceMapPage(
          settings: settings,
          attendance: _todayAttendance,
          now: _now,
          checkOut: checkOut,
          getCurrentLocation: settings.requiresLocation
              ? (widget.getCurrentLocation ?? _deviceLocation)
              : null,
          onSubmit: (position) =>
              _submitAttendance(checkOut: checkOut, position: position),
        ),
      ),
    );
    if (mounted) await _loadDashboard(showLoading: false);
  }

  Future<void> _submitAttendance({
    required bool checkOut,
    required AttendanceCoordinates? position,
  }) async {
    try {
      if (position?.isMocked == true) {
        throw const ApiException(
          'Lokasi terdeteksi sebagai GPS tiruan. Matikan aplikasi fake GPS dan coba lagi.',
        );
      }

      final settings =
          _attendanceSettings ?? await widget.api.attendanceSettings();
      final selfie = await (widget.captureSelfie ?? _captureSelfie)();
      if (selfie == null) return;
      if (checkOut) {
        final integrityToken = settings.playIntegrityRequired
            ? await const PlayIntegrityClient().createToken(
                userId: widget.employee.id,
                action: 'check-out',
                latitude: position?.latitude,
                longitude: position?.longitude,
                selfie: selfie,
              )
            : null;
        await widget.api.checkOut(
          latitude: position?.latitude,
          longitude: position?.longitude,
          selfie: selfie,
          integrityToken: integrityToken,
        );
      } else {
        final integrityToken = settings.playIntegrityRequired
            ? await const PlayIntegrityClient().createToken(
                userId: widget.employee.id,
                action: 'check-in',
                latitude: position?.latitude,
                longitude: position?.longitude,
                selfie: selfie,
              )
            : null;
        final attendance = await widget.api.checkIn(
          latitude: position?.latitude,
          longitude: position?.longitude,
          selfie: selfie,
          integrityToken: integrityToken,
        );
        await _loadDashboard(showLoading: false);
        if (!mounted) return;
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              attendance.verificationStatus == 'pending_review'
                  ? 'Absensi diklat online tercatat dan menunggu review.'
                  : 'Absen masuk dan selfie berhasil disimpan.',
            ),
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }

      await _loadDashboard(showLoading: false);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Absen pulang berhasil.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on PlatformException catch (error) {
      _showError(
        ApiException(
          error.message ??
              'Kamera tidak dapat digunakan. Periksa izin kamera lalu coba lagi.',
        ),
      );
    } catch (error) {
      if (error is ApiException && error.isNetworkError) {
        final refreshed = await _loadDashboard(showLoading: false);
        final attendanceRecorded = checkOut
            ? _todayAttendance?.checkOutAt != null
            : _todayAttendance != null;

        if (!mounted) return;
        if (refreshed && attendanceRecorded) {
          Navigator.of(context).pop();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Status absensi sudah dikonfirmasi server.'),
              behavior: SnackBarBehavior.floating,
            ),
          );
          return;
        }
        if (!refreshed) {
          Navigator.of(context).pop();
          _showError(
            const ApiException(
              'Koneksi terputus dan status absensi belum dapat dipastikan. '
              'Tekan Coba lagi di beranda sebelum mengirim ulang.',
            ),
          );
          return;
        }
        _showError(
          const ApiException(
            'Absensi belum tercatat. Koneksi sudah pulih, silakan coba lagi.',
          ),
        );
        return;
      }
      _showError(error);
    }
  }

  Future<void> _openRequestForm({
    String initialType = 'izin',
    List<String>? allowedTypes,
  }) async {
    final requestTypes =
        allowedTypes ??
        const ['izin', 'sakit', 'cuti', 'lembur', 'perjalanan_dinas', 'diklat'];
    if (!requestTypes.contains(initialType)) {
      throw ArgumentError.value(
        initialType,
        'initialType',
        'Jenis pengajuan harus termasuk dalam allowedTypes.',
      );
    }
    var selectedType = initialType;
    var selectedSubtype = _defaultRequestSubtype(initialType);
    var selectedStartDate = DateTime.now();
    var selectedEndDate = DateTime.now();
    var note = '';
    var additionalDetail = '';
    var startTime = '18:00';
    var endTime = '20:00';
    XFile? attachment;

    String? additionalDetailLabel() => switch (selectedType) {
      'perjalanan_dinas' => 'Kota / tujuan perjalanan',
      'diklat' => 'Nama penyelenggara diklat',
      'lembur' => 'Ringkasan pekerjaan lembur',
      _ => null,
    };

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Container(
            padding: const EdgeInsets.fromLTRB(22, 12, 22, 24),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
            ),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const _SheetHandle(),
                  Text(
                    'Pengajuan ${_requestTypeLabel(selectedType)}',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Isi kategori dan detail dengan benar. Atasan departemen meninjau sebelum keputusan akhir admin.',
                    style: TextStyle(color: AppColors.muted, fontSize: 13),
                  ),
                  const SizedBox(height: 20),
                  if (requestTypes.length > 1) ...[
                    const _FieldLabel('Jenis pengajuan'),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      children: requestTypes.map((type) {
                        return ChoiceChip(
                          label: Text(_requestTypeLabel(type)),
                          selected: selectedType == type,
                          onSelected: (_) => setSheetState(() {
                            selectedType = type;
                            selectedSubtype = _defaultRequestSubtype(type);
                            additionalDetail = '';
                            attachment = null;
                          }),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 14),
                  ],
                  if (_requestSubtypes(selectedType).isNotEmpty) ...[
                    const _FieldLabel('Kategori'),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      children: _requestSubtypes(selectedType).map((subtype) {
                        return ChoiceChip(
                          label: Text(_requestSubtypeLabel(subtype)),
                          selected: selectedSubtype == subtype,
                          onSelected: (_) =>
                              setSheetState(() => selectedSubtype = subtype),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 14),
                  ],
                  const _FieldLabel('Rentang tanggal'),
                  OutlinedButton.icon(
                    onPressed: () async {
                      final picked = await showDateRangePicker(
                        context: context,
                        initialDateRange: DateTimeRange(
                          start: selectedStartDate,
                          end: selectedEndDate,
                        ),
                        firstDate: DateTime.now().subtract(
                          const Duration(days: 365),
                        ),
                        lastDate: DateTime.now().add(const Duration(days: 365)),
                        locale: const Locale('id', 'ID'),
                      );
                      if (picked != null) {
                        setSheetState(() {
                          selectedStartDate = picked.start;
                          selectedEndDate = picked.end;
                        });
                      }
                    },
                    icon: const Icon(Icons.date_range_outlined, size: 17),
                    label: Text(
                      selectedStartDate == selectedEndDate
                          ? _formatDate(selectedStartDate)
                          : '${_formatDate(selectedStartDate)} – ${_formatDate(selectedEndDate)}',
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (selectedType == 'lembur') ...[
                    const _FieldLabel('Waktu lembur'),
                    Row(
                      children: [
                        Expanded(
                          child: _TimeOfDayField(
                            label: 'Mulai',
                            initialValue: startTime,
                            onChanged: (value) =>
                                setSheetState(() => startTime = value),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _TimeOfDayField(
                            label: 'Selesai',
                            initialValue: endTime,
                            onChanged: (value) =>
                                setSheetState(() => endTime = value),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                  ],
                  if (additionalDetailLabel() case final detailLabel?) ...[
                    _FieldLabel(detailLabel),
                    TextField(
                      onChanged: (value) =>
                          setSheetState(() => additionalDetail = value),
                      decoration: InputDecoration(
                        hintText: selectedType == 'diklat'
                            ? 'Contoh: Lembaga pelatihan'
                            : 'Contoh: Jakarta',
                        border: const OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],
                  if (selectedType == 'sakit') ...[
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () async {
                              final file = await FilePicker.pickFile(
                                type: FileType.custom,
                                allowedExtensions: [
                                  'pdf',
                                  'jpg',
                                  'jpeg',
                                  'png',
                                ],
                              );
                              if (file != null) {
                                attachment = file.xFile;
                                setSheetState(() {});
                              }
                            },
                            icon: const Icon(Icons.attach_file_rounded),
                            label: Text(
                              attachment?.name ??
                                  (selectedSubtype == 'sakit_dengan_surat'
                                      ? 'Lampirkan surat dokter *'
                                      : 'Lampirkan surat dokter (opsional)'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                        if (attachment != null) ...[
                          const SizedBox(width: 8),
                          IconButton(
                            tooltip: 'Hapus lampiran',
                            onPressed: () =>
                                setSheetState(() => attachment = null),
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ],
                    ),
                    if (selectedSubtype == 'sakit_dengan_surat' &&
                        attachment == null)
                      const Padding(
                        padding: EdgeInsets.only(top: 5),
                        child: Text(
                          'Surat dokter wajib untuk kategori ini.',
                          style: TextStyle(
                            color: Color(0xFFB42338),
                            fontSize: 11,
                          ),
                        ),
                      ),
                    const SizedBox(height: 14),
                  ],
                  const _FieldLabel('Alasan'),
                  TextField(
                    maxLines: 3,
                    onChanged: (value) => setSheetState(() => note = value),
                    decoration: const InputDecoration(
                      hintText: 'Tulis alasan pengajuan...',
                      alignLabelWithHint: true,
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed:
                          note.trim().isEmpty ||
                              _loading ||
                              (selectedType == 'sakit' &&
                                  selectedSubtype == 'sakit_dengan_surat' &&
                                  attachment == null) ||
                              (additionalDetailLabel() != null &&
                                  additionalDetail.trim().isEmpty)
                          ? null
                          : () async {
                              try {
                                await widget.api.createLeaveRequest(
                                  type: selectedType,
                                  startDate: selectedStartDate,
                                  endDate: selectedEndDate,
                                  reason: note.trim(),
                                  subtype:
                                      _requestSubtypes(selectedType).isNotEmpty
                                      ? selectedSubtype
                                      : null,
                                  attachment: attachment,
                                  details: switch (selectedType) {
                                    'perjalanan_dinas' => {
                                      'destination': additionalDetail.trim(),
                                    },
                                    'diklat' => {
                                      'institution': additionalDetail.trim(),
                                    },
                                    'lembur' => {
                                      'start_time': startTime,
                                      'end_time': endTime,
                                      'work_description': additionalDetail
                                          .trim(),
                                    },
                                    _ => const {},
                                  },
                                );
                                await _loadDashboard(showLoading: false);
                                if (!mounted ||
                                    !context.mounted ||
                                    !sheetContext.mounted) {
                                  return;
                                }
                                Navigator.of(sheetContext).pop();
                                ScaffoldMessenger.of(this.context).showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Pengajuan berhasil dikirim.',
                                    ),
                                    behavior: SnackBarBehavior.floating,
                                  ),
                                );
                              } catch (error) {
                                _showError(error);
                              }
                            },
                      child: Text(_loading ? 'Memuat...' : 'Kirim pengajuan'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openAttendanceCorrection(AttendanceRecord attendance) async {
    final existing = _correctionFor(attendance.id);
    if (existing != null && existing.status.startsWith('pending_')) {
      _showError(const ApiException('Permohonan koreksi masih dalam proses.'));
      return;
    }

    var checkInTime = TimeOfDay.fromDateTime(attendance.checkInAt);
    var hasCheckOut = attendance.checkOutAt != null;
    var checkOutTime = attendance.checkOutAt == null
        ? TimeOfDay.fromDateTime(
            attendance.checkInAt.add(const Duration(hours: 8)),
          )
        : TimeOfDay.fromDateTime(attendance.checkOutAt!);
    var reason = '';
    var submitting = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Container(
            padding: const EdgeInsets.fromLTRB(22, 12, 22, 24),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
            ),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const _SheetHandle(),
                  Text(
                    'Ajukan koreksi absensi',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Koreksi ditinjau atasan terlebih dahulu dan baru diterapkan HR. Perubahan lama tetap tercatat.',
                    style: TextStyle(color: AppColors.muted, fontSize: 13),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    _formatDate(attendance.workDate),
                    style: const TextStyle(
                      color: AppColors.ink,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  _TimeOfDayField(
                    label: 'Jam masuk yang benar',
                    initialValue: _formatTimeOfDay(checkInTime),
                    onChanged: (value) => setSheetState(() {
                      final parts = value.split(':');
                      checkInTime = TimeOfDay(
                        hour: int.parse(parts[0]),
                        minute: int.parse(parts[1]),
                      );
                    }),
                  ),
                  const SizedBox(height: 10),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Catat jam pulang'),
                    value: hasCheckOut,
                    onChanged: (value) => setSheetState(() {
                      hasCheckOut = value ?? false;
                    }),
                  ),
                  if (hasCheckOut) ...[
                    _TimeOfDayField(
                      label: 'Jam pulang yang benar',
                      initialValue: _formatTimeOfDay(checkOutTime),
                      onChanged: (value) => setSheetState(() {
                        final parts = value.split(':');
                        checkOutTime = TimeOfDay(
                          hour: int.parse(parts[0]),
                          minute: int.parse(parts[1]),
                        );
                      }),
                    ),
                    const SizedBox(height: 10),
                  ],
                  const _FieldLabel('Alasan koreksi'),
                  TextField(
                    maxLines: 3,
                    maxLength: 1000,
                    onChanged: (value) => reason = value,
                    decoration: const InputDecoration(
                      hintText: 'Jelaskan alasan perubahan jam absensi',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: submitting
                          ? null
                          : () async {
                              if (reason.trim().length < 5) {
                                _showError(
                                  const ApiException(
                                    'Alasan koreksi minimal 5 karakter.',
                                  ),
                                );
                                return;
                              }
                              setSheetState(() => submitting = true);
                              final date = attendance.workDate;
                              final checkInAt = DateTime(
                                date.year,
                                date.month,
                                date.day,
                                checkInTime.hour,
                                checkInTime.minute,
                              );
                              DateTime? checkOutAt;
                              if (hasCheckOut) {
                                var checkOutDate = DateTime(
                                  date.year,
                                  date.month,
                                  date.day,
                                );
                                if (attendance.checkOutAt != null &&
                                    (attendance.checkOutAt!.year != date.year ||
                                        attendance.checkOutAt!.month !=
                                            date.month ||
                                        attendance.checkOutAt!.day !=
                                            date.day)) {
                                  checkOutDate = DateTime(
                                    attendance.checkOutAt!.year,
                                    attendance.checkOutAt!.month,
                                    attendance.checkOutAt!.day,
                                  );
                                } else if (checkOutTime.hour * 60 +
                                        checkOutTime.minute <
                                    checkInTime.hour * 60 +
                                        checkInTime.minute) {
                                  checkOutDate = checkOutDate.add(
                                    const Duration(days: 1),
                                  );
                                }
                                checkOutAt = DateTime(
                                  checkOutDate.year,
                                  checkOutDate.month,
                                  checkOutDate.day,
                                  checkOutTime.hour,
                                  checkOutTime.minute,
                                );
                              }
                              try {
                                await widget.api.createAttendanceCorrection(
                                  attendanceId: attendance.id,
                                  checkInAt: checkInAt,
                                  checkOutAt: checkOutAt,
                                  reason: reason.trim(),
                                );
                                await _loadDashboard(showLoading: false);
                                if (!mounted || !sheetContext.mounted) return;
                                Navigator.of(sheetContext).pop();
                                ScaffoldMessenger.of(this.context).showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Permohonan koreksi absensi berhasil dikirim.',
                                    ),
                                    behavior: SnackBarBehavior.floating,
                                  ),
                                );
                              } catch (error) {
                                if (sheetContext.mounted) {
                                  setSheetState(() => submitting = false);
                                }
                                _showError(error);
                              }
                            },
                      child: Text(
                        submitting ? 'Mengirim...' : 'Kirim permohonan',
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  AttendanceCorrectionRecord? _correctionFor(int attendanceId) {
    for (final correction in _attendanceCorrections) {
      if (correction.attendanceId == attendanceId) return correction;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      _HomeTab(
        now: _now,
        leaveBalance: _leaveBalance,
        history: _attendanceHistory.map(_historyItem).toList(),
        onOpenSelfie: _showAttendanceSelfie,
        canReview: _employee.canReviewLeaveRequests,
        approvalCount: _reviewQueue.length,
        onOpenApprovals: () => setState(() => _selectedTab = 4),
        attendanceCount: _attendanceHistory
            .where(
              (record) =>
                  record.workDate.year == _now.year &&
                  record.workDate.month == _now.month,
            )
            .length,
        requests: _requests,
        unreadNotifications: _unreadNotifications,
        onOpenTab: (index) => setState(() => _selectedTab = index),
        onOpenAttendance: () => setState(() => _selectedTab = 1),
        onOpenFeature: _openFeature,
        onOpenNotifications: _openNotifications,
        onOpenProfile: () {
          Navigator.of(context).push<void>(
            MaterialPageRoute(
              builder: (_) => Scaffold(
                backgroundColor: AppColors.background,
                body: _ProfileTab(
                  api: widget.api,
                  employee: _employee,
                  onEmployeeUpdated: (employee) {
                    setState(() => _employee = employee);
                  },
                  onSignOut: widget.onSignOut,
                ),
              ),
            ),
          );
        },
      ),
      _AttendanceTab(
        now: _now,
        attendance: _todayAttendance,
        shift: _attendanceSettings?.shift,
        settings: _attendanceSettings,
        onCheckIn:
            !_loading &&
                _loadError == null &&
                _todayAttendance == null &&
                _attendanceSettings?.attendanceBlocked != true
            ? () => _openAttendanceMap(checkOut: false)
            : null,
        onCheckOut:
            !_loading &&
                _loadError == null &&
                _attendanceSettings?.attendanceBlocked != true &&
                _todayAttendance?.checkOutAt == null &&
                _todayAttendance != null
            ? () => _openAttendanceMap(checkOut: true)
            : null,
      ),
      _HistoryTab(
        items: _attendanceHistory
            .map(
              (attendance) => _historyItem(
                attendance,
                correctionStatus: _correctionFor(attendance.id)?.status,
              ),
            )
            .toList(),
        onOpenSelfie: _showAttendanceSelfie,
        onOpenRequests: () => setState(() => _selectedTab = 3),
        onOpenFeature: _openFeature,
        onRequestCorrection: (attendanceId) {
          final attendance = _attendanceHistory.firstWhere(
            (record) => record.id == attendanceId,
          );
          _openAttendanceCorrection(attendance);
        },
      ),
      _RequestsTab(
        requests: _requests,
        leaveBalance: _leaveBalance,
        onCreateRequest: _openRequestForm,
        loading: _loading,
      ),
      if (_employee.canReviewLeaveRequests)
        _ApprovalsTab(
          requests: _reviewQueue,
          loading: _loading,
          onReview: _reviewLeaveRequest,
          onRefresh: () => _loadDashboard(),
        ),
    ];

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: AppColors.navy,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
        systemNavigationBarColor: Colors.white,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Column(
          children: [
            if (_loadError != null)
              MaterialBanner(
                content: Text(_loadError!),
                leading: const Icon(Icons.cloud_off_outlined),
                actions: [
                  TextButton(
                    onPressed: _loading ? null : () => _loadDashboard(),
                    child: const Text('Coba lagi'),
                  ),
                ],
              ),
            Expanded(
              child: Stack(
                children: [
                  IndexedStack(index: _selectedTab, children: pages),
                  if (_loading && _todayAttendance == null)
                    const Positioned.fill(child: _DashboardLoadingOverlay()),
                ],
              ),
            ),
          ],
        ),
        bottomNavigationBar: _AttendanceBottomBar(
          selectedIndex: _selectedTab == 4
              ? 3
              : _selectedTab >= 2
              ? 2
              : _selectedTab,
          canReview: _employee.canReviewLeaveRequests,
          onSelected: (index) => setState(() => _selectedTab = index),
        ),
      ),
    );
  }
}

class _DashboardLoadingOverlay extends StatelessWidget {
  const _DashboardLoadingOverlay();

  @override
  Widget build(BuildContext context) => ColoredBox(
    key: const Key('dashboard.loading'),
    color: Colors.white.withValues(alpha: 0.94),
    child: Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 310),
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 28),
          padding: const EdgeInsets.fromLTRB(22, 24, 22, 20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.line),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF5FC),
                  borderRadius: BorderRadius.circular(19),
                ),
                child: const Icon(
                  Icons.space_dashboard_rounded,
                  color: AppColors.blue,
                  size: 29,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Memuat data kehadiran',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 6),
              const Text(
                'Menyiapkan ringkasan dan layanan karyawan.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AppColors.muted,
                  fontSize: 12,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 20),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: const LinearProgressIndicator(
                  minHeight: 5,
                  color: AppColors.blue,
                  backgroundColor: Color(0xFFEAF0F5),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _AttendanceBottomBar extends StatelessWidget {
  const _AttendanceBottomBar({
    required this.selectedIndex,
    required this.canReview,
    required this.onSelected,
  });

  final int selectedIndex;
  final bool canReview;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.paddingOf(context).bottom;
    return SizedBox(
      height: 72 + bottomInset,
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          border: Border(top: BorderSide(color: Color(0xFFE1EAF5))),
          boxShadow: [
            BoxShadow(
              color: Color(0x0A173B56),
              blurRadius: 16,
              offset: Offset(0, -5),
            ),
          ],
        ),
        padding: EdgeInsets.only(
          bottom: bottomInset,
          left: 7,
          right: 7,
          top: 5,
        ),
        child: Row(
          children: [
            Expanded(
              child: _BottomBarItem(
                key: const Key('navigation.home'),
                label: 'Beranda',
                icon: Icons.home_rounded,
                selected: selectedIndex == 0,
                onTap: () => onSelected(0),
              ),
            ),
            Expanded(
              child: _BottomBarItem(
                key: const Key('navigation.attendance'),
                label: 'Absen',
                icon: Icons.fingerprint_rounded,
                selected: selectedIndex == 1,
                onTap: () => onSelected(1),
              ),
            ),
            Expanded(
              child: _BottomBarItem(
                key: const Key('navigation.data'),
                label: 'Riwayat',
                icon: Icons.receipt_long_rounded,
                selected: selectedIndex == 2,
                onTap: () => onSelected(2),
              ),
            ),
            if (canReview)
              Expanded(
                child: _BottomBarItem(
                  key: const Key('navigation.approvals'),
                  label: 'Setuju',
                  icon: Icons.fact_check_outlined,
                  selected: selectedIndex == 3,
                  onTap: () => onSelected(4),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _BottomBarItem extends StatelessWidget {
  const _BottomBarItem({
    super.key,
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          constraints: const BoxConstraints(minHeight: 48),
          padding: EdgeInsets.symmetric(horizontal: selected ? 6 : 8),
          decoration: BoxDecoration(
            gradient: selected
                ? const LinearGradient(
                    colors: [Color(0xFF0875D1), Color(0xFF06489E)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  )
                : null,
            borderRadius: BorderRadius.circular(26),
            boxShadow: selected
                ? const [
                    BoxShadow(
                      color: Color(0x332D7AC0),
                      blurRadius: 12,
                      offset: Offset(0, 5),
                    ),
                  ]
                : null,
          ),
          child: selected
              ? FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(icon, size: 18, color: Colors.white),
                      const SizedBox(width: 4),
                      Text(
                        label,
                        maxLines: 1,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                )
              : Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(icon, size: 20, color: AppColors.muted),
                    const SizedBox(height: 2),
                    Text(
                      label,
                      maxLines: 1,
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 10,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
        ),
      ],
    ),
  );
}

class _NotificationsPage extends StatefulWidget {
  const _NotificationsPage({
    required this.api,
    required this.notifications,
    required this.unreadCount,
  });

  final AttendlyApi api;
  final List<AppNotification> notifications;
  final int unreadCount;

  @override
  State<_NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<_NotificationsPage> {
  late List<AppNotification> _notifications = List.of(widget.notifications);
  late int _unreadCount = widget.unreadCount;
  final Set<String> _updating = {};
  bool _markingAll = false;

  Future<void> _refresh() async {
    try {
      final inbox = await widget.api.notifications();
      if (!mounted) return;
      setState(() {
        _notifications = inbox.notifications;
        _unreadCount = inbox.unreadCount;
      });
    } catch (error) {
      _showError(error);
    }
  }

  Future<void> _markRead(AppNotification notification) async {
    if (notification.isRead || _updating.contains(notification.id)) return;
    setState(() => _updating.add(notification.id));
    try {
      await widget.api.markNotificationRead(notification.id);
      if (!mounted) return;
      setState(() {
        final index = _notifications.indexWhere(
          (item) => item.id == notification.id,
        );
        if (index >= 0) {
          _notifications[index] = AppNotification(
            id: notification.id,
            title: notification.title,
            message: notification.message,
            createdAt: notification.createdAt,
            data: notification.data,
            readAt: DateTime.now(),
          );
          _unreadCount = (_unreadCount - 1).clamp(0, _unreadCount).toInt();
        }
        _updating.remove(notification.id);
      });
    } catch (error) {
      if (mounted) setState(() => _updating.remove(notification.id));
      _showError(error);
    }
  }

  Future<void> _markAllRead() async {
    if (_unreadCount == 0 || _markingAll) return;
    setState(() => _markingAll = true);
    try {
      await widget.api.markAllNotificationsRead();
      if (!mounted) return;
      final readAt = DateTime.now();
      setState(() {
        _notifications = _notifications
            .map(
              (notification) => notification.isRead
                  ? notification
                  : AppNotification(
                      id: notification.id,
                      title: notification.title,
                      message: notification.message,
                      createdAt: notification.createdAt,
                      data: notification.data,
                      readAt: readAt,
                    ),
            )
            .toList();
        _unreadCount = 0;
        _markingAll = false;
      });
    } catch (error) {
      if (mounted) setState(() => _markingAll = false);
      _showError(error);
    }
  }

  void _showError(Object error) {
    if (!mounted) return;
    final message = error is ApiException
        ? error.message
        : 'Gagal memperbarui notifikasi: $error';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  String _dateLabel(DateTime value) {
    final local = value.toLocal();
    final day = local.day.toString().padLeft(2, '0');
    final month = local.month.toString().padLeft(2, '0');
    final hour = local.hour.toString().padLeft(2, '0');
    final minute = local.minute.toString().padLeft(2, '0');
    return '$day/$month/${local.year} · $hour:$minute';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Notifikasi'),
      actions: [
        TextButton(
          onPressed: _unreadCount == 0 || _markingAll ? null : _markAllRead,
          child: _markingAll
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Tandai semua dibaca'),
        ),
      ],
    ),
    body: _notifications.isEmpty
        ? RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: const [
                SizedBox(height: 150),
                Icon(
                  Icons.notifications_off_outlined,
                  size: 48,
                  color: AppColors.muted,
                ),
                SizedBox(height: 14),
                Center(
                  child: Text(
                    'Belum ada notifikasi',
                    style: TextStyle(color: AppColors.muted),
                  ),
                ),
              ],
            ),
          )
        : RefreshIndicator(
            onRefresh: _refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: _notifications.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final notification = _notifications[index];
                final updating = _updating.contains(notification.id);
                return Material(
                  color: notification.isRead
                      ? Colors.white
                      : const Color(0xFFF0F7FF),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                    side: const BorderSide(color: Color(0xFFDCE9F8)),
                  ),
                  child: InkWell(
                    key: Key('notifications.item.${notification.id}'),
                    onTap: notification.isRead
                        ? null
                        : () => _markRead(notification),
                    borderRadius: BorderRadius.circular(20),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            notification.isRead
                                ? Icons.notifications_none_rounded
                                : Icons.notifications_active_outlined,
                            color: AppColors.blue,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  notification.title,
                                  style: TextStyle(
                                    fontWeight: notification.isRead
                                        ? FontWeight.w600
                                        : FontWeight.w700,
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Text(notification.message),
                                const SizedBox(height: 8),
                                Text(
                                  _dateLabel(notification.createdAt),
                                  style: const TextStyle(
                                    color: AppColors.muted,
                                    fontSize: 11,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (updating)
                            const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          else if (!notification.isRead)
                            const Padding(
                              padding: EdgeInsets.only(top: 6),
                              child: CircleAvatar(
                                radius: 4,
                                backgroundColor: AppColors.blue,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
  );
}

class _HomeTab extends StatelessWidget {
  const _HomeTab({
    required this.now,
    required this.leaveBalance,
    required this.history,
    required this.onOpenSelfie,
    required this.canReview,
    required this.approvalCount,
    required this.onOpenApprovals,
    required this.attendanceCount,
    required this.requests,
    required this.unreadNotifications,
    required this.onOpenTab,
    required this.onOpenAttendance,
    required this.onOpenProfile,
    required this.onOpenFeature,
    required this.onOpenNotifications,
  });

  final DateTime now;
  final LeaveBalanceRecord? leaveBalance;
  final List<_HistoryItem> history;
  final Future<void> Function(int attendanceId, bool checkOut) onOpenSelfie;
  final bool canReview;
  final int approvalCount;
  final VoidCallback onOpenApprovals;
  final int attendanceCount;
  final List<LeaveRequestRecord> requests;
  final int unreadNotifications;
  final ValueChanged<int> onOpenTab;
  final VoidCallback onOpenAttendance;
  final VoidCallback onOpenProfile;
  final ValueChanged<String> onOpenFeature;
  final VoidCallback onOpenNotifications;

  @override
  Widget build(BuildContext context) {
    final approvedLeaveDays = <int>{};
    for (final request in requests.where(
      (item) =>
          item.status == 'approved' &&
          const {'izin', 'sakit', 'cuti'}.contains(item.type),
    )) {
      var day = DateTime(
        request.startDate.year,
        request.startDate.month,
        request.startDate.day,
      );
      final end = DateTime(
        request.endDate.year,
        request.endDate.month,
        request.endDate.day,
      );
      while (!day.isAfter(end)) {
        if (day.year == now.year && day.month == now.month) {
          approvedLeaveDays.add(day.millisecondsSinceEpoch);
        }
        day = day.add(const Duration(days: 1));
      }
    }
    final menuItems = <(String, String, IconData, Color)>[
      if (canReview)
        (
          'approvals',
          approvalCount == 0 ? 'Persetujuan' : 'Setuju ($approvalCount)',
          Icons.fact_check_outlined,
          const Color(0xFF0875D1),
        ),
      ('izin', 'Izin', Icons.description_outlined, const Color(0xFF0875D1)),
      (
        'lembur',
        'Lembur',
        Icons.hourglass_bottom_rounded,
        const Color(0xFF0875D1),
      ),
      (
        'payroll',
        'Info Gaji',
        Icons.payments_outlined,
        const Color(0xFF0875D1),
      ),
      (
        'activity',
        'Aktivitas',
        Icons.edit_note_rounded,
        const Color(0xFF0875D1),
      ),
      (
        'perjalanan',
        'Perjalanan',
        Icons.flight_takeoff_rounded,
        const Color(0xFF0875D1),
      ),
      ('diklat', 'Diklat', Icons.co_present_outlined, const Color(0xFF0875D1)),
      ('spt', 'SPT', Icons.request_quote_outlined, const Color(0xFF0875D1)),
      ('news', 'Berita', Icons.newspaper_rounded, const Color(0xFF0875D1)),
    ];

    return SafeArea(
      top: false,
      child: Stack(
        children: [
          const Positioned.fill(
            child: IgnorePointer(
              child: CustomPaint(painter: _HomePageBackdropPainter()),
            ),
          ),
          Column(
            children: [
              _HomeHeader(
                key: const Key('home.header'),
                topInset: MediaQuery.paddingOf(context).top,
                unreadNotifications: unreadNotifications,
                onOpenNotifications: onOpenNotifications,
                onOpenProfile: onOpenProfile,
              ),
              Expanded(
                child: CustomScrollView(
                  physics: const BouncingScrollPhysics(),
                  slivers: [
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      sliver: SliverToBoxAdapter(
                        child: _MonthlyAttendanceCard(
                          leaveBalance: leaveBalance,
                          attendanceCount: attendanceCount,
                          approvedLeaveDays: approvedLeaveDays.length,
                          onOpenAttendance: onOpenAttendance,
                        ),
                      ),
                    ),
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(16, 17, 16, 18),
                      sliver: SliverList(
                        delegate: SliverChildListDelegate([
                          const Padding(
                            padding: EdgeInsets.only(left: 2, bottom: 9),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Layanan karyawan',
                                        style: TextStyle(
                                          color: AppColors.ink,
                                          fontSize: 18,
                                          fontWeight: FontWeight.w700,
                                          letterSpacing: -0.3,
                                        ),
                                      ),
                                      SizedBox(height: 3),
                                      Text(
                                        'Akses kebutuhan kerja dengan mudah',
                                        style: TextStyle(
                                          color: AppColors.muted,
                                          fontSize: 11,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Icon(
                                  Icons.grid_view_rounded,
                                  color: AppColors.blue,
                                  size: 18,
                                ),
                              ],
                            ),
                          ),
                          GridView(
                            gridDelegate:
                                const SliverGridDelegateWithFixedCrossAxisCount(
                                  crossAxisCount: 3,
                                  mainAxisSpacing: 10,
                                  crossAxisSpacing: 10,
                                  mainAxisExtent: 72,
                                ),
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            children: menuItems
                                .map(
                                  (item) => _HomeMenuTile(
                                    key: Key('home.menu.${item.$1}'),
                                    icon: item.$3,
                                    label: item.$2,
                                    color: item.$4,
                                    onTap: () => item.$1 == 'approvals'
                                        ? onOpenApprovals()
                                        : onOpenFeature(item.$1),
                                  ),
                                )
                                .toList(),
                          ),
                          const SizedBox(height: 14),
                          _SectionHeading(
                            title: 'Aktivitas terbaru',
                            trailing: TextButton(
                              onPressed: () => onOpenTab(2),
                              child: const Text('Lihat semua'),
                            ),
                          ),
                          if (history.isEmpty)
                            const _EmptyState(
                              icon: Icons.history_rounded,
                              title: 'Belum ada aktivitas',
                              message:
                                  'Riwayat absensi akan muncul setelah kamu absen.',
                            )
                          else
                            _SurfaceCard(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 13,
                              ),
                              child: Column(
                                children: [
                                  for (
                                    var i = 0;
                                    i < history.length && i < 3;
                                    i++
                                  ) ...[
                                    _HistoryRow(
                                      item: history[i],
                                      onOpenSelfie: onOpenSelfie,
                                    ),
                                    if (i != history.length - 1 && i != 2)
                                      const Divider(
                                        height: 1,
                                        color: AppColors.line,
                                      ),
                                  ],
                                ],
                              ),
                            ),
                          const SizedBox(height: 12),
                        ]),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HomeHeader extends StatelessWidget {
  const _HomeHeader({
    super.key,
    required this.topInset,
    required this.unreadNotifications,
    required this.onOpenNotifications,
    required this.onOpenProfile,
  });

  final double topInset;
  final int unreadNotifications;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenProfile;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.fromLTRB(20, topInset + 6, 16, 12),
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF062C68), Color(0xFF0759B5), Color(0xFF078BD1)],
        stops: [0, 0.62, 1],
      ),
      borderRadius: BorderRadius.vertical(bottom: Radius.circular(30)),
    ),
    child: Stack(
      clipBehavior: Clip.hardEdge,
      children: [
        const Positioned.fill(
          child: IgnorePointer(
            child: CustomPaint(
              key: Key('home.header.backdrop'),
              painter: _HomeBackdropPainter(),
            ),
          ),
        ),
        Row(
          children: [
            const Expanded(child: _BrandMark(tagline: true)),
            Stack(
              clipBehavior: Clip.none,
              children: [
                IconButton(
                  visualDensity: VisualDensity.compact,
                  key: const Key('home.notifications'),
                  tooltip: 'Notifikasi',
                  onPressed: onOpenNotifications,
                  icon: const Icon(
                    Icons.notifications_none_rounded,
                    color: Colors.white,
                    size: 26,
                  ),
                ),
                if (unreadNotifications > 0)
                  Positioned(
                    right: 6,
                    top: 6,
                    child: Container(
                      key: const Key('home.notifications.unread'),
                      width: 9,
                      height: 9,
                      decoration: const BoxDecoration(
                        color: Color(0xFFFF334F),
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 4),
            InkWell(
              key: const Key('home.profile'),
              onTap: onOpenProfile,
              customBorder: const CircleBorder(),
              child: const CircleAvatar(
                radius: 19,
                backgroundColor: Color(0xFFF2F6FC),
                child: Icon(
                  Icons.person_rounded,
                  color: Color(0xFF91A0B7),
                  size: 28,
                ),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _HomeBackdropPainter extends CustomPainter {
  const _HomeBackdropPainter();

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.clipRect(Offset.zero & size);
    final w = size.width;
    final h = size.height;
    final darkShape = Paint()
      ..color = const Color(0xFF001C55).withValues(alpha: 0.53);
    final darkPath = Path()
      ..moveTo(0, 0)
      ..lineTo(w * 0.72, 0)
      ..cubicTo(w * 0.69, h * 0.48, w * 0.48, h * 0.67, w * 0.38, h)
      ..lineTo(w * 0.10, h)
      ..cubicTo(w * 0.28, h * 0.60, w * 0.02, h * 0.42, 0, h * 0.50)
      ..close();
    canvas.drawPath(darkPath, darkShape);

    final blueShape = Paint()
      ..color = const Color(0xFF12A9F1).withValues(alpha: 0.42);
    final bluePath = Path()
      ..moveTo(w * 0.88, 0)
      ..lineTo(w, 0)
      ..lineTo(w, h)
      ..lineTo(w * 0.76, h)
      ..cubicTo(w * 0.88, h * 0.62, w * 0.70, h * 0.48, w * 0.88, 0)
      ..close();
    canvas.drawPath(bluePath, blueShape);

    final orbPaint = Paint()
      ..color = const Color(0xFF116BB7).withValues(alpha: 0.42);
    canvas.drawCircle(Offset(w * 0.66, h * 0.90), h * 0.38, orbPaint);
    canvas.drawCircle(
      Offset(w * 0.08, h * 0.80),
      h * 0.30,
      Paint()..color = const Color(0xFF1E92D5).withValues(alpha: 0.22),
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _HomeBackdropPainter oldDelegate) => false;
}

class _HomePageBackdropPainter extends CustomPainter {
  const _HomePageBackdropPainter();

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.clipRect(Offset.zero & size);
    final glow = Paint()
      ..color = const Color(0xFFB7DCFF).withValues(alpha: 0.20);
    final rings = Paint()
      ..color = const Color(0xFFB7DCFF).withValues(alpha: 0.34)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    final center = Offset(size.width * 0.92, size.height * 0.72);
    canvas.drawCircle(center, 46, glow);
    canvas.drawCircle(center, 62, rings);
    canvas.drawCircle(
      center,
      78,
      rings..color = rings.color.withValues(alpha: 0.65),
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _HomePageBackdropPainter oldDelegate) => false;
}

class _MonthlyAttendanceCard extends StatelessWidget {
  const _MonthlyAttendanceCard({
    required this.leaveBalance,
    required this.attendanceCount,
    required this.approvedLeaveDays,
    required this.onOpenAttendance,
  });

  final LeaveBalanceRecord? leaveBalance;
  final int attendanceCount;
  final int approvedLeaveDays;
  final VoidCallback onOpenAttendance;

  @override
  Widget build(BuildContext context) {
    return Container(
      key: const Key('home.monthlySummary'),
      padding: const EdgeInsets.fromLTRB(15, 14, 15, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: const Color(0xFFDCE9F8)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x142C6EB3),
            blurRadius: 20,
            offset: Offset(0, 7),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF4FF),
                  borderRadius: BorderRadius.circular(13),
                ),
                alignment: Alignment.center,
                child: const Icon(
                  Icons.calendar_month_rounded,
                  color: Color(0xFF0875D1),
                  size: 21,
                ),
              ),
              const SizedBox(width: 9),
              const Expanded(
                child: Text(
                  'Rekap Absensi Bulan Ini',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Color(0xFF102E5B),
                    fontSize: 14,
                    height: 1.15,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.25,
                  ),
                ),
              ),
              const SizedBox(width: 6),
              TextButton(
                key: const Key('home.attendance'),
                onPressed: onOpenAttendance,
                style: TextButton.styleFrom(
                  foregroundColor: const Color(0xFF0759B5),
                  backgroundColor: const Color(0xFFEAF4FF),
                  minimumSize: const Size(0, 34),
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  shape: const StadiumBorder(),
                  textStyle: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('Lihat detail'),
                    SizedBox(width: 3),
                    Icon(Icons.chevron_right_rounded, size: 16),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const _AttendanceCalendarIllustration(),
              const SizedBox(width: 10),
              Expanded(
                child: Row(
                  children: [
                    _HomeMetric(
                      title: 'HADIR',
                      value: '$attendanceCount hari',
                      subtitle: '',
                      color: AppColors.green,
                    ),
                    Container(
                      width: 1,
                      height: 42,
                      color: const Color(0xFFDCE7F4),
                    ),
                    _HomeMetric(
                      title: 'IZIN',
                      value: '$approvedLeaveDays hari',
                      subtitle: '',
                      color: AppColors.blue,
                    ),
                    Container(
                      width: 1,
                      height: 42,
                      color: const Color(0xFFDCE7F4),
                    ),
                    _HomeMetric(
                      title: 'SISA CUTI',
                      value: leaveBalance?.configured == true
                          ? '${leaveBalance!.remainingDays} hari'
                          : '—',
                      subtitle: '',
                      color: AppColors.orange,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AttendanceCalendarIllustration extends StatelessWidget {
  const _AttendanceCalendarIllustration();

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const Key('home.calendarIllustration'),
    width: 52,
    height: 56,
    child: Stack(
      clipBehavior: Clip.none,
      children: [
        Positioned(
          left: 3,
          top: 4,
          child: Transform.rotate(
            angle: -0.12,
            child: Container(
              width: 39,
              height: 42,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFF8FD4FF), Color(0xFF38A8F5)],
                ),
                borderRadius: BorderRadius.circular(10),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x332D8ED4),
                    blurRadius: 8,
                    offset: Offset(0, 4),
                  ),
                ],
              ),
              child: const Icon(
                Icons.calendar_month_rounded,
                color: Colors.white,
                size: 29,
              ),
            ),
          ),
        ),
        Positioned(
          right: 0,
          bottom: 1,
          child: Container(
            width: 21,
            height: 21,
            decoration: BoxDecoration(
              color: const Color(0xFF079CE8),
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 2),
            ),
            child: const Icon(
              Icons.check_rounded,
              color: Colors.white,
              size: 14,
            ),
          ),
        ),
      ],
    ),
  );
}

class _AttendanceTimeCell extends StatelessWidget {
  const _AttendanceTimeCell({
    required this.label,
    required this.value,
    required this.icon,
    required this.active,
  });

  final String label;
  final String value;
  final IconData icon;
  final bool active;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: 56),
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
    decoration: BoxDecoration(
      color: const Color(0xFFFAFBFD),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: AppColors.line),
    ),
    child: Row(
      children: [
        Icon(icon, size: 16, color: active ? AppColors.green : AppColors.muted),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.muted, fontSize: 10),
              ),
              const SizedBox(height: 3),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: active ? AppColors.green : AppColors.ink,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _AttendanceMapPage extends StatefulWidget {
  const _AttendanceMapPage({
    required this.settings,
    required this.attendance,
    required this.now,
    required this.checkOut,
    required this.getCurrentLocation,
    required this.onSubmit,
  });

  final AttendanceSettings settings;
  final AttendanceRecord? attendance;
  final DateTime now;
  final bool checkOut;
  final Future<AttendanceCoordinates?> Function()? getCurrentLocation;
  final Future<void> Function(AttendanceCoordinates?) onSubmit;

  @override
  State<_AttendanceMapPage> createState() => _AttendanceMapPageState();
}

class _AttendanceMapPageState extends State<_AttendanceMapPage> {
  final MapController _mapController = MapController();
  AttendanceCoordinates? _position;
  String? _locationError;
  bool _loadingLocation = true;
  bool _submitting = false;
  bool _tileError = false;
  bool _mapReady = false;

  @override
  void initState() {
    super.initState();
    _refreshLocation();
  }

  Future<void> _refreshLocation() async {
    if (widget.getCurrentLocation == null) {
      setState(() {
        _position = null;
        _locationError = null;
        _loadingLocation = false;
      });
      return;
    }

    setState(() {
      _loadingLocation = true;
      _locationError = null;
    });
    try {
      final position = await widget.getCurrentLocation!();
      if (position == null) {
        throw const ApiException('Lokasi GPS belum berhasil didapat.');
      }
      if (!mounted) return;
      setState(() {
        _position = position;
        _loadingLocation = false;
      });
      if (_mapReady) {
        _mapController.move(
          latlong.LatLng(position.latitude, position.longitude),
          16,
        );
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _locationError = error is ApiException
            ? error.message
            : 'Lokasi tidak berhasil didapat. Periksa GPS dan izin lokasi.';
        _loadingLocation = false;
      });
    }
  }

  Future<void> _submit() async {
    if (_submitting) return;
    if (widget.settings.requiresLocation && _position == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Lokasi GPS harus didapat sebelum absensi.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }
    if (_position?.isMocked == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Lokasi terdeteksi sebagai GPS tiruan. Matikan aplikasi fake GPS dan coba lagi.',
          ),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      var position = _position;
      if (widget.getCurrentLocation != null) {
        position = await widget.getCurrentLocation!();
        if (position == null) {
          throw const ApiException('Lokasi GPS belum berhasil didapat.');
        }
        if (mounted) {
          setState(() => _position = position);
          if (_mapReady) {
            _mapController.move(
              latlong.LatLng(position.latitude, position.longitude),
              16,
            );
          }
        }
      }
      if (position?.isMocked == true) {
        throw const ApiException(
          'Lokasi terdeteksi sebagai GPS tiruan. Matikan aplikasi fake GPS dan coba lagi.',
        );
      }
      await widget.onSubmit(position);
    } on PlatformException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.message ??
                'Kamera tidak dapat digunakan. Periksa izin kamera lalu coba lagi.',
          ),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _locationError = error is ApiException
            ? error.message
            : 'Absensi tidak dapat diproses: $error';
      });
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final position = _position;
    final location = position == null
        ? null
        : latlong.LatLng(position.latitude, position.longitude);
    final office =
        widget.settings.officeLatitude == null ||
            widget.settings.officeLongitude == null
        ? null
        : latlong.LatLng(
            widget.settings.officeLatitude!,
            widget.settings.officeLongitude!,
          );
    final accuracyMeters = position?.accuracyMeters ?? 0;
    final insets = MediaQuery.paddingOf(context);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        children: [
          Positioned.fill(
            child: location == null
                ? const ColoredBox(
                    color: Color(0xFFE6EDF1),
                    child: Center(
                      child: Icon(
                        Icons.map_outlined,
                        color: AppColors.muted,
                        size: 52,
                      ),
                    ),
                  )
                : FlutterMap(
                    mapController: _mapController,
                    options: MapOptions(
                      initialCenter: location,
                      initialZoom: 16,
                      onMapReady: () => _mapReady = true,
                      interactionOptions: const InteractionOptions(
                        flags: InteractiveFlag.all & ~InteractiveFlag.rotate,
                      ),
                    ),
                    children: [
                      TileLayer(
                        urlTemplate:
                            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        userAgentPackageName: 'id.co.attendly.mobile',
                        errorTileCallback: (_, _, _) {
                          if (mounted && !_tileError) {
                            setState(() => _tileError = true);
                          }
                        },
                      ),
                      if (office != null &&
                          widget.settings.radiusMeters != null)
                        CircleLayer(
                          circles: [
                            CircleMarker(
                              point: office,
                              radius: widget.settings.radiusMeters!.toDouble(),
                              useRadiusInMeter: true,
                              color: AppColors.blue.withValues(alpha: 0.1),
                              borderColor: AppColors.blue.withValues(
                                alpha: 0.55,
                              ),
                              borderStrokeWidth: 2,
                            ),
                          ],
                        ),
                      if (accuracyMeters > 0)
                        CircleLayer(
                          circles: [
                            CircleMarker(
                              point: location,
                              radius: accuracyMeters,
                              useRadiusInMeter: true,
                              color: AppColors.blue.withValues(alpha: 0.08),
                              borderColor: AppColors.blue.withValues(
                                alpha: 0.4,
                              ),
                              borderStrokeWidth: 1,
                            ),
                          ],
                        ),
                      MarkerLayer(
                        markers: [
                          if (office != null)
                            Marker(
                              point: office,
                              width: 42,
                              height: 48,
                              child: const Icon(
                                Icons.location_on_rounded,
                                color: AppColors.orange,
                                size: 42,
                              ),
                            ),
                          Marker(
                            point: location,
                            width: 46,
                            height: 54,
                            child: const Icon(
                              Icons.person_pin_circle_rounded,
                              color: AppColors.blue,
                              size: 46,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
          ),
          Positioned(
            top: insets.top + 10,
            left: 16,
            right: 16,
            child: Row(
              children: [
                _MapCircleButton(
                  icon: Icons.arrow_back_rounded,
                  label: 'Kembali',
                  onPressed: () => Navigator.of(context).maybePop(),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(15),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x220C2940),
                          blurRadius: 16,
                          offset: Offset(0, 5),
                        ),
                      ],
                    ),
                    child: Text(
                      widget.checkOut ? 'Absen pulang' : 'Absen masuk',
                      style: const TextStyle(
                        color: AppColors.ink,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 9),
                _MapCircleButton(
                  icon: Icons.my_location_rounded,
                  label: 'Perbarui lokasi',
                  onPressed: _loadingLocation ? null : _refreshLocation,
                ),
              ],
            ),
          ),
          if (_tileError)
            Positioned(
              top: insets.top + 78,
              left: 20,
              right: 20,
              child: const _MapNotice(
                icon: Icons.wifi_off_rounded,
                text: 'Peta tidak dapat dimuat. Periksa koneksi internet.',
              ),
            ),
          if (location != null)
            Positioned(
              right: 14,
              bottom: MediaQuery.sizeOf(context).height * 0.44 + 8,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.9),
                  borderRadius: BorderRadius.circular(5),
                ),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                  child: Text(
                    '© OpenStreetMap contributors',
                    style: TextStyle(
                      color: AppColors.ink,
                      fontSize: 10,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ),
            ),
          Positioned(
            right: 18,
            bottom: insets.bottom + 225,
            child: _MapCircleButton(
              icon: Icons.my_location_rounded,
              label: 'Ke lokasi saya',
              onPressed: location == null
                  ? null
                  : () => _mapController.move(location, 17),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            height: MediaQuery.sizeOf(context).height * 0.44,
            child: DecoratedBox(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
                boxShadow: [
                  BoxShadow(
                    color: Color(0x220C2940),
                    blurRadius: 20,
                    offset: Offset(0, -5),
                  ),
                ],
              ),
              child: ListView(
                padding: EdgeInsets.fromLTRB(19, 17, 19, insets.bottom + 14),
                children: [
                  Center(
                    child: Container(
                      width: 38,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFD8DEE8),
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  ),
                  const SizedBox(height: 15),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: const Color(0xFFEAF5FC),
                          borderRadius: BorderRadius.circular(13),
                        ),
                        child: const Icon(
                          Icons.location_on_rounded,
                          color: AppColors.blue,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.settings.officeName ??
                                  (widget.settings.onlineTraining
                                      ? 'Diklat online'
                                      : 'Lokasi kerja'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: AppColors.ink,
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _loadingLocation
                                  ? 'Mencari lokasi GPS...'
                                  : _locationError ??
                                        (widget.settings.requiresLocation
                                            ? 'Akurasi GPS ${position?.accuracyMeters?.round() ?? '-'} meter'
                                            : 'Absensi ini tidak memerlukan GPS'),
                              style: TextStyle(
                                color: _locationError == null
                                    ? AppColors.muted
                                    : const Color(0xFFB42338),
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (_loadingLocation)
                        const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      else if (_locationError != null)
                        IconButton(
                          key: const Key('attendance.locationRetry'),
                          tooltip: 'Coba dapatkan lokasi lagi',
                          onPressed: _refreshLocation,
                          icon: const Icon(Icons.refresh_rounded),
                        )
                      else
                        const Icon(
                          Icons.check_circle_rounded,
                          color: AppColors.green,
                        ),
                    ],
                  ),
                  if (widget.settings.requiresLocation &&
                      widget.settings.radiusMeters != null) ...[
                    const SizedBox(height: 11),
                    _MapNotice(
                      icon: Icons.shield_outlined,
                      text:
                          'Radius absensi ${widget.settings.radiusMeters} meter',
                    ),
                  ],
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: AppColors.background,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.schedule_rounded,
                          color: AppColors.navy,
                          size: 20,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                widget.settings.shift?.name ??
                                    'Belum ada shift yang ditetapkan',
                                style: const TextStyle(
                                  color: AppColors.ink,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                widget.settings.shift == null
                                    ? _formatDate(widget.now)
                                    : '${widget.settings.shift!.startTime}–${widget.settings.shift!.endTime} · ${_formatDate(widget.now)}',
                                style: const TextStyle(
                                  color: AppColors.muted,
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (widget.checkOut &&
                            widget.attendance?.checkInAt != null)
                          Text(
                            'Masuk ${_formatTime(widget.attendance!.checkInAt)}',
                            style: const TextStyle(
                              color: AppColors.green,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 11),
                  const Text(
                    'Selfie wajib diambil langsung dari kamera depan untuk absen masuk maupun pulang.',
                    style: TextStyle(color: AppColors.muted, fontSize: 11),
                  ),
                  const SizedBox(height: 13),
                  SizedBox(
                    height: 50,
                    child: FilledButton.icon(
                      key: Key(
                        widget.checkOut
                            ? 'attendance.mapCheckOut'
                            : 'attendance.mapCheckIn',
                      ),
                      onPressed: _loadingLocation || _submitting
                          ? null
                          : _submit,
                      icon: _submitting
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Icon(
                              widget.checkOut
                                  ? Icons.logout_rounded
                                  : Icons.camera_alt_rounded,
                            ),
                      label: Text(
                        _submitting
                            ? 'Memproses...'
                            : widget.checkOut
                            ? 'Lanjut ke selfie pulang'
                            : 'Lanjut ke selfie masuk',
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MapNotice extends StatelessWidget {
  const _MapNotice({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
    decoration: BoxDecoration(
      color: const Color(0xFFF2F7FA),
      borderRadius: BorderRadius.circular(11),
    ),
    child: Row(
      children: [
        Icon(icon, size: 15, color: AppColors.navy),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: AppColors.navy,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    ),
  );
}

class _MapCircleButton extends StatelessWidget {
  const _MapCircleButton({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: const CircleBorder(),
    elevation: 3,
    child: IconButton(
      tooltip: label,
      onPressed: onPressed,
      icon: Icon(icon, color: AppColors.navy),
    ),
  );
}

class _AttendanceTab extends StatelessWidget {
  const _AttendanceTab({
    required this.now,
    required this.attendance,
    required this.shift,
    required this.settings,
    required this.onCheckIn,
    required this.onCheckOut,
  });

  final DateTime now;
  final AttendanceRecord? attendance;
  final WorkShiftSchedule? shift;
  final AttendanceSettings? settings;
  final VoidCallback? onCheckIn;
  final VoidCallback? onCheckOut;

  @override
  Widget build(BuildContext context) {
    return _PageScaffold(
      title: 'Absensi',
      subtitle: 'Catat kehadiran dengan aman',
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
        children: [
          _AttendanceCard(
            now: now,
            checkInAt: attendance?.checkInAt,
            checkOutAt: attendance?.checkOutAt,
            attendanceMode: attendance?.attendanceMode ?? 'onsite',
            verificationStatus: attendance?.verificationStatus ?? 'recorded',
            selfieReviewStatus:
                attendance?.selfieReviewStatus ?? 'not_required',
            shift: shift,
            lateMinutes: attendance?.lateMinutes,
            attendanceBlockReason: settings?.attendanceBlockReason,
            onCheckIn: onCheckIn,
            onCheckOut: onCheckOut,
          ),
          const SizedBox(height: 18),
          const _SectionHeading(title: 'Lokasi absensi'),
          _LocationCard(settings: settings),
        ],
      ),
    );
  }
}

class _FeaturePage extends StatefulWidget {
  const _FeaturePage({
    required this.api,
    required this.feature,
    this.leaveBalance,
    required this.onCreateRequest,
    required this.onCreateActivity,
  });

  final AttendlyApi api;
  final String feature;
  final LeaveBalanceRecord? leaveBalance;
  final Future<void> Function(String type, List<String> allowedTypes)
  onCreateRequest;
  final Future<void> Function() onCreateActivity;

  @override
  State<_FeaturePage> createState() => _FeaturePageState();
}

class _FeaturePageState extends State<_FeaturePage> {
  late Future<dynamic> _data;
  String _newsFilter = 'semua';

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _data = switch (widget.feature) {
      'activity' => widget.api.activityReports(),
      'payroll' => widget.api.payrollRecords(),
      'spt' => widget.api.taxDocuments(),
      'news' => widget.api.announcements(),
      _ => widget.api.leaveRequests(),
    };
  }

  String get _title => switch (widget.feature) {
    'izin' => 'Izin dan cuti',
    'lembur' => 'Pengajuan lembur',
    'perjalanan' => 'Perjalanan dinas',
    'diklat' => 'Pengajuan diklat',
    'activity' => 'Laporan aktivitas',
    'payroll' => 'Informasi gaji',
    'spt' => 'Dokumen SPT',
    'news' => 'Berita dan pengumuman',
    _ => 'Data karyawan',
  };

  String get _subtitle => switch (widget.feature) {
    'izin' => 'Izin, sakit, dan hak cuti',
    'lembur' => 'Catat lembur dan waktu kerja tambahan',
    'perjalanan' => 'Riwayat tugas kerja luar kantor',
    'diklat' => 'Riwayat pengembangan kompetensi',
    'activity' => 'Catatan hasil pekerjaan harian',
    'payroll' => 'Rincian payroll pribadi',
    'spt' => 'Dokumen pajak pribadi',
    'news' => 'Informasi resmi perusahaan',
    _ => '',
  };

  List<String> get _requestTypes => switch (widget.feature) {
    'izin' => ['izin', 'sakit', 'cuti'],
    'lembur' => ['lembur'],
    'perjalanan' => ['perjalanan_dinas'],
    'diklat' => ['diklat'],
    _ => [],
  };

  @override
  Widget build(BuildContext context) {
    final requestFeature = _requestTypes.isNotEmpty;
    final allowCreate = requestFeature || widget.feature == 'activity';

    return Scaffold(
      backgroundColor: AppColors.background,
      body: _PageScaffold(
        title: _title,
        subtitle: _subtitle,
        showBackButton: true,
        trailing: allowCreate
            ? IconButton(
                key: const Key('feature.create'),
                tooltip: requestFeature ? 'Buat pengajuan' : 'Tambah aktivitas',
                onPressed: () async {
                  if (requestFeature) {
                    await widget.onCreateRequest(
                      _requestTypes.first,
                      _requestTypes,
                    );
                  } else {
                    await widget.onCreateActivity();
                  }
                  if (mounted) setState(_reload);
                },
                icon: const Icon(Icons.add_circle_outline_rounded),
              )
            : null,
        child: FutureBuilder<dynamic>(
          future: _data,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return _EmptyState(
                icon: Icons.cloud_off_outlined,
                title: 'Data belum dapat dimuat',
                message: '${snapshot.error}',
                action: OutlinedButton(
                  onPressed: () => setState(_reload),
                  child: const Text('Coba lagi'),
                ),
              );
            }

            final data = snapshot.data;
            if (requestFeature) {
              final requests = (data as List<LeaveRequestRecord>)
                  .where((record) => _requestTypes.contains(record.type))
                  .toList();
              return Column(
                children: [
                  if (widget.feature == 'izin') ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                      child: _LeaveBalanceCard(balance: widget.leaveBalance),
                    ),
                  ],
                  Expanded(
                    child: requests.isEmpty
                        ? _EmptyState(
                            icon: Icons.event_note_outlined,
                            title: 'Belum ada ${_title.toLowerCase()}',
                            message:
                                'Pengajuan yang kamu kirim akan tampil di sini.',
                            action: FilledButton(
                              onPressed: () async {
                                await widget.onCreateRequest(
                                  _requestTypes.first,
                                  _requestTypes,
                                );
                                if (mounted) setState(_reload);
                              },
                              child: const Text('Buat pengajuan'),
                            ),
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                            itemCount: requests.length,
                            separatorBuilder: (_, _) =>
                                const SizedBox(height: 10),
                            itemBuilder: (context, index) => _RequestRecordCard(
                              api: widget.api,
                              record: requests[index],
                            ),
                          ),
                  ),
                ],
              );
            }

            if (widget.feature == 'activity') {
              final reports = data as List<ActivityReportRecord>;
              if (reports.isEmpty) {
                return _EmptyState(
                  icon: Icons.edit_note_rounded,
                  title: 'Belum ada aktivitas',
                  message: 'Kirim laporan aktivitas dan hasil kerja harianmu.',
                  action: FilledButton(
                    onPressed: () async {
                      await widget.onCreateActivity();
                      if (mounted) setState(_reload);
                    },
                    child: const Text('Lapor aktivitas'),
                  ),
                );
              }
              return ListView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                children: reports.map((report) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _SurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  report.title,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                              _StatusPill(
                                label: _leaveStatusLabel(report.status),
                                color: _leaveStatusColor(report.status),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            '${_formatDate(report.activityDate)}'
                            '${report.hours == null ? '' : ' · ${report.hours} jam'}',
                            style: const TextStyle(
                              color: AppColors.muted,
                              fontSize: 12,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Text(report.description),
                          if (report.reviewNote != null &&
                              report.reviewNote!.isNotEmpty) ...[
                            const Divider(height: 20),
                            Text(
                              'Catatan peninjau: ${report.reviewNote}',
                              style: const TextStyle(
                                color: AppColors.muted,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                }).toList(),
              );
            }

            if (widget.feature == 'payroll') {
              final records = data as List<PayrollRecord>;
              if (records.isEmpty) {
                return const _EmptyState(
                  icon: Icons.account_balance_wallet_outlined,
                  title: 'Data gaji belum tersedia',
                  message: 'Admin akan menerbitkan slip gaji melalui sistem.',
                );
              }
              return ListView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                children: records.map((record) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _SurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${_monthNames[record.periodStart.month - 1]} ${record.periodStart.year}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 15,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _MoneyRow(
                            label: 'Penghasilan bruto',
                            amount: record.grossAmount,
                          ),
                          const SizedBox(height: 7),
                          _MoneyRow(
                            label: 'Total potongan',
                            amount: record.deductionsAmount,
                          ),
                          const Divider(height: 22),
                          _MoneyRow(
                            label: 'Diterima (netto)',
                            amount: record.netAmount,
                            emphasized: true,
                          ),
                          if (record.payslipAvailable) ...[
                            const SizedBox(height: 12),
                            OutlinedButton.icon(
                              onPressed: () => _saveDocument(
                                context,
                                widget.api.downloadPayslip(record.id),
                                'slip-gaji-${record.periodStart.year}-${record.periodStart.month}.pdf',
                              ),
                              icon: const Icon(Icons.download_outlined),
                              label: const Text('Unduh slip gaji'),
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                }).toList(),
              );
            }

            if (widget.feature == 'spt') {
              final documents = data as List<TaxDocumentRecord>;
              if (documents.isEmpty) {
                return const _EmptyState(
                  icon: Icons.description_outlined,
                  title: 'Dokumen SPT belum tersedia',
                  message:
                      'Hubungi HR atau admin jika dokumen belum diterbitkan.',
                );
              }
              return ListView(
                padding: const EdgeInsets.fromLTRB(18, 0, 18, 28),
                children: documents.map((document) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _SurfaceCard(
                      child: ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(
                          Icons.description_outlined,
                          color: AppColors.blue,
                        ),
                        title: Text(document.title),
                        subtitle: Text(
                          '${document.taxYear}'
                          '${document.description == null ? '' : ' · ${document.description}'}',
                        ),
                        trailing: IconButton(
                          tooltip: 'Unduh dokumen',
                          onPressed: () => _saveDocument(
                            context,
                            widget.api.downloadTaxDocument(document.id),
                            'spt-${document.taxYear}-${document.id}.pdf',
                          ),
                          icon: const Icon(Icons.download_outlined),
                        ),
                      ),
                    ),
                  );
                }).toList(),
              );
            }

            final allNews = data as List<AnnouncementRecord>;
            final news = allNews
                .where(
                  (item) =>
                      _newsFilter == 'semua' || item.category == _newsFilter,
                )
                .toList();
            if (allNews.isEmpty) {
              return const _EmptyState(
                icon: Icons.campaign_outlined,
                title: 'Belum ada informasi',
                message:
                    'Berita dan pengumuman perusahaan akan tampil di sini.',
              );
            }
            return Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                  child: Wrap(
                    spacing: 8,
                    children: [
                      for (final category in ['semua', 'berita', 'pengumuman'])
                        ChoiceChip(
                          label: Text(
                            category == 'semua'
                                ? 'Semua'
                                : _capitalized(category),
                          ),
                          selected: _newsFilter == category,
                          onSelected: (_) =>
                              setState(() => _newsFilter = category),
                        ),
                    ],
                  ),
                ),
                Expanded(
                  child: news.isEmpty
                      ? const _EmptyState(
                          icon: Icons.campaign_outlined,
                          title: 'Tidak ada informasi pada kategori ini',
                          message: 'Coba pilih kategori lainnya.',
                        )
                      : ListView(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                          children: news.map((item) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: _SurfaceCard(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        const Icon(
                                          Icons.campaign_outlined,
                                          color: AppColors.blue,
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            item.title,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      '${_capitalized(item.category)} · ${_formatDate(item.publishedAt.toLocal())}',
                                      style: const TextStyle(
                                        color: AppColors.muted,
                                        fontSize: 11,
                                      ),
                                    ),
                                    const Divider(height: 20),
                                    Text(item.body),
                                  ],
                                ),
                              ),
                            );
                          }).toList(),
                        ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Future<void> _saveDocument(
    BuildContext context,
    Future<Uint8List> bytesFuture,
    String fileName,
  ) async {
    try {
      final bytes = await bytesFuture;
      final savedPath = await FilePicker.saveFile(
        dialogTitle: 'Simpan dokumen',
        fileName: fileName,
        bytes: bytes,
        mimeType: 'application/pdf',
      );
      if (savedPath == null || !context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Dokumen berhasil disimpan.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Unduhan gagal: $error'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }
}

class _LeaveBalanceCard extends StatelessWidget {
  const _LeaveBalanceCard({required this.balance});

  final LeaveBalanceRecord? balance;

  @override
  Widget build(BuildContext context) {
    final configured = balance?.configured == true;
    return _SurfaceCard(
      color: const Color(0xFFFFFBF2),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: const Color(0xFFFFF0D8),
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.beach_access_outlined,
              color: Color(0xFFB9741C),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  configured
                      ? 'Saldo cuti ${balance!.year}'
                      : 'Saldo cuti belum diatur',
                  style: const TextStyle(
                    color: AppColors.ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  configured
                      ? 'Terpakai ${balance!.usedDays} dari ${balance!.entitlementDays} hari'
                      : 'HRD akan menetapkan hak cuti tahunan kamu.',
                  style: const TextStyle(color: AppColors.muted, fontSize: 11),
                ),
                if (!configured) ...[
                  const SizedBox(height: 3),
                  const Text(
                    'Cuti tahunan menunggu saldo HRD sebelum persetujuan final.',
                    style: TextStyle(color: AppColors.muted, fontSize: 10),
                  ),
                ],
              ],
            ),
          ),
          if (configured)
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '${balance!.remainingDays}',
                  style: const TextStyle(
                    color: Color(0xFFB9741C),
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const Text(
                  'hari tersisa',
                  style: TextStyle(color: AppColors.muted, fontSize: 9),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

class _RequestRecordCard extends StatelessWidget {
  const _RequestRecordCard({required this.api, required this.record});

  final AttendlyApi api;
  final LeaveRequestRecord record;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              record.subtype == null
                  ? _requestTypeLabel(record.type)
                  : _requestSubtypeLabel(record.subtype!),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 7),
            Align(
              alignment: Alignment.centerRight,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 170),
                child: _StatusPill(
                  label: _leaveStatusLabel(record.status),
                  color: _leaveStatusColor(record.status),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 7),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(
              Icons.info_outline_rounded,
              size: 15,
              color: AppColors.muted,
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                _requestProgress(record),
                style: const TextStyle(
                  color: AppColors.muted,
                  fontSize: 11,
                  height: 1.3,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 7),
        Text(
          '${_formatDate(record.startDate)}'
          '${record.startDate == record.endDate ? '' : ' – ${_formatDate(record.endDate)}'}',
          style: const TextStyle(color: AppColors.muted, fontSize: 12),
        ),
        const SizedBox(height: 10),
        Text(record.reason),
        if (record.details['destination'] case final String destination)
          _DetailLine(label: 'Tujuan', value: destination),
        if (record.details['institution'] case final String institution)
          _DetailLine(label: 'Penyelenggara', value: institution),
        if (record.details['start_time'] case final String startTime)
          _DetailLine(
            label: 'Waktu lembur',
            value: '$startTime–${record.details['end_time'] ?? ''}',
          ),
        if (record.reviewNote != null && record.reviewNote!.isNotEmpty)
          _DetailLine(label: 'Catatan peninjau', value: record.reviewNote!),
        if (record.attachmentAvailable) ...[
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: () => _saveRequestAttachment(context),
            icon: const Icon(Icons.download_outlined),
            label: const Text('Unduh lampiran'),
          ),
        ],
      ],
    ),
  );

  Future<void> _saveRequestAttachment(BuildContext context) async {
    try {
      final bytes = await api.downloadRequestAttachment(record.id);
      final path = await FilePicker.saveFile(
        dialogTitle: 'Simpan lampiran pengajuan',
        fileName: 'lampiran-pengajuan-${record.id}',
        bytes: bytes,
        mimeType: 'application/octet-stream',
      );
      if (path == null || !context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lampiran berhasil disimpan.')),
      );
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Unduhan lampiran gagal: $error')));
    }
  }
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 7),
    child: Text(
      '$label: $value',
      style: const TextStyle(color: AppColors.muted, fontSize: 12),
    ),
  );
}

class _MoneyRow extends StatelessWidget {
  const _MoneyRow({
    required this.label,
    required this.amount,
    this.emphasized = false,
  });

  final String label;
  final double amount;
  final bool emphasized;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: emphasized ? AppColors.ink : AppColors.muted,
            fontSize: emphasized ? 13 : 12,
            fontWeight: emphasized ? FontWeight.w700 : FontWeight.normal,
          ),
        ),
      ),
      Text(
        'Rp ${_formatRupiah(amount)}',
        style: TextStyle(
          color: emphasized ? AppColors.green : AppColors.ink,
          fontWeight: FontWeight.w700,
          fontSize: emphasized ? 15 : 12,
        ),
      ),
    ],
  );
}

String _formatRupiah(double value) => value.round().toString().replaceAllMapped(
  RegExp(r'\B(?=(\d{3})+(?!\d))'),
  (match) => '.',
);

class _HomeMetric extends StatelessWidget {
  const _HomeMetric({
    required this.title,
    required this.value,
    required this.subtitle,
    required this.color,
  });

  final String title;
  final String value;
  final String subtitle;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 7,
                height: 7,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              ),
              const SizedBox(width: 5),
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.muted,
                    fontSize: 9,
                    letterSpacing: 0.25,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF112E5A),
              fontSize: 19,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.3,
            ),
          ),
          if (subtitle.isNotEmpty) ...[
            const SizedBox(height: 1),
            Text(
              subtitle,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: AppColors.muted, fontSize: 10),
            ),
          ],
        ],
      ),
    ),
  );
}

class _HomeMenuTile extends StatelessWidget {
  const _HomeMenuTile({
    super.key,
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),
      side: const BorderSide(color: Color(0xFFDCE9F8)),
    ),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.all(4),
        child: Stack(
          children: [
            Positioned(
              top: 14,
              right: 0,
              child: Icon(Icons.chevron_right_rounded, color: color, size: 18),
            ),
            Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    alignment: Alignment.center,
                    child: Icon(icon, color: color, size: 23),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    label,
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF183B67),
                      fontSize: 12,
                      height: 1.1,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _AttendanceCard extends StatelessWidget {
  const _AttendanceCard({
    required this.now,
    required this.checkInAt,
    required this.checkOutAt,
    required this.attendanceMode,
    required this.verificationStatus,
    required this.selfieReviewStatus,
    required this.shift,
    required this.lateMinutes,
    required this.attendanceBlockReason,
    required this.onCheckIn,
    required this.onCheckOut,
  });

  final DateTime now;
  final DateTime? checkInAt;
  final DateTime? checkOutAt;
  final String attendanceMode;
  final String verificationStatus;
  final String selfieReviewStatus;
  final WorkShiftSchedule? shift;
  final int? lateMinutes;
  final String? attendanceBlockReason;
  final VoidCallback? onCheckIn;
  final VoidCallback? onCheckOut;

  @override
  Widget build(BuildContext context) {
    final completed = checkOutAt != null;
    final checkedIn = checkInAt != null;
    final reviewLabel = attendanceMode == 'online_training'
        ? switch (verificationStatus) {
            'pending_review' => 'MENUNGGU REVIEW',
            'approved' => 'DISETUJUI',
            'rejected' => 'DITOLAK',
            _ => 'DIKLAT ONLINE',
          }
        : selfieReviewStatus == 'pending_review'
        ? 'MENUNGGU REVIEW'
        : null;
    final statusColor = verificationStatus == 'rejected'
        ? const Color(0xFFB42338)
        : reviewLabel == 'MENUNGGU REVIEW'
        ? AppColors.orange
        : AppColors.green;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.line),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0C2C6EB3),
            blurRadius: 18,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Absensi hari ini',
                  style: TextStyle(
                    color: AppColors.ink,
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              _StatusPill(
                label:
                    (attendanceBlockReason != null
                        ? 'ABSENSI DIBLOKIR'
                        : reviewLabel) ??
                    (completed
                        ? 'SELESAI'
                        : checkedIn
                        ? 'SUDAH MASUK'
                        : 'BELUM ABSEN'),
                color: attendanceBlockReason != null
                    ? AppColors.orange
                    : reviewLabel != null
                    ? statusColor
                    : completed || checkedIn
                    ? AppColors.green
                    : AppColors.orange,
              ),
            ],
          ),
          const SizedBox(height: 18),
          Center(
            child: Column(
              children: [
                Text(
                  _formatTime(now),
                  style: const TextStyle(
                    color: AppColors.navy,
                    fontSize: 36,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -1.5,
                    height: 1,
                  ),
                ),
                const SizedBox(height: 7),
                Text(
                  _formatDate(now),
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              color: const Color(0xFFF4F8FE),
              borderRadius: BorderRadius.circular(15),
            ),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE6F2F9),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.schedule_rounded,
                    size: 20,
                    color: AppColors.navy,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Jadwal kerja',
                        style: TextStyle(color: AppColors.muted, fontSize: 11),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        shift?.displayText ?? 'Belum ada shift yang ditetapkan',
                        style: const TextStyle(
                          color: AppColors.ink,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if ((lateMinutes ?? 0) > 0) ...[
                        const SizedBox(height: 4),
                        Text(
                          'Terlambat $lateMinutes menit',
                          style: const TextStyle(
                            color: AppColors.orange,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _AttendanceTimeCell(
                  label: 'Jam masuk',
                  value: checkInAt == null
                      ? 'Belum tercatat'
                      : '${_formatClock(checkInAt!)} WIB',
                  icon: Icons.login_rounded,
                  active: checkedIn,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _AttendanceTimeCell(
                  label: 'Jam pulang',
                  value: checkOutAt == null
                      ? 'Belum tercatat'
                      : '${_formatClock(checkOutAt!)} WIB',
                  icon: Icons.logout_rounded,
                  active: completed,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (attendanceBlockReason != null) ...[
            Container(
              key: const Key('attendance.approvedAbsence'),
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF5E8),
                borderRadius: BorderRadius.circular(13),
                border: Border.all(color: const Color(0xFFF0D7AF)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.event_busy_outlined,
                    color: AppColors.orange,
                    size: 19,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      attendanceBlockReason!,
                      style: const TextStyle(
                        color: AppColors.orange,
                        fontSize: 12,
                        height: 1.4,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 360;
              final checkInButton = FilledButton.icon(
                key: const Key('attendance.checkIn'),
                onPressed: checkedIn ? null : onCheckIn,
                icon: Icon(
                  checkedIn ? Icons.check_rounded : Icons.login_rounded,
                  size: 18,
                ),
                label: Text(checkedIn ? 'Sudah masuk' : 'Absen masuk'),
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.navy,
                  minimumSize: const Size.fromHeight(48),
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(13),
                  ),
                ),
              );
              final checkOutButton = OutlinedButton.icon(
                key: const Key('attendance.checkOut'),
                onPressed: !checkedIn || completed ? null : onCheckOut,
                icon: const Icon(Icons.logout_rounded, size: 18),
                label: Text(completed ? 'Sudah pulang' : 'Absen pulang'),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                  foregroundColor: AppColors.navy,
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                  side: const BorderSide(color: Color(0xFFD5E3EC)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(13),
                  ),
                ),
              );

              if (compact) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    checkInButton,
                    const SizedBox(height: 9),
                    checkOutButton,
                  ],
                );
              }

              return Row(
                children: [
                  Expanded(child: checkInButton),
                  const SizedBox(width: 10),
                  Expanded(child: checkOutButton),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _LocationCard extends StatelessWidget {
  const _LocationCard({required this.settings});

  final AttendanceSettings? settings;

  @override
  Widget build(BuildContext context) {
    final title = settings?.onlineTraining == true
        ? 'Diklat online'
        : settings?.officeName ??
              (settings?.locationAssigned == true
                  ? 'Lokasi kerja'
                  : 'Lokasi belum ditentukan');
    final detail = settings == null
        ? 'Informasi lokasi belum berhasil dimuat'
        : settings!.onlineTraining
        ? 'Absensi diklat tidak memerlukan GPS'
        : !settings!.locationAssigned
        ? 'Hubungi administrator untuk menetapkan cabang'
        : settings!.geofenceEnabled
        ? 'GPS wajib · Radius ${settings!.radiusMeters} meter'
        : 'Validasi GPS tidak diwajibkan di lokasi ini';
    final status = settings?.onlineTraining == true
        ? 'ONLINE'
        : settings?.geofenceEnabled == true
        ? 'GPS'
        : settings?.locationAssigned == true
        ? 'LOKASI'
        : 'BELUM ADA';
    final statusColor = settings?.geofenceEnabled == true
        ? AppColors.green
        : AppColors.orange;

    return _SurfaceCard(
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF0F2),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.location_on_outlined,
                  color: AppColors.blue,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: AppColors.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      detail,
                      style: TextStyle(color: AppColors.muted, fontSize: 11),
                    ),
                  ],
                ),
              ),
              _StatusPill(label: status, color: statusColor),
            ],
          ),
        ),
      ),
    );
  }
}

class _FeatureListTile extends StatelessWidget {
  const _FeatureListTile({
    super.key,
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(16),
      side: const BorderSide(color: AppColors.line),
    ),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: const Color(0xFFEAF4FF),
                borderRadius: BorderRadius.circular(12),
              ),
              alignment: Alignment.center,
              child: Icon(icon, size: 18, color: AppColors.blue),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: AppColors.ink,
                  fontSize: 12,
                  height: 1.15,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              size: 17,
              color: AppColors.muted,
            ),
          ],
        ),
      ),
    ),
  );
}

class _TimeOfDayField extends StatelessWidget {
  const _TimeOfDayField({
    required this.label,
    required this.initialValue,
    required this.onChanged,
  });

  final String label;
  final String initialValue;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final parts = initialValue.split(':');
    final initialTime = TimeOfDay(
      hour: int.parse(parts.first),
      minute: int.parse(parts.last),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(color: AppColors.muted, fontSize: 11),
        ),
        const SizedBox(height: 5),
        OutlinedButton(
          onPressed: () async {
            final picked = await showTimePicker(
              context: context,
              initialTime: initialTime,
              builder: (context, child) => Localizations.override(
                context: context,
                locale: const Locale('id', 'ID'),
                child: child,
              ),
            );
            if (picked != null) {
              onChanged(
                '${picked.hour.toString().padLeft(2, '0')}:'
                '${picked.minute.toString().padLeft(2, '0')}',
              );
            }
          },
          child: Text(initialValue),
        ),
      ],
    );
  }
}

class _HistoryTab extends StatelessWidget {
  const _HistoryTab({
    required this.items,
    required this.onOpenSelfie,
    required this.onOpenRequests,
    required this.onOpenFeature,
    required this.onRequestCorrection,
  });

  final List<_HistoryItem> items;
  final Future<void> Function(int attendanceId, bool checkOut) onOpenSelfie;
  final VoidCallback onOpenRequests;
  final ValueChanged<String> onOpenFeature;
  final ValueChanged<int> onRequestCorrection;

  @override
  Widget build(BuildContext context) {
    return _PageScaffold(
      title: 'Data Absensi',
      subtitle: 'Riwayat kehadiran dan layanan karyawan',
      child: ListView(
        key: const Key('history.scroll'),
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 36),
        children: [
          const _SectionHeading(title: 'Layanan karyawan'),
          GridView(
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 11,
              crossAxisSpacing: 11,
              mainAxisExtent: 76,
            ),
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            children:
                const [
                      ('izin', 'Izin & cuti', Icons.event_note_outlined),
                      ('lembur', 'Lembur', Icons.more_time_rounded),
                      (
                        'payroll',
                        'Info gaji',
                        Icons.account_balance_wallet_outlined,
                      ),
                      ('activity', 'Aktivitas', Icons.edit_note_rounded),
                      (
                        'perjalanan',
                        'Perjalanan dinas',
                        Icons.luggage_outlined,
                      ),
                      ('diklat', 'Diklat', Icons.school_outlined),
                      ('spt', 'SPT', Icons.description_outlined),
                      ('news', 'Berita & pengumuman', Icons.campaign_outlined),
                    ]
                    .map(
                      (item) => _FeatureListTile(
                        key: Key('history.service.${item.$1}'),
                        icon: item.$3,
                        title: item.$2,
                        onTap: () => onOpenFeature(item.$1),
                      ),
                    )
                    .toList(),
          ),
          const SizedBox(height: 22),
          _SectionHeading(
            title: 'Riwayat absensi',
            trailing: TextButton(
              onPressed: onOpenRequests,
              child: const Text('Pengajuan'),
            ),
          ),
          if (items.isEmpty)
            const _EmptyState(
              icon: Icons.history_rounded,
              title: 'Belum ada riwayat',
              message: 'Catatan absensi kamu akan muncul setelah check-in.',
            )
          else
            _SurfaceCard(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              child: Column(
                children: [
                  for (var i = 0; i < items.length; i++) ...[
                    _HistoryRow(item: items[i], onOpenSelfie: onOpenSelfie),
                    Align(
                      alignment: Alignment.centerRight,
                      child:
                          items[i].correctionStatus?.startsWith('pending_') ==
                              true
                          ? Text(
                              _attendanceCorrectionStatusLabel(
                                items[i].correctionStatus!,
                              ),
                              style: const TextStyle(
                                color: AppColors.orange,
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
                              ),
                            )
                          : TextButton.icon(
                              key: Key(
                                'history.correction.${items[i].attendanceId}',
                              ),
                              onPressed: () =>
                                  onRequestCorrection(items[i].attendanceId),
                              icon: const Icon(
                                Icons.edit_calendar_outlined,
                                size: 15,
                              ),
                              label: Text(
                                items[i].correctionStatus == null
                                    ? 'Ajukan koreksi'
                                    : '${_attendanceCorrectionStatusLabel(items[i].correctionStatus!)} · Ajukan lagi',
                                style: const TextStyle(fontSize: 10),
                              ),
                            ),
                    ),
                    if (i != items.length - 1)
                      const Divider(height: 1, color: AppColors.line),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _RequestsTab extends StatelessWidget {
  const _RequestsTab({
    required this.requests,
    required this.leaveBalance,
    required this.onCreateRequest,
    required this.loading,
  });

  final List<LeaveRequestRecord> requests;
  final LeaveBalanceRecord? leaveBalance;
  final VoidCallback onCreateRequest;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return _PageScaffold(
      title: 'Pengajuan',
      subtitle: 'Izin, cuti, perjalanan dinas, dan diklat',
      trailing: IconButton(
        onPressed: loading ? null : onCreateRequest,
        tooltip: 'Buat pengajuan',
        icon: const Icon(Icons.add_circle_outline_rounded),
      ),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
        children: [
          if (leaveBalance != null) ...[
            _LeaveBalanceCard(balance: leaveBalance!),
            const SizedBox(height: 14),
          ],
          _SurfaceCard(
            color: AppColors.navy,
            child: Row(
              children: [
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Perlu izin?',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 6),
                      Text(
                        'Ajukan lebih awal agar tim kamu tahu.',
                        style: TextStyle(
                          color: Color(0xFFD5DEF2),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                FilledButton(
                  onPressed: loading ? null : onCreateRequest,
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: AppColors.navy,
                  ),
                  child: const Text('Buat'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 22),
          _SectionHeading(
            title: 'Pengajuan kamu',
            trailing: Text('${requests.length} total'),
          ),
          if (requests.isEmpty)
            _EmptyState(
              icon: Icons.event_available_outlined,
              title: 'Belum ada pengajuan',
              message: 'Pengajuan baru akan muncul di sini.',
              action: OutlinedButton(
                onPressed: loading ? null : onCreateRequest,
                child: const Text('Ajukan izin atau cuti'),
              ),
            )
          else
            ...requests.map(
              (request) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _SurfaceCard(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.event_note_rounded,
                        color: AppColors.blue,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              request.subtype == null
                                  ? _requestTypeLabel(request.type)
                                  : _requestSubtypeLabel(request.subtype!),
                              style: const TextStyle(
                                color: AppColors.ink,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${_formatDate(request.startDate)}'
                              '${request.startDate == request.endDate ? '' : ' – ${_formatDate(request.endDate)}'}'
                              ' · ${request.reason}',
                              style: const TextStyle(
                                color: AppColors.muted,
                                fontSize: 12,
                              ),
                            ),
                            if (request.details['destination']
                                case final String destination)
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  'Tujuan: $destination',
                                  style: const TextStyle(
                                    color: AppColors.muted,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            if (request.details['institution']
                                case final String institution)
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  'Penyelenggara: $institution',
                                  style: const TextStyle(
                                    color: AppColors.muted,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            const SizedBox(height: 8),
                            _StatusPill(
                              label: _leaveStatusLabel(request.status),
                              color: _leaveStatusColor(request.status),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              _requestProgress(request),
                              style: const TextStyle(
                                color: AppColors.muted,
                                fontSize: 11,
                                height: 1.3,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ApprovalsTab extends StatelessWidget {
  const _ApprovalsTab({
    required this.requests,
    required this.loading,
    required this.onReview,
    required this.onRefresh,
  });

  final List<LeaveRequestReviewItem> requests;
  final bool loading;
  final void Function(LeaveRequestReviewItem request, String decision) onReview;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return _PageScaffold(
      title: 'Persetujuan',
      subtitle: 'Tinjau pengajuan yang ditugaskan kepada kamu',
      trailing: IconButton(
        key: const Key('approvals.refresh'),
        onPressed: loading ? null : onRefresh,
        tooltip: 'Muat ulang',
        icon: const Icon(Icons.refresh_rounded),
      ),
      child: ListView(
        key: const Key('approvals.scroll'),
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
        children: [
          _SectionHeading(
            title: 'Menunggu keputusan',
            trailing: Text('${requests.length} pengajuan'),
          ),
          if (requests.isEmpty)
            _EmptyState(
              icon: Icons.task_alt_rounded,
              title: 'Tidak ada pengajuan menunggu',
              message: loading
                  ? 'Sedang memuat daftar pengajuan.'
                  : 'Pengajuan untuk ditinjau akan muncul di sini.',
            )
          else
            ...requests.map((request) {
              final details = request.details;
              final detailLines = <String>[
                if (details['destination'] is String)
                  'Tujuan: ${details['destination']}',
                if (details['institution'] is String)
                  'Instansi: ${details['institution']}',
                if (details['start_time'] is String &&
                    details['end_time'] is String)
                  'Waktu lembur: ${details['start_time']}–${details['end_time']}',
                if (details['work_description'] is String)
                  details['work_description'] as String,
              ];

              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _SurfaceCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              request.subtype == null
                                  ? _requestTypeLabel(request.type)
                                  : _requestSubtypeLabel(request.subtype!),
                              style: const TextStyle(
                                color: AppColors.ink,
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          _StatusPill(
                            label: _approvalStageLabel(request.reviewStage),
                            color: AppColors.blue,
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        request.applicantName,
                        style: const TextStyle(
                          color: AppColors.ink,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      if (request.applicantDepartment != null ||
                          request.workGroup != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text(
                            [
                              if (request.applicantDepartment != null)
                                request.applicantDepartment!,
                              if (request.workGroup != null) request.workGroup!,
                            ].join(' · '),
                            style: const TextStyle(
                              color: AppColors.muted,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      const SizedBox(height: 8),
                      Text(
                        '${_formatDate(request.startDate)}'
                        '${request.startDate == request.endDate ? '' : ' – ${_formatDate(request.endDate)}'}',
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 12,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        request.reason,
                        style: const TextStyle(
                          color: AppColors.ink,
                          fontSize: 13,
                          height: 1.4,
                        ),
                      ),
                      for (final line in detailLines)
                        Padding(
                          padding: const EdgeInsets.only(top: 5),
                          child: Text(
                            line,
                            style: const TextStyle(
                              color: AppColors.muted,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      if (request.attachmentAvailable)
                        const Padding(
                          padding: EdgeInsets.only(top: 8),
                          child: Row(
                            children: [
                              Icon(
                                Icons.attach_file_rounded,
                                size: 15,
                                color: AppColors.blue,
                              ),
                              SizedBox(width: 4),
                              Text(
                                'Lampiran disertakan',
                                style: TextStyle(
                                  color: AppColors.blue,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              key: Key('approvals.reject.${request.id}'),
                              onPressed: loading
                                  ? null
                                  : () => onReview(request, 'rejected'),
                              child: const Text('Tolak'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: FilledButton(
                              key: Key('approvals.approve.${request.id}'),
                              onPressed: loading
                                  ? null
                                  : () => onReview(request, 'approved'),
                              child: const Text('Setujui / Teruskan'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }
}

class _ProfileTab extends StatefulWidget {
  const _ProfileTab({
    required this.api,
    required this.employee,
    required this.onEmployeeUpdated,
    required this.onSignOut,
  });

  final AttendlyApi api;
  final Employee employee;
  final ValueChanged<Employee> onEmployeeUpdated;
  final Future<void> Function() onSignOut;

  @override
  State<_ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<_ProfileTab> {
  late Employee _employee;
  late final TextEditingController _nameController;
  late final TextEditingController _nikController;
  late final TextEditingController _phoneController;
  late final TextEditingController _addressController;
  late Future<Uint8List> _photoFuture;
  XFile? _npwpDocument;
  XFile? _profilePhoto;
  Uint8List? _photoBytes;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _employee = widget.employee;
    _nameController = TextEditingController(text: _employee.name);
    _nikController = TextEditingController(text: _employee.nik ?? '');
    _phoneController = TextEditingController(text: _employee.phone ?? '');
    _addressController = TextEditingController(text: _employee.address ?? '');
    _photoFuture = _employee.hasProfilePhoto
        ? widget.api.profilePhotoBytes()
        : Future.value(Uint8List(0));
  }

  @override
  void dispose() {
    _nameController.dispose();
    _nikController.dispose();
    _phoneController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  Future<void> _choosePhoto() async {
    try {
      final file = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        maxWidth: 1200,
        imageQuality: 82,
      );
      if (file == null) return;
      final bytes = await file.readAsBytes();
      if (mounted) {
        setState(() {
          _profilePhoto = file;
          _photoBytes = bytes;
        });
      }
    } catch (error) {
      _showMessage('Foto profil tidak dapat dipilih: $error');
    }
  }

  Future<void> _chooseNpwpDocument() async {
    try {
      final file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      );
      if (file != null && mounted) {
        setState(() => _npwpDocument = file.xFile);
      }
    } catch (error) {
      _showMessage('Lampiran NPWP tidak dapat dipilih: $error');
    }
  }

  Future<void> _saveProfile() async {
    final name = _nameController.text.trim();
    final nik = _nikController.text.trim();
    if (name.isEmpty ||
        (nik.isNotEmpty && !RegExp(r'^\d{16}$').hasMatch(nik))) {
      _showMessage('Nama wajib diisi dan NIK harus terdiri dari 16 angka.');
      return;
    }

    setState(() => _saving = true);
    try {
      final employee = await widget.api.updateProfile(
        name: name,
        nik: nik.isEmpty ? null : nik,
        phone: _phoneController.text.trim().isEmpty
            ? null
            : _phoneController.text.trim(),
        address: _addressController.text.trim().isEmpty
            ? null
            : _addressController.text.trim(),
        npwpDocument: _npwpDocument,
        profilePhoto: _profilePhoto,
      );
      if (!mounted) return;
      setState(() {
        _employee = employee;
        _npwpDocument = null;
        _profilePhoto = null;
      });
      widget.onEmployeeUpdated(employee);
      _showMessage('Data profil berhasil disimpan.');
    } catch (error) {
      _showMessage(
        error is ApiException ? error.message : 'Profil gagal disimpan: $error',
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _saveNpwpDocument() async {
    try {
      final bytes = await widget.api.downloadNpwpDocument();
      final savedPath = await FilePicker.saveFile(
        dialogTitle: 'Simpan lampiran NPWP',
        fileName: 'lampiran-npwp.${_employee.npwpDocumentExtension ?? 'pdf'}',
        bytes: bytes,
        mimeType: 'application/octet-stream',
      );
      if (savedPath != null) _showMessage('Lampiran NPWP berhasil disimpan.');
    } catch (error) {
      _showMessage(
        error is ApiException
            ? error.message
            : 'Lampiran NPWP tidak dapat diunduh: $error',
      );
    }
  }

  Widget _profileAvatar(Uint8List? bytes) {
    if (bytes != null && bytes.isNotEmpty) {
      return CircleAvatar(radius: 42, backgroundImage: MemoryImage(bytes));
    }
    return CircleAvatar(
      radius: 42,
      backgroundColor: const Color(0xFFE8ECFF),
      child: Text(
        _initials(_employee.name),
        style: const TextStyle(
          color: AppColors.blue,
          fontSize: 22,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return _PageScaffold(
      title: 'Profil',
      subtitle: 'Data pribadi dan dokumen karyawan',
      trailing: IconButton(
        tooltip: 'Kembali',
        onPressed: () => Navigator.of(context).maybePop(),
        icon: const Icon(Icons.arrow_back_rounded),
      ),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
        children: [
          _SurfaceCard(
            child: Row(
              children: [
                FutureBuilder<Uint8List>(
                  future: _photoFuture,
                  builder: (context, snapshot) {
                    return Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        _profileAvatar(_photoBytes ?? snapshot.data),
                        if (snapshot.hasError) ...[
                          const SizedBox(height: 4),
                          const Text(
                            'Foto gagal dimuat',
                            style: TextStyle(
                              color: AppColors.orange,
                              fontSize: 9,
                            ),
                          ),
                        ],
                      ],
                    );
                  },
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _employee.name,
                        style: const TextStyle(
                          color: AppColors.ink,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _employee.email,
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 12,
                        ),
                      ),
                      if (_employee.department != null ||
                          _employee.baseLocation != null) ...[
                        const SizedBox(height: 8),
                        Text(
                          [
                            if (_employee.department != null)
                              _employee.department!,
                            if (_employee.baseLocation != null)
                              _employee.baseLocation!,
                          ].join(' · '),
                          style: const TextStyle(
                            color: AppColors.blue,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            key: const Key('profile.photo.pick'),
            onPressed: _saving ? null : _choosePhoto,
            icon: const Icon(Icons.add_a_photo_outlined),
            label: Text(
              _profilePhoto == null ? 'Pilih foto profil' : _profilePhoto!.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          const SizedBox(height: 22),
          const _SectionHeading(title: 'Data karyawan'),
          _SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const _FieldLabel('Nama lengkap'),
                TextField(
                  key: const Key('profile.name'),
                  controller: _nameController,
                  textCapitalization: TextCapitalization.words,
                  maxLength: 255,
                  decoration: const InputDecoration(
                    hintText: 'Nama sesuai identitas',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 8),
                const _FieldLabel('NIK'),
                TextField(
                  key: const Key('profile.nik'),
                  controller: _nikController,
                  keyboardType: TextInputType.number,
                  maxLength: 16,
                  decoration: const InputDecoration(
                    hintText: '16 digit nomor induk kependudukan',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 8),
                const _FieldLabel('Nomor telepon'),
                TextField(
                  key: const Key('profile.phone'),
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  maxLength: 30,
                  decoration: const InputDecoration(
                    hintText: 'Nomor yang dapat dihubungi',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 8),
                const _FieldLabel('Alamat'),
                TextField(
                  key: const Key('profile.address'),
                  controller: _addressController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 2000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    hintText: 'Alamat tempat tinggal',
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          const _SectionHeading(title: 'Dokumen NPWP'),
          _SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _npwpDocument != null
                      ? 'Lampiran baru: ${_npwpDocument!.name}'
                      : _employee.hasNpwpDocument
                      ? 'Lampiran NPWP sudah tersimpan.'
                      : 'Belum ada lampiran NPWP.',
                  style: const TextStyle(
                    color: AppColors.ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    OutlinedButton.icon(
                      key: const Key('profile.npwp.pick'),
                      onPressed: _saving ? null : _chooseNpwpDocument,
                      icon: const Icon(Icons.attach_file_rounded),
                      label: const Text('Pilih PDF / JPG / PNG'),
                    ),
                    if (_employee.hasNpwpDocument && _npwpDocument == null)
                      TextButton.icon(
                        onPressed: _saving ? null : _saveNpwpDocument,
                        icon: const Icon(Icons.download_outlined),
                        label: const Text('Unduh'),
                      ),
                    if (_npwpDocument != null)
                      TextButton(
                        onPressed: _saving
                            ? null
                            : () => setState(() => _npwpDocument = null),
                        child: const Text('Hapus pilihan'),
                      ),
                  ],
                ),
                const SizedBox(height: 4),
                const Text(
                  'Ukuran maksimal 5 MB. Dokumen hanya dapat diakses melalui akun kamu.',
                  style: TextStyle(color: AppColors.muted, fontSize: 11),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              key: const Key('profile.save'),
              onPressed: _saving ? null : _saveProfile,
              icon: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.save_outlined),
              label: Text(_saving ? 'Menyimpan...' : 'Simpan data profil'),
            ),
          ),
          const SizedBox(height: 22),
          const _SectionHeading(title: 'Akun'),
          _SurfaceCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                _ProfileAction(
                  icon: Icons.help_outline_rounded,
                  title: 'Bantuan',
                  onTap: () => _showInfo(
                    context,
                    'Bantuan',
                    'Hubungi administrator perusahaan untuk bantuan akun.',
                  ),
                ),
                const Divider(height: 1, color: AppColors.line),
                _ProfileAction(
                  icon: Icons.privacy_tip_outlined,
                  title: 'Privasi & data',
                  onTap: () => _showInfo(
                    context,
                    'Privasi & data',
                    'Data profil dan dokumen pribadi disimpan secara privat di server perusahaan.',
                  ),
                ),
                const Divider(height: 1, color: AppColors.line),
                _ProfileAction(
                  icon: Icons.info_outline_rounded,
                  title: 'Tentang Attendly',
                  onTap: () => _showInfo(
                    context,
                    'Attendly',
                    'Aplikasi absensi karyawan.',
                  ),
                ),
                const Divider(height: 1, color: AppColors.line),
                _ProfileAction(
                  icon: Icons.logout_rounded,
                  title: 'Keluar dari akun',
                  onTap: () async {
                    final confirmed = await showDialog<bool>(
                      context: context,
                      builder: (context) => AlertDialog(
                        title: const Text('Keluar dari akun?'),
                        content: const Text(
                          'Sesi login di perangkat ini akan diakhiri.',
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(context, false),
                            child: const Text('Batal'),
                          ),
                          FilledButton(
                            onPressed: () => Navigator.pop(context, true),
                            child: const Text('Keluar'),
                          ),
                        ],
                      ),
                    );
                    if (confirmed == true) {
                      await widget.onSignOut();
                      if (context.mounted) {
                        Navigator.of(context).maybePop();
                      }
                    }
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PageScaffold extends StatelessWidget {
  const _PageScaffold({
    required this.title,
    required this.subtitle,
    required this.child,
    this.trailing,
    this.showBackButton = false,
  });

  final String title;
  final String subtitle;
  final Widget child;
  final Widget? trailing;
  final bool showBackButton;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 16),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xFF062C68),
                  Color(0xFF0759B5),
                  Color(0xFF078BD1),
                ],
                stops: [0, 0.62, 1],
              ),
              borderRadius: BorderRadius.vertical(bottom: Radius.circular(30)),
            ),
            child: Stack(
              children: [
                const Positioned.fill(
                  child: IgnorePointer(
                    child: CustomPaint(painter: _HomeBackdropPainter()),
                  ),
                ),
                Row(
                  children: [
                    if (showBackButton) ...[
                      IconButton(
                        key: const Key('page.back'),
                        tooltip: 'Kembali',
                        visualDensity: VisualDensity.compact,
                        onPressed: () => Navigator.of(context).maybePop(),
                        style: IconButton.styleFrom(
                          foregroundColor: Colors.white,
                          backgroundColor: Colors.white.withValues(alpha: 0.13),
                        ),
                        icon: const Icon(Icons.arrow_back_rounded),
                      ),
                      const SizedBox(width: 6),
                    ],
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            title,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 21,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.35,
                            ),
                          ),
                          if (subtitle.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              subtitle,
                              style: const TextStyle(
                                color: Color(0xFFD8EEFF),
                                fontSize: 11,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    if (trailing != null)
                      IconTheme(
                        data: const IconThemeData(color: Colors.white),
                        child: trailing!,
                      ),
                  ],
                ),
              ],
            ),
          ),
          Expanded(child: child),
        ],
      ),
    );
  }
}

class _HistoryItem {
  const _HistoryItem({
    required this.attendanceId,
    required this.day,
    required this.month,
    required this.title,
    required this.subtitle,
    required this.time,
    required this.status,
    required this.statusColor,
    this.hasCheckInSelfie = false,
    this.hasCheckOutSelfie = false,
    this.correctionStatus,
  });

  final int attendanceId;
  final String day;
  final String month;
  final String title;
  final String subtitle;
  final String time;
  final String status;
  final Color statusColor;
  final bool hasCheckInSelfie;
  final bool hasCheckOutSelfie;
  final String? correctionStatus;
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: _SurfaceCard(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 34, color: AppColors.muted),
              const SizedBox(height: 10),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppColors.ink,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.muted, fontSize: 12),
              ),
              if (action != null) ...[const SizedBox(height: 14), action!],
            ],
          ),
        ),
      ),
    );
  }
}

_HistoryItem _historyItem(
  AttendanceRecord attendance, {
  String? correctionStatus,
}) {
  final date = attendance.workDate;
  final checkOut = attendance.checkOutAt;
  final isOnlineTraining = attendance.attendanceMode == 'online_training';
  final reviewLabel = switch (attendance.verificationStatus) {
    'pending_review' => 'Menunggu review',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
    _ => 'Tercatat',
  };
  final reviewStatus = switch (attendance.verificationStatus) {
    'pending_review' => 'Menunggu',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
    _ => 'Tercatat',
  };
  return _HistoryItem(
    attendanceId: attendance.id,
    day: date.day.toString().padLeft(2, '0'),
    month: _monthNames[date.month - 1].substring(0, 3).toUpperCase(),
    title:
        '${_dayNames[date.weekday - 1]}, ${date.day} ${_monthNames[date.month - 1]}',
    subtitle: isOnlineTraining
        ? 'Diklat online · $reviewLabel'
        : checkOut == null
        ? 'Check-in tercatat · belum pulang'
        : 'Absensi tersimpan',
    time: checkOut == null
        ? _formatClock(attendance.checkInAt)
        : '${_formatClock(attendance.checkInAt)} – ${_formatClock(checkOut)}',
    status: isOnlineTraining
        ? reviewStatus
        : checkOut == null
        ? 'Masuk'
        : 'Lengkap',
    statusColor: attendance.verificationStatus == 'rejected'
        ? const Color(0xFFB42338)
        : attendance.verificationStatus == 'pending_review'
        ? AppColors.orange
        : checkOut == null
        ? AppColors.orange
        : AppColors.green,
    hasCheckInSelfie: attendance.hasCheckInSelfie,
    hasCheckOutSelfie: attendance.hasCheckOutSelfie,
    correctionStatus: correctionStatus,
  );
}

String _attendanceCorrectionStatusLabel(String status) => switch (status) {
  'pending_group' => 'Menunggu ketua tim',
  'pending_division' => 'Menunggu kepala divisi',
  'pending_director' => 'Menunggu direktur',
  'pending_ceo' => 'Menunggu direktur utama',
  'pending_commissioner' => 'Menunggu komisaris',
  'pending_hr' => 'Menunggu penerapan HR',
  'applied' => 'Koreksi diterapkan',
  'rejected' => 'Koreksi ditolak',
  _ => status,
};

String _leaveStatusLabel(String status) => switch (status) {
  'pending' || 'pending_division' => 'MENUNGGU PIMPINAN DIVISI',
  'pending_group' => 'MENUNGGU GROUP HEAD',
  'pending_director' => 'MENUNGGU DIREKTUR',
  'pending_ceo' => 'MENUNGGU DIREKTUR UTAMA',
  'pending_commissioner' => 'MENUNGGU KOMISARIS',
  'pending_hr' || 'pending_admin' => 'MENUNGGU HR',
  'approved' => 'DISETUJUI',
  'rejected' => 'DITOLAK',
  _ => status.toUpperCase(),
};

String _approvalStageLabel(String stage) => switch (stage) {
  'group' => 'GROUP HEAD',
  'division' => 'PIMPINAN DIVISI',
  'director' => 'DIREKTUR',
  'ceo' => 'DIREKTUR UTAMA',
  'commissioner' => 'KOMISARIS',
  'hr' => 'HR',
  _ => stage.toUpperCase(),
};

String _requestProgress(LeaveRequestRecord request) => switch (request.status) {
  'pending' || 'pending_division' => 'Menunggu persetujuan Pimpinan Divisi.',
  'pending_group' => 'Menunggu persetujuan Group Head.',
  'pending_director' => 'Menunggu persetujuan direktur.',
  'pending_ceo' => 'Menunggu persetujuan Direktur Utama.',
  'pending_commissioner' => 'Menunggu persetujuan Komisaris.',
  'pending_hr' || 'pending_admin' => 'Menunggu keputusan akhir HR.',
  'approved' => 'Pengajuan disetujui.',
  'rejected' =>
    request.supervisorStatus == 'rejected'
        ? 'Pengajuan ditolak pada tahap Pimpinan Divisi.'
        : 'Pengajuan ditolak oleh peninjau.',
  _ => 'Status pengajuan: ${request.status}.',
};

Color _leaveStatusColor(String status) => switch (status) {
  'approved' => AppColors.green,
  'rejected' => const Color(0xFFB42338),
  _ => AppColors.orange,
};

String _capitalize(String value) =>
    value.isEmpty ? value : '${value[0].toUpperCase()}${value.substring(1)}';

String _requestTypeLabel(String type) => switch (type) {
  'izin' => 'Izin',
  'sakit' => 'Sakit',
  'cuti' => 'Cuti',
  'lembur' => 'Lembur',
  'perjalanan_dinas' => 'Perjalanan Dinas',
  'diklat' => 'Diklat',
  _ => _capitalize(type),
};

String _defaultRequestSubtype(String type) => switch (type) {
  'sakit' => 'sakit_tanpa_surat',
  'cuti' => 'cuti_tahunan',
  _ => 'izin_pribadi',
};

List<String> _requestSubtypes(String type) => switch (type) {
  'izin' => ['izin_pribadi', 'izin_keluarga', 'izin_kedukaan'],
  'sakit' => ['sakit_tanpa_surat', 'sakit_dengan_surat'],
  'cuti' => [
    'cuti_tahunan',
    'cuti_menikah',
    'cuti_melahirkan',
    'cuti_keluarga',
    'cuti_khusus',
  ],
  _ => const [],
};

String _requestSubtypeLabel(String subtype) => switch (subtype) {
  'sakit_tanpa_surat' => 'Sakit tanpa surat dokter',
  'sakit_dengan_surat' => 'Sakit dengan surat dokter',
  'izin_pribadi' => 'Keperluan pribadi',
  'izin_keluarga' => 'Keperluan keluarga',
  'izin_kedukaan' => 'Kedukaan',
  'cuti_tahunan' => 'Cuti tahunan',
  'cuti_menikah' => 'Cuti menikah',
  'cuti_melahirkan' => 'Cuti melahirkan',
  'cuti_keluarga' => 'Cuti keluarga',
  'cuti_khusus' => 'Cuti khusus',
  _ => _capitalize(subtype.replaceAll('_', ' ')),
};

String _capitalized(String value) => _capitalize(value);

String _initials(String name) {
  final parts = name.trim().split(RegExp(r'\s+'));
  if (parts.isEmpty || parts.first.isEmpty) return '?';
  return parts.length == 1
      ? parts.first.substring(0, 1).toUpperCase()
      : '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'
            .toUpperCase();
}

class _HistoryRow extends StatelessWidget {
  const _HistoryRow({required this.item, required this.onOpenSelfie});

  final _HistoryItem item;
  final Future<void> Function(int attendanceId, bool checkOut) onOpenSelfie;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          key: Key('history.row.${item.day}.${item.month}'),
          padding: const EdgeInsets.symmetric(vertical: 15),
          child: Row(
            children: [
              Container(
                width: 56,
                height: 64,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF4FF),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      item.day,
                      style: const TextStyle(
                        color: Color(0xFF0A4F9B),
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      item.month,
                      style: const TextStyle(
                        color: Color(0xFF5C7FA4),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      item.subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 12,
                        height: 1.2,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 5),
              SizedBox(
                width: 86,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      item.time,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.end,
                      style: const TextStyle(
                        color: AppColors.ink,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    _StatusPill(label: item.status, color: item.statusColor),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (item.hasCheckInSelfie || item.hasCheckOutSelfie)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                if (item.hasCheckInSelfie)
                  OutlinedButton.icon(
                    key: Key('history.selfie.checkIn.${item.attendanceId}'),
                    onPressed: () => onOpenSelfie(item.attendanceId, false),
                    icon: const Icon(Icons.camera_alt_outlined, size: 15),
                    label: const Text('Selfie masuk'),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size(0, 34),
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      textStyle: const TextStyle(fontSize: 11),
                    ),
                  ),
                if (item.hasCheckOutSelfie)
                  OutlinedButton.icon(
                    key: Key('history.selfie.checkOut.${item.attendanceId}'),
                    onPressed: () => onOpenSelfie(item.attendanceId, true),
                    icon: const Icon(Icons.camera_alt_outlined, size: 15),
                    label: const Text('Selfie pulang'),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size(0, 34),
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      textStyle: const TextStyle(fontSize: 11),
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 20,
            decoration: BoxDecoration(
              color: AppColors.blue,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              title,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.2,
              ),
            ),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

class _SurfaceCard extends StatelessWidget {
  const _SurfaceCard({
    required this.child,
    this.padding = const EdgeInsets.all(15),
    this.color = Colors.white,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: color == Colors.white
              ? const Color(0xFFDCE9F8)
              : color.withValues(alpha: 0.12),
        ),
        boxShadow: color == Colors.white
            ? const [
                BoxShadow(
                  color: Color(0x0C2C6EB3),
                  blurRadius: 18,
                  offset: Offset(0, 6),
                ),
              ]
            : null,
      ),
      child: child,
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.1,
        ),
      ),
    );
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({this.onLight = false, this.tagline = false});

  final bool onLight;
  final bool tagline;

  @override
  Widget build(BuildContext context) {
    return Row(
      key: const Key('brand.logo'),
      mainAxisSize: MainAxisSize.min,
      children: [
        const _AttendlySymbol(size: 36),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Attendly',
              style: TextStyle(
                color: onLight ? AppColors.navy : Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.5,
              ),
            ),
            if (tagline)
              const SizedBox(
                width: 145,
                child: Text(
                  'Absensi Lebih Mudah',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Color(0xFFD8EEFF),
                    fontSize: 9,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _AttendlySymbol extends StatelessWidget {
  const _AttendlySymbol({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('brand.symbol'),
    width: size,
    height: size,
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF11C4D8), Color(0xFF0875D1), Color(0xFF0759B5)],
      ),
      borderRadius: BorderRadius.circular(size * 0.24),
      boxShadow: [
        BoxShadow(
          color: const Color(0xFF003A83).withValues(alpha: 0.22),
          blurRadius: 8,
          offset: const Offset(0, 3),
        ),
      ],
    ),
    child: Stack(
      alignment: Alignment.center,
      children: [
        Icon(Icons.check_rounded, size: size * 0.62, color: Colors.white),
      ],
    ),
  );
}

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 38,
        height: 4,
        margin: const EdgeInsets.only(bottom: 18),
        decoration: BoxDecoration(
          color: const Color(0xFFE4E8F0),
          borderRadius: BorderRadius.circular(4),
        ),
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(
        label,
        style: const TextStyle(
          color: AppColors.ink,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _ProfileAction extends StatelessWidget {
  const _ProfileAction({
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      leading: Icon(icon, color: AppColors.muted, size: 20),
      title: Text(
        title,
        style: const TextStyle(
          color: AppColors.ink,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
      ),
      trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
      dense: true,
    );
  }
}

void _showInfo(BuildContext context, String title, String message) {
  showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Tutup'),
        ),
      ],
    ),
  );
}

const _dayNames = [
  'Senin',
  'Selasa',
  'Rabu',
  'Kamis',
  'Jumat',
  'Sabtu',
  'Minggu',
];

const _monthNames = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
];

String _formatDate(DateTime date) =>
    '${_dayNames[date.weekday - 1]}, ${date.day} ${_monthNames[date.month - 1]} ${date.year}';

String _formatTime(DateTime date) =>
    '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}:${date.second.toString().padLeft(2, '0')}';

String _formatClock(DateTime date) =>
    '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';

String _formatTimeOfDay(TimeOfDay time) =>
    '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';
