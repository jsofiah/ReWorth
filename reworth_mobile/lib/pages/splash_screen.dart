import 'package:flutter/material.dart';
import 'package:reworth_mobile/pages/login_page.dart';
import '../utils/app_text_styles.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../services/auth_service.dart';
import 'home_page.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  final _supabase = Supabase.instance.client;
  int _stage = 1;

  static const _stages = [
    _StageProps(rotate: 70, scale: 0.4, duration: 0),      // Screen 1
    _StageProps(rotate: -40, scale: 0.75, duration: 400),  // Screen 2
    _StageProps(rotate: 0, scale: 1.15, duration: 800),    // Screen 3 (overshoot)
    _StageProps(rotate: 0, scale: 1.0, duration: 800),     // Screen 4
  ];

  static const _delays = [1, 10, 5];

  bool _showGradient = false;
  bool _showLogoText = false;

  @override
  void initState() {
    super.initState();
    _runAnimation();
  }

  Future<void> _checkSession() async {
    await Future.delayed(const Duration(seconds: 2));

    final user = _supabase.auth.currentUser;

    print("USER LOGIN: $user");

    if (!mounted) return;

    if (user != null) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => LoginPage()),
      );
    }
  }

  Future<void> _runAnimation() async {
    // 1 → 2
    await Future.delayed(Duration(milliseconds: _delays[0]));
    if (!mounted) return;
    setState(() => _stage = 2);

    // 2 → 3
    await Future.delayed(
      Duration(milliseconds: _stages[1].duration + _delays[1]),
    );
    if (!mounted) return;
    setState(() => _stage = 3);

    // 3 → 4
    await Future.delayed(
      Duration(milliseconds: _stages[2].duration + _delays[2]),
    );
    if (!mounted) return;
    setState(() {
      _stage = 4;
      _showGradient = true;
    });

    // show text
    await Future.delayed(const Duration(milliseconds: 200));
    if (!mounted) return;
    setState(() => _showLogoText = true);

    // navigate
    await Future.delayed(const Duration(milliseconds: 1200));
    if (!mounted) return;
    _checkSession();
  }

  @override
  Widget build(BuildContext context) {
    final stageIndex = _stage - 1;
    final props = _stages[stageIndex];
    final prevProps = stageIndex > 0 ? _stages[stageIndex - 1] : props;

    final duration = Duration(milliseconds: props.duration);
    const curve = Curves.easeInOutCubic;

    return Scaffold(
      body: Stack(
        children: [
          //Base putih
          Container(color: Colors.white),

          //Gradient (fade in)
          AnimatedOpacity(
            opacity: _showGradient ? 1 : 0,
            duration: const Duration(milliseconds: 800),
            curve: Curves.easeInOut,
            child: Container(
              decoration: const BoxDecoration(
                image: DecorationImage(
                  image: AssetImage('assets/gradient.png'),
                  fit: BoxFit.cover,
                ),
              ),
            ),
          ),

          //Logo animasi
          Center(
            child: TweenAnimationBuilder<double>(
              tween: Tween<double>(
                begin: _degToRad(prevProps.rotate),
                end: _degToRad(props.rotate),
              ),
              duration: duration,
              curve: curve,
              builder: (context, rotation, _) {
                return TweenAnimationBuilder<double>(
                  tween: Tween<double>(
                    begin: prevProps.scale,
                    end: props.scale,
                  ),
                  duration: duration,
                  curve: curve,
                  builder: (context, scale, _) {
                    return Transform.rotate(
                      angle: rotation,
                      child: Transform.scale(
                        scale: scale,
                        child: _stage < 4
                            // SCREEN 1–3
                            ? Image.asset(
                                'assets/logo.png',
                                width: 90,
                                height: 90,
                              )

                            // SCREEN 4
                            : Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Image.asset(
                                    'assets/logo.png',
                                    width: 70,
                                    height: 70,
                                  ),
                                  const SizedBox(width: 12),

                                  AnimatedOpacity(
                                    opacity: _showLogoText ? 1 : 0,
                                    duration: const Duration(milliseconds: 400),
                                    child: AnimatedSlide(
                                      offset: _showLogoText
                                          ? Offset.zero
                                          : const Offset(0.3, 0),
                                      duration: const Duration(milliseconds: 400),
                                      curve: Curves.easeOut,
                                      child: Text(
                                        "ReWorth",
                                        style: AppTextStyles.headline.copyWith(
                                          fontSize: 40,
                                        )
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  double _degToRad(double deg) => deg * (3.14159265 / 180);
}


class _StageProps {
  final double rotate;
  final double scale;
  final int duration;

  const _StageProps({
    required this.rotate,
    required this.scale,
    required this.duration,
  });
}