import os
import sys

file_path = r'd:\development\tripromio\lib\presentation\screens\home\home_screen.dart'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read().replace('\r\n', '\n')

def repl(old, new, max_replace=1):
    global content
    old = old.replace('\r\n', '\n')
    if old not in content:
        raise ValueError(f"FAILED TO FIND chunk:\n{old}")
    content = content.replace(old, new, max_replace)

# 1. Imports
imports_old = "import '../../../data/services/trip_service.dart';"
imports_new = """import '../../../data/services/trip_service.dart';
import '../../../data/services/auth_service.dart';
import '../../../data/services/profile_service.dart';
import '../../../data/models/profile_stats_model.dart';
import '../../../data/models/user_model.dart';"""
try:
    repl(imports_old, imports_new)
except ValueError:
    pass

# 2. _HomeBodyState vars
state_old = """class _HomeBodyState extends State<_HomeBody> {
  final _tripService = TripService();"""
state_new = """class _HomeBodyState extends State<_HomeBody> {
  final _tripService = TripService();
  final _profileService = ProfileService();
  UserModel? _user;
  ProfileStatsModel? _stats;
  bool _loadingStats = true;"""
try:
    repl(state_old, state_new)
except ValueError:
    pass

# 3. dispose
dispose_old = """  @override
  void dispose() {
    _tripService.dispose();
    super.dispose();
  }"""
dispose_new = """  @override
  void dispose() {
    _profileService.dispose();
    _tripService.dispose();
    super.dispose();
  }"""
try:
    repl(dispose_old, dispose_new)
except ValueError:
    pass

# 4. loadTrips -> loadStats
load_trips_old = """  Future<void> _loadTrips() async {"""
load_trips_new = """  Future<void> _loadStats() async {
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
try:
    repl(load_trips_old, load_trips_new)
except ValueError:
    pass

# 5. _HomeHeader call
header_call_old = """const _HomeHeader(),"""
header_call_new = """_HomeHeader(user: _user, stats: _stats, loadingStats: _loadingStats),"""
try:
    repl(header_call_old, header_call_new)
except ValueError:
    pass

# 6. _HomeHeader constructor
header_class_old = """class _HomeHeader extends StatelessWidget {
  const _HomeHeader();"""
header_class_new = """class _HomeHeader extends StatelessWidget {
  const _HomeHeader({super.key, this.user, this.stats, this.loadingStats = true});
  final UserModel? user;
  final ProfileStatsModel? stats;
  final bool loadingStats;"""
try:
    repl(header_class_old, header_class_new)
except ValueError:
    pass

# 7. Greeting
greeting_old = """Text(
                      'Hey, Arjun! ðŸ‘‹',"""
greeting_new = """Text(
                      'Hey, ${user?.name.isNotEmpty == true ? user!.name : 'there'}! 👋',"""
try:
    repl(greeting_old, greeting_new)
except ValueError:
    greeting_old_2 = """Text(
                      'Hey, Arjun! 👋',"""
    try:
        repl(greeting_old_2, greeting_new)
    except ValueError:
        pass

# 8. Stats Row
stats_row_old = """Row(
            children: const [
              _StatBadge(value: '12', label: 'Trips'),
              SizedBox(width: AppConstants.spacingMd),
              _StatBadge(value: '28', label: 'Reviews'),
              SizedBox(width: AppConstants.spacingMd),
              _StatBadge(value: '4.8 â˜…', label: 'Rating'),
            ],
          )"""
stats_row_new = """Row(
            children: [
              if (loadingStats) ...[
                const _StatSkeleton(),
                const SizedBox(width: AppConstants.spacingMd),
                const _StatSkeleton(),
              ] else ...[
                _StatBadge(value: '${stats?.tripsCount ?? 0}', label: 'Trips'),
                const SizedBox(width: AppConstants.spacingMd),
                _StatBadge(value: '${stats?.connectionsCount ?? 0}', label: 'Connections'),
              ],
            ],
          )"""
try:
    repl(stats_row_old, stats_row_new)
except ValueError:
    stats_row_old_2 = """Row(
            children: const [
              _StatBadge(value: '12', label: 'Trips'),
              SizedBox(width: AppConstants.spacingMd),
              _StatBadge(value: '28', label: 'Reviews'),
              SizedBox(width: AppConstants.spacingMd),
              _StatBadge(value: '4.8 ★', label: 'Rating'),
            ],
          )"""
    try:
        repl(stats_row_old_2, stats_row_new)
    except ValueError:
        pass

# 9. Notification badge
badge_old = """badgeCount: 3,"""
badge_new = """badgeCount: 0,"""
try:
    repl(badge_old, badge_new, 1)
except ValueError:
    pass

# 10. UserAvatar call
avatar_call_old = """_UserAvatar(),"""
avatar_call_new = """_UserAvatar(user: user),"""
try:
    repl(avatar_call_old, avatar_call_new)
except ValueError:
    pass

# 11. UserAvatar class
avatar_class_old = """class _UserAvatar extends StatelessWidget {
  const _UserAvatar();"""
avatar_class_new = """class _UserAvatar extends StatelessWidget {
  const _UserAvatar({super.key, this.user});
  final UserModel? user;"""
try:
    repl(avatar_class_old, avatar_class_new)
except ValueError:
    pass

avatar_build_old = """  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(
          colors: [Color(0xFF5B6CF8), Color(0xFF8B9CF8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.30),
          width: 2,
        ),
      ),
      child: const Center(
        child: Text(
          'A',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: Colors.white,
          ),
        ),
      ),
    );
  }"""
avatar_build_new = """  @override
  Widget build(BuildContext context) {
    final photoUrl = user?.profile?.profilePhotoUrl;
    final initial = user?.name.isNotEmpty == true ? user!.name[0].toUpperCase() : 'U';

    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(
          colors: [Color(0xFF5B6CF8), Color(0xFF8B9CF8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.30),
          width: 2,
        ),
        image: photoUrl != null
            ? DecorationImage(
                image: NetworkImage(photoUrl),
                fit: BoxFit.cover,
              )
            : null,
      ),
      child: photoUrl == null
          ? Center(
              child: Text(
                initial,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                ),
              ),
            )
          : null,
    );
  }"""
try:
    repl(avatar_build_old, avatar_build_new)
except ValueError:
    pass

# 12. Skeleton class
stat_skeleton = """
class _StatSkeleton extends StatelessWidget {
  const _StatSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 70,
      height: 50,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(AppConstants.radiusMd),
      ),
    );
  }
}
"""
if "_StatSkeleton" not in content:
    content += stat_skeleton

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

sys.stdout.write("DONE\n")
