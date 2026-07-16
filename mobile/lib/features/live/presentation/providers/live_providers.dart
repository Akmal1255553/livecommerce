import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/live/data/live_repository.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';

final liveRepositoryProvider = Provider<LiveRepository>((ref) {
  ref.watch(authRepositoryProvider);
  final dio = ref.watch(authDioProvider);
  return LiveRepository(remote: LiveRemoteDataSource(dio));
});

class LiveListState {
  const LiveListState({
    this.sessions = const [],
    this.isLoading = false,
    this.error,
  });

  final List<LiveSession> sessions;
  final bool isLoading;
  final String? error;

  LiveListState copyWith({
    List<LiveSession>? sessions,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return LiveListState(
      sessions: sessions ?? this.sessions,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class LiveListNotifier extends StateNotifier<LiveListState> {
  LiveListNotifier(this._repository) : super(const LiveListState());

  final LiveRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final sessions = await _repository.listLive();
      state = state.copyWith(sessions: sessions, isLoading: false);
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }
}

final liveListProvider =
    StateNotifierProvider<LiveListNotifier, LiveListState>((ref) {
  return LiveListNotifier(ref.watch(liveRepositoryProvider));
});

class LiveReplayListNotifier extends StateNotifier<LiveListState> {
  LiveReplayListNotifier(this._repository) : super(const LiveListState());

  final LiveRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final sessions = await _repository.listReplays();
      state = state.copyWith(sessions: sessions, isLoading: false);
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }
}

final liveReplayListProvider =
    StateNotifierProvider<LiveReplayListNotifier, LiveListState>((ref) {
  return LiveReplayListNotifier(ref.watch(liveRepositoryProvider));
});

final liveReplayDetailProvider =
    FutureProvider.family<LiveSession, String>((ref, id) async {
  return ref.watch(liveRepositoryProvider).get(id);
});

class LiveRoomState {
  const LiveRoomState({
    this.session,
    this.messages = const [],
    this.isLoading = false,
    this.isSending = false,
    this.isAddingToCart = false,
    this.isHost = false,
    this.error,
  });

  final LiveSession? session;
  final List<LiveChatMessage> messages;
  final bool isLoading;
  final bool isSending;
  final bool isAddingToCart;
  final bool isHost;
  final String? error;

  LiveRoomState copyWith({
    LiveSession? session,
    List<LiveChatMessage>? messages,
    bool? isLoading,
    bool? isSending,
    bool? isAddingToCart,
    bool? isHost,
    String? error,
    bool clearError = false,
  }) {
    return LiveRoomState(
      session: session ?? this.session,
      messages: messages ?? this.messages,
      isLoading: isLoading ?? this.isLoading,
      isSending: isSending ?? this.isSending,
      isAddingToCart: isAddingToCart ?? this.isAddingToCart,
      isHost: isHost ?? this.isHost,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class LiveRoomNotifier extends StateNotifier<LiveRoomState> {
  LiveRoomNotifier(
    this._repository,
    this._sessionId,
    this._currentUserId,
    this._onCartSynced,
  ) : super(const LiveRoomState());

  final LiveRepository _repository;
  final String _sessionId;
  final String? _currentUserId;
  final Future<void> Function()? _onCartSynced;
  Timer? _pollTimer;
  bool _joined = false;

  Future<void> enter({bool asHost = false}) async {
    state = state.copyWith(isLoading: true, clearError: true, isHost: asHost);
    try {
      final session = await _repository.get(_sessionId);
      final isHost = asHost ||
          (_currentUserId != null &&
              session.seller?.id == _currentUserId &&
              session.publisherToken != null);

      if (!isHost && !_joined) {
        await _repository.join(_sessionId);
        _joined = true;
      }

      final messages = await _repository.chat(_sessionId);
      state = state.copyWith(
        session: session,
        messages: messages,
        isLoading: false,
        isHost: isHost,
      );
      _startPolling();
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) async {
      try {
        final session = await _repository.get(_sessionId);
        final afterId =
            state.messages.isEmpty ? null : state.messages.last.id;
        final newer = await _repository.chat(_sessionId, afterId: afterId);
        final merged = [...state.messages, ...newer];
        state = state.copyWith(session: session, messages: merged);
      } catch (_) {
        // Keep room open on transient poll errors.
      }
    });
  }

  Future<bool> sendChat(String message) async {
    final text = message.trim();
    if (text.isEmpty) {
      return false;
    }
    state = state.copyWith(isSending: true, clearError: true);
    try {
      final msg = await _repository.sendChat(_sessionId, text);
      state = state.copyWith(
        messages: [...state.messages, msg],
        isSending: false,
      );
      return true;
    } catch (error) {
      state = state.copyWith(isSending: false, error: describeFailure(error));
      return false;
    }
  }

  Future<bool> pinProduct(String productId) async {
    try {
      final session = await _repository.pinProduct(_sessionId, productId);
      state = state.copyWith(session: session, clearError: true);
      return true;
    } catch (error) {
      state = state.copyWith(error: describeFailure(error));
      return false;
    }
  }

  Future<List<LiveAssistantSuggestion>> loadAssistantSuggestions() async {
    try {
      final result = await _repository.assistantSuggestions(_sessionId);
      return result.suggestions;
    } catch (error) {
      state = state.copyWith(error: describeFailure(error));
      return const [];
    }
  }

  Future<bool> addPinnedToCart(String productId) async {
    state = state.copyWith(isAddingToCart: true, clearError: true);
    try {
      final result = await _repository.addToCart(_sessionId, productId);
      state = state.copyWith(
        messages: [...state.messages, result.chatMessage],
        isAddingToCart: false,
      );
      final sync = _onCartSynced;
      if (sync != null) {
        await sync();
      }
      return true;
    } catch (error) {
      state = state.copyWith(
        isAddingToCart: false,
        error: describeFailure(error),
      );
      return false;
    }
  }

  Future<bool> endLive() async {
    try {
      final session = await _repository.end(_sessionId);
      state = state.copyWith(session: session, clearError: true);
      await leave();
      return true;
    } catch (error) {
      state = state.copyWith(error: describeFailure(error));
      return false;
    }
  }

  Future<void> leave() async {
    _pollTimer?.cancel();
    _pollTimer = null;
    if (_joined) {
      try {
        await _repository.leave(_sessionId);
      } catch (_) {}
      _joined = false;
    }
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }
}

final liveRoomProvider = StateNotifierProvider.autoDispose
    .family<LiveRoomNotifier, LiveRoomState, String>((ref, sessionId) {
  final userId = ref.watch(authNotifierProvider).user?.id;
  final notifier = LiveRoomNotifier(
    ref.watch(liveRepositoryProvider),
    sessionId,
    userId,
    () => ref.read(cartNotifierProvider.notifier).load(),
  );
  ref.onDispose(() {
    unawaited(notifier.leave());
  });
  return notifier;
});
