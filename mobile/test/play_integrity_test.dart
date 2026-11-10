import 'package:attendly_mobile/play_integrity.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('id.co.attendly.mobile/play_integrity');

  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  test('request hash binds the action and normalized coordinates', () {
    expect(
      PlayIntegrityClient.requestHash(7, 'check-in', -6.2, 106.8, null),
      '702q1skDK40ksCjRmQEN7IcXmvOR3C07Tg-PMr7i2aM',
    );
    expect(
      PlayIntegrityClient.requestHash(7, 'check-out', -6.2, 106.8, null),
      isNot(PlayIntegrityClient.requestHash(7, 'check-in', -6.2, 106.8, null)),
    );
  });

  test(
    'requests a standard Android token with the bound request hash',
    () async {
      debugDefaultTargetPlatformOverride = TargetPlatform.android;
      MethodCall? capturedCall;
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, (call) async {
            capturedCall = call;
            return 'opaque-google-token';
          });

      final token =
          await PlayIntegrityClient(
            channel: channel,
            cloudProjectNumber: '123456789012',
          ).createToken(
            userId: 7,
            action: 'check-in',
            latitude: -6.2,
            longitude: 106.8,
          );

      expect(token, 'opaque-google-token');
      expect(capturedCall?.method, 'requestToken');
      expect(capturedCall?.arguments, {
        'cloudProjectNumber': '123456789012',
        'requestHash': '702q1skDK40ksCjRmQEN7IcXmvOR3C07Tg-PMr7i2aM',
      });
    },
  );
}
