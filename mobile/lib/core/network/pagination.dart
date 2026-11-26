import 'package:dio/dio.dart';

const _pageSize = 100;

/// Upper bound so a server that always reports a next page cannot loop forever.
const _maxPages = 50;

/// Loads every page of a Laravel paginated endpoint. A plain list response is
/// returned as it is.
Future<List<Map<String, dynamic>>> fetchAllPages(
  Dio dio,
  String path, {
  Map<String, dynamic> query = const {},
}) async {
  final items = <Map<String, dynamic>>[];
  for (var page = 1; page <= _maxPages; page++) {
    final response = await dio.get<Object?>(
      path,
      queryParameters: {...query, 'per_page': _pageSize, 'page': page},
    );
    final body = response.data;
    final rows = body is Map ? body['data'] as List? ?? const [] : body as List;
    items.addAll(rows.map((item) => Map<String, dynamic>.from(item as Map)));
    if (body is! Map || body['next_page_url'] == null) break;
  }
  return items;
}
