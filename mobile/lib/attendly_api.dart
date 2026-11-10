import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api',
);

class ApiException implements Exception {
  const ApiException(
    this.message, {
    this.statusCode,
    this.isNetworkError = false,
  });

  final String message;
  final int? statusCode;
  final bool isNetworkError;

  @override
  String toString() => message;
}

class Employee {
  const Employee({
    required this.id,
    required this.name,
    required this.email,
    this.nik,
    this.phone,
    this.address,
    this.hasNpwpDocument = false,
    this.npwpDocumentExtension,
    this.hasProfilePhoto = false,
    this.department,
    this.baseLocation,
    this.canReviewLeaveRequests = false,
  });

  final int id;
  final String name;
  final String email;
  final String? nik;
  final String? phone;
  final String? address;
  final bool hasNpwpDocument;
  final String? npwpDocumentExtension;
  final bool hasProfilePhoto;
  final String? department;
  final String? baseLocation;
  final bool canReviewLeaveRequests;

  factory Employee.fromJson(Map<String, dynamic> json) => Employee(
    id: json['id'] as int,
    name: json['name'] as String,
    email: json['email'] as String,
    nik: json['nik'] as String?,
    phone: json['phone'] as String?,
    address: json['address'] as String?,
    hasNpwpDocument: json['has_npwp_document'] as bool? ?? false,
    npwpDocumentExtension: json['npwp_document_extension'] as String?,
    hasProfilePhoto: json['has_profile_photo'] as bool? ?? false,
    department: json['department'] as String?,
    baseLocation: json['base_location'] as String?,
    canReviewLeaveRequests: json['can_review_leave_requests'] as bool? ?? false,
  );
}

class AttendanceRecord {
  const AttendanceRecord({
    required this.id,
    required this.workDate,
    required this.checkInAt,
    this.checkOutAt,
    this.attendanceMode = 'onsite',
    this.verificationStatus = 'recorded',
    this.lateMinutes,
    this.selfieReviewStatus = 'not_required',
    this.hasCheckInSelfie = false,
    this.hasCheckOutSelfie = false,
  });

  final int id;
  final DateTime workDate;
  final DateTime checkInAt;
  final DateTime? checkOutAt;
  final String attendanceMode;
  final String verificationStatus;
  final int? lateMinutes;
  final String selfieReviewStatus;
  final bool hasCheckInSelfie;
  final bool hasCheckOutSelfie;

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) =>
      AttendanceRecord(
        id: json['id'] as int,
        workDate: DateTime.parse(json['work_date'] as String),
        checkInAt: DateTime.parse(json['check_in_at'] as String).toLocal(),
        checkOutAt: json['check_out_at'] == null
            ? null
            : DateTime.parse(json['check_out_at'] as String).toLocal(),
        attendanceMode: json['attendance_mode'] as String? ?? 'onsite',
        verificationStatus:
            json['verification_status'] as String? ?? 'recorded',
        lateMinutes: (json['late_minutes'] as num?)?.toInt(),
        selfieReviewStatus:
            json['selfie_review_status'] as String? ?? 'not_required',
        hasCheckInSelfie: json['has_check_in_selfie'] as bool? ?? false,
        hasCheckOutSelfie: json['has_check_out_selfie'] as bool? ?? false,
      );
}

class AttendanceCorrectionRecord {
  const AttendanceCorrectionRecord({
    required this.id,
    required this.attendanceId,
    required this.workDate,
    required this.checkInAt,
    required this.status,
    required this.reason,
    this.checkOutAt,
    this.applicationNote,
  });

  final int id;
  final int attendanceId;
  final DateTime workDate;
  final DateTime checkInAt;
  final DateTime? checkOutAt;
  final String status;
  final String reason;
  final String? applicationNote;

  factory AttendanceCorrectionRecord.fromJson(Map<String, dynamic> json) =>
      AttendanceCorrectionRecord(
        id: json['id'] as int,
        attendanceId: json['attendance_id'] as int,
        workDate: DateTime.parse(json['work_date'] as String),
        checkInAt: DateTime.parse(json['check_in_at'] as String).toLocal(),
        checkOutAt: json['check_out_at'] == null
            ? null
            : DateTime.parse(json['check_out_at'] as String).toLocal(),
        status: json['status'] as String,
        reason: json['reason'] as String,
        applicationNote: json['application_note'] as String?,
      );
}

