import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/location_model.dart';

class NominatimService {
  static Future<List<LocationModel>> search(String query) async {
    final url = Uri.parse(
      'https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=5',
    );

    final response = await http.get(
      url,
      headers: {
        'User-Agent': 'reworth-app', // WAJIB (nominatim rule)
      },
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);

      return (data as List).map((item) {
        return LocationModel(
          latitude: double.parse(item['lat']),
          longitude: double.parse(item['lon']),
          address: item['display_name'],
        );
      }).toList();
    } else {
      return [];
    }
  }
}