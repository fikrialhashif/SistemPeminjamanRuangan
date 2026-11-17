import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'features/auth/presentation/login_page.dart';
import 'features/bookings/presentation/booking_form_page.dart';
import 'features/bookings/presentation/my_bookings_page.dart';
import 'features/approvals/presentation/approvals_page.dart';
import 'features/resources/presentation/resources_page.dart';

final _router = GoRouter(
  routes: [
    GoRoute(path: '/', builder: (context, state) => const LoginPage()),
    GoRoute(
      path: '/resources',
      builder: (context, state) => const ResourcesPage(),
    ),
    GoRoute(
      path: '/bookings',
      builder: (context, state) => const MyBookingsPage(),
    ),
    GoRoute(
      path: '/approvals',
      builder: (context, state) => const ApprovalsPage(),
    ),
    GoRoute(
      path: '/bookings/new/:resourceId',
      builder: (context, state) =>
          BookingFormPage(resourceId: state.pathParameters['resourceId']!),
    ),
  ],
);

class BookingApp extends StatelessWidget {
  const BookingApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp.router(
    title: 'Smart Room Booking',
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: Colors.indigo),
      useMaterial3: true,
    ),
    routerConfig: _router,
  );
}
