import re
import sys

path = r'd:\development\tripromio\lib\presentation\screens\trips\trip_members_screen.dart'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Imports
import_replacement = """import '../../../core/theme/app_colors.dart';
import '../../../data/models/trip_member_model.dart';
import '../../../data/services/trip_service.dart';
import '../../../data/services/chat_service.dart';
import '../../../data/services/profile_service.dart';
import '../../../routes/app_routes.dart';"""
content = re.sub(
    r"import '../../../core/theme/app_colors\.dart';\s*import '../../../data/models/trip_member_model\.dart';\s*import '../../../data/services/trip_service\.dart';",
    import_replacement,
    content
)

# 2. Add profile service & current user ID
state_vars = """class _TripMembersScreenState extends State<TripMembersScreen> {
  final _tripService = TripService();
  final _profileService = ProfileService();

  int? _tripId;
  int? _currentUserId;"""
content = re.sub(
    r"class _TripMembersScreenState extends State<TripMembersScreen> \{\s*final _tripService = TripService\(\);\s*int\? _tripId;",
    state_vars,
    content
)

# 3. Add _loadCurrentUser
load_func = """
  Future<void> _loadCurrentUser() async {
    try {
      final user = await _profileService.getProfile();
      if (mounted) setState(() => _currentUserId = user.id);
    } catch (_) {}
  }

  @override
  void didChangeDependencies() {"""
content = content.replace("  @override\n  void didChangeDependencies() {", load_func)

init_call = """    if (!_initialized) {
      _initialized = true;
      _loadCurrentUser();"""
content = content.replace("    if (!_initialized) {\n      _initialized = true;", init_call)

# 4. Dispose
dispose_repl = """  @override
  void dispose() {
    _tripService.dispose();
    _profileService.dispose();
    super.dispose();
  }"""
content = re.sub(
    r"  @override\s*void dispose\(\) \{\s*_tripService\.dispose\(\);\s*super\.dispose\(\);\s*\}",
    dispose_repl,
    content
)

# 5. Build _MemberCard
build_card = """        itemBuilder: (context, index) {
          final member = _members[index];
          return _MemberCard(
            member: member,
            currentUserId: _currentUserId,
          );
        },"""
content = re.sub(
    r"        itemBuilder: \(context, index\) \{\s*final member = _members\[index\];\s*return _MemberCard\(member: member\);\s*\},",
    build_card,
    content
)

# 6. Replace _MemberCard entirely
member_card_pattern = r"class _MemberCard extends StatelessWidget \{.*?(?=// ── Avatar initials ──)"
new_card = """class _MemberCard extends StatefulWidget {
  const _MemberCard({required this.member, this.currentUserId});
  final TripMemberModel member;
  final int? currentUserId;

  @override
  State<_MemberCard> createState() => _MemberCardState();
}

class _MemberCardState extends State<_MemberCard> {
  bool _startingChat = false;

  Future<void> _startChat() async {
    if (widget.member.userId == widget.currentUserId) return;
    setState(() => _startingChat = true);
    try {
      final chatService = ChatService();
      final conv = await chatService.createConversation(widget.member.userId);
      if (!mounted) return;
      Navigator.pushNamed(
        context,
        AppRoutes.conversationDetail,
        arguments: conv,
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    } finally {
      if (mounted) setState(() => _startingChat = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final member = widget.member;
    final bool isOwner = member.role.toLowerCase() == 'owner';
    final bool canMessage = widget.currentUserId != null &&
        member.userId != widget.currentUserId &&
        member.status.toLowerCase() == 'active';

    return Container(
      margin: const EdgeInsets.only(bottom: AppConstants.spacingSm),
      padding: const EdgeInsets.all(AppConstants.spacingMd),
      decoration: BoxDecoration(
        color: AppColors.surfaceLight,
        borderRadius: BorderRadius.circular(AppConstants.radiusLg),
        border: Border.all(color: AppColors.borderLight),
      ),
      child: Row(
        children: [
          // Avatar
          _Initials(name: member.userName, seed: member.userId),
          const SizedBox(width: AppConstants.spacingMd),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  member.userName,
                  style: GoogleFonts.nunito(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimaryLight,
                  ),
                ),
                if (member.joinedAt != null && !isOwner) ...[
                  const SizedBox(height: 2),
                  Text(
                    'Joined ${_formatDate(member.joinedAt!)}',
                    style: GoogleFonts.nunito(
                      fontSize: 12,
                      color: AppColors.textSecondaryLight,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (canMessage)
            _startingChat
                ? const Padding(
                    padding: EdgeInsets.only(right: 12),
                    child: SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : IconButton(
                    icon: const Icon(Icons.chat_bubble_outline_rounded),
                    color: AppColors.primary,
                    onPressed: _startChat,
                    tooltip: 'Message',
                  ),
          _RoleBadge(role: member.role),
        ],
      ),
    );
  }

  String _formatDate(DateTime date) {
    final months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }
}

"""
content = re.sub(member_card_pattern, new_card, content, flags=re.DOTALL)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Patch applied successfully.")
