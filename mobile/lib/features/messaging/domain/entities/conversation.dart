class ChatParticipant {
  const ChatParticipant({
    required this.id,
    required this.username,
    required this.displayName,
    this.avatarUrl,
  });

  final String id;
  final String username;
  final String displayName;
  final String? avatarUrl;

  factory ChatParticipant.fromJson(Map<String, dynamic> json) {
    return ChatParticipant(
      id: json['id'] as String,
      username: json['username'] as String,
      displayName: json['display_name'] as String? ?? json['username'] as String,
      avatarUrl: json['avatar_url'] as String?,
    );
  }
}

class Conversation {
  const Conversation({
    required this.id,
    required this.type,
    this.orderId,
    this.lastMessagePreview,
    this.lastMessageAt,
    required this.unreadCount,
    required this.participant,
    required this.createdAt,
  });

  final String id;
  final String type;
  final String? orderId;
  final String? lastMessagePreview;
  final String? lastMessageAt;
  final int unreadCount;
  final ChatParticipant participant;
  final String createdAt;

  factory Conversation.fromJson(Map<String, dynamic> json) {
    return Conversation(
      id: json['id'] as String,
      type: json['type'] as String? ?? 'direct',
      orderId: json['order_id'] as String?,
      lastMessagePreview: json['last_message_preview'] as String?,
      lastMessageAt: json['last_message_at'] as String?,
      unreadCount: json['unread_count'] as int? ?? 0,
      participant: ChatParticipant.fromJson(
        json['participant'] as Map<String, dynamic>,
      ),
      createdAt: json['created_at'] as String,
    );
  }
}

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.conversationId,
    required this.type,
    this.body,
    this.imageUrl,
    required this.sender,
    required this.createdAt,
  });

  final String id;
  final String conversationId;
  final String type;
  final String? body;
  final String? imageUrl;
  final ChatParticipant sender;
  final String createdAt;

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    return ChatMessage(
      id: json['id'] as String,
      conversationId: json['conversation_id'] as String,
      type: json['type'] as String? ?? 'text',
      body: json['body'] as String?,
      imageUrl: json['image_url'] as String?,
      sender: ChatParticipant.fromJson(json['sender'] as Map<String, dynamic>),
      createdAt: json['created_at'] as String,
    );
  }
}

class ConversationPage {
  const ConversationPage({
    required this.conversations,
    this.nextCursor,
    required this.hasMore,
  });

  final List<Conversation> conversations;
  final String? nextCursor;
  final bool hasMore;
}

class MessagePage {
  const MessagePage({
    required this.messages,
    this.nextCursor,
    required this.hasMore,
  });

  final List<ChatMessage> messages;
  final String? nextCursor;
  final bool hasMore;
}
