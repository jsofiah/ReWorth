import 'dart:convert';
import 'package:http/http.dart' as http;

class GeocodingService {
  static Future<String?> getDistrictFromCoordinates(double lat, double lng) async {
    try {
      final url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&zoom=18&addressdetails=1';
      
      final response = await http.get(Uri.parse(url), headers: {
        'User-Agent': 'ReWorthApp/1.0',
      });
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final address = data['address'];
        
        String? district = address['suburb'] ?? 
                          address['city_district'] ?? 
                          address['town'] ?? 
                          address['city'];
        
        return district;
      }
      return null;
    } catch (e) {
      print('Geocoding error: $e');
      return null;
    }
  }
  
  static Future<int?> getDistrictIdByName(String districtName) async {
    try {
      final response = await http.get(
        Uri.parse('https://api.rajaongkir.com/starter/city?province=35'),
        headers: {'key': '99bGIm1f37ba0ba707716c27oHaKPpc8'},
      );
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final cities = data['rajaongkir']['results'] as List;
        
        final found = cities.firstWhere(
          (c) => c['city_name'].toLowerCase().contains(districtName.toLowerCase()),
          orElse: () => null,
        );
        
        if (found != null) {
          return found['city_id'] as int;
        }
      }
      return null;
    } catch (e) {
      print('Error get district id: $e');
      return null;
    }
  }
}