import io

file_path = r'd:\development\tripromio\lib\data\services\chat_service.dart'
with io.open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

old_str = "  /// Fetch paginated message history"
new_str = """  /// Fetch a specific conversation by ID.
  Future<ConversationModel> getConversation(int conversationId) async {
    final response = await _client.get('${ApiConstants.conversations}/$conversationId');
    final data = response.dataAsMap;
    final convJson = data['conversation'] as Map<String, dynamic>?;
    if (convJson != null) {
      return ConversationModel.fromJson(convJson);
    }
    return ConversationModel.fromJson(data);
  }

  /// Fetch paginated message history"""

if old_str in content:
    content = content.replace(old_str, new_str)
    with io.open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS")
else:
    print("NOT FOUND")
