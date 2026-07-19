import 'package:flutter/widgets.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';

import 'live_av_surface_stub.dart'
    if (dart.library.io) 'live_av_surface_io.dart' as impl;

/// Host/viewer live A/V surface. Native (Android/iOS) uses Agora RTC;
/// web/desktop show a degraded placeholder.
class LiveAvSurface extends StatelessWidget {
  const LiveAvSurface({
    super.key,
    required this.session,
    required this.isHost,
  });

  final LiveSession session;
  final bool isHost;

  @override
  Widget build(BuildContext context) {
    return impl.buildLiveAvSurface(
      session: session,
      isHost: isHost,
    );
  }
}
