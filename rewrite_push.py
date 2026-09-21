import io

file_path = r'd:\development\tripromio\lib\data\services\push_notification_service.dart'

content = """import 'dart:async';
import 'dart:convert';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../core/constants/api_constants.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/storage/token_storage.dart';
import '../../routes/app_router.dart';
import '../../presentation/screens/connections/conversation_detail_screen.dart';
import 'chat_service.dart';

/// Top-level background message handler for FCM.
/// Must be outside of any class and annotated with @pragma('vm:entry-point').
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // OS handles showing the notification for background FCM messages.
  // We keep this empty and minimal to satisfy Firebase requirements.
  if (kDebugMode) {
    print('[FCM Background] Message received: ${message.messageId}');
  }
}

/// Handles Firebase Cloud Messaging (FCM) lifecycle for Tripromio.
class PushNotificationService {
  PushNotificationService._();

  static final PushNotificationService instance = PushNotificationService._();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  final ApiClient _apiClient = ApiClient();
  final TokenStorage _tokenStorage = TokenStorage();
  
  String? _currentToken;
  bool _isInitialised = false;
  
  /// Stores a conversation ID if navigation is attempted before auth is ready.
  static int? _pendingConversationId;

  // ── Initialization ────────────────────────────────────────────────────────

  Future<void> initialize() async {
    if (_isInitialised) return;

    try {
      // 1. Setup local notifications
      const androidInitSettings =
          AndroidInitializationSettings('@mipmap/launcher_icon');
      const initSettings = InitializationSettings(android: androidInitSettings);
      
      await _localNotifications.initialize(
        initSettings,
        onDidReceiveNotificationResponse: _onLocalNotificationTapped,
      );

      // Create Android Notification Channel
      const channel = AndroidNotificationChannel(
        'tripromio_chat_channel', // id
        'Chat Messages', // title
        description: 'Notifications for new chat messages',
        importance: Importance.high,
      );
      
      await _localNotifications
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(channel);

      // 2. Background handler
      FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

      // 3. Permission & Token
      await _requestPermission();
      await _fetchAndCacheToken();
      _listenTokenRefresh();

      // 4. Listeners
      _listenForegroundMessages();
      _setupTapHandlers();

      _isInitialised = true;
    } catch (e) {
      if (kDebugMode) {
        print('[FCM] Initialization error (non-fatal): $e');
      }
    }
  }

  // ── Sync / Register / Unregister ──────────────────────────────────────────

  Future<void> syncCurrentToken() async {
    if (_currentToken == null) {
      await _fetchAndCacheToken();
    } else {
      await _syncTokenIfAuthenticated(_currentToken!);
    }
  }

  Future<void> registerDeviceToken({
    required String token,
    required String platform,
  }) async {
    try {
      await _apiClient.post(
        ApiConstants.profileDeviceToken,
        body: {
          'fcm_token': token,
          'platform': platform,
        },
      );
      if (kDebugMode) {
        print('[FCM] Device token registered with backend (platform: $platform).');
      }
    } on ApiException catch (e) {
      if (kDebugMode) {
        print('[FCM] Device token registration failed (API error): $e');
      }
    } catch (e) {
      if (kDebugMode) {
        print('[FCM] Device token registration unexpected error (non-fatal): $e');
      }
    }
  }

  Future<void> unregisterCurrentToken() async {
    if (_currentToken != null) {
      await unregisterDeviceToken(token: _currentToken!);
    }
  }

  Future<void> unregisterDeviceToken({required String token}) async {
    try {
      await _apiClient.delete(
        ApiConstants.profileDeviceToken,
        body: {'fcm_token': token},
      );
      if (kDebugMode) {
        print('[FCM] Device token unregistered from backend.');
      }
    } on ApiException catch (e) {
      if (kDebugMode) {
        print('[FCM] Device token unregister failed (API error): $e');
      }
    } catch (e) {
      if (kDebugMode) {
        print('[FCM] Device token unregister unexpected error (non-fatal): $e');
      }
    }
  }

  // ── Permission & Token Retrieval ──────────────────────────────────────────

  Future<void> _requestPermission() async {
    try {
      await _messaging.requestPermission();
    } catch (e) {
      if (kDebugMode) {
        print('[FCM] Permission request error (non-fatal): $e');
      }
    }
  }

  Future<void> _fetchAndCacheToken() async {
    try {
      final token = await _messaging.getToken();
      if (token != null) {
        _currentToken = token;
        await _syncTokenIfAuthenticated(token);
      }
    } catch (e) {
      if (kDebugMode) {
        print('[FCM] Token fetch error (non-fatal): $e');
      }
    }
  }

  void _listenTokenRefresh() {
    _messaging.onTokenRefresh.listen(
      (newToken) {
        _currentToken = newToken;
        unawaited(_syncTokenIfAuthenticated(newToken));
      },
      onError: (Object e) {
        if (kDebugMode) {
          print('[FCM] Token refresh stream error (non-fatal): $e');
        }
      },
    );
  }

  // ── Foreground Messages ───────────────────────────────────────────────────

  void _listenForegroundMessages() {
    FirebaseMessaging.onMessage.listen(
      (RemoteMessage message) {
        final notification = message.notification;
        final data = message.data;

        if (notification != null && data['type'] == 'chat_message') {
          // Show local heads-up notification
          _localNotifications.show(
            notification.hashCode,
            notification.title,
            notification.body,
            const NotificationDetails(
              android: AndroidNotificationDetails(
                'tripromio_chat_channel',
                'Chat Messages',
                importance: Importance.high,
                priority: Priority.high,
              ),
            ),
            payload: jsonEncode(data),
          );
        }
      },
      onError: (Object e) {
        if (kDebugMode) {
          print('[FCM] Foreground message stream error (non-fatal): $e');
        }
      },
    );
  }

  // ── Navigation & Tap Handling ─────────────────────────────────────────────

  void _setupTapHandlers() {
    // 1. App in Background (tapped to open)
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      _handleNotificationData(message.data);
    });

    // 2. App Terminated (tapped to launch)
    _messaging.getInitialMessage().then((RemoteMessage? message) {
      if (message != null) {
        _handleNotificationData(message.data);
      }
    });
  }

  void _onLocalNotificationTapped(NotificationResponse response) {
    if (response.payload != null) {
      try {
        final data = jsonDecode(response.payload!) as Map<String, dynamic>;
        _handleNotificationData(data);
      } catch (e) {
        if (kDebugMode) {
          print('[FCM] Local notification payload parse error: $e');
        }
      }
    }
  }

  void _handleNotificationData(Map<String, dynamic> data) {
    if (data['type'] == 'chat_message') {
      final conversationIdStr = data['conversation_id'];
      if (conversationIdStr != null) {
        final conversationId = int.tryParse(conversationIdStr.toString());
        if (conversationId != null) {
          _navigateToConversation(conversationId);
        }
      }
    }
  }

  static Future<void> _navigateToConversation(int conversationId) async {
    try {
      final hasSession = await TokenStorage().hasToken();
      if (!hasSession || AppRouter.navigatorKey.currentContext == null) {
        // App is still bootstrapping or user is logged out.
        // Save pending ID to be consumed later.
        _pendingConversationId = conversationId;
        return;
      }

      final context = AppRouter.navigatorKey.currentContext!;
      final conversation = await ChatService().getConversation(conversationId);
      
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ConversationDetailScreen(conversation: conversation),
        ),
      );
    } catch (e) {
      if (kDebugMode) {
        print('[FCM] Navigation error: $e');
      }
    }
  }
  
  /// Called by Home or post-login sequence to consume any pending navigation.
  static void consumePendingNavigation() {
    if (_pendingConversationId != null) {
      final id = _pendingConversationId!;
      _pendingConversationId = null;
      _navigateToConversation(id);
    }
  }

  // ── Internal helpers ──────────────────────────────────────────────────────

  Future<void> _syncTokenIfAuthenticated(String token) async {
    try {
      final hasSession = await _tokenStorage.hasToken();
      if (!hasSession) return;
      await registerDeviceToken(token: token, platform: _currentPlatform);
    } catch (e) {
      // safe ignore
    }
  }

  String get _currentPlatform {
    switch (defaultTargetPlatform) {
      case TargetPlatform.iOS:
        return 'ios';
      case TargetPlatform.android:
      default:
        return 'android';
    }
  }
}
"""

with io.open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("SUCCESS")
