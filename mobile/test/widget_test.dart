import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:attendly_mobile/attendly_api.dart';
import 'package:attendly_mobile/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';

class _FakeAttendlyApi implements AttendlyApi {
  _FakeAttendlyApi({this.shift = _regularShift, this.canReview = false});

  static const _regularShift = WorkShiftSchedule(
    name: 'Shift Reguler',
    startTime: '08:00',
    endTime: '17:00',
    gracePeriodMinutes: 10,
    workDays: [1, 2, 3, 4, 5],
  );

  final WorkShiftSchedule? shift;
  final bool canReview;
  String? token;
  Completer<String?>? tokenReadCompleter;
  Completer<void>? dashboardLoadCompleter;
  AttendanceRecord? attendance;
  XFile? submittedSelfie;
  XFile? submittedCheckoutSelfie;
  XFile? submittedRequestAttachment;
  final List<(int, bool)> requestedSelfies = [];
  bool dropCheckInResponse = false;
  String? attendanceBlockReason;
  final List<AttendanceRecord> attendanceHistory = [];
  final List<AttendanceCorrectionRecord> corrections = [];
  final List<LeaveRequestRecord> requests = [];
  final List<LeaveRequestReviewItem> reviewItems = [];
  final List<(int, String, String?, int?, bool)> reviewDecisions = [];
  final List<AppNotification> notificationsData = [];
  final List<ActivityReportRecord> reports = [];
  final List<PayrollRecord> payroll = [];
  final List<TaxDocumentRecord> taxDocumentsData = [];
  final List<AnnouncementRecord> announcementsData = [];
  LeaveBalanceRecord leaveBalanceData = LeaveBalanceRecord(
    configured: false,
    year: DateTime.now().year,
  );
  late final employee = Employee(
    id: 1,
    name: 'Rizky Aditya',
    email: 'rizky@example.test',
    canReviewLeaveRequests: canReview,
  );
  var nextRequestId = 1;
  var nextCorrectionId = 1;

  @override
  Future<String?> readToken() async => tokenReadCompleter?.future ?? token;

  Future<void> _waitForDashboard() async {
    await dashboardLoadCompleter?.future;
  }

  @override
  Future<void> clearToken() async => token = null;

  @override
  Future<Employee> login(String email, String password) async {
    if (email != employee.email || password != 'password') {
      throw const ApiException('Email atau kata sandi tidak sesuai.');
    }
    token = 'test-token';
    return employee;
  }

  @override
  Future<Employee> me() async => employee;

  @override
  Future<Employee> updateProfile({
    required String name,
    required String? nik,
    required String? phone,
    required String? address,
    XFile? npwpDocument,
    XFile? profilePhoto,
  }) async => Employee(
    id: employee.id,
    name: name,
    email: employee.email,
    nik: nik,
    phone: phone,
    address: address,
    hasNpwpDocument: npwpDocument != null || employee.hasNpwpDocument,
    hasProfilePhoto: profilePhoto != null || employee.hasProfilePhoto,
    canReviewLeaveRequests: employee.canReviewLeaveRequests,
  );

  @override
  Future<Uint8List> profilePhotoBytes() async => Uint8List(0);

  @override
  Future<Uint8List> downloadNpwpDocument() async => Uint8List(0);

