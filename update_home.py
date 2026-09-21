import io

file_path = r'd:\development\tripromio\lib\presentation\screens\home\home_screen.dart'
with io.open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

import_str = "import '../../../data/services/trip_service.dart';"
new_import_str = "import '../../../data/services/trip_service.dart';\nimport '../../../data/services/push_notification_service.dart';"
content = content.replace(import_str, new_import_str)

init_str = """  @override
  void initState() {"""
new_init_str = """  @override
  void initState() {
    // Consume pending push notification navigation if any
    PushNotificationService.consumePendingNavigation();"""

content = content.replace(init_str, new_init_str)

with io.open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("SUCCESS")
