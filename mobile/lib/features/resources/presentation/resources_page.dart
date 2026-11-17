import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'resource_providers.dart';

class ResourcesPage extends ConsumerStatefulWidget {
  const ResourcesPage({super.key});
  @override
  ConsumerState<ResourcesPage> createState() => _ResourcesPageState();
}

class _ResourcesPageState extends ConsumerState<ResourcesPage> {
  String search = '';
  String? type;

  @override
  Widget build(BuildContext context) {
    final resources = ref.watch(
      resourceListProvider(ResourceFilter(search: search, type: type)),
    );
    return Scaffold(
      appBar: AppBar(
        title: const Text('Resource'),
        actions: [
          IconButton(
            onPressed: () => context.push('/bookings'),
            icon: const Icon(Icons.event_note),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              decoration: const InputDecoration(
                labelText: 'Cari resource',
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: (value) => setState(() => search = value),
            ),
          ),
          Wrap(
            spacing: 8,
            children: [
              FilterChip(
                label: const Text('Semua'),
                selected: type == null,
                onSelected: (_) => setState(() => type = null),
              ),
              FilterChip(
                label: const Text('Ruangan'),
                selected: type == 'room',
                onSelected: (_) => setState(() => type = 'room'),
              ),
              FilterChip(
                label: const Text('Aset'),
                selected: type == 'asset',
                onSelected: (_) => setState(() => type = 'asset'),
              ),
            ],
          ),
          Expanded(
            child: resources.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => Center(child: Text(error.toString())),
              data: (items) => items.isEmpty
                  ? const Center(child: Text('Resource tidak ditemukan'))
                  : ListView.builder(
                      itemCount: items.length,
                      itemBuilder: (_, index) {
                        final item = items[index];
                        return ListTile(
                          title: Text(item['name']?.toString() ?? '-'),
                          subtitle: Text(item['type']?.toString() ?? '-'),
                          onTap: item['id'] == null
                              ? null
                              : () =>
                                    context.push('/bookings/new/${item['id']}'),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
