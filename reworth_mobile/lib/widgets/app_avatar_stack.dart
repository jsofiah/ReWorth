import 'package:flutter/material.dart';
import '../utils/app_colors.dart';
import '../utils/app_image_helper.dart';

class AvatarStack extends StatelessWidget {
  final List<String> avatars;
  final int maxAvatars;
  final double avatarSize;

  const AvatarStack({
    super.key,
    required this.avatars,
    this.maxAvatars = 3,
    this.avatarSize = 24,
  });

  @override
  Widget build(BuildContext context) {
    final displayList = avatars.take(4).toList();

    return SizedBox(
      width: 90,
      height: 24,
      child: Stack(
        children: [
          ...List.generate(displayList.length, (i) {
            final url = AppImageHelper.fotoProfil(displayList[i]);

            return Positioned(
              left: i * 16.0,
              child: Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.white,
                    width: 1.5,
                  ),
                  image: url.isNotEmpty
                      ? DecorationImage(
                          image: NetworkImage(url),
                          fit: BoxFit.cover,
                        )
                      : null,
                  color: url.isEmpty
                      ? AppColors.primary.withOpacity(0.4)
                      : null,
                ),
                child: url.isEmpty
                    ? const Icon(
                        Icons.person,
                        size: 13,
                        color: Colors.white,
                      )
                    : null,
              ),
            );
          }),

          if (avatars.length > 4)
            Positioned(
              left: 4 * 16.0,
              child: Container(
                width: 24,
                height: 24,
                decoration: const BoxDecoration(
                  color: Colors.black54,
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    '+${avatars.length - 4}',
                    style: const TextStyle(
                      fontSize: 10,
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}