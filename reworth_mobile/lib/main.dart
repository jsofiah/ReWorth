import 'package:flutter/material.dart';
import 'package:uni_links/uni_links.dart';
import 'utils/app_theme.dart';
import 'pages/splash_screen.dart';
import 'pages/login_page.dart';
import 'pages/home_page.dart';
import 'pages/profil_page.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'pages/reset_password_page.dart';

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await Supabase.initialize(
    url: 'https://rxzrbyqqhkxemdjbcntc.supabase.co',
    anonKey: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ',
  );

  runApp(const MyApp());

  setupDeepLinks();
}

void setupDeepLinks() async {
  final Uri? initialLink = await getInitialUri();
  handleDeepLinkString(initialLink as String?);
  
  linkStream.listen(handleDeepLinkString);
}

void handleDeepLinkString(String? uriString) {
  if (uriString == null) return;
  
  final Uri? uri = Uri.tryParse(uriString);
  if (uri?.scheme == 'reworth' && uri?.path == '/reset-password') {
    navigatorKey.currentState?.pushNamed('/reset-password');
  }
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'ReWorth',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      navigatorKey: navigatorKey,
      initialRoute: '/',
      routes: {
        '/': (context) => const SplashScreen(),
        '/login': (context) => const LoginPage(),
        '/home': (context) => const HomePage(),
        '/profil': (context) => const ProfilPage(),
        '/reset-password': (context) => const ResetPasswordPage(),
      },
    );
  }
}