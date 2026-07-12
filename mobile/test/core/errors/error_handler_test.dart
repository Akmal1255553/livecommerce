import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/errors/exceptions.dart';
import 'package:livecommerce_mobile/core/errors/failures.dart';

void main() {
  test('maps validation exception to validation failure', () {
    const exception = ValidationException(
      {'email': ['Required']},
      'Validation failed.',
    );

    final failure = mapExceptionToFailure(exception);

    expect(failure, isA<ValidationFailure>());
    expect((failure as ValidationFailure).fieldErrors['email'], ['Required']);
  });

  test('maps auth exception to auth failure', () {
    final failure = mapExceptionToFailure(AuthException('Unauthenticated.'));

    expect(failure, isA<AuthFailure>());
  });
}
