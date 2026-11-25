import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' show Size;

import 'package:camera/camera.dart';
import 'package:flutter/services.dart' show DeviceOrientation;
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

enum FaceGuidancePhase { front, turn, done }

enum FaceGuidanceAction { none, captureFront, captureTurn }

enum FaceEnrollmentAction { none, captureFront, captureTurn }

class FaceEnrollmentGuidanceTracker {
  FaceEnrollmentGuidanceTracker({this.frontYawMax = 10, this.turnYawMin = 25});

  final double frontYawMax;
  final double turnYawMin;
  static const requiredSamples = 3;
  static const frontStableFrames = 4;
  static const turnStableFrames = 3;

  int _frontFrames = 0;
  int _turnFrames = 0;
  int _firstTurnSign = 0;
  bool _frontCaptured = false;
  bool _firstTurnCaptured = false;
  bool _secondTurnCaptured = false;

  bool get frontCaptured => _frontCaptured;
  bool get complete => _secondTurnCaptured;

  int get capturedCount =>
      (_frontCaptured ? 1 : 0) +
      (_firstTurnCaptured ? 1 : 0) +
      (_secondTurnCaptured ? 1 : 0);

  void reset() {
    _frontFrames = 0;
    _turnFrames = 0;
    _firstTurnSign = 0;
    _frontCaptured = false;
    _firstTurnCaptured = false;
    _secondTurnCaptured = false;
  }

  void markFrontCaptured() {
    _frontCaptured = true;
    _frontFrames = 0;
    _turnFrames = 0;
  }

  void markTurnCaptured(double yaw) {
    if (!_firstTurnCaptured) {
      _firstTurnCaptured = true;
      _firstTurnSign = yaw.sign.toInt();
    } else {
      _secondTurnCaptured = true;
    }
    _frontFrames = 0;
    _turnFrames = 0;
  }

  FaceEnrollmentAction update({
    required bool faceDetected,
    required double? yaw,
  }) {
    if (!faceDetected || yaw == null) {
      _frontFrames = 0;
      _turnFrames = 0;
      return FaceEnrollmentAction.none;
    }
    if (_secondTurnCaptured) return FaceEnrollmentAction.none;

    if (!_frontCaptured) {
      if (yaw.abs() <= frontYawMax) {
        _frontFrames += 1;
        if (_frontFrames >= frontStableFrames) {
          _frontFrames = 0;
          return FaceEnrollmentAction.captureFront;
        }
      } else {
        _frontFrames = 0;
      }
      return FaceEnrollmentAction.none;
    }

    if (!_firstTurnCaptured) {
      if (yaw.abs() >= turnYawMin) {
        _turnFrames += 1;
        if (_turnFrames >= turnStableFrames) {
          _turnFrames = 0;
          return FaceEnrollmentAction.captureTurn;
        }
      } else {
        _turnFrames = 0;
      }
      return FaceEnrollmentAction.none;
    }

    final sign = yaw.sign.toInt();
    if (sign != 0 && sign != _firstTurnSign && yaw.abs() >= turnYawMin) {
      _turnFrames += 1;
      if (_turnFrames >= turnStableFrames) {
        _turnFrames = 0;
        return FaceEnrollmentAction.captureTurn;
      }
    } else {
      _turnFrames = 0;
    }
    return FaceEnrollmentAction.none;
  }
}

class FaceGuidanceTracker {
  FaceGuidanceTracker({this.frontYawMax = 10, this.turnYawMin = 22});

  final double frontYawMax;
  final double turnYawMin;
  static const frontStableFrames = 4;
  static const turnStableFrames = 3;

  FaceGuidancePhase _phase = FaceGuidancePhase.front;
  int _frontFrames = 0;
  int _turnFrames = 0;

  FaceGuidancePhase get phase => _phase;

  void reset() {
    _phase = FaceGuidancePhase.front;
    _frontFrames = 0;
    _turnFrames = 0;
  }

  void markFrontCaptured() {
    _phase = FaceGuidancePhase.turn;
    _frontFrames = 0;
    _turnFrames = 0;
  }

