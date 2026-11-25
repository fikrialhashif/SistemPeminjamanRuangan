import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/permissions/app_permissions.dart';

final locationServiceProvider = Provider<LocationService>(
  (ref) => LocationService(),
);

class LocationEvidence {
  const LocationEvidence({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    required this.timestamp,
    required this.isMock,
    this.preciseAccuracy = true,
  });
  final double latitude;
  final double longitude;
  final double accuracy;
  final DateTime timestamp;
  final bool isMock;
  final bool preciseAccuracy;

  Map<String, Object> toJson() => {
    'gps_latitude': latitude,
    'gps_longitude': longitude,
    'gps_accuracy': accuracy,
    'gps_timestamp': timestamp.toUtc().toIso8601String(),
    'gps_is_mock': isMock,
  };
}

class LocationService {
  Future<PermissionState> permissionState() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return PermissionState.denied;
    }
    return AppPermissions.status(Permission.location);
  }

  Future<PermissionState> requestPermission() =>
      AppPermissions.request(Permission.location);

  Future<LocationAccuracyStatus> locationAccuracyStatus() async {
    try {
      return await Geolocator.getLocationAccuracy();
    } catch (_) {
      return LocationAccuracyStatus.unknown;
    }
  }

  Future<LocationEvidence> currentEvidence() async {
    final accuracyStatus = await locationAccuracyStatus();
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.best),
    );
    return LocationEvidence(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      timestamp: position.timestamp,
      isMock: position.isMocked,
      preciseAccuracy: accuracyStatus != LocationAccuracyStatus.reduced,
    );
  }
}

double distanceBetweenMeters({
  required double fromLatitude,
  required double fromLongitude,
  required double toLatitude,
  required double toLongitude,
}) => Geolocator.distanceBetween(
  fromLatitude,
  fromLongitude,
  toLatitude,
  toLongitude,
);
