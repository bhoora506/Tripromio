import io

file_path = r'd:\development\tripromio\lib\data\services\push_notification_service.dart'
with io.open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

old_init = "initializationSettings: initSettings"
new_init = "settings: initSettings"
content = content.replace(old_init, new_init)

with io.open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("SUCCESS")
