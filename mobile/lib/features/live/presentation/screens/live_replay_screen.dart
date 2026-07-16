import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:livecommerce_mobile/features/live/presentation/providers/live_providers.dart';

class LiveReplayScreen extends ConsumerStatefulWidget {
  const LiveReplayScreen({super.key, required this.sessionId});

  final String sessionId;

  @override
  ConsumerState<LiveReplayScreen> createState() => _LiveReplayScreenState();
}

class _LiveReplayScreenState extends ConsumerState<LiveReplayScreen> {
  Timer? _tick;
  double _position = 0;
  bool _playing = false;

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  void _toggle(int duration) {
    if (_playing) {
      _tick?.cancel();
      setState(() => _playing = false);
      return;
    }

    setState(() => _playing = true);
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        if (_position >= duration) {
          _position = duration.toDouble();
          _playing = false;
          _tick?.cancel();
        } else {
          _position += 1;
        }
      });
    });
  }

  LiveTimelineProduct? _activeProduct(LiveSession session) {
    LiveTimelineProduct? current;
    for (final item in session.productTimeline) {
      if (item.offsetSeconds <= _position) {
        current = item;
      } else {
        break;
      }
    }
    return current;
  }

  String _fmt(int seconds) {
    final m = (seconds ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(liveReplayDetailProvider(widget.sessionId));

    return async.when(
      loading: () => const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      ),
      error: (error, _) => Scaffold(
        appBar: AppBar(title: const Text('Replay')),
        body: Center(child: Text('$error')),
      ),
      data: (session) {
        final duration = (session.durationSeconds ?? 60).clamp(1, 24 * 3600);
        final active = _activeProduct(session);
        final price = active == null
            ? null
            : NumberFormat('#,###').format(active.price.toInt());

        return Scaffold(
          backgroundColor: Colors.black,
          appBar: AppBar(
            backgroundColor: Colors.black,
            foregroundColor: Colors.white,
            title: Text(session.title),
          ),
          body: SafeArea(
            child: Column(
              children: [
                Expanded(
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      Container(
                        decoration: const BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              Color(0xFF1A1A2E),
                              Color(0xFF16213E),
                              Color(0xFF0F3460),
                            ],
                          ),
                        ),
                        child: Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                _playing ? Icons.pause_circle : Icons.play_circle,
                                color: Colors.white54,
                                size: 72,
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'Replay placeholder',
                                style: TextStyle(color: Colors.white70),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                session.replayUrl ?? 'No replay URL',
                                style: const TextStyle(
                                  color: Colors.white38,
                                  fontSize: 11,
                                ),
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),
                        ),
                      ),
                      if (active != null)
                        Positioned(
                          left: 12,
                          right: 12,
                          bottom: 24,
                          child: Material(
                            color: Colors.black54,
                            borderRadius: BorderRadius.circular(12),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap: () =>
                                  context.push('/products/${active.productId}'),
                              child: Padding(
                                padding: const EdgeInsets.all(12),
                                child: Row(
                                  children: [
                                    const Icon(
                                      Icons.shopping_bag,
                                      color: Colors.white70,
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            active.title,
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                          Text(
                                            '$price ${active.currency} · @ ${_fmt(active.offsetSeconds)}',
                                            style: const TextStyle(
                                              color: Color(0xFFFF6B6B),
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const Icon(
                                      Icons.chevron_right,
                                      color: Colors.white70,
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  child: Column(
                    children: [
                      if (session.productTimeline.isNotEmpty) ...[
                        SizedBox(
                          height: 36,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: session.productTimeline.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(width: 8),
                            itemBuilder: (context, index) {
                              final item = session.productTimeline[index];
                              return ActionChip(
                                label: Text(_fmt(item.offsetSeconds)),
                                onPressed: () {
                                  setState(
                                    () => _position = item.offsetSeconds.toDouble(),
                                  );
                                },
                              );
                            },
                          ),
                        ),
                        const SizedBox(height: 8),
                      ],
                      Row(
                        children: [
                          IconButton(
                            onPressed: () => _toggle(duration),
                            icon: Icon(
                              _playing ? Icons.pause : Icons.play_arrow,
                              color: Colors.white,
                            ),
                          ),
                          Text(
                            _fmt(_position.toInt()),
                            style: const TextStyle(color: Colors.white70),
                          ),
                          Expanded(
                            child: Slider(
                              value: _position.clamp(0, duration.toDouble()),
                              max: duration.toDouble(),
                              onChanged: (value) {
                                setState(() => _position = value);
                              },
                            ),
                          ),
                          Text(
                            _fmt(duration),
                            style: const TextStyle(color: Colors.white70),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
