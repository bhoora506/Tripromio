import io

file_path = r'd:\laragon\www\Tripromio\app\Services\FCMService.php'
with io.open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('private function getAccessToken()', 'protected function getAccessToken()')
content = content.replace('private function handleFCMError', 'protected function handleFCMError')

with io.open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("SUCCESS")
