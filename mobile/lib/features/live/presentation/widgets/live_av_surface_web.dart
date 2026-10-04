import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/video/browser_media_surface.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:livecommerce_mobile/features/live/presentation/widgets/live_hls_view.dart';
import 'package:livecommerce_mobile/features/live/presentation/widgets/live_obs_panel.dart';

Widget buildLiveAvSurface({required LiveSession session, required bool isHost}) {
  if (session.isRtmp && session.hlsUrl != null) {
    return isHost ? LiveObsHostPanel(session: session)
        : LiveHlsView(hlsUrl: session.hlsUrl!);
  }
  return BrowserMediaSurface(configuration: {
    'mode': 'agora',
    'appId': session.appId,
    'channel': session.channelId,
    'token': isHost ? session.publisherToken : session.subscriberToken,
    'isHost': isHost,
  });
}