class WorkShiftSchedule {
  const WorkShiftSchedule({
    required this.name,
    required this.startTime,
    required this.endTime,
    required this.gracePeriodMinutes,
    required this.workDays,
  });

  final String name;
  final String startTime;
  final String endTime;
  final int gracePeriodMinutes;
  final List<int> workDays;

  factory WorkShiftSchedule.fromJson(Map<String, dynamic> json) =>
      WorkShiftSchedule(
        name: json['name'] as String,
        startTime: json['start_time'] as String,
        endTime: json['end_time'] as String,
        gracePeriodMinutes: (json['grace_period_minutes'] as num).toInt(),
        workDays: (json['work_days'] as List<dynamic>)
            .map((day) => (day as num).toInt())
            .toList(growable: false),
      );

  String get displayText {
    const dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    final days = workDays
        .where((day) => day >= 1 && day <= 7)
        .map((day) => dayNames[day - 1])
        .join(', ');
    return '$name · $startTime–$endTime\n$days · toleransi $gracePeriodMinutes menit';
  }
}

class AttendanceSettings {
  const AttendanceSettings({
    required this.locationAssigned,
    required this.geofenceEnabled,
    required this.requiresLocation,
    required this.onlineTraining,
    this.selfieRequired = true,
    this.playIntegrityRequired = false,
    this.attendanceBlocked = false,
    this.attendanceBlockReason,
    this.officeName,
    this.officeLatitude,
    this.officeLongitude,
    this.radiusMeters,
    this.shift,
  });

  final bool locationAssigned;
  final bool geofenceEnabled;
  final bool requiresLocation;
  final bool onlineTraining;
  final bool selfieRequired;
  final bool playIntegrityRequired;
  final bool attendanceBlocked;
  final String? attendanceBlockReason;
  final String? officeName;
  final double? officeLatitude;
  final double? officeLongitude;
  final int? radiusMeters;
  final WorkShiftSchedule? shift;

  factory AttendanceSettings.fromJson(Map<String, dynamic> json) =>
      AttendanceSettings(
        locationAssigned: json['location_assigned'] as bool? ?? false,
        geofenceEnabled: json['geofence_enabled'] as bool? ?? false,
        requiresLocation: json['requires_location'] as bool? ?? false,
        onlineTraining: json['online_training'] as bool? ?? false,
        selfieRequired: json['selfie_required'] as bool? ?? true,
        playIntegrityRequired:
            json['play_integrity_required'] as bool? ?? false,
        attendanceBlocked: json['attendance_blocked'] as bool? ?? false,
        attendanceBlockReason: json['attendance_block_reason'] as String?,
        officeName: json['office_name'] as String?,
        officeLatitude: (json['office_latitude'] as num?)?.toDouble(),
        officeLongitude: (json['office_longitude'] as num?)?.toDouble(),
        radiusMeters: (json['radius_meters'] as num?)?.toInt(),
        shift: json['shift'] is Map<String, dynamic>
            ? WorkShiftSchedule.fromJson(json['shift'] as Map<String, dynamic>)
            : null,
      );
}

class LeaveRequestRecord {
  const LeaveRequestRecord({
    required this.id,
    required this.type,
    required this.startDate,
    required this.endDate,
    required this.reason,
    required this.status,
    this.subtype,
    this.details = const {},
    this.attachmentAvailable = false,
    this.supervisorStatus = 'pending',
    this.reviewNote,
  });

  final int id;
  final String type;
  final DateTime startDate;
  final DateTime endDate;
  final String reason;
  final String status;
  final String? subtype;
  final Map<String, dynamic> details;
  final bool attachmentAvailable;
  final String supervisorStatus;
  final String? reviewNote;

  factory LeaveRequestRecord.fromJson(Map<String, dynamic> json) =>
      LeaveRequestRecord(
        id: json['id'] as int,
        type: json['type'] as String,
        startDate: DateTime.parse(json['start_date'] as String),
        endDate: DateTime.parse(json['end_date'] as String),
        reason: json['reason'] as String,
        status: json['status'] as String,
        subtype: json['subtype'] as String?,
        details: json['details'] is Map<String, dynamic>
            ? json['details'] as Map<String, dynamic>
            : const {},
        attachmentAvailable: json['attachment_available'] as bool? ?? false,
        supervisorStatus: json['supervisor_status'] as String? ?? 'pending',
        reviewNote: json['review_note'] as String?,
      );
}

