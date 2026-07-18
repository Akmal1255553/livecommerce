import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/messaging/domain/entities/conversation.dart';

class MessagingRemoteDataSource {
  MessagingRemoteDataSource(this._dio);

  final Dio _dio;

  Future<ConversationPage> fetchConversations({
    String? cursor,
    int limit = 20,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/conversations',
      queryParameters: {
        if (cursor != null) 'cursor': cursor,
        'limit': limit,
      },
    );
    final body = response.data!;
    final data = body['data'] as List<dynamic>;
    final meta = body['meta'] as Map<String, dynamic>? ?? {};
    return ConversationPage(
      conversations: data
          .map((item) => Conversation.fromJson(item as Map<String, dynamic>))
          .toList(),
      nextCursor: meta['next_cursor'] as String?,
      hasMore: meta['has_more'] as bool? ?? false,
    );
  }

  Future<Conversation> createConversation({
    required String sellerId,
    String? orderId,
    String? message,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/conversations',
      data: {
        'seller_id': sellerId,
        if (orderId != null) 'order_id': orderId,
        if (message != null) 'message': message,
      },
    );
    return Conversation.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<MessagePage> fetchMessages(
    String conversationId, {
    String? cursor,
    int limit = 30,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/conversations/$conversationId/messages',
      queryParameters: {
        if (cursor != null) 'cursor': cursor,
        'limit': limit,
      },
    );
    final body = response.data!;
    final data = body['data'] as List<dynamic>;
    final meta = body['meta'] as Map<String, dynamic>? ?? {};
    return MessagePage(
      messages: data
          .map((item) => ChatMessage.fromJson(item as Map<String, dynamic>))
          .toList(),
      nextCursor: meta['next_cursor'] as String?,
      hasMore: meta['has_more'] as bool? ?? false,
    );
  }

  Future<ChatMessage> sendMessage(
    String conversationId, {
    String? body,
    String? imageUrl,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/conversations/$conversationId/messages',
      data: {
        if (body != null) 'body': body,
        if (imageUrl != null) 'image_url': imageUrl,
      },
    );
    return ChatMessage.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<void> markRead(String conversationId) async {
    await _dio.put<void>('/conversations/$conversationId/read');
  }

  Future<int> fetchUnreadCount() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/conversations/unread-count',
    );
    final data = response.data!['data'] as Map<String, dynamic>? ?? {};
    return data['unread_count'] as int? ?? 0;
  }
}

class MessagingRepository {
  MessagingRepository({required MessagingRemoteDataSource remote})
      : _remote = remote;

  final MessagingRemoteDataSource _remote;

  Future<ConversationPage> fetchConversations({
    String? cursor,
    int limit = 20,
  }) =>
      _remote.fetchConversations(cursor: cursor, limit: limit);

  Future<Conversation> createConversation({
    required String sellerId,
    String? orderId,
    String? message,
  }) =>
      _remote.createConversation(
        sellerId: sellerId,
        orderId: orderId,
        message: message,
      );

  Future<MessagePage> fetchMessages(
    String conversationId, {
    String? cursor,
    int limit = 30,
  }) =>
      _remote.fetchMessages(conversationId, cursor: cursor, limit: limit);

  Future<ChatMessage> sendMessage(
    String conversationId, {
    String? body,
    String? imageUrl,
  }) =>
      _remote.sendMessage(conversationId, body: body, imageUrl: imageUrl);

  Future<void> markRead(String conversationId) =>
      _remote.markRead(conversationId);

  Future<int> fetchUnreadCount() => _remote.fetchUnreadCount();
}

final messagingRepositoryProvider = Provider<MessagingRepository>((ref) {
  final dio = ref.watch(authDioProvider);
  return MessagingRepository(remote: MessagingRemoteDataSource(dio));
});
