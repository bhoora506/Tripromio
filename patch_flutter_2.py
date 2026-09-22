import re
import sys

path = r'd:\development\tripromio\lib\presentation\screens\trips\trip_members_screen.dart'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix the two compilation errors
content = content.replace("final conv = await chatService.createConversation(widget.member.userId);", "final conv = await chatService.createConversation(recipientId: widget.member.userId);")

nav_push = """      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ConversationDetailScreen(conversation: conv),
        ),
      );"""

# Replace Navigator.pushNamed(...) with the correct push
content = re.sub(
    r"Navigator\.pushNamed\(\s*context,\s*AppRoutes\.conversationDetail,\s*arguments:\s*conv,\s*\);",
    nav_push,
    content,
    flags=re.DOTALL
)

# And make sure ConversationDetailScreen is imported
import_line = "import '../connections/conversation_detail_screen.dart';\n"
if "conversation_detail_screen.dart" not in content:
    content = import_line + content

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Patch 2 applied successfully.")