class ReviewWorkLocation {
  const ReviewWorkLocation({required this.id, required this.name});

  final int id;
  final String name;

  factory ReviewWorkLocation.fromJson(Map<String, dynamic> json) =>
      ReviewWorkLocation(id: json['id'] as int, name: json['name'] as String);
}

class LeaveRequestReviewItem {
  const LeaveRequestReviewItem({
    required this.id,
    required this.applicantName,
    required this.type,
    required this.startDate,
    required this.endDate,
    required this.reason,
    required this.status,
    required this.reviewStage,
    required this.isFinalReviewer,
    this.applicantDepartment,
    this.workGroup,
    this.subtype,
    this.details = const {},
    this.attachmentAvailable = false,
  });

  final int id;
  final String applicantName;
  final String? applicantDepartment;
  final String? workGroup;
  final String type;
  final String? subtype;
  final DateTime startDate;
  final DateTime endDate;
  final String reason;
  final Map<String, dynamic> details;
  final String status;
  final String reviewStage;
  final bool isFinalReviewer;
  final bool attachmentAvailable;

  factory LeaveRequestReviewItem.fromJson(Map<String, dynamic> json) =>
      LeaveRequestReviewItem(
        id: json['id'] as int,
        applicantName: json['applicant_name'] as String? ?? 'Karyawan',
        applicantDepartment: json['applicant_department'] as String?,
        workGroup: json['work_group'] as String?,
        type: json['type'] as String,
        subtype: json['subtype'] as String?,
        startDate: DateTime.parse(json['start_date'] as String),
        endDate: DateTime.parse(json['end_date'] as String),
        reason: json['reason'] as String,
        details: json['details'] is Map<String, dynamic>
            ? json['details'] as Map<String, dynamic>
            : const {},
        status: json['status'] as String,
        reviewStage: json['review_stage'] as String? ?? '',
        isFinalReviewer: json['is_final_reviewer'] as bool? ?? false,
        attachmentAvailable: json['attachment_available'] as bool? ?? false,
      );
}

class LeaveRequestReviewQueue {
  const LeaveRequestReviewQueue({
    required this.requests,
    required this.workLocations,
  });

  final List<LeaveRequestReviewItem> requests;
  final List<ReviewWorkLocation> workLocations;

  factory LeaveRequestReviewQueue.fromJson(Map<String, dynamic> json) =>
      LeaveRequestReviewQueue(
        requests: _listFromJson(
          json['data'],
        ).map(LeaveRequestReviewItem.fromJson).toList(),
        workLocations: _listFromJson(
          json['work_locations'],
        ).map(ReviewWorkLocation.fromJson).toList(),
      );
}

class LeaveBalanceRecord {
  const LeaveBalanceRecord({
    required this.configured,
    required this.year,
    this.entitlementDays,
    this.usedDays,
    this.remainingDays,
  });

  final bool configured;
  final int year;
  final int? entitlementDays;
  final int? usedDays;
  final int? remainingDays;

  factory LeaveBalanceRecord.fromJson(Map<String, dynamic> json) =>
      LeaveBalanceRecord(
        configured: json['configured'] as bool,
        year: json['year'] as int,
        entitlementDays: (json['entitlement_days'] as num?)?.toInt(),
        usedDays: (json['used_days'] as num?)?.toInt(),
        remainingDays: (json['remaining_days'] as num?)?.toInt(),
      );
}

class ActivityReportRecord {
  const ActivityReportRecord({
    required this.id,
    required this.activityDate,
    required this.title,
    required this.description,
    required this.status,
    this.hours,
    this.reviewNote,
  });

  final int id;
  final DateTime activityDate;
  final String title;
  final String description;
  final String status;
  final double? hours;
  final String? reviewNote;

  factory ActivityReportRecord.fromJson(Map<String, dynamic> json) =>
      ActivityReportRecord(
        id: json['id'] as int,
        activityDate: DateTime.parse(json['activity_date'] as String),
        title: json['title'] as String,
        description: json['description'] as String,
        status: json['status'] as String,
        hours: (json['hours'] as num?)?.toDouble(),
        reviewNote: json['review_note'] as String?,
      );
}

