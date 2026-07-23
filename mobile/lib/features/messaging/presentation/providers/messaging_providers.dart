import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/messaging/data/messaging_repository.dart';
import 'package:livecommerce_mobile/features/messaging/domain/entities/conversation.dart';

// ── Conversations list ──────────────────────────────────────────────────────

class ConversationsState {
  const ConversationsState({
    this.conversations = const [],
    this.isLoading = false,
    this.error,
    this.nextCursor,
    this.hasMore = false,
  });

  final List<Conversation> conversations;
  final bool isLoading;
  final String? error;
  final String? nextCursor;
  final bool hasMore;

  ConversationsState copyWith({
    List<Conversation>? conversations,
    bool? isLoading,
    String? error,
    bool clearError = false,
    String? nextCursor,
    bool? hasMore,
  }) {
    return ConversationsState(
      conversations: conversations ?? this.conversations,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      nextCursor: nextCursor ?? this.nextCursor,
      hasMore: hasMore ?? this.hasMore,
    );
  }
}

class ConversationsNotifier extends StateNotifier<ConversationsState> {
  ConversationsNotifier(this._repo) : super(const ConversationsState());

  final MessagingRepository _repo;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final page = await _repo.fetchConversations();
      state = state.copyWith(
        conversations: page.conversations,
        isLoading: false,
        nextCursor: page.nextCursor,
        hasMore: page.hasMore,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => load();

  Future<Conversation> createConversation({
    required String sellerId,
    String? orderId,
    String? message,
  }) async {
    final conversation = await _repo.createConversation(
      sellerId: sellerId,
      orderId: orderId,
      message: message,
    );
    final exists = state.conversations.any((c) => c.id == conversation.id);
    if (!exists) {
      state = state.copyWith(
        conversations: [conversation, ...state.conversations],
      );
    }
    return conversation;
  }
}

final conversationsNotifierProvider =
    StateNotifierProvider<ConversationsNotifier, ConversationsState>((ref) {
  return ConversationsNotifier(ref.watch(messagingRepositoryProvider));
});

// ── Chat (messages) ─────────────────────────────────────────────────────────

class ChatState {
  const ChatState({
    this.messages = const [],
    this.isLoading = false,
    this.isSending = false,
    this.error,
    this.sendError,
    this.nextCursor,
    this.hasMore = false,
  });

  final List<ChatMessage> messages;
  final bool isLoading;
  final bool isSending;
  final String? error;
  final String? sendError;
  final String? nextCursor;
  final bool hasMore;

  ChatState copyWith({
    List<ChatMessage>? messages,
    bool? isLoading,
    bool? isSending,
    String? error,
    bool clearError = false,
    String? sendError,
    bool clearSendError = false,
    String? nextCursor,
    bool? hasMore,
  }) {
    return ChatState(
      messages: messages ?? this.messages,
      isLoading: isLoading ?? this.isLoading,
      isSending: isSending ?? this.isSending,
      error: clearError ? null : (error ?? this.error),
      sendError: clearSendError ? null : (sendError ?? this.sendError),
      nextCursor: nextCursor ?? this.nextCursor,
      hasMore: hasMore ?? this.hasMore,
    );
  }
}

class ChatNotifier extends StateNotifier<ChatState> {
  ChatNotifier(this._repo, this._conversationId) : super(const ChatState());

  final MessagingRepository _repo;
  final String _conversationId;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final page = await _repo.fetchMessages(_conversationId);
      // API returns newest-first; reverse for chronological display.
      state = state.copyWith(
        messages: page.messages.reversed.toList(),
        isLoading: false,
        nextCursor: page.nextCursor,
        hasMore: page.hasMore,
      );
      await _markRead();
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  /// Silently re-fetches the latest page; ignores errors.
  Future<void> poll() async {
    try {
      final page = await _repo.fetchMessages(_conversationId);
      final refreshed = page.messages.reversed.toList();
      final hasNew = refreshed.length != state.messages.length ||
          (refreshed.isNotEmpty &&
              state.messages.isNotEmpty &&
              refreshed.last.id != state.messages.last.id);
      if (hasNew) {
        state = state.copyWith(messages: refreshed);
        await _markRead();
      }
    } catch (_) {
      // Intentionally silent; polling errors should not surface to UI.
    }
  }

  Future<bool> sendMessage(String body, {String? imageUrl}) async {
    final trimmed = body.trim();
    if (trimmed.isEmpty && (imageUrl == null || imageUrl.isEmpty)) {
      return false;
    }
    state = state.copyWith(isSending: true, clearSendError: true);
    try {
      final message = await _repo.sendMessage(
        _conversationId,
        body: trimmed.isEmpty ? null : trimmed,
        imageUrl: imageUrl,
      );
      state = state.copyWith(
        messages: [...state.messages, message],
        isSending: false,
      );
      return true;
    } catch (e) {
      state = state.copyWith(isSending: false, sendError: e.toString());
      return false;
    }
  }

  Future<void> _markRead() async {
    try {
      await _repo.markRead(_conversationId);
    } catch (_) {}
  }
}

final chatNotifierProvider =
    StateNotifierProvider.family<ChatNotifier, ChatState, String>(
  (ref, conversationId) =>
      ChatNotifier(ref.watch(messagingRepositoryProvider), conversationId),
);

// ── Unread count ─────────────────────────────────────────────────────────────

final unreadCountProvider = FutureProvider<int>((ref) {
  return ref.watch(messagingRepositoryProvider).fetchUnreadCount();
});
