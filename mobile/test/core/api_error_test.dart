import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mobile/core/network/api_error.dart';

void main() {
  test('maps structured API errors', () {
    final error = ApiError.fromResponse(Response(
      requestOptions: RequestOptions(),
      statusCode: 422,
      data: {'code': 'VALIDATION_FAILED', 'message': 'Invalid input', 'details': {'field': 'email'}},
    ));

    expect(error.code, 'VALIDATION_FAILED');
    expect(error.message, 'Invalid input');
    expect(error.details, {'field': 'email'});
  });

  test('maps legacy message-only API errors', () {
    final error = ApiError.fromResponse(Response(
      requestOptions: RequestOptions(),
      statusCode: 401,
      data: {'message': 'Invalid credentials.'},
    ));

    expect(error.code, isNull);
    expect(error.message, 'Invalid credentials.');
  });
}