class PayrollRecord {
  const PayrollRecord({
    required this.id,
    required this.periodStart,
    required this.grossAmount,
    required this.deductionsAmount,
    required this.netAmount,
    required this.payslipAvailable,
  });

  final int id;
  final DateTime periodStart;
  final double grossAmount;
  final double deductionsAmount;
  final double netAmount;
  final bool payslipAvailable;

  factory PayrollRecord.fromJson(Map<String, dynamic> json) => PayrollRecord(
    id: json['id'] as int,
    periodStart: DateTime.parse(json['period_start'] as String),
    grossAmount: (json['gross_amount'] as num).toDouble(),
    deductionsAmount: (json['deductions_amount'] as num).toDouble(),
    netAmount: (json['net_amount'] as num).toDouble(),
    payslipAvailable: json['payslip_available'] as bool? ?? false,
  );
}

class TaxDocumentRecord {
  const TaxDocumentRecord({
    required this.id,
    required this.taxYear,
    required this.title,
    this.description,
  });

  final int id;
  final int taxYear;
  final String title;
  final String? description;

  factory TaxDocumentRecord.fromJson(Map<String, dynamic> json) =>
      TaxDocumentRecord(
        id: json['id'] as int,
        taxYear: json['tax_year'] as int,
        title: json['title'] as String,
        description: json['description'] as String?,
      );
}

class AnnouncementRecord {
  const AnnouncementRecord({
    required this.id,
    required this.category,
    required this.title,
    required this.body,
    required this.publishedAt,
  });

  final int id;
  final String category;
  final String title;
  final String body;
  final DateTime publishedAt;

  factory AnnouncementRecord.fromJson(Map<String, dynamic> json) =>
      AnnouncementRecord(
        id: json['id'] as int,
        category: json['category'] as String,
        title: json['title'] as String,
        body: json['body'] as String,
        publishedAt: DateTime.parse(json['published_at'] as String),
      );
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.createdAt,
    this.data = const {},
    this.readAt,
  });

  final String id;
  final String title;
  final String message;
  final DateTime createdAt;
  final Map<String, dynamic> data;
  final DateTime? readAt;

  bool get isRead => readAt != null;

  factory AppNotification.fromJson(Map<String, dynamic> json) =>
      AppNotification(
        id: json['id'] as String,
        title: json['title'] as String? ?? 'Notifikasi',
        message: json['message'] as String? ?? '',
        createdAt: DateTime.parse(json['created_at'] as String).toLocal(),
        data: json['data'] is Map<String, dynamic>
            ? json['data'] as Map<String, dynamic>
            : const {},
        readAt: json['read_at'] is String
            ? DateTime.parse(json['read_at'] as String).toLocal()
            : null,
      );
}

class NotificationInbox {
  const NotificationInbox({
    required this.notifications,
    required this.unreadCount,
  });

  final List<AppNotification> notifications;
  final int unreadCount;
}

class PushClientConfig {
  const PushClientConfig({
    required this.apiKey,
    required this.appId,
    required this.messagingSenderId,
    required this.projectId,
  });

  final String apiKey;
  final String appId;
  final String messagingSenderId;
  final String projectId;

  factory PushClientConfig.fromJson(Map<String, dynamic> json) =>
      PushClientConfig(
        apiKey: json['apiKey'] as String,
        appId: json['appId'] as String,
        messagingSenderId: json['messagingSenderId'] as String,
        projectId: json['projectId'] as String,
      );
}

