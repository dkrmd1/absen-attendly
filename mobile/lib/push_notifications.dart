import 'dart:async';
import 'dart:convert';

import 'package:attendly_mobile/attendly_api.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class AttendlyPushNotifications {
  AttendlyPushNotifications(this._api);

  static const _channel = AndroidNotificationChannel(
    'attendly_updates',
    'Notifikasi Attendly',
    description: 'Pembaruan pengajuan dan aktivitas Attendly.',
    importance: Importance.high,
  );

  final AttendlyApi _api;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();
  final StreamController<Map<String, dynamic>> _openedNotifications =
      StreamController<Map<String, dynamic>>.broadcast();
  final List<StreamSubscription<dynamic>> _subscriptions = [];
  FirebaseMessaging? _messaging;
  Future<void>? _initialization;
  bool _initialized = false;

  Stream<Map<String, dynamic>> get openedNotifications =>
      _openedNotifications.stream;

  Future<void> initialize() {
    if (_initialized) return Future<void>.value();
    final pending = _initialization;
    if (pending != null) return pending;

    final initialization = _initialize();
    _initialization = initialization;
    return initialization.catchError((Object error) {
      _initialization = null;
      throw error;
    });
  }

  Future<void> _initialize() async {
    if (kIsWeb ||
        (defaultTargetPlatform != TargetPlatform.android &&
            defaultTargetPlatform != TargetPlatform.iOS)) {
      return;
    }

    final config = await _api.pushConfig();
    if (config == null) return;

    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(
        options: FirebaseOptions(
          apiKey: config.apiKey,
          appId: config.appId,
          messagingSenderId: config.messagingSenderId,
          projectId: config.projectId,
        ),
      );
    }

    _messaging = FirebaseMessaging.instance;
    await _localNotifications.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
        ),
      ),
      onDidReceiveNotificationResponse: _handleLocalNotificationTap,
    );
    await _localNotifications
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.createNotificationChannel(_channel);

    await _messaging!.requestPermission(alert: true, badge: true, sound: true);
    _subscriptions.add(FirebaseMessaging.onMessage.listen(_showForeground));
    _subscriptions.add(
      FirebaseMessaging.onMessageOpenedApp.listen(_handleRemoteNotificationTap),
    );
    _subscriptions.add(
      // The Admin SDK needs an FCM registration token, not the new API's FID.
      // ignore: deprecated_member_use
      _messaging!.onTokenRefresh.listen((token) {
        unawaited(
          _registerToken(token).catchError((Object error) {
            debugPrint('Attendly FCM device registration failed: $error');
          }),
        );
      }),
    );
    // The Admin SDK needs an FCM registration token, not the new API's FID.
    // ignore: deprecated_member_use
    final token = await _messaging!.getToken();
    if (token != null) {
      await _registerToken(token);
    }

    final initialMessage = await _messaging!.getInitialMessage();
    if (initialMessage != null) {
      _handleRemoteNotificationTap(initialMessage);
    }
    _initialized = true;
  }

  Future<void> registerDevice() async {
    await initialize();
    if (!_initialized) return;
    final token = await _registeredToken();
    await _registerToken(token);
  }

  Future<void> unregisterDevice() async {
    await initialize();
    if (!_initialized) return;
    final token = await _registeredToken();
    await _api.unregisterPushDevice(token);
  }

  Future<void> dispose() async {
    for (final subscription in _subscriptions) {
      await subscription.cancel();
    }
    await _openedNotifications.close();
  }

  Future<void> _registerToken(String token) async {
    if (await _api.readToken() == null) return;
    await _api.registerPushDevice(token);
  }

  Future<String> _registeredToken() async {
    // The Admin SDK needs the FCM registration token; onRegistered emits an FID.
    // ignore: deprecated_member_use
    final token = await _messaging!.getToken();
    if (token == null || token.isEmpty) {
      throw StateError(
        'Firebase belum memberikan token FCM untuk perangkat ini.',
      );
    }
    return token;
  }

  Future<void> _showForeground(RemoteMessage message) async {
    final title =
        message.notification?.title ?? message.data['title']?.toString();
    final body =
        message.notification?.body ?? message.data['message']?.toString();
    if (title == null || body == null) return;

    await _localNotifications.show(
      id: DateTime.now().millisecondsSinceEpoch.remainder(1 << 31),
      title: title,
      body: body,
      notificationDetails: const NotificationDetails(
        android: AndroidNotificationDetails(
          'attendly_updates',
          'Notifikasi Attendly',
          channelDescription: 'Pembaruan pengajuan dan aktivitas Attendly.',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(),
      ),
      payload: jsonEncode(message.data),
    );
  }

  void _handleRemoteNotificationTap(RemoteMessage message) {
    _openedNotifications.add(Map<String, dynamic>.from(message.data));
  }

  void _handleLocalNotificationTap(NotificationResponse response) {
    final payload = response.payload;
    if (payload == null || payload.isEmpty) return;
    final decoded = jsonDecode(payload);
    if (decoded is Map<String, dynamic>) {
      _openedNotifications.add(decoded);
    }
  }
}
