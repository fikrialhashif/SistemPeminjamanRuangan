import 'package:camera/camera.dart';
import 'package:flutter/material.dart';

class CameraPreviewBox extends StatelessWidget {
  const CameraPreviewBox({
    super.key,
    required this.controller,
    this.borderRadius = 16,
    this.fit = BoxFit.cover,
  });

  final CameraController controller;
  final double borderRadius;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) {
    final previewSize = controller.value.previewSize;
    if (!controller.value.isInitialized || previewSize == null) {
      return const ColoredBox(color: Colors.black);
    }

    final portrait = MediaQuery.orientationOf(context) == Orientation.portrait;
    final width = portrait ? previewSize.height : previewSize.width;
    final height = portrait ? previewSize.width : previewSize.height;

    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: FittedBox(
        fit: fit,
        clipBehavior: Clip.hardEdge,
        child: SizedBox(
          width: width,
          height: height,
          child: CameraPreview(controller),
        ),
      ),
    );
  }
}