abstract interface class AttendlyApi {
  Future<String?> readToken();
  Future<void> clearToken();
  Future<Employee> login(String email, String password);
  Future<Employee> me();
  Future<Employee> updateProfile({
    required String name,
    required String? nik,
    required String? phone,
    required String? address,
    XFile? npwpDocument,
    XFile? profilePhoto,
  });
  Future<Uint8List> profilePhotoBytes();
  Future<Uint8List> downloadNpwpDocument();
  Future<void> logout();
  Future<AttendanceSettings> attendanceSettings();
  Future<AttendanceRecord?> todayAttendance();
  Future<List<AttendanceRecord>> attendances();
  Future<Uint8List> attendanceSelfieBytes({
    required int attendanceId,
    required bool checkOut,
  });
  Future<List<AttendanceCorrectionRecord>> attendanceCorrections();
  Future<AttendanceCorrectionRecord> createAttendanceCorrection({
    required int attendanceId,
    required DateTime checkInAt,
    DateTime? checkOutAt,
    required String reason,
  });
  Future<AttendanceRecord> checkIn({
    double? latitude,
    double? longitude,
    required XFile selfie,
    String? integrityToken,
  });
  Future<AttendanceRecord> checkOut({
    double? latitude,
    double? longitude,
    required XFile selfie,
    String? integrityToken,
  });
  Future<List<LeaveRequestRecord>> leaveRequests();
  Future<LeaveRequestReviewQueue> reviewQueue();
  Future<void> reviewLeaveRequest({
    required int requestId,
    required String decision,
    String? note,
    int? workLocationId,
    bool isOnline = false,
  });
  Future<NotificationInbox> notifications();
  Future<void> markNotificationRead(String id);
  Future<void> markAllNotificationsRead();
  Future<PushClientConfig?> pushConfig();
  Future<void> registerPushDevice(String token);
  Future<void> unregisterPushDevice(String token);
  Future<LeaveBalanceRecord> leaveBalance();
  Future<LeaveRequestRecord> createLeaveRequest({
    required String type,
    required DateTime startDate,
    required DateTime endDate,
    required String reason,
    String? subtype,
    Map<String, String> details = const {},
    XFile? attachment,
  });
  Future<List<ActivityReportRecord>> activityReports();
  Future<ActivityReportRecord> createActivityReport({
    required DateTime activityDate,
    required String title,
    required String description,
    double? hours,
  });
  Future<List<PayrollRecord>> payrollRecords();
  Future<List<TaxDocumentRecord>> taxDocuments();
  Future<Uint8List> downloadPayslip(int id);
  Future<Uint8List> downloadTaxDocument(int id);
  Future<Uint8List> downloadRequestAttachment(int id);
  Future<List<AnnouncementRecord>> announcements();
}

abstract interface class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> delete();
}

