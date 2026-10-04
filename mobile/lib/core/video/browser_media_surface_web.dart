import 'dart:convert';
import 'dart:js_interop';

import 'package:flutter/widgets.dart';
import 'package:web/web.dart' as web;

/// Same-origin SDK frame. Credentials travel in messages, never URLs or logs.
class BrowserMediaSurface extends StatefulWidget {
  const BrowserMediaSurface({super.key, required this.configuration,
    this.active = true, this.muted = true, this.interactive = true});
  final Map<String, Object?> configuration;
  final bool active;
  final bool muted;
  final bool interactive;

  @override
  State<BrowserMediaSurface> createState() => _BrowserMediaSurfaceState();
}

class _BrowserMediaSurfaceState extends State<BrowserMediaSurface> {
  web.HTMLIFrameElement? _frame;
  late final JSFunction _listener;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _listener = ((web.MessageEvent event) {
      if (event.origin != web.window.location.origin ||
          event.source != _frame?.contentWindow) {
        return;
      }
      final data = event.data.dartify();
      if (data is Map && data['type'] == 'lc-media-ready') {
        _ready = true;
        _send('init', widget.configuration);
        _sync();
      }
    }).toJS;
    web.window.addEventListener('message', _listener);
  }

  void _send(String type, Map<String, Object?> data) {
    _frame?.contentWindow?.postMessage(
      {'type': type, ...data}.jsify(), web.window.location.origin.toJS);
  }

  void _sync() => _send('playback', {
    'active': widget.active, 'muted': widget.muted,
  });

  @override
  void didUpdateWidget(covariant BrowserMediaSurface oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!_ready) return;
    if (jsonEncode(oldWidget.configuration) != jsonEncode(widget.configuration)) {
      _send('init', widget.configuration);
    }
    _sync();
  }

  @override
  void dispose() {
    _send('dispose', {});
    web.window.removeEventListener('message', _listener);
    _frame?.remove();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => HtmlElementView.fromTagName(
    tagName: 'iframe',
    onElementCreated: (element) {
      final frame = element as web.HTMLIFrameElement;
      _frame = frame;
      frame
        ..title = 'LiveCommerce video'
        ..allow = 'camera; microphone; autoplay; fullscreen'
        ..referrerPolicy = 'no-referrer'
        ..src = Uri.parse(web.document.baseURI).resolve('media_player.html').toString();
      frame.style
        ..border = '0'
        ..width = '100%'
        ..height = '100%'
        ..pointerEvents = widget.interactive ? 'auto' : 'none';
    },
  );
}