  @override
  Future<Uint8List> attendanceSelfieBytes({
    required int attendanceId,
    required bool checkOut,
  }) async {
    requestedSelfies.add((attendanceId, checkOut));
    return Uint8List.fromList(
      base64Decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=',
      ),
    );
  }

  @override
  Future<void> logout() async => token = null;

  @override
  Future<AttendanceSettings> attendanceSettings() async {
    await _waitForDashboard();
    return AttendanceSettings(
      locationAssigned: true,
      geofenceEnabled: true,
      requiresLocation: true,
      onlineTraining: false,
      attendanceBlocked: attendanceBlockReason != null,
      attendanceBlockReason: attendanceBlockReason,
      officeName: 'Kantor Pusat',
      officeLatitude: -6.2,
      officeLongitude: 106.8,
      radiusMeters: 100,
      shift: shift,
    );
  }

  @override
  Future<AttendanceRecord?> todayAttendance() async {
    await _waitForDashboard();
    return attendance;
  }

  @override
  Future<List<AttendanceRecord>> attendances() async {
    await _waitForDashboard();
    return List.of(attendanceHistory);
  }

  @override
  Future<List<AttendanceCorrectionRecord>> attendanceCorrections() async {
    await _waitForDashboard();
    return List.of(corrections);
  }

  @override
  Future<AttendanceCorrectionRecord> createAttendanceCorrection({
    required int attendanceId,
    required DateTime checkInAt,
    DateTime? checkOutAt,
    required String reason,
  }) async {
    final attendance = attendanceHistory.firstWhere(
      (record) => record.id == attendanceId,
    );
    final correction = AttendanceCorrectionRecord(
      id: nextCorrectionId++,
      attendanceId: attendanceId,
      workDate: attendance.workDate,
      checkInAt: checkInAt,
      checkOutAt: checkOutAt,
      status: 'pending_hr',
      reason: reason,
    );
    corrections.insert(0, correction);
    return correction;
  }

  @override
  Future<AttendanceRecord> checkIn({
    double? latitude,
    double? longitude,
    required XFile selfie,
    String? integrityToken,
  }) async {
    submittedSelfie = selfie;
    final now = DateTime.now();
    attendance = AttendanceRecord(
      id: 1,
      workDate: DateTime(now.year, now.month, now.day),
      checkInAt: now,
      selfieReviewStatus: 'submitted',
    );
    attendanceHistory.removeWhere(
      (record) =>
          record.workDate.year == now.year &&
          record.workDate.month == now.month &&
          record.workDate.day == now.day,
    );
    attendanceHistory.insert(0, attendance!);
    if (dropCheckInResponse) {
      dropCheckInResponse = false;
      throw const ApiException(
        'Tidak dapat terhubung ke server. Periksa koneksi internet kamu.',
        isNetworkError: true,
      );
    }
    return attendance!;
  }

  @override
  Future<AttendanceRecord> checkOut({
    double? latitude,
    double? longitude,
    required XFile selfie,
    String? integrityToken,
  }) async {
    submittedCheckoutSelfie = selfie;
    final current = attendance;
    if (current == null) {
      throw const ApiException('Absen masuk belum tercatat.', statusCode: 409);
    }
    attendance = AttendanceRecord(
      id: current.id,
      workDate: current.workDate,
      checkInAt: current.checkInAt,
      checkOutAt: DateTime.now(),
    );
    attendanceHistory[0] = attendance!;
    return attendance!;
  }

  @override
  Future<List<LeaveRequestRecord>> leaveRequests() async {
    await _waitForDashboard();
    return List.of(requests);
  }

  @override
  Future<LeaveRequestReviewQueue> reviewQueue() async {
    await _waitForDashboard();
    return LeaveRequestReviewQueue(
      requests: List.of(reviewItems),
      workLocations: const [ReviewWorkLocation(id: 1, name: 'Kantor Pusat')],
    );
  }

  @override
  Future<void> reviewLeaveRequest({
    required int requestId,
    required String decision,
    String? note,
    int? workLocationId,
    bool isOnline = false,
  }) async {
    reviewDecisions.add((requestId, decision, note, workLocationId, isOnline));
    reviewItems.removeWhere((request) => request.id == requestId);
  }

  @override
  Future<NotificationInbox> notifications() async {
    await _waitForDashboard();
    return NotificationInbox(
      notifications: List.of(notificationsData),
      unreadCount: notificationsData.where((item) => !item.isRead).length,
    );
  }

  @override
  Future<void> markNotificationRead(String id) async {
    final index = notificationsData.indexWhere((item) => item.id == id);
    if (index == -1) return;
    final notification = notificationsData[index];
    notificationsData[index] = AppNotification(
      id: notification.id,
      title: notification.title,
      message: notification.message,
      createdAt: notification.createdAt,
      data: notification.data,
      readAt: DateTime.now(),
    );
  }

  @override
  Future<void> markAllNotificationsRead() async {
    for (final notification in List.of(notificationsData)) {
      await markNotificationRead(notification.id);
    }
  }

  @override
  Future<PushClientConfig?> pushConfig() async => null;

  @override
  Future<void> registerPushDevice(String token) async {}

  @override
  Future<void> unregisterPushDevice(String token) async {}

  @override
  Future<LeaveBalanceRecord> leaveBalance() async {
    await _waitForDashboard();
    return leaveBalanceData;
  }

  @override
  Future<LeaveRequestRecord> createLeaveRequest({
    required String type,
    required DateTime startDate,
    required DateTime endDate,
    required String reason,
    String? subtype,
    Map<String, String> details = const {},
    XFile? attachment,
  }) async {
    submittedRequestAttachment = attachment;
    final request = LeaveRequestRecord(
      id: nextRequestId++,
      type: type,
      startDate: startDate,
      endDate: endDate,
      reason: reason,
      status: 'pending',
      subtype: subtype,
      details: details,
      attachmentAvailable: attachment != null,
    );
    requests.insert(0, request);
    return request;
  }

  @override
  Future<List<ActivityReportRecord>> activityReports() async =>
      List.of(reports);

  @override
  Future<ActivityReportRecord> createActivityReport({
    required DateTime activityDate,
    required String title,
    required String description,
    double? hours,
  }) async {
    final report = ActivityReportRecord(
      id: reports.length + 1,
      activityDate: activityDate,
      title: title,
      description: description,
      status: 'pending',
      hours: hours,
    );
    reports.insert(0, report);
    return report;
  }

  @override
  Future<List<PayrollRecord>> payrollRecords() async => List.of(payroll);

  @override
  Future<List<TaxDocumentRecord>> taxDocuments() async =>
      List.of(taxDocumentsData);

  @override
  Future<Uint8List> downloadPayslip(int id) async => Uint8List(0);

  @override
  Future<Uint8List> downloadTaxDocument(int id) async => Uint8List(0);

  @override
  Future<Uint8List> downloadRequestAttachment(int id) async => Uint8List(0);

  @override
  Future<List<AnnouncementRecord>> announcements() async =>
      List.of(announcementsData);
}

