import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/permissions/app_permissions.dart';

class LocationEvidence {
  const LocationEvidence({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    required this.timestamp,
    required this.isMock,
  });
  final double latitude;
  final double longitude;
  final double accuracy;
  final DateTime timestamp;
  final bool isMock;

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

  Future<LocationEvidence> currentEvidence() async {
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    );
    return LocationEvidence(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      timestamp: position.timestamp,
      isMock: position.isMocked,
    );
  }
}
