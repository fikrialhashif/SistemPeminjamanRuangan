import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// The Siboru mark: a door with a location pin.
///
/// The shapes use a 108 x 108 viewport and mirror the Android vector in
/// `res/drawable/ic_launcher_foreground.xml`.
class SiboruMark extends StatelessWidget {
  const SiboruMark({
    required this.size,
    this.viewport = const Rect.fromLTWH(0, 0, 108, 108),
    super.key,
  });

  final double size;

  /// The part of the 108 viewport that fills [size].
  final Rect viewport;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Logo Siboru',
    image: true,
    child: CustomPaint(
      size: Size.square(size),
      painter: SiboruMarkPainter(viewport),
    ),
  );
}

class SiboruMarkPainter extends CustomPainter {
  const SiboruMarkPainter(this.viewport);

  final Rect viewport;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / viewport.width, size.height / viewport.height);
    canvas.translate(-viewport.left, -viewport.top);

    final white = Paint()..color = Colors.white;
    final green = Paint()..color = AppColors.primary;
    final amber = Paint()..color = AppColors.accent;

    final door = Path()
      ..moveTo(32, 76)
      ..lineTo(32, 45)
      ..arcToPoint(const Offset(62, 45), radius: const Radius.circular(15))
      ..lineTo(62, 76)
      ..close();
    canvas.drawPath(door, white);
    canvas.drawCircle(const Offset(38.5, 58), 2.4, green);

    // A green margin keeps the pin apart from the door.
    canvas.drawPath(_pin(86.58, 53.85, 78.15, 67.91, 14.5), green);
    canvas.drawPath(_pin(82, 55.94, 76.06, 66.55, 12), amber);
    canvas.drawCircle(const Offset(66, 60), 4.6, green);

    canvas.restore();
  }

  Path _pin(double tip, double left, double right, double y, double radius) =>
      Path()
        ..moveTo(66, tip)
        ..lineTo(left, y)
        ..arcToPoint(
          Offset(right, y),
          radius: Radius.circular(radius),
          largeArc: true,
        )
        ..close();

  @override
  bool shouldRepaint(covariant SiboruMarkPainter oldDelegate) =>
      oldDelegate.viewport != viewport;
}
