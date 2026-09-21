import io

file_path = r'd:\development\tripromio\lib\data\services\push_notification_service.dart'
with io.open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix initialize
old_init = """      await _localNotifications.initialize(
        initSettings,
        onDidReceiveNotificationResponse: _onLocalNotificationTapped,
      );"""
new_init = """      await _localNotifications.initialize(
        initializationSettings: initSettings,
        onDidReceiveNotificationResponse: _onLocalNotificationTapped,
      );"""
content = content.replace(old_init, new_init)

# Check if it was `initializationSettings` or `settings`? The error said `settings`?
# Wait, let me just try `initializationSettings: initSettings`

# Fix show
old_show = """          _localNotifications.show(
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
          );"""
new_show = """          _localNotifications.show(
            id: notification.hashCode,
            title: notification.title,
            body: notification.body,
            notificationDetails: const NotificationDetails(
              android: AndroidNotificationDetails(
                'tripromio_chat_channel',
                'Chat Messages',
                importance: Importance.high,
                priority: Priority.high,
              ),
            ),
            payload: jsonEncode(data),
          );"""
content = content.replace(old_show, new_show)

# Fix mounted context
old_nav = """      final context = AppRouter.navigatorKey.currentContext!;
      final conversation = await ChatService().getConversation(conversationId);
      
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ConversationDetailScreen(conversation: conversation),
        ),
      );"""
new_nav = """      final conversation = await ChatService().getConversation(conversationId);
      
      final context = AppRouter.navigatorKey.currentContext;
      if (context != null && context.mounted) {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ConversationDetailScreen(conversation: conversation),
          ),
        );
      }"""
content = content.replace(old_nav, new_nav)

with io.open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("SUCCESS")
