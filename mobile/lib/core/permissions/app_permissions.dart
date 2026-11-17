import 'package:permission_handler/permission_handler.dart';

enum PermissionState {
  granted,
  notRequested,
  denied,
  permanentlyDenied,
  restricted,
}

class AppPermissions {
  const AppPermissions._();

  static Future<PermissionState> status(Permission permission) async =>
      _map(await permission.status);
  static Future<PermissionState> request(Permission permission) async =>
      _map(await permission.request());
  static Future<bool> openSettings() => openAppSettings();

  static PermissionState _map(PermissionStatus status) {
    if (status.isGranted || status.isLimited) return PermissionState.granted;
    if (status.isPermanentlyDenied) return PermissionState.permanentlyDenied;
    if (status.isRestricted) return PermissionState.restricted;
    if (status.isDenied) return PermissionState.denied;
    return PermissionState.notRequested;
  }
}
