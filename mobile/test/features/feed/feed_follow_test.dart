import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/features/feed/presentation/widgets/feed_action_rail.dart';

void main() {
  testWidgets('plus calls follow separately from avatar and check allows unfollow', (tester) async {
    var follows = 0;
    var avatars = 0;
    Widget rail(bool following) => MaterialApp(home: Scaffold(body: FeedActionRail(
      avatarUrl: null, likeCount: 0, commentCount: 0, isLiked: false,
      isBookmarked: false, onLike: () {}, onComment: () {}, onBookmark: () {},
      onShare: () {}, onMore: () {}, onAvatarTap: () => avatars++,
      onFollow: () => follows++, isFollowing: following,
    )));
    await tester.pumpWidget(rail(false));
    await tester.tap(find.byIcon(Icons.add));
    expect(follows, 1);
    expect(avatars, 0);
    await tester.pumpWidget(rail(true));
    await tester.tap(find.byIcon(Icons.check));
    expect(follows, 2);
  });
}
