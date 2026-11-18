import 'dart:convert';
import 'dart:io';
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

  Future<Map<String, dynamic>> challenge() async {
    try {
      final response = await _dio.get('/face/challenge');
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> enroll(
    List<Uint8List> frames,
    List<String> poses,
  ) async {
    try {
      final response = await _dio.post(
        '/face/enroll',
        data: {
          'consent': true,
          'images': frames.map(base64Encode).toList(),
          'poses': poses,
        },
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

  Future<Uint8List> capture(CameraController controller) async {
    final file = await controller.takePicture();
    try {
      return await file.readAsBytes();
    } finally {
      final discarded = File(file.path);
      if (await discarded.exists()) {
        await discarded.delete();
      }
    }
  }
}

String faceChallengeInstruction(String? challengeType) =>
    switch (challengeType) {
      'turn' => 'Tolehkan kepala ke kiri atau kanan',
      'blink' => 'Kedipkan mata Anda',
      'turn_left' => 'Tengokkan kepala ke kiri',
      'turn_right' => 'Tengokkan kepala ke kanan',
      _ => 'Lakukan gerakan yang diminta',
    };
