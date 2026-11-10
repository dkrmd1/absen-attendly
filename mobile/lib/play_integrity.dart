import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import 'attendly_api.dart';

class PlayIntegrityClient {
  const PlayIntegrityClient({
    MethodChannel channel = const MethodChannel(
      'id.co.attendly.mobile/play_integrity',
    ),
    String cloudProjectNumber = const String.fromEnvironment(
      'PLAY_INTEGRITY_CLOUD_PROJECT_NUMBER',
    ),
  }) : _channel = channel,
       _cloudProjectNumber = cloudProjectNumber;

  final MethodChannel _channel;
  final String _cloudProjectNumber;

  Future<String> createToken({
    required int userId,
    required String action,
    double? latitude,
    double? longitude,
    XFile? selfie,
  }) async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      throw const ApiException(
        'Play Integrity hanya tersedia pada aplikasi Android yang terdaftar di Google Play.',
      );
    }

    if (_cloudProjectNumber.isEmpty) {
      throw const ApiException(
        'Nomor Google Cloud Project untuk Play Integrity belum dikonfigurasi.',
      );
    }

    try {
      final token = await _channel.invokeMethod<String>('requestToken', {
        'cloudProjectNumber': _cloudProjectNumber,
        'requestHash': requestHash(
          userId,
          action,
          latitude,
          longitude,
          selfie == null
              ? null
              : sha256.convert(await selfie.readAsBytes()).toString(),
        ),
      });
      if (token == null || token.isEmpty) {
        throw const ApiException(
          'Google Play tidak memberikan token integritas.',
        );
      }

      return token;
    } on PlatformException catch (error) {
      throw ApiException(
        error.message ?? 'Pemeriksaan integritas Google Play gagal.',
      );
    }
  }

  static String requestHash(
    int userId,
    String action,
    double? latitude,
    double? longitude,
    String? selfieHash,
  ) {
    String coordinate(double? value) =>
        value == null ? '-' : value.toStringAsFixed(7);
    final request = [
      action,
      userId.toString(),
      coordinate(latitude),
      coordinate(longitude),
      selfieHash ?? '-',
    ].join('|');

    return base64Url
        .encode(sha256.convert(utf8.encode(request)).bytes)
        .replaceAll('=', '');
  }
}
