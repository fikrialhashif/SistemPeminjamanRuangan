import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/check_in/data/check_in_repository.dart';
import 'package:mobile/features/face/data/face_repository.dart';

void main() {
  test('memetakan kode alasan terstruktur ke pesan ramah', () {
    expect(checkInReasonMessage('mock_location'), contains('Lokasi tiruan'));
    expect(checkInReasonMessage('outside_geofence'), contains('luar area'));
  });

  test('memetakan instruksi liveness dari server', () {
    expect(faceChallengeInstruction('turn'), contains('Tolehkan'));
    expect(faceChallengeInstruction('turn_left'), contains('kiri'));
    expect(faceChallengeInstruction('turn_right'), contains('kanan'));
  });

  test('memetakan rejection_reasons dari backend', () {
    final result = {
      'result': 'rejected',
      'rejection_reasons': [
        'qr: Token expired',
        'gps: Mock GPS detected',
        'face: verification failed',
      ],
    };

    final message = checkInDecisionMessage(result);
    expect(message, contains('QR sudah kedaluwarsa'));
    expect(message, contains('Lokasi tiruan'));
    expect(message, contains('Verifikasi wajah gagal'));
  });

  test('hasil accepted dan needs_manual_review', () {
    expect(
      checkInDecisionMessage({'result': 'accepted'}),
      'Check-in berhasil.',
    );
    expect(
      checkInDecisionMessage({'result': 'needs_manual_review'}),
      contains('Verifikasi manual'),
    );
  });
}
