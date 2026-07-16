import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/core/network/api_json.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/cart.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';

class LiveRemoteDataSource {
  LiveRemoteDataSource(this._dio);

  final Dio _dio;

  Future<List<LiveSession>> listLive({int limit = 20}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/live',
      queryParameters: {'limit': limit},
    );
    final data = response.data!['data'] as List<dynamic>? ?? [];
    return data
        .map((e) => LiveSession.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<LiveSession>> listReplays({int limit = 20}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/live/replays',
      queryParameters: {'limit': limit},
    );
    final data = response.data!['data'] as List<dynamic>? ?? [];
    return data
        .map((e) => LiveSession.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<LiveSession> start({
    required String title,
    List<String> productIds = const [],
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/live/start',
      data: {
        'title': title,
        if (productIds.isNotEmpty) 'product_ids': productIds,
      },
    );
    return LiveSession.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<LiveSession> get(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/live/$id');
    return LiveSession.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<LiveSession> end(String id) async {
    final response = await _dio.post<Map<String, dynamic>>('/live/$id/end');
    return LiveSession.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<LiveSession> pinProduct(String sessionId, String productId) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/live/$sessionId/pin-product',
      data: {'product_id': productId},
    );
    return LiveSession.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<LiveSession> unpinProduct(String sessionId, String productId) async {
    final response = await _dio.delete<Map<String, dynamic>>(
      '/live/$sessionId/pin-product/$productId',
    );
    return LiveSession.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<List<LiveChatMessage>> chat(
    String sessionId, {
    int? afterId,
    int limit = 50,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/live/$sessionId/chat',
      queryParameters: {
        if (afterId != null) 'after_id': afterId,
        'limit': limit,
      },
    );
    final data = response.data!['data'] as List<dynamic>? ?? [];
    return data
        .map((e) => LiveChatMessage.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<LiveChatMessage> sendChat(String sessionId, String message) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/live/$sessionId/chat',
      data: {'message': message},
    );
    return LiveChatMessage.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<Map<String, int>> join(String sessionId) async {
    final response =
        await _dio.post<Map<String, dynamic>>('/live/$sessionId/join');
    final data = response.data!['data'] as Map<String, dynamic>? ?? {};
    return {
      'current_viewers': data['current_viewers'] as int? ?? 0,
      'peak_viewers': data['peak_viewers'] as int? ?? 0,
      'unique_viewers': data['unique_viewers'] as int? ?? 0,
    };
  }

  Future<Map<String, int>> leave(String sessionId) async {
    final response =
        await _dio.post<Map<String, dynamic>>('/live/$sessionId/leave');
    final data = response.data!['data'] as Map<String, dynamic>? ?? {};
    return {
      'current_viewers': data['current_viewers'] as int? ?? 0,
      'peak_viewers': data['peak_viewers'] as int? ?? 0,
      'unique_viewers': data['unique_viewers'] as int? ?? 0,
    };
  }

  Future<LiveAddToCartResult> addToCart(
    String sessionId,
    String productId, {
    int quantity = 1,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/live/$sessionId/add-to-cart',
      data: {
        'product_id': productId,
        'quantity': quantity,
      },
    );
    final data = response.data!['data'] as Map<String, dynamic>;
    final cartJson = unwrapApiResource(data['cart']);
    final chatJson = unwrapApiResource(data['chat_message']);
    return LiveAddToCartResult(
      cart: Cart.fromJson(cartJson),
      chatMessage: LiveChatMessage.fromJson(chatJson),
    );
  }

  Future<LiveAssistantResult> assistantSuggestions(String sessionId) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/live/$sessionId/assistant/suggestions',
    );
    final data = response.data!['data'] as Map<String, dynamic>;
    final list = data['suggestions'] as List<dynamic>? ?? [];
    return LiveAssistantResult(
      provider: data['provider'] as String? ?? 'rule_based',
      suggestions: list
          .map((e) => LiveAssistantSuggestion.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class LiveRepository {
  LiveRepository({required LiveRemoteDataSource remote}) : _remote = remote;

  final LiveRemoteDataSource _remote;

  Future<List<LiveSession>> listLive({int limit = 20}) =>
      _remote.listLive(limit: limit);

  Future<List<LiveSession>> listReplays({int limit = 20}) =>
      _remote.listReplays(limit: limit);

  Future<LiveSession> start({
    required String title,
    List<String> productIds = const [],
  }) =>
      _remote.start(title: title, productIds: productIds);

  Future<LiveSession> get(String id) => _remote.get(id);

  Future<LiveSession> end(String id) => _remote.end(id);

  Future<LiveSession> pinProduct(String sessionId, String productId) =>
      _remote.pinProduct(sessionId, productId);

  Future<LiveSession> unpinProduct(String sessionId, String productId) =>
      _remote.unpinProduct(sessionId, productId);

  Future<List<LiveChatMessage>> chat(
    String sessionId, {
    int? afterId,
    int limit = 50,
  }) =>
      _remote.chat(sessionId, afterId: afterId, limit: limit);

  Future<LiveChatMessage> sendChat(String sessionId, String message) =>
      _remote.sendChat(sessionId, message);

  Future<Map<String, int>> join(String sessionId) => _remote.join(sessionId);

  Future<Map<String, int>> leave(String sessionId) => _remote.leave(sessionId);

  Future<LiveAddToCartResult> addToCart(
    String sessionId,
    String productId, {
    int quantity = 1,
  }) =>
      _remote.addToCart(sessionId, productId, quantity: quantity);

  Future<LiveAssistantResult> assistantSuggestions(String sessionId) =>
      _remote.assistantSuggestions(sessionId);
}

class LiveAddToCartResult {
  const LiveAddToCartResult({
    required this.cart,
    required this.chatMessage,
  });

  final Cart cart;
  final LiveChatMessage chatMessage;
}

class LiveAssistantSuggestion {
  const LiveAssistantSuggestion({
    required this.id,
    required this.type,
    required this.priority,
    required this.title,
    required this.body,
    this.action,
    this.payload = const {},
  });

  final String id;
  final String type;
  final int priority;
  final String title;
  final String body;
  final String? action;
  final Map<String, dynamic> payload;

  String? get productId => payload['product_id'] as String?;
  String? get replyText => payload['reply_text'] as String?;

  factory LiveAssistantSuggestion.fromJson(Map<String, dynamic> json) {
    return LiveAssistantSuggestion(
      id: json['id'] as String,
      type: json['type'] as String? ?? 'tip',
      priority: json['priority'] as int? ?? 99,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      action: json['action'] as String?,
      payload: (json['payload'] as Map<String, dynamic>?) ?? const {},
    );
  }
}

class LiveAssistantResult {
  const LiveAssistantResult({
    required this.provider,
    required this.suggestions,
  });

  final String provider;
  final List<LiveAssistantSuggestion> suggestions;
}