  FaceGuidanceAction update({
    required bool faceDetected,
    required double? yaw,
  }) {
    if (!faceDetected || yaw == null) {
      _frontFrames = 0;
      _turnFrames = 0;
      return FaceGuidanceAction.none;
    }
    if (_phase == FaceGuidancePhase.done) return FaceGuidanceAction.none;

    final yawAbs = yaw.abs();
    if (_phase == FaceGuidancePhase.front) {
      if (yawAbs <= frontYawMax) {
        _frontFrames += 1;
        if (_frontFrames >= frontStableFrames) {
          _phase = FaceGuidancePhase.turn;
          _frontFrames = 0;
          return FaceGuidanceAction.captureFront;
        }
      } else {
        _frontFrames = 0;
      }
      return FaceGuidanceAction.none;
    }

    if (yawAbs >= turnYawMin) {
      _turnFrames += 1;
      if (_turnFrames >= turnStableFrames) {
        _phase = FaceGuidancePhase.done;
        return FaceGuidanceAction.captureTurn;
      }
    } else {
      _turnFrames = 0;
    }
    return FaceGuidanceAction.none;
  }
}

ImageFormatGroup cameraImageFormatGroup() =>
    Platform.isAndroid ? ImageFormatGroup.nv21 : ImageFormatGroup.bgra8888;

InputImage? inputImageFromCameraImage(
  CameraImage image,
  CameraController controller,
) {
  final rotation = _rotation(controller);
  if (rotation == null) return null;

  final bytes = image.planes.length == 1
      ? image.planes.first.bytes
      : _yuv420ToNv21(image);
  return InputImage.fromBytes(
    bytes: bytes,
    metadata: InputImageMetadata(
      size: Size(image.width.toDouble(), image.height.toDouble()),
      rotation: rotation,
      format: Platform.isAndroid
          ? InputImageFormat.nv21
          : InputImageFormat.bgra8888,
      bytesPerRow: image.planes.first.bytesPerRow,
    ),
  );
}

InputImageRotation? _rotation(CameraController controller) {
  final sensorOrientation = controller.description.sensorOrientation;
  if (Platform.isIOS) {
    return InputImageRotationValue.fromRawValue(sensorOrientation);
  }
  final deviceOrientation =
      const {
        DeviceOrientation.portraitUp: 0,
        DeviceOrientation.landscapeLeft: 90,
        DeviceOrientation.portraitDown: 180,
        DeviceOrientation.landscapeRight: 270,
      }[controller.value.deviceOrientation] ??
      0;
  final compensation =
      controller.description.lensDirection == CameraLensDirection.front
      ? (sensorOrientation + deviceOrientation) % 360
      : (sensorOrientation - deviceOrientation + 360) % 360;
  return InputImageRotationValue.fromRawValue(compensation);
}

Uint8List _yuv420ToNv21(CameraImage image) {
  final yPlane = image.planes[0];
  final uPlane = image.planes[1];
  final vPlane = image.planes[2];
  final width = image.width;
  final height = image.height;
  final ySize = width * height;
  final bytes = Uint8List(ySize + (ySize ~/ 2));

  for (var row = 0; row < height; row++) {
    final from = row * yPlane.bytesPerRow;
    bytes.setRange(row * width, (row + 1) * width, yPlane.bytes, from);
  }

  final uvWidth = width ~/ 2;
  final uvHeight = height ~/ 2;
  final uStride = uPlane.bytesPerPixel ?? 1;
  final vStride = vPlane.bytesPerPixel ?? 1;
  var offset = ySize;
  for (var row = 0; row < uvHeight; row++) {
    for (var column = 0; column < uvWidth; column++) {
      final vIndex = row * vPlane.bytesPerRow + column * vStride;
      final uIndex = row * uPlane.bytesPerRow + column * uStride;
      if (vIndex < vPlane.bytes.length && uIndex < uPlane.bytes.length) {
        bytes[offset++] = vPlane.bytes[vIndex];
        bytes[offset++] = uPlane.bytes[uIndex];
      }
    }
  }
  return bytes;
}
