import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/face/data/face_guidance.dart';

void main() {
  test('wajah depan stabil meminta foto depan', () {
    final tracker = FaceGuidanceTracker();
    for (var i = 0; i < FaceGuidanceTracker.frontStableFrames - 1; i++) {
      expect(
        tracker.update(faceDetected: true, yaw: 2),
        FaceGuidanceAction.none,
      );
    }
    expect(
      tracker.update(faceDetected: true, yaw: 2),
      FaceGuidanceAction.captureFront,
    );
    expect(tracker.phase, FaceGuidancePhase.turn);
  });

  test('tolehan stabil setelah foto depan meminta foto toleh', () {
    final tracker = FaceGuidanceTracker()..markFrontCaptured();
    for (var i = 0; i < FaceGuidanceTracker.turnStableFrames - 1; i++) {
      expect(
        tracker.update(faceDetected: true, yaw: -28),
        FaceGuidanceAction.none,
      );
    }
    expect(
      tracker.update(faceDetected: true, yaw: -30),
      FaceGuidanceAction.captureTurn,
    );
    expect(tracker.phase, FaceGuidancePhase.done);
  });

  test('wajah belum depan tidak langsung meminta foto', () {
    final tracker = FaceGuidanceTracker();
    for (var i = 0; i < 8; i++) {
      expect(
        tracker.update(faceDetected: true, yaw: 35),
        FaceGuidanceAction.none,
      );
    }
    expect(tracker.phase, FaceGuidancePhase.front);
  });

  test('kehilangan wajah setelah foto depan tetap menunggu tolehan', () {
    final tracker = FaceGuidanceTracker()..markFrontCaptured();
    tracker.update(faceDetected: false, yaw: null);
    expect(tracker.phase, FaceGuidancePhase.turn);
  });

  test('pendaftaran otomatis mengambil depan lalu dua tolehan berlawanan', () {
    final tracker = FaceEnrollmentGuidanceTracker();

    for (
      var i = 0;
      i < FaceEnrollmentGuidanceTracker.frontStableFrames - 1;
      i++
    ) {
      expect(
        tracker.update(faceDetected: true, yaw: 2),
        FaceEnrollmentAction.none,
      );
    }
    expect(
      tracker.update(faceDetected: true, yaw: 2),
      FaceEnrollmentAction.captureFront,
    );
    tracker.markFrontCaptured();
    expect(tracker.capturedCount, 1);

    for (
      var i = 0;
      i < FaceEnrollmentGuidanceTracker.turnStableFrames - 1;
      i++
    ) {
      expect(
        tracker.update(faceDetected: true, yaw: -30),
        FaceEnrollmentAction.none,
      );
    }
    expect(
      tracker.update(faceDetected: true, yaw: -30),
      FaceEnrollmentAction.captureTurn,
    );
    tracker.markTurnCaptured(-30);
    expect(tracker.capturedCount, 2);
    expect(tracker.complete, isFalse);

    for (var i = 0; i < 6; i++) {
      expect(
        tracker.update(faceDetected: true, yaw: -32),
        FaceEnrollmentAction.none,
      );
    }

    for (
      var i = 0;
      i < FaceEnrollmentGuidanceTracker.turnStableFrames - 1;
      i++
    ) {
      expect(
        tracker.update(faceDetected: true, yaw: 30),
        FaceEnrollmentAction.none,
      );
    }
    expect(
      tracker.update(faceDetected: true, yaw: 30),
      FaceEnrollmentAction.captureTurn,
    );
    tracker.markTurnCaptured(30);
    expect(tracker.complete, isTrue);
    expect(tracker.capturedCount, 3);
  });

  test('pendaftaran menunggu wajah lurus sebelum mengambil sampel', () {
    final tracker = FaceEnrollmentGuidanceTracker();
    for (var i = 0; i < 8; i++) {
      expect(
        tracker.update(faceDetected: true, yaw: 35),
        FaceEnrollmentAction.none,
      );
    }
    expect(tracker.capturedCount, 0);
  });

  test('pendaftaran mengabaikan tolehan yang sama arah dua kali', () {
    final tracker = FaceEnrollmentGuidanceTracker()
      ..markFrontCaptured()
      ..markTurnCaptured(-30);
    for (var i = 0; i < 8; i++) {
      expect(
        tracker.update(faceDetected: true, yaw: -35),
        FaceEnrollmentAction.none,
      );
    }
    expect(tracker.complete, isFalse);
    expect(tracker.capturedCount, 2);
  });
}
