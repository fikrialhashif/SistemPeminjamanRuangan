import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/resource_repository.dart';

final resourceListProvider =
    FutureProvider.family<List<Map<String, dynamic>>, ResourceFilter>(
      (ref, filter) => ref
          .read(resourceRepositoryProvider)
          .list(
            search: filter.search,
            type: filter.type,
            category: filter.category,
            locationId: filter.locationId,
            capacityMin: filter.capacityMin,
          ),
    );

final resourceDetailProvider =
    FutureProvider.family<Map<String, dynamic>, String>(
      (ref, id) => ref.read(resourceRepositoryProvider).get(id),
    );

class ResourceFilter {
  const ResourceFilter({
    this.search = '',
    this.type,
    this.category,
    this.locationId,
    this.capacityMin,
  });
  final String search;
  final String? type;
  final String? category;
  final String? locationId;
  final int? capacityMin;

  @override
  bool operator ==(Object other) =>
      other is ResourceFilter &&
      search == other.search &&
      type == other.type &&
      category == other.category &&
      locationId == other.locationId &&
      capacityMin == other.capacityMin;

  @override
  int get hashCode =>
      Object.hash(search, type, category, locationId, capacityMin);
}