XFile _testSelfie() => XFile.fromData(
  Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xD9]),
  name: 'selfie.jpg',
  mimeType: 'image/jpeg',
);

Future<void> _signIn(WidgetTester tester) async {
  await tester.enterText(
    find.byKey(const Key('login.email')),
    'rizky@example.test',
  );
  await tester.enterText(find.byKey(const Key('login.password')), 'password');
  await tester.tap(find.byKey(const Key('login.submit')));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('session and dashboard loading use branded progress screens', (
    tester,
  ) async {
    final api = _FakeAttendlyApi()..tokenReadCompleter = Completer<String?>();

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pump();
    expect(find.byKey(const Key('session.loading')), findsOneWidget);
    expect(find.text('Menyiapkan ruang kerjamu'), findsOneWidget);
    expect(find.byKey(const Key('session.loading.progress')), findsOneWidget);

    api.tokenReadCompleter!.complete(null);
    await tester.pumpAndSettle();
    api.dashboardLoadCompleter = Completer<void>();

    await tester.enterText(
      find.byKey(const Key('login.email')),
      'rizky@example.test',
    );
    await tester.enterText(find.byKey(const Key('login.password')), 'password');
    await tester.tap(find.byKey(const Key('login.submit')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.byKey(const Key('dashboard.loading')), findsOneWidget);
    expect(find.text('Memuat data kehadiran'), findsOneWidget);

    api.dashboardLoadCompleter!.complete();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('dashboard.loading')), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('notification inbox supports opening and marking messages read', (
    tester,
  ) async {
    final api = _FakeAttendlyApi()
      ..notificationsData.addAll([
        AppNotification(
          id: 'notice-1',
          title: 'Pengajuan perlu ditinjau',
          message: 'Ada pengajuan izin yang menunggu persetujuan.',
          createdAt: DateTime(2026, 11, 4, 9),
        ),
        AppNotification(
          id: 'notice-2',
          title: 'Pembaruan pengajuan',
          message: 'Pengajuan kamu sudah diteruskan ke HR.',
          createdAt: DateTime(2026, 11, 4, 8),
        ),
      ]);

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await _signIn(tester);

    await tester.tap(find.byKey(const Key('home.notifications')));
    await tester.pumpAndSettle();
    expect(find.text('Notifikasi'), findsOneWidget);
    expect(find.text('Pengajuan perlu ditinjau'), findsOneWidget);

    await tester.tap(find.byKey(const Key('notifications.item.notice-1')));
    await tester.pumpAndSettle();
    expect(api.notificationsData.first.isRead, isTrue);

    await tester.tap(find.text('Tandai semua dibaca'));
    await tester.pumpAndSettle();
    expect(
      api.notificationsData.every((notification) => notification.isRead),
      isTrue,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('home dashboard fits compact phone layouts without overflow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(AttendlyApp(api: _FakeAttendlyApi()));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('brand.logo')), findsOneWidget);
    expect(find.byKey(const Key('brand.symbol')), findsOneWidget);
    expect(find.text('Attendly'), findsOneWidget);
    await _signIn(tester);

    expect(find.byKey(const Key('brand.symbol')), findsOneWidget);
    expect(find.text('Attendly'), findsOneWidget);
    expect(find.byKey(const Key('home.attendance')), findsOneWidget);
    expect(find.byKey(const Key('home.header.backdrop')), findsOneWidget);
    expect(find.byKey(const Key('home.calendarIllustration')), findsOneWidget);
    await tester.ensureVisible(find.byKey(const Key('home.attendance')));
    await tester.tap(find.byKey(const Key('home.attendance')));
    await tester.pumpAndSettle();
    expect(find.text('Absensi'), findsOneWidget);
    await tester.tap(find.byKey(const Key('navigation.home')));
    await tester.pumpAndSettle();
    await tester.drag(
      find.byType(CustomScrollView).first,
      const Offset(0, -420),
    );
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('home.menu.izin')), findsOneWidget);
    expect(find.byKey(const Key('home.menu.news')), findsOneWidget);
    expect(find.byKey(const Key('home.header')), findsOneWidget);
    expect(find.byKey(const Key('home.notifications')), findsOneWidget);
    expect(tester.getTopLeft(find.byKey(const Key('home.header'))).dy, 0);
    expect(tester.takeException(), isNull);

    tester.view.physicalSize = const Size(320, 720);
    await tester.pump();
    expect(tester.takeException(), isNull);
    final firstMenuTile = tester.getRect(
      find.byKey(const Key('home.menu.izin')),
    );
    final nextRowMenuTile = tester.getRect(
      find.byKey(const Key('home.menu.activity')),
    );
    expect(firstMenuTile.height, 72);
    expect(nextRowMenuTile.height, firstMenuTile.height);
    expect(nextRowMenuTile.left, firstMenuTile.left);

    const features = {
      'izin': 'Izin dan cuti',
      'lembur': 'Pengajuan lembur',
      'payroll': 'Informasi gaji',
      'activity': 'Laporan aktivitas',
      'perjalanan': 'Perjalanan dinas',
      'diklat': 'Pengajuan diklat',
      'spt': 'Dokumen SPT',
      'news': 'Berita dan pengumuman',
    };
    for (final entry in features.entries) {
      final menuItem = find.byKey(Key('home.menu.${entry.key}'));
      await tester.ensureVisible(menuItem);
      await tester.tap(menuItem);
      await tester.pumpAndSettle();
      expect(find.text(entry.value), findsOneWidget);
      expect(tester.takeException(), isNull);
      Navigator.of(tester.element(find.text(entry.value))).pop();
      await tester.pumpAndSettle();
    }

    await tester.tap(find.byKey(const Key('navigation.attendance')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.byKey(const Key('navigation.data')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.ensureVisible(find.text('Pengajuan'));
    await tester.tap(find.text('Pengajuan'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('profile data can be edited and saved from the mobile app', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(AttendlyApp(api: _FakeAttendlyApi()));
    await tester.pumpAndSettle();
    await _signIn(tester);

    await tester.tap(find.byKey(const Key('home.profile')));
    await tester.pumpAndSettle();
    expect(find.text('Data karyawan'), findsOneWidget);
    expect(find.byKey(const Key('profile.nik')), findsOneWidget);
    expect(find.byKey(const Key('profile.address')), findsOneWidget);
    expect(find.byKey(const Key('profile.photo.pick')), findsOneWidget);
    await tester.enterText(find.byKey(const Key('profile.name')), 'Nama Baru');
    await tester.scrollUntilVisible(
      find.byKey(const Key('profile.npwp.pick')),
      240,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.byKey(const Key('profile.npwp.pick')), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('profile.save')),
      240,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(find.byKey(const Key('profile.save')));
    await tester.pumpAndSettle();
    expect(find.text('Data profil berhasil disimpan.'), findsOneWidget);
    expect(find.text('Nama Baru'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('populated feature cards fit narrow mobile screens', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 720);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _FakeAttendlyApi();
    final today = DateTime.now();
    api.requests.add(
      LeaveRequestRecord(
        id: 1,
        type: 'cuti',
        subtype: 'cuti_tahunan',
        startDate: today,
        endDate: today.add(const Duration(days: 3)),
        reason:
            'Keperluan keluarga dengan keterangan yang cukup panjang untuk menguji tampilan kartu pada layar ponsel kecil.',
        status: 'pending_admin',
      ),
    );
    api.reports.add(
      ActivityReportRecord(
        id: 1,
        activityDate: today,
        title: 'Penyelesaian laporan rekonsiliasi pekerjaan bulanan',
        description:
            'Menyelesaikan pemeriksaan data lintas departemen dan menyiapkan ringkasan hasil pekerjaan untuk ditinjau atasan.',
        status: 'pending',
        hours: 7.5,
      ),
    );
    api.payroll.add(
      PayrollRecord(
        id: 1,
        periodStart: DateTime(today.year, today.month),
        grossAmount: 1250000000,
        deductionsAmount: 175000000,
        netAmount: 1075000000,
        payslipAvailable: false,
      ),
    );
    api.taxDocumentsData.add(
      TaxDocumentRecord(
        id: 1,
        taxYear: today.year,
        title: 'Bukti potong pajak penghasilan tahunan karyawan',
        description:
            'Dokumen resmi yang diterbitkan oleh bagian sumber daya manusia.',
      ),
    );
    api.announcementsData.add(
      AnnouncementRecord(
        id: 1,
        category: 'pengumuman',
        title:
            'Informasi jadwal pemeliharaan sistem dan perubahan jam operasional',
        body:
            'Mohon seluruh karyawan memperhatikan jadwal pemeliharaan sistem absensi serta perubahan jam operasional kantor.',
        publishedAt: today,
      ),
    );
    api.attendanceHistory.add(
      AttendanceRecord(
        id: 1,
        workDate: today.subtract(const Duration(days: 1)),
        checkInAt: DateTime(today.year, today.month, today.day - 1, 8),
        checkOutAt: DateTime(today.year, today.month, today.day - 1, 17),
      ),
    );
    for (var index = 0; index < 12; index++) {
      final workDate = today.subtract(Duration(days: index + 1));
      api.attendanceHistory.add(
        AttendanceRecord(
          id: index + 2,
          workDate: workDate,
          checkInAt: DateTime(workDate.year, workDate.month, workDate.day, 8),
          checkOutAt: DateTime(workDate.year, workDate.month, workDate.day, 17),
          attendanceMode: index == 11 ? 'online_training' : 'onsite',
          verificationStatus: index == 11 ? 'rejected' : 'recorded',
        ),
      );
    }

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.drag(
      find.byType(CustomScrollView).first,
      const Offset(0, -420),
    );
    await tester.pumpAndSettle();

    for (final entry in const {
      'izin': 'Keperluan keluarga dengan keterangan',
      'activity': 'Penyelesaian laporan rekonsiliasi',
      'payroll': 'Penghasilan bruto',
      'spt': 'Bukti potong pajak penghasilan',
      'news': 'Informasi jadwal pemeliharaan sistem',
    }.entries) {
      final menuItem = find.byKey(Key('home.menu.${entry.key}'));
      await tester.ensureVisible(menuItem);
      final menuBounds = tester.getRect(menuItem);
      if (menuBounds.top < 0) {
        await tester.drag(
          find.byType(CustomScrollView).first,
          Offset(0, -menuBounds.top + 16),
        );
        await tester.pumpAndSettle();
      }
      await tester.tap(menuItem);
      await tester.pumpAndSettle();
      expect(find.textContaining(entry.value), findsWidgets);
      expect(tester.takeException(), isNull);
      await tester.tap(find.byKey(const Key('page.back')));
      await tester.pumpAndSettle();
    }

    await tester.tap(find.byKey(const Key('navigation.data')));
    await tester.pumpAndSettle();
    expect(find.text('Riwayat absensi'), findsOneWidget);
    for (final feature in const [
      'izin',
      'lembur',
      'payroll',
      'activity',
      'perjalanan',
      'diklat',
      'spt',
      'news',
    ]) {
      expect(find.byKey(Key('history.service.$feature')), findsOneWidget);
    }
    final oldestDate = today.subtract(const Duration(days: 12));
    const monthAbbreviations = [
      'JAN',
      'FEB',
      'MAR',
      'APR',
      'MEI',
      'JUN',
      'JUL',
      'AGU',
      'SEP',
      'OKT',
      'NOV',
      'DES',
    ];
    final oldestRow = find
        .byKey(
          Key(
            'history.row.${oldestDate.day.toString().padLeft(2, '0')}.'
            '${monthAbbreviations[oldestDate.month - 1]}',
          ),
        )
        .last;
    await tester.drag(
      find.byKey(const Key('history.scroll')),
      const Offset(0, -1800),
    );
    await tester.pumpAndSettle();
    expect(
      tester.getRect(oldestRow).bottom,
      lessThanOrEqualTo(
        tester.getTopLeft(find.byKey(const Key('navigation.data'))).dy,
      ),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('opens private check-in and check-out selfies from history', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi();
    final attendanceDate = DateTime.now().subtract(const Duration(days: 1));
    api.attendanceHistory.add(
      AttendanceRecord(
        id: 21,
        workDate: DateTime(
          attendanceDate.year,
          attendanceDate.month,
          attendanceDate.day,
        ),
        checkInAt: DateTime(
          attendanceDate.year,
          attendanceDate.month,
          attendanceDate.day,
          8,
        ),
        checkOutAt: DateTime(
          attendanceDate.year,
          attendanceDate.month,
          attendanceDate.day,
          17,
        ),
        hasCheckInSelfie: true,
        hasCheckOutSelfie: true,
      ),
    );

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.tap(find.byKey(const Key('navigation.data')));
    await tester.pumpAndSettle();

    final checkInButton = find.byKey(const Key('history.selfie.checkIn.21'));
    await tester.ensureVisible(checkInButton);
    await tester.tap(checkInButton);
    await tester.pumpAndSettle();
    expect(api.requestedSelfies, [(21, false)]);
    expect(find.text('Selfie absen masuk'), findsOneWidget);
    expect(find.byKey(const Key('history.selfie.image')), findsOneWidget);
    await tester.tap(find.byKey(const Key('history.selfie.close')));
    await tester.pumpAndSettle();

    final checkOutButton = find.byKey(const Key('history.selfie.checkOut.21'));
    await tester.ensureVisible(checkOutButton);
    await tester.tap(checkOutButton);
    await tester.pumpAndSettle();
    expect(api.requestedSelfies, [(21, false), (21, true)]);
    expect(find.text('Selfie absen pulang'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reviewers can approve assigned requests from mobile', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final today = DateTime.now();
    final api = _FakeAttendlyApi(canReview: true)
      ..reviewItems.add(
        LeaveRequestReviewItem(
          id: 45,
          applicantName: 'Dina Pratiwi',
          applicantDepartment: 'Operasional',
          workGroup: 'Layanan',
          type: 'izin',
          subtype: 'izin_pribadi',
          startDate: today,
          endDate: today,
          reason: 'Keperluan keluarga.',
          status: 'pending_group',
          reviewStage: 'group',
          isFinalReviewer: true,
        ),
      );

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await _signIn(tester);

    expect(find.byKey(const Key('navigation.approvals')), findsOneWidget);
    await tester.tap(find.byKey(const Key('navigation.approvals')));
    await tester.pumpAndSettle();
    expect(find.text('Dina Pratiwi'), findsOneWidget);
    expect(find.text('Keperluan keluarga.'), findsOneWidget);

    await tester.tap(find.byKey(const Key('approvals.approve.45')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('approvals.confirm')));
    await tester.pumpAndSettle();

    expect(api.reviewDecisions.single.$1, 45);
    expect(api.reviewDecisions.single.$2, 'approved');
    expect(find.text('Tidak ada pengajuan menunggu'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('submits attendance correction from attendance history', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi();
    final attendanceDate = DateTime.now().subtract(const Duration(days: 1));
    api.attendanceHistory.add(
      AttendanceRecord(
        id: 8,
        workDate: DateTime(
          attendanceDate.year,
          attendanceDate.month,
          attendanceDate.day,
        ),
        checkInAt: DateTime(
          attendanceDate.year,
          attendanceDate.month,
          attendanceDate.day,
          8,
        ),
        checkOutAt: DateTime(
          attendanceDate.year,
          attendanceDate.month,
          attendanceDate.day,
          17,
        ),
      ),
    );

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.tap(find.byKey(const Key('navigation.data')));
    await tester.pumpAndSettle();

    final correctionButton = find.byKey(const Key('history.correction.8'));
    await tester.ensureVisible(correctionButton);
    await tester.tap(correctionButton);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextField).last,
      'Mesin absensi terlambat membaca kartu.',
    );
    await tester.tap(find.text('Kirim permohonan'));
    await tester.pumpAndSettle();

    expect(api.corrections, hasLength(1));
    expect(find.text('Menunggu penerapan HR'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('signs in and records a server-backed attendance flow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi();

    await tester.pumpWidget(
      AttendlyApp(
        api: api,
        getCurrentLocation: () async => const AttendanceCoordinates(
          latitude: -6.2,
          longitude: 106.8,
          accuracyMeters: 8,
        ),
        captureSelfie: () async => _testSelfie(),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Masuk'), findsOneWidget);

    await _signIn(tester);
    expect(find.byKey(const Key('home.profile')), findsOneWidget);
    expect(find.text('Rekap Absensi Bulan Ini'), findsOneWidget);
    await tester.tap(find.byKey(const Key('navigation.attendance')));
    await tester.pumpAndSettle();
    expect(
      find.text(
        'Shift Reguler · 08:00–17:00\nSen, Sel, Rab, Kam, Jum · toleransi 10 menit',
      ),
      findsOneWidget,
    );
    expect(find.text('Kantor Pusat'), findsOneWidget);
    expect(find.text('GPS wajib · Radius 100 meter'), findsOneWidget);
    expect(find.text('DEMO'), findsNothing);
    expect(api.token, 'test-token');

    await tester.tap(find.byKey(const Key('attendance.checkIn')));
    await tester.pumpAndSettle();
    expect(find.text('Akurasi GPS 8 meter'), findsOneWidget);
    expect(find.text('© OpenStreetMap contributors'), findsOneWidget);
    await tester.tap(find.byKey(const Key('attendance.mapCheckIn')));
    await tester.pumpAndSettle();
    expect(find.text('SUDAH MASUK'), findsOneWidget);
    expect(api.attendance, isNotNull);
    expect(api.submittedSelfie, isNotNull);

    await tester.tap(find.byKey(const Key('attendance.checkOut')));
    await tester.pumpAndSettle();
    tester
        .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
        .hideCurrentSnackBar();
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('attendance.mapCheckOut')));
    await tester.tap(find.byKey(const Key('attendance.mapCheckOut')));
    await tester.pumpAndSettle();
    expect(find.text('SELESAI'), findsOneWidget);
    expect(api.attendance?.checkOutAt, isNotNull);
    expect(api.submittedCheckoutSelfie, isNotNull);
  });

  testWidgets('submits leave requests to the authenticated API', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi();

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.tap(find.byKey(const Key('home.menu.izin')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('feature.create')));
    await tester.pumpAndSettle();
    expect(find.text('Jenis pengajuan'), findsOneWidget);
    expect(find.text('Lembur'), findsNothing);
    expect(find.text('Perjalanan Dinas'), findsNothing);
    expect(find.text('Diklat'), findsNothing);

    await tester.tap(find.text('Sakit'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sakit dengan surat dokter'));
    await tester.pumpAndSettle();
    expect(find.text('Surat dokter wajib untuk kategori ini.'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Kirim pengajuan'),
          )
          .onPressed,
      isNull,
    );
    await tester.tap(find.text('Sakit tanpa surat dokter'));
    await tester.enterText(find.byType(TextField), 'Periksa ke dokter');
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kirim pengajuan'));
    await tester.pumpAndSettle();

    expect(api.requests, hasLength(1));
    expect(api.requests.first.type, 'sakit');
    expect(find.text('Sakit tanpa surat dokter'), findsOneWidget);
    expect(find.text('MENUNGGU PIMPINAN DIVISI'), findsOneWidget);
    expect(find.text('Menunggu persetujuan Pimpinan Divisi.'), findsOneWidget);
    expect(find.textContaining('Periksa ke dokter'), findsOneWidget);
  });

  testWidgets('confirms check-in from server after a lost response', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi()..dropCheckInResponse = true;

    await tester.pumpWidget(
      AttendlyApp(
        api: api,
        getCurrentLocation: () async =>
            const AttendanceCoordinates(latitude: -6.2, longitude: 106.8),
        captureSelfie: () async => _testSelfie(),
      ),
    );
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.tap(find.byKey(const Key('navigation.attendance')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('attendance.checkIn')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('attendance.mapCheckIn')));
    await tester.pumpAndSettle();

    expect(api.attendance, isNotNull);
    expect(find.text('SUDAH MASUK'), findsOneWidget);
    expect(
      find.text('Status absensi sudah dikonfirmasi server.'),
      findsOneWidget,
    );
  });

  testWidgets('blocks attendance when the location is marked as mocked', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi();

    await tester.pumpWidget(
      AttendlyApp(
        api: api,
        getCurrentLocation: () async => const AttendanceCoordinates(
          latitude: -6.2,
          longitude: 106.8,
          isMocked: true,
        ),
        captureSelfie: () async => _testSelfie(),
      ),
    );
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.tap(find.byKey(const Key('navigation.attendance')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('attendance.checkIn')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('attendance.mapCheckIn')));
    await tester.pumpAndSettle();

    expect(api.attendance, isNull);
    expect(
      find.text(
        'Lokasi terdeteksi sebagai GPS tiruan. Matikan aplikasi fake GPS dan coba lagi.',
      ),
      findsOneWidget,
    );
    expect(find.text('Selfie bukti absensi'), findsNothing);
  });

  testWidgets('shows when no shift is assigned and still allows attendance', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi(shift: null);

    await tester.pumpWidget(
      AttendlyApp(
        api: api,
        getCurrentLocation: () async =>
            const AttendanceCoordinates(latitude: -6.2, longitude: 106.8),
        captureSelfie: () async => _testSelfie(),
      ),
    );
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.tap(find.byKey(const Key('navigation.attendance')));
    await tester.pumpAndSettle();

    expect(find.text('Belum ada shift yang ditetapkan'), findsOneWidget);
    expect(find.byKey(const Key('attendance.checkIn')), findsOneWidget);

    await tester.tap(find.byKey(const Key('attendance.checkIn')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('attendance.skipSelfie')), findsNothing);
    await tester.tap(find.byKey(const Key('attendance.mapCheckIn')));
    await tester.pumpAndSettle();

    expect(api.attendance, isNotNull);
    expect(api.submittedSelfie, isNotNull);
    expect(find.text('SUDAH MASUK'), findsOneWidget);
  });

  testWidgets('approved leave blocks check-in and check-out in the app', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi()
      ..attendanceBlockReason =
          'Absensi tidak dapat dilakukan karena pengajuan cuti Anda telah disetujui untuk hari ini.'
      ..attendance = AttendanceRecord(
        id: 1,
        workDate: DateTime.now(),
        checkInAt: DateTime.now().subtract(const Duration(hours: 8)),
      );

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await _signIn(tester);
    await tester.tap(find.byKey(const Key('navigation.attendance')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('attendance.approvedAbsence')), findsOneWidget);
    expect(
      find.textContaining('pengajuan cuti Anda telah disetujui'),
      findsOneWidget,
    );
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('attendance.checkIn')))
          .onPressed,
      isNull,
    );
    expect(
      tester
          .widget<OutlinedButton>(find.byKey(const Key('attendance.checkOut')))
          .onPressed,
      isNull,
    );
  });

  testWidgets('shows an explicit login error for invalid credentials', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(412, 915);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = _FakeAttendlyApi();

    await tester.pumpWidget(AttendlyApp(api: api));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('login.email')),
      'wrong@example.test',
    );
    await tester.enterText(find.byKey(const Key('login.password')), 'wrong');
    await tester.tap(find.byKey(const Key('login.submit')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('login.error')), findsOneWidget);
    expect(find.text('Email atau kata sandi tidak sesuai.'), findsOneWidget);
  });
}
