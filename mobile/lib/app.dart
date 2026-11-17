import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'features/auth/presentation/login_page.dart';
import 'features/bookings/presentation/booking_form_page.dart';
import 'features/bookings/presentation/bundled_booking_page.dart';
import 'features/bookings/presentation/recurring_booking_page.dart';
import 'features/bookings/presentation/waitlist_page.dart';
import 'features/bookings/presentation/my_bookings_page.dart';
import 'features/check_in/presentation/check_in_page.dart';
import 'features/face/presentation/face_enrollment_page.dart';
import 'features/approvals/presentation/approvals_page.dart';
import 'features/resources/presentation/resources_page.dart';
import 'features/loans/presentation/manager_loans_page.dart';
import 'features/notifications/presentation/notifications_page.dart';
import 'features/notifications/presentation/penalties_page.dart';

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
      path: '/face/enrollment',
      builder: (context, state) => const FaceEnrollmentPage(),
    ),
    GoRoute(
      path: '/bookings/:bookingId/check-in',
      builder: (context, state) =>
          CheckInPage(bookingId: state.pathParameters['bookingId']!),
    ),
    GoRoute(
      path: '/bookings/bundled',
      builder: (context, state) => const BundledBookingPage(),
    ),
    GoRoute(
      path: '/bookings/:bookingId/waitlist',
      builder: (context, state) =>
          WaitlistPage(bookingId: state.pathParameters['bookingId']!),
    ),
    GoRoute(
      path: '/bookings/recurring/:resourceId',
      builder: (context, state) =>
          RecurringBookingPage(resourceId: state.pathParameters['resourceId']!),
    ),
    GoRoute(
      path: '/bookings/new/:resourceId',
      builder: (context, state) =>
          BookingFormPage(resourceId: state.pathParameters['resourceId']!),
    ),
    GoRoute(
      path: '/manager/loans',
      builder: (context, state) => const ManagerLoansPage(),
    ),
    GoRoute(
      path: '/notifications',
      builder: (context, state) => const NotificationsPage(),
    ),
    GoRoute(
      path: '/penalties',
      builder: (context, state) => const PenaltiesPage(),
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
