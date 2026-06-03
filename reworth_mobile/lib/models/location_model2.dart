class LocationData2 {
  final double latitude;
  final double longitude;
  final String address;
  String? districtName;
  int? districtId;
  
  LocationData2({
    required this.latitude,
    required this.longitude,
    required this.address,
    this.districtName,
    this.districtId,
  });
}