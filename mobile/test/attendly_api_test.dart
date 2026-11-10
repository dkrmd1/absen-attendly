import 'dart:convert';
import 'dart:typed_data';

import 'package:attendly_mobile/attendly_api.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';

class _MemoryTokenStore implements TokenStore {
  String? token;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;

  @override
  Future<void> delete() async => token = null;
}

XFile _testSelfie() => XFile.fromData(
  Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xD9]),
  name: 'selfie.jpg',
  mimeType: 'image/jpeg',
);

void main() {
  test(
    'push config and device registration use their intended API routes',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final requests = <http.Request>[];
      final api = HttpAttendlyApi(
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
        client: MockClient((request) async {
          requests.add(request);
          if (request.url.path == '/api/push/config') {
            return http.Response(
              jsonEncode({
                'data': {
                  'apiKey': 'public-key',
                  'appId': '1:123:android:abc',
                  'messagingSenderId': '123',
                  'projectId': 'attendly-demo',
                },
              }),
              200,
            );
          }
          return http.Response('{"message":"ok"}', 200);
        }),
      );

      final config = await api.pushConfig();
      expect(config?.projectId, 'attendly-demo');
      expect(config?.apiKey, 'public-key');
      await api.registerPushDevice('device-token');
      await api.unregisterPushDevice('device-token');

      expect(
        requests.map((request) => '${request.method} ${request.url.path}'),
        [
          'GET /api/push/config',
          'POST /api/push/devices',
          'DELETE /api/push/devices',
        ],
      );
      expect(requests[1].headers['authorization'], 'Bearer session-token');
      expect(jsonDecode(requests[1].body), {
        'token': 'device-token',
        'platform': 'android',
      });
      expect(requests[2].headers['authorization'], 'Bearer session-token');
      expect(jsonDecode(requests[2].body), {'token': 'device-token'});
    },
  );

  test(
    'review queue and decisions use authenticated leave request endpoints',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final requests = <http.Request>[];
      final api = HttpAttendlyApi(
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
        client: MockClient((request) async {
          requests.add(request);
          if (request.method == 'GET') {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'id': 42,
                    'applicant_name': 'Dina',
                    'applicant_department': 'Operasional',
                    'work_group': 'Layanan',
                    'type': 'izin',
                    'subtype': 'izin_pribadi',
                    'start_date': '2026-11-06',
                    'end_date': '2026-11-06',
                    'reason': 'Keperluan keluarga',
                    'details': {},
                    'status': 'pending_group',
                    'review_stage': 'group',
                    'is_final_reviewer': true,
                  },
                ],
                'work_locations': [
                  {'id': 3, 'name': 'Kantor Pusat'},
                ],
              }),
              200,
            );
          }
          return http.Response('{"data":{"id":42,"status":"approved"}}', 200);
        }),
      );

      final queue = await api.reviewQueue();
      expect(queue.requests.single.applicantName, 'Dina');
      expect(queue.requests.single.isFinalReviewer, isTrue);
      expect(queue.workLocations.single.name, 'Kantor Pusat');
      await api.reviewLeaveRequest(
        requestId: 42,
        decision: 'approved',
        note: 'Disetujui.',
      );

      expect(
        requests.map((request) => '${request.method} ${request.url.path}'),
        [
          'GET /api/leave-requests/review-queue',
          'PATCH /api/leave-requests/42/review',
        ],
      );
      expect(jsonDecode(requests.last.body), {
        'status': 'approved',
        'review_note': 'Disetujui.',
        'is_online': false,
      });
    },
  );
  test(
    'attendance selfie downloads use authenticated private endpoints',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final requests = <http.Request>[];
      final expectedBytes = Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xD9]);
      final api = HttpAttendlyApi(
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
        client: MockClient((request) async {
          requests.add(request);
          return http.Response.bytes(
            expectedBytes,
            200,
            headers: {'content-type': 'image/jpeg'},
          );
        }),
      );

      final checkIn = await api.attendanceSelfieBytes(
        attendanceId: 18,
        checkOut: false,
      );
      final checkOut = await api.attendanceSelfieBytes(
        attendanceId: 18,
        checkOut: true,
      );

      expect(checkIn, expectedBytes);
      expect(checkOut, expectedBytes);
      expect(requests.map((request) => request.url.path), [
        '/api/attendances/18/selfies/check-in',
        '/api/attendances/18/selfies/check-out',
      ]);
      expect(
        requests.every(
          (request) =>
              request.headers['Authorization'] == '******' &&
              request.headers['Accept'] == 'image/*',
        ),
        isTrue,
      );
    },
  );

  test(
    'attendance correction requests use authenticated API endpoints',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final requests = <http.Request>[];
      final api = HttpAttendlyApi(
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
        client: MockClient((request) async {
          requests.add(request);
          final data = {
            'id': 12,
            'attendance_id': 3,
            'work_date': '2026-11-03',
            'check_in_at': '2026-11-03T08:15:00Z',
            'check_out_at': '2026-11-03T17:00:00Z',
            'status': 'pending_group',
            'reason': 'Mesin absensi terlambat membaca kartu.',
            'application_note': null,
          };
          return request.method == 'POST'
              ? http.Response(jsonEncode({'data': data}), 201)
              : http.Response(
                  jsonEncode({
                    'data': [data],
                  }),
                  200,
                );
        }),
      );

      final correction = await api.createAttendanceCorrection(
        attendanceId: 3,
        checkInAt: DateTime.utc(2026, 11, 3, 8, 15),
        checkOutAt: DateTime.utc(2026, 11, 3, 17),
        reason: 'Mesin absensi terlambat membaca kartu.',
      );
      expect(correction.status, 'pending_group');
      expect(correction.attendanceId, 3);
      final corrections = await api.attendanceCorrections();
      expect(corrections.single.id, 12);
      expect(
        requests.map((request) => '${request.method} ${request.url.path}'),
        [
          'POST /api/attendances/3/corrections',
          'GET /api/attendance-corrections',
        ],
      );
      expect(jsonDecode(requests.first.body), {
        'check_in_at': '2026-11-03T08:15:00.000Z',
        'check_out_at': '2026-11-03T17:00:00.000Z',
        'reason': 'Mesin absensi terlambat membaca kartu.',
      });
    },
  );

  test(
    'notifications load and read actions use authenticated endpoints',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final requests = <http.Request>[];
      final api = HttpAttendlyApi(
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
        client: MockClient((request) async {
          requests.add(request);
          if (request.url.path == '/api/notifications') {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'id': 'notification-1',
                    'title': 'Pengajuan perlu ditinjau',
                    'message': 'Ada pengajuan baru.',
                    'data': {'kind': 'leave_request_review'},
                    'created_at': '2026-11-04T08:30:00Z',
                    'read_at': null,
                  },
                ],
                'unread_count': 3,
              }),
              200,
            );
          }
          return http.Response('{"message":"OK"}', 200);
        }),
      );

      final inbox = await api.notifications();
      expect(inbox.unreadCount, 3);
      expect(inbox.notifications, hasLength(1));
      expect(inbox.notifications.single.title, 'Pengajuan perlu ditinjau');
      expect(inbox.notifications.single.isRead, isFalse);

      await api.markNotificationRead('notification-1');
      await api.markAllNotificationsRead();

      expect(
        requests.map((request) => '${request.method} ${request.url.path}'),
        [
          'GET /api/notifications',
          'POST /api/notifications/notification-1/read',
          'POST /api/notifications/read-all',
        ],
      );
      expect(requests, hasLength(3));
    },
  );

  test('leave balance loads the private HR-configured balance', () async {
    final tokenStore = _MemoryTokenStore()..token = 'session-token';
    final api = HttpAttendlyApi(
      tokenStore: tokenStore,
      baseUrl: 'https://api.example.test/api',
      client: MockClient((request) async {
        expect(request.url.path, '/api/leave-balance');
        expect(request.headers['Authorization'], 'Bearer session-token');
        return http.Response(
          jsonEncode({
            'data': {
              'configured': true,
              'year': 2026,
              'entitlement_days': 12,
              'used_days': 3,
              'remaining_days': 9,
            },
          }),
          200,
        );
      }),
    );

    final balance = await api.leaveBalance();
    expect(balance.configured, isTrue);
    expect(balance.year, 2026);
    expect(balance.entitlementDays, 12);
    expect(balance.usedDays, 3);
    expect(balance.remainingDays, 9);
  });

  test('login saves a token and authenticated calls use it', () async {
    final tokenStore = _MemoryTokenStore();
    final authorizationHeaders = <String?>[];
    final client = MockClient((request) async {
      if (request.url.path == '/api/login') {
        expect(jsonDecode(request.body), {
          'email': 'rizky@example.test',
          'password': 'password',
        });
        return http.Response(
          jsonEncode({
            'access_token': 'secret-test-token',
            'token_type': 'Bearer',
            'user': {
              'id': 7,
              'name': 'Rizky Aditya',
              'email': 'rizky@example.test',
              'department': 'Operasional',
              'base_location': 'Cabang Bandung',
            },
          }),
          200,
        );
      }

      authorizationHeaders.add(request.headers['Authorization']);
      if (request.url.path == '/api/me') {
        return http.Response(
          jsonEncode({
            'data': {
              'id': 7,
              'name': 'Rizky Aditya',
              'email': 'rizky@example.test',
              'department': 'Operasional',
              'base_location': 'Cabang Bandung',
            },
          }),
          200,
        );
      }
      if (request.url.path == '/api/logout') {
        return http.Response('{"message":"Berhasil keluar."}', 200);
      }
      return http.Response('{"message":"Not found"}', 404);
    });
    final api = HttpAttendlyApi(
      client: client,
      tokenStore: tokenStore,
      baseUrl: 'https://api.example.test/api',
    );

    final user = await api.login('rizky@example.test', 'password');
    expect(user.name, 'Rizky Aditya');
    expect(user.department, 'Operasional');
    expect(user.baseLocation, 'Cabang Bandung');
    expect(tokenStore.token, 'secret-test-token');
    expect((await api.me()).id, 7);
    await api.logout();

    expect(authorizationHeaders, [
      'Bearer secret-test-token',
      'Bearer secret-test-token',
    ]);
    expect(tokenStore.token, isNull);
  });

  test(
    'check-in conflicts are surfaced instead of presented as success',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final api = HttpAttendlyApi(
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
        client: MockClient(
          (_) async => http.Response(
            '{"message":"Absensi masuk hari ini sudah tercatat."}',
            409,
          ),
        ),
      );

      await expectLater(
        api.checkIn(latitude: -6.2, longitude: 106.8, selfie: _testSelfie()),
        throwsA(
          isA<ApiException>()
              .having((error) => error.statusCode, 'statusCode', 409)
              .having(
                (error) => error.message,
                'message',
                'Absensi masuk hari ini sudah tercatat.',
              ),
        ),
      );
    },
  );

  test(
    'network failures are reported as recoverable connection errors',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final api = HttpAttendlyApi(
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
        client: MockClient((_) async {
          throw http.ClientException('connection refused');
        }),
      );

      await expectLater(
        api.todayAttendance(),
        throwsA(
          isA<ApiException>()
              .having((error) => error.isNetworkError, 'isNetworkError', isTrue)
              .having(
                (error) => error.message,
                'message',
                'Tidak dapat terhubung ke server. Pastikan alamat API benar, Apache aktif, dan HP dapat membuka server melalui jaringan lokal.',
              ),
        ),
      );
    },
  );

  test(
    'travel requests include destination details in the API payload',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final client = MockClient((request) async {
        expect(request.url.path, '/api/leave-requests');
        expect(jsonDecode(request.body), {
          'type': 'perjalanan_dinas',
          'start_date': '2026-11-03',
          'end_date': '2026-11-05',
          'reason': 'Kunjungan cabang',
          'details': {'destination': 'Bandung'},
        });

        return http.Response(
          jsonEncode({
            'data': {
              'id': 42,
              'type': 'perjalanan_dinas',
              'start_date': '2026-11-03',
              'end_date': '2026-11-05',
              'reason': 'Kunjungan cabang',
              'status': 'pending',
              'details': {'destination': 'Bandung'},
            },
          }),
          201,
        );
      });
      final api = HttpAttendlyApi(
        client: client,
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
      );

      final request = await api.createLeaveRequest(
        type: 'perjalanan_dinas',
        startDate: DateTime(2026, 11, 3),
        endDate: DateTime(2026, 11, 5),
        reason: 'Kunjungan cabang',
        details: {'destination': 'Bandung'},
      );

      expect(request.details['destination'], 'Bandung');
    },
  );

  test('check-in sends the coordinates captured by the device', () async {
    final tokenStore = _MemoryTokenStore()..token = 'session-token';
    final client = MockClient((request) async {
      expect(request.url.path, '/api/attendance/check-in');
      expect(request.headers['X-Play-Integrity'], 'verified-token');
      expect(request.headers['content-type'], contains('multipart/form-data'));
      final multipartBody = latin1.decode(request.bodyBytes);
      expect(multipartBody, contains('name="latitude"'));
      expect(multipartBody, contains('-6.2'));
      expect(multipartBody, contains('name="longitude"'));
      expect(multipartBody, contains('name="selfie"; filename="selfie.jpg"'));

      return http.Response(
        jsonEncode({
          'data': {
            'id': 5,
            'work_date': '2026-11-02',
            'check_in_at': '2026-11-02T08:00:00Z',
          },
        }),
        201,
      );
    });

    final api = HttpAttendlyApi(
      client: client,
      tokenStore: tokenStore,
      baseUrl: 'https://api.example.test/api',
    );

    final attendance = await api.checkIn(
      latitude: -6.2,
      longitude: 106.8,
      selfie: _testSelfie(),
      integrityToken: 'verified-token',
    );

    expect(attendance.id, 5);
  });

  test('check-in uploads a captured selfie as multipart form data', () async {
    final tokenStore = _MemoryTokenStore()..token = 'session-token';
    final client = MockClient((request) async {
      expect(request.url.path, '/api/attendance/check-in');
      expect(
        request.headers['content-type'],
        startsWith('multipart/form-data; boundary='),
      );
      final multipartBody = latin1.decode(request.bodyBytes);
      expect(multipartBody, contains('name="latitude"'));
      expect(multipartBody, contains('-6.2'));
      expect(multipartBody, contains('name="longitude"'));
      expect(multipartBody, contains('name="selfie"; filename="selfie.jpg"'));

      return http.Response(
        jsonEncode({
          'data': {
            'id': 5,
            'work_date': '2026-11-02',
            'check_in_at': '2026-11-02T08:00:00Z',
            'selfie_review_status': 'submitted',
          },
        }),
        201,
      );
    });
    final api = HttpAttendlyApi(
      client: client,
      tokenStore: tokenStore,
      baseUrl: 'https://api.example.test/api',
    );

    final attendance = await api.checkIn(
      latitude: -6.2,
      longitude: 106.8,
      selfie: XFile.fromData(
        Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xD9]),
        name: 'selfie.jpg',
        mimeType: 'image/jpeg',
      ),
    );

    expect(attendance.selfieReviewStatus, 'submitted');
  });

  test('check-out uploads a required selfie as multipart form data', () async {
    final tokenStore = _MemoryTokenStore()..token = 'session-token';
    final client = MockClient((request) async {
      expect(request.url.path, '/api/attendance/check-out');
      expect(
        request.headers['content-type'],
        startsWith('multipart/form-data; boundary='),
      );
      final multipartBody = latin1.decode(request.bodyBytes);
      expect(multipartBody, contains('name="selfie"; filename="selfie.jpg"'));
      expect(multipartBody, contains('name="latitude"'));

      return http.Response(
        jsonEncode({
          'data': {
            'id': 5,
            'work_date': '2026-11-02',
            'check_in_at': '2026-11-02T08:00:00Z',
            'check_out_at': '2026-11-02T17:00:00Z',
          },
        }),
        200,
      );
    });
    final api = HttpAttendlyApi(
      client: client,
      tokenStore: tokenStore,
      baseUrl: 'https://api.example.test/api',
    );

    final attendance = await api.checkOut(
      latitude: -6.2,
      longitude: 106.8,
      selfie: _testSelfie(),
    );
    expect(attendance.checkOutAt, isNotNull);
  });

  test(
    'profile updates upload employee data and both private documents',
    () async {
      final tokenStore = _MemoryTokenStore()..token = 'session-token';
      final client = MockClient((request) async {
        expect(request.url.path, '/api/profile');
        expect(request.method, 'POST');
        expect(
          request.headers['content-type'],
          startsWith('multipart/form-data; boundary='),
        );
        final multipartBody = latin1.decode(request.bodyBytes);
        expect(multipartBody, contains('name="name"'));
        expect(multipartBody, contains('Rizky Aditya'));
        expect(multipartBody, contains('name="nik"'));
        expect(multipartBody, contains('3201010101010001'));
        expect(multipartBody, contains('name="phone"'));
        expect(multipartBody, contains('081234567890'));
        expect(multipartBody, contains('name="address"'));
        expect(multipartBody, contains('Jl. Merdeka'));
        expect(
          multipartBody,
          contains('name="npwp_document"; filename="lampiran-npwp.pdf"'),
        );
        expect(
          multipartBody,
          contains('name="profile_photo"; filename="foto-profil.jpg"'),
        );

        return http.Response(
          jsonEncode({
            'data': {
              'id': 7,
              'name': 'Rizky Aditya',
              'email': 'rizky@example.test',
              'nik': '3201010101010001',
              'phone': '081234567890',
              'address': 'Jl. Merdeka',
              'has_npwp_document': true,
              'npwp_document_extension': 'pdf',
              'has_profile_photo': true,
            },
          }),
          200,
        );
      });
      final api = HttpAttendlyApi(
        client: client,
        tokenStore: tokenStore,
        baseUrl: 'https://api.example.test/api',
      );

      final profile = await api.updateProfile(
        name: 'Rizky Aditya',
        nik: '3201010101010001',
        phone: '081234567890',
        address: 'Jl. Merdeka',
        npwpDocument: XFile.fromData(
          Uint8List.fromList([1, 2, 3]),
          name: 'npwp.pdf',
          mimeType: 'application/pdf',
        ),
        profilePhoto: XFile.fromData(
          Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xD9]),
          name: 'photo.jpg',
          mimeType: 'image/jpeg',
        ),
      );

      expect(profile.nik, '3201010101010001');
      expect(profile.address, 'Jl. Merdeka');
      expect(profile.hasNpwpDocument, isTrue);
      expect(profile.npwpDocumentExtension, 'pdf');
      expect(profile.hasProfilePhoto, isTrue);
    },
  );

  test('online attendance can be submitted without GPS coordinates', () async {
    final tokenStore = _MemoryTokenStore()..token = 'session-token';
    final client = MockClient((request) async {
      expect(request.url.path, '/api/attendance/check-in');
      final multipartBody = latin1.decode(request.bodyBytes);
      expect(multipartBody, contains('name="selfie"; filename="selfie.jpg"'));
      expect(multipartBody, isNot(contains('name="latitude"')));

      return http.Response(
        jsonEncode({
          'data': {
            'id': 8,
            'work_date': '2026-11-02',
            'check_in_at': '2026-11-02T08:00:00Z',
            'attendance_mode': 'online_training',
            'verification_status': 'pending_review',
          },
        }),
        201,
      );
    });
    final api = HttpAttendlyApi(
      client: client,
      tokenStore: tokenStore,
      baseUrl: 'https://api.example.test/api',
    );

    final attendance = await api.checkIn(selfie: _testSelfie());

    expect(attendance.attendanceMode, 'online_training');
    expect(attendance.verificationStatus, 'pending_review');
  });

  test('attendance settings expose per-assignment GPS requirements', () async {
    final tokenStore = _MemoryTokenStore()..token = 'session-token';
    final api = HttpAttendlyApi(
      tokenStore: tokenStore,
      baseUrl: 'https://api.example.test/api',
      client: MockClient((request) async {
        expect(request.url.path, '/api/attendance/settings');
        return http.Response(
          '{"data":{"location_assigned":true,"geofence_enabled":false,"requires_location":false,"online_training":true,"selfie_required":true,"attendance_blocked":true,"attendance_block_reason":"Pengajuan cuti disetujui.","play_integrity_required":true,"office_name":"Diklat online","radius_meters":null,"shift":{"id":3,"name":"Shift Reguler","start_time":"08:00","end_time":"17:00","grace_period_minutes":10,"work_days":[1,2,3,4,5]}}}',
          200,
        );
      }),
    );

    final settings = await api.attendanceSettings();

    expect(settings.locationAssigned, isTrue);
    expect(settings.geofenceEnabled, isFalse);
    expect(settings.requiresLocation, isFalse);
    expect(settings.onlineTraining, isTrue);
    expect(settings.selfieRequired, isTrue);
    expect(settings.attendanceBlocked, isTrue);
    expect(settings.attendanceBlockReason, 'Pengajuan cuti disetujui.');
    expect(settings.playIntegrityRequired, isTrue);
    expect(settings.officeName, 'Diklat online');
    expect(
      settings.shift?.displayText,
      'Shift Reguler · 08:00–17:00\nSen, Sel, Rab, Kam, Jum · toleransi 10 menit',
    );
  });

  test('attendance records parse server-calculated late minutes', () {
    final attendance = AttendanceRecord.fromJson({
      'id': 5,
      'work_date': '2026-11-02',
      'check_in_at': '2026-11-02T08:15:00Z',
      'late_minutes': 5,
    });

    expect(attendance.lateMinutes, 5);
  });
}
