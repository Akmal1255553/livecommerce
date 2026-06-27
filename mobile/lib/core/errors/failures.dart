sealed class Failure {
  const Failure(this.message);

  final String message;
}

final class ServerFailure extends Failure {
  const ServerFailure([super.message = 'Server error']);
}

final class NetworkFailure extends Failure {
  const NetworkFailure([super.message = 'Network error']);
}

final class AuthFailure extends Failure {
  const AuthFailure([super.message = 'Authentication failed']);
}

final class ValidationFailure extends Failure {
  const ValidationFailure(this.fieldErrors, [super.message = 'Validation failed']);

  final Map<String, List<String>> fieldErrors;
}

final class NotFoundFailure extends Failure {
  const NotFoundFailure([super.message = 'Resource not found']);
}

final class PermissionFailure extends Failure {
  const PermissionFailure([super.message = 'Permission denied']);
}

final class RateLimitFailure extends Failure {
  const RateLimitFailure([super.message = 'Too many requests']);
}
