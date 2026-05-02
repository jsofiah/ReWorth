import 'package:geolocator/geolocator.dart';
import '../models/location_model.dart';

class LocationService {
  static Future<LocationModel?> getCurrentLocation() async {
    bool serviceEnabled;
    LocationPermission permission;

    // cek GPS aktif
    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return null;

    // cek permission
    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) return null;
    }

    if (permission == LocationPermission.deniedForever) return null;

    // ambil posisi
    final position = await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );

    return LocationModel(
      latitude: position.latitude,
      longitude: position.longitude,
    );
  }
}