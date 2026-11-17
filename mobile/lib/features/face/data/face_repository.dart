import 'dart:convert';
import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';

final faceRepositoryProvider = Provider<FaceRepository>(
  (ref) => FaceRepository(ref.read(dioProvider)),
);

class FaceRepository {
  FaceRepository(this._dio);
  final Dio _dio;

  Future<Map<String, dynamic>> status() async {
    try {
      final response = await _dio.get('/face/status');
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> enroll(List<Uint8List> frames) async {
    try {
      final response = await _dio.post(
        '/face/enroll',
        data: {'consent': true, 'images': frames.map(base64Encode).toList()},
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<void> delete() async {
    try {
      await _dio.delete('/face');
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> verify({
    required String bookingId,
    required String qrToken,
    required Map<String, Object> location,
    required Uint8List frame,
    Map<String, dynamic>? challenge,
  }) async {
    try {
      final data = <String, dynamic>{
        'qr_token': qrToken,
        ...location,
        'face_image': base64Encode(frame),
      };
      if (challenge != null) {
        data['face_challenge_response'] = challenge;
      }
      final response = await _dio.post(
        '/bookings/$bookingId/check-in',
        data: data,
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Uint8List> capture(CameraController controller) async =>
      (await controller.takePicture()).readAsBytes();
}
