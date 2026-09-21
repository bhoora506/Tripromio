import re

file_path = r'd:\development\tripromio\lib\presentation\screens\home\home_screen.dart'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update _HomeBodyState to include UserModel and fetch it
# We need to add `import '../../../data/models/user_model.dart';` if not there.
if "user_model.dart" not in content:
    content = content.replace("import '../../../data/models/profile_stats_model.dart';", "import '../../../data/models/profile_stats_model.dart';\nimport '../../../data/models/user_model.dart';")

content = content.replace("ProfileStatsModel? _stats;\n  bool _loadingStats = true;", "UserModel? _user;\n  ProfileStatsModel? _stats;\n  bool _loadingStats = true;")

load_stats_new = """
  Future<void> _loadStats() async {
    setState(() => _loadingStats = true);
    try {
      final user = await AuthService().getCurrentUser();
      final stats = await _profileService.getStats();
      if (!mounted) return;
      setState(() {
        _user = user;
        _stats = stats;
        _loadingStats = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadingStats = false);
    }
  }

  Future<void> _loadTrips() async {"""
content = re.sub(r"Future<void> _loadStats\(\) async \{.*?\n  Future<void> _loadTrips\(\) async \{", load_stats_new.strip() + " {", content, flags=re.DOTALL)

# Pass user to _HomeHeader
content = content.replace("_HomeHeader(stats: _stats, loadingStats: _loadingStats),", "_HomeHeader(user: _user, stats: _stats, loadingStats: _loadingStats),")

# Update _HomeHeader constructor
content = content.replace("class _HomeHeader extends StatelessWidget {\n  const _HomeHeader({this.stats, this.loadingStats = true});\n  final ProfileStatsModel? stats;\n  final bool loadingStats;", """class _HomeHeader extends StatelessWidget {
  const _HomeHeader({this.user, this.stats, this.loadingStats = true});
  final UserModel? user;
  final ProfileStatsModel? stats;
  final bool loadingStats;""")

# Update greeting
greeting_old = "AuthService().currentUser"
content = content.replace(greeting_old, "user")

# Update _UserAvatar
avatar_old = "final user = AuthService().currentUser;"
avatar_new = "final user = this.user;"
content = content.replace(avatar_old, avatar_new)

# Add user to _UserAvatar constructor inside _HomeHeader
content = content.replace("const _UserAvatar()", "_UserAvatar(user: user)")

# Update _UserAvatar definition
avatar_class_old = """class _UserAvatar extends StatelessWidget {
  const _UserAvatar();"""
avatar_class_new = """class _UserAvatar extends StatelessWidget {
  const _UserAvatar({this.user});
  final UserModel? user;"""
content = content.replace(avatar_class_old, avatar_class_new)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("HomeScreen fixed successfully.")
