import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/core/errors/exceptions.dart';
import 'package:livecommerce_mobile/core/errors/failures.dart';

Failure mapExceptionToFailure(Object error) {
  if (error is ValidationException) {
    return ValidationFailure(error.fieldErrors, error.message);
  }

  if (error is AuthException) {
    return AuthFailure(error.message);
  }

  if (error is NetworkException) {
    return NetworkFailure(error.message);
  }

  if (error is ServerException) {
    return ServerFailure(error.message);
  }

  if (error is DioException) {
    final statusCode = error.response?.statusCode;
    final data = error.response?.data;

    if (statusCode == 401) {
      return AuthFailure(_messageFromBody(data) ?? 'Unauthenticated.');
    }

    if (statusCode == 403) {
      return PermissionFailure(_messageFromBody(data) ?? 'Forbidden.');
    }

    if (statusCode == 404) {
      return NotFoundFailure(_messageFromBody(data) ?? 'Resource not found.');
    }

    if (statusCode == 422) {
      return ValidationFailure(
        _errorsFromBody(data),
        _messageFromBody(data) ?? 'Validation failed.',
      );
    }

    if (statusCode == 429) {
      return RateLimitFailure(_messageFromBody(data) ?? 'Too many requests.');
    }

    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.connectionError) {
      return NetworkFailure('Connection failed.');
    }

    return ServerFailure(_messageFromBody(data) ?? 'Request failed.');
  }

  return ServerFailure('Unexpected error.');
}

String? _messageFromBody(Object? data) {
  if (data is Map<String, dynamic>) {
    final message = data['message'];
    return message is String ? message : null;
  }

  return null;
}

Map<String, List<String>> _errorsFromBody(Object? data) {
  if (data is! Map<String, dynamic>) {
    return const {};
  }

  final errors = data['errors'];
  if (errors is! Map<String, dynamic>) {
    return const {};
  }

  return errors.map(
    (key, value) => MapEntry(
      key,
      value is List ? value.map((e) => e.toString()).toList() : [value.toString()],
    ),
  );
}
