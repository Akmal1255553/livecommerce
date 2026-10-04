import 'package:flutter/widgets.dart';

class BrowserMediaSurface extends StatelessWidget {
  const BrowserMediaSurface({super.key, required this.configuration,
    this.active = true, this.muted = true, this.interactive = true});
  final Map<String, Object?> configuration;
  final bool active;
  final bool muted;
  final bool interactive;

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}