class SecureTokenStore implements TokenStore {
  SecureTokenStore([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'attendly.access_token';

  @override
  Future<String?> read() => _storage.read(key: _tokenKey);

  @override
  Future<void> write(String token) =>
      _storage.write(key: _tokenKey, value: token);

  @override
  Future<void> delete() => _storage.delete(key: _tokenKey);
}

class HttpAttendlyApi implements AttendlyApi {
  HttpAttendlyApi({
    http.Client? client,
    TokenStore? tokenStore,
    String? baseUrl,
  }) : _client = client ?? http.Client(),
       _tokenStore = tokenStore ?? SecureTokenStore(),
       _baseUrl = (baseUrl ?? apiBaseUrl).replaceFirst(RegExp(r'/$'), '');

  final http.Client _client;
  final TokenStore _tokenStore;
  final String _baseUrl;

  @override
  Future<String?> readToken() => _tokenStore.read();

  @override
  Future<void> clearToken() => _tokenStore.delete();

  @override
  Future<Employee> login(String email, String password) async {
    final response = await _send(
      'POST',
      '/login',
      body: {'email': email, 'password': password},
    );
    final data = _object(response)['user'];
    if (data is! Map<String, dynamic>) {
      throw const FormatException(
        'Respons login tidak berisi profil pengguna.',
      );
    }

    final token = _object(response)['access_token'];
    if (token is! String || token.isEmpty) {
      throw const FormatException('Respons login tidak berisi token akses.');
    }

    await _tokenStore.write(token);
    return Employee.fromJson(data);
  }

  @override
  Future<Employee> me() async {
    final response = await _send('GET', '/me', authenticated: true);
    return Employee.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<Employee> updateProfile({
    required String name,
    required String? nik,
    required String? phone,
    required String? address,
    XFile? npwpDocument,
    XFile? profilePhoto,
  }) async {
    final response = await _send(
      'POST',
      '/profile',
      authenticated: true,
      body: {'name': name, 'nik': ?nik, 'phone': ?phone, 'address': ?address},
      uploads: {'npwp_document': ?npwpDocument, 'profile_photo': ?profilePhoto},
    );
    return Employee.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<Uint8List> profilePhotoBytes() =>
      _download('/profile/photo', accept: 'image/*');

  @override
  Future<Uint8List> downloadNpwpDocument() => _download('/profile/npwp');

  @override
  Future<void> logout() async {
    try {
      await _send('POST', '/logout', authenticated: true);
    } finally {
      await clearToken();
    }
  }

  @override
  Future<AttendanceSettings> attendanceSettings() async {
    final response = await _send(
      'GET',
      '/attendance/settings',
      authenticated: true,
    );
    return AttendanceSettings.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<AttendanceRecord?> todayAttendance() async {
    final response = await _send(
      'GET',
      '/attendance/today',
      authenticated: true,
    );
    final data = _object(response)['data'];
    if (data == null) return null;
    if (data is! Map<String, dynamic>) {
      throw const FormatException('Respons absensi hari ini tidak valid.');
    }
    return AttendanceRecord.fromJson(data);
  }

  @override
  Future<List<AttendanceRecord>> attendances() async {
    final response = await _send(
      'GET',
      '/attendances?per_page=31',
      authenticated: true,
    );
    return _listData(response).map(AttendanceRecord.fromJson).toList();
  }

  @override
  Future<Uint8List> attendanceSelfieBytes({
    required int attendanceId,
    required bool checkOut,
  }) => _download(
    '/attendances/$attendanceId/selfies/${checkOut ? 'check-out' : 'check-in'}',
    accept: 'image/*',
  );

  @override
  Future<List<AttendanceCorrectionRecord>> attendanceCorrections() async {
    final response = await _send(
      'GET',
      '/attendance-corrections?per_page=30',
      authenticated: true,
    );
    return _listData(
      response,
    ).map(AttendanceCorrectionRecord.fromJson).toList();
  }

  @override
  Future<AttendanceCorrectionRecord> createAttendanceCorrection({
    required int attendanceId,
    required DateTime checkInAt,
    DateTime? checkOutAt,
    required String reason,
  }) async {
    final response = await _send(
      'POST',
      '/attendances/$attendanceId/corrections',
      authenticated: true,
      body: {
        'check_in_at': checkInAt.toIso8601String(),
        'check_out_at': checkOutAt?.toIso8601String(),
        'reason': reason,
      },
    );
    return AttendanceCorrectionRecord.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<AttendanceRecord> checkIn({
    double? latitude,
    double? longitude,
    required XFile selfie,
    String? integrityToken,
  }) async {
    final response = await _send(
      'POST',
      '/attendance/check-in',
      authenticated: true,
      body: {'latitude': ?latitude, 'longitude': ?longitude},
      upload: selfie,
      extraHeaders: {'X-Play-Integrity': ?integrityToken},
    );
    return AttendanceRecord.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<AttendanceRecord> checkOut({
    double? latitude,
    double? longitude,
    required XFile selfie,
    String? integrityToken,
  }) async {
    final response = await _send(
      'POST',
      '/attendance/check-out',
      authenticated: true,
      body: {'latitude': ?latitude, 'longitude': ?longitude},
      upload: selfie,
      extraHeaders: {'X-Play-Integrity': ?integrityToken},
    );
    return AttendanceRecord.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<List<LeaveRequestRecord>> leaveRequests() async {
    final response = await _send(
      'GET',
      '/leave-requests?per_page=20',
      authenticated: true,
    );
    return _listData(response).map(LeaveRequestRecord.fromJson).toList();
  }

  @override
  Future<LeaveRequestReviewQueue> reviewQueue() async {
    final response = await _send(
      'GET',
      '/leave-requests/review-queue?per_page=30',
      authenticated: true,
    );
    return LeaveRequestReviewQueue.fromJson(_object(response));
  }

  @override
  Future<void> reviewLeaveRequest({
    required int requestId,
    required String decision,
    String? note,
    int? workLocationId,
    bool isOnline = false,
  }) async {
    await _send(
      'PATCH',
      '/leave-requests/$requestId/review',
      body: {
        'status': decision,
        'review_note': note,
        'work_location_id': ?workLocationId,
        'is_online': isOnline,
      },
      authenticated: true,
    );
  }

  @override
  Future<NotificationInbox> notifications() async {
    final response = await _send('GET', '/notifications', authenticated: true);
    final data = _object(response);
    return NotificationInbox(
      notifications: _listData(response).map(AppNotification.fromJson).toList(),
      unreadCount: (data['unread_count'] as num?)?.toInt() ?? 0,
    );
  }

  @override
  Future<void> markNotificationRead(String id) async {
    await _send(
      'POST',
      '/notifications/${Uri.encodeComponent(id)}/read',
      authenticated: true,
    );
  }

  @override
  Future<void> markAllNotificationsRead() async {
    await _send('POST', '/notifications/read-all', authenticated: true);
  }

  @override
  Future<PushClientConfig?> pushConfig() async {
    final response = await _send('GET', '/push/config');
    final data = _object(response)['data'];
    if (data == null) return null;
    return PushClientConfig.fromJson(_object(data));
  }

  @override
  Future<void> registerPushDevice(String token) async {
    await _send(
      'POST',
      '/push/devices',
      authenticated: true,
      body: {
        'token': token,
        'platform': defaultTargetPlatform == TargetPlatform.iOS
            ? 'ios'
            : 'android',
      },
    );
  }

  @override
  Future<void> unregisterPushDevice(String token) async {
    await _send(
      'DELETE',
      '/push/devices',
      authenticated: true,
      body: {'token': token},
    );
  }

  @override
  Future<LeaveBalanceRecord> leaveBalance() async {
    final response = await _send('GET', '/leave-balance', authenticated: true);
    return LeaveBalanceRecord.fromJson(_nestedObject(response, 'data'));
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
    final response = await _send(
      'POST',
      '/leave-requests',
      authenticated: true,
      body: {
        'type': type,
        'start_date': _dateOnly(startDate),
        'end_date': _dateOnly(endDate),
        'reason': reason,
        'subtype': ?subtype,
        if (details.isNotEmpty) 'details': details,
      },
      upload: attachment,
      uploadFieldName: 'attachment',
    );
    return LeaveRequestRecord.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<List<ActivityReportRecord>> activityReports() async {
    final response = await _send(
      'GET',
      '/activity-reports?per_page=30',
      authenticated: true,
    );
    return _listData(response).map(ActivityReportRecord.fromJson).toList();
  }

  @override
  Future<ActivityReportRecord> createActivityReport({
    required DateTime activityDate,
    required String title,
    required String description,
    double? hours,
  }) async {
    final response = await _send(
      'POST',
      '/activity-reports',
      authenticated: true,
      body: {
        'activity_date': _dateOnly(activityDate),
        'title': title,
        'description': description,
        'hours': ?hours,
      },
    );
    return ActivityReportRecord.fromJson(_nestedObject(response, 'data'));
  }

  @override
  Future<List<PayrollRecord>> payrollRecords() async {
    final response = await _send(
      'GET',
      '/payroll?per_page=12',
      authenticated: true,
    );
    return _listData(response).map(PayrollRecord.fromJson).toList();
  }

  @override
  Future<List<TaxDocumentRecord>> taxDocuments() async {
    final response = await _send('GET', '/tax-documents', authenticated: true);
    return _listData(response).map(TaxDocumentRecord.fromJson).toList();
  }

  @override
  Future<Uint8List> downloadPayslip(int id) =>
      _download('/payroll/$id/payslip');

  @override
  Future<Uint8List> downloadTaxDocument(int id) =>
      _download('/tax-documents/$id/file');

  @override
  Future<Uint8List> downloadRequestAttachment(int id) =>
      _download('/leave-requests/$id/attachment');

  @override
  Future<List<AnnouncementRecord>> announcements() async {
    final response = await _send(
      'GET',
      '/announcements?per_page=20',
      authenticated: true,
    );
    return _listData(response).map(AnnouncementRecord.fromJson).toList();
  }

  Future<dynamic> _send(
    String method,
    String path, {
    bool authenticated = false,
    Map<String, Object?>? body,
    XFile? upload,
    String uploadFieldName = 'selfie',
    Map<String, XFile> uploads = const {},
    Map<String, String> extraHeaders = const {},
  }) async {
    final headers = <String, String>{'Accept': 'application/json'};
    headers.addAll(extraHeaders);
    if (body != null && upload == null && uploads.isEmpty) {
      headers['Content-Type'] = 'application/json';
    }
    if (authenticated) {
      final token = await readToken();
      if (token == null || token.isEmpty) {
        throw const ApiException(
          'Sesi login tidak ditemukan.',
          statusCode: 401,
        );
      }
      headers['Authorization'] = 'Bearer $token';
    }

    final uri = Uri.parse('$_baseUrl$path');
    final http.BaseRequest request;
    if (upload == null && uploads.isEmpty) {
      final jsonRequest = http.Request(method, uri)..headers.addAll(headers);
      if (body != null) jsonRequest.body = jsonEncode(body);
      request = jsonRequest;
    } else {
      final multipartRequest = http.MultipartRequest(method, uri)
        ..headers.addAll(headers);
      if (body != null) {
        multipartRequest.fields.addAll(_multipartFields(body));
      }
      final allUploads = <String, XFile>{...uploads, uploadFieldName: ?upload};
      for (final entry in allUploads.entries) {
        final fallbackName = switch (entry.key) {
          'selfie' => 'selfie.jpg',
          'npwp_document' => 'lampiran-npwp.pdf',
          'profile_photo' => 'foto-profil.jpg',
          'attachment' => 'lampiran',
          _ => 'upload',
        };
        multipartRequest.files.add(
          http.MultipartFile.fromBytes(
            entry.key,
            await entry.value.readAsBytes(),
            filename: entry.value.name.isEmpty
                ? fallbackName
                : entry.value.name,
          ),
        );
      }
      request = multipartRequest;
    }

    try {
      final streamedResponse = await _client
          .send(request)
          .timeout(const Duration(seconds: 20));
      final response = await http.Response.fromStream(
        streamedResponse,
      ).timeout(const Duration(seconds: 20));
      final decoded = response.body.isEmpty ? null : jsonDecode(response.body);

      if (response.statusCode < 200 || response.statusCode >= 300) {
        final message =
            decoded is Map<String, dynamic> && decoded['message'] is String
            ? decoded['message'] as String
            : 'Permintaan gagal (HTTP ${response.statusCode}).';
        throw ApiException(message, statusCode: response.statusCode);
      }
      return decoded;
    } on TimeoutException {
      throw const ApiException(
        'Koneksi ke server terlalu lambat. Status absensi belum dipastikan.',
        isNetworkError: true,
      );
    } on http.ClientException {
      throw const ApiException(
        'Tidak dapat terhubung ke server. Pastikan alamat API benar, Apache aktif, dan HP dapat membuka server melalui jaringan lokal.',
        isNetworkError: true,
      );
    }
  }

  Future<Uint8List> _download(
    String path, {
    String accept = 'application/octet-stream',
  }) async {
    final token = await readToken();
    if (token == null || token.isEmpty) {
      throw const ApiException('Sesi login tidak ditemukan.', statusCode: 401);
    }

    try {
      final response = await _client
          .get(
            Uri.parse('$_baseUrl$path'),
            headers: {'Authorization': '******', 'Accept': accept},
          )
          .timeout(const Duration(seconds: 20));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw ApiException(
          'Dokumen tidak dapat diunduh (HTTP ${response.statusCode}).',
          statusCode: response.statusCode,
        );
      }

      return response.bodyBytes;
    } on TimeoutException {
      throw const ApiException('Unduhan terlalu lama.', isNetworkError: true);
    } on http.ClientException {
      throw const ApiException(
        'Tidak dapat terhubung ke server. Pastikan alamat API benar, Apache aktif, dan HP dapat membuka server melalui jaringan lokal.',
        isNetworkError: true,
      );
    }
  }
}

Map<String, dynamic> _object(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  throw const FormatException('Respons server tidak valid.');
}

Map<String, dynamic> _nestedObject(dynamic value, String key) {
  final nested = _object(value)[key];
  if (nested is Map<String, dynamic>) return nested;
  throw FormatException('Respons server tidak berisi data "$key" yang valid.');
}

List<Map<String, dynamic>> _listData(dynamic value) {
  final data = _object(value)['data'];
  if (data is! List) {
    throw const FormatException('Respons server tidak berisi daftar data.');
  }

  return data.map((item) => _object(item)).toList();
}

List<Map<String, dynamic>> _listFromJson(dynamic value) {
  if (value is! List) {
    throw const FormatException('Respons server tidak berisi daftar data.');
  }
  return value.map((item) => _object(item)).toList();
}

String _dateOnly(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

Map<String, String> _multipartFields(Map<String, Object?> values) {
  final fields = <String, String>{};

  void append(String key, Object? value) {
    if (value is Map) {
      for (final entry in value.entries) {
        append('$key[${entry.key}]', entry.value);
      }
    } else if (value != null) {
      fields[key] = value.toString();
    }
  }

  for (final entry in values.entries) {
    append(entry.key, entry.value);
  }

  return fields;
}
