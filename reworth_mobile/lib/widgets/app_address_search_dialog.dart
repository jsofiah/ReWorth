import 'package:flutter/material.dart';
import '../services/nominatim_service.dart';
import '../models/location_model.dart';

class AddressSearchDialog extends StatefulWidget {
  final Function(LocationModel) onSelected;

  const AddressSearchDialog({super.key, required this.onSelected});

  @override
  State<AddressSearchDialog> createState() => _AddressSearchDialogState();
}

class _AddressSearchDialogState extends State<AddressSearchDialog> {
  final TextEditingController _searchController = TextEditingController();
  List<LocationModel> _results = [];
  bool _isLoading = false;

  Future<void> _search(String value) async {
    if (value.length < 3) return;

    setState(() => _isLoading = true);

    final res = await NominatimService.search(value);

    setState(() {
      _results = res;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text("Cari alamat"),
      content: SizedBox(
        width: double.maxFinite,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _searchController,
              decoration: const InputDecoration(
                hintText: "Ketik alamat...",
              ),
              onChanged: _search,
            ),

            const SizedBox(height: 10),

            if (_isLoading)
              const CircularProgressIndicator(),

            if (!_isLoading)
              SizedBox(
                height: 250,
                child: ListView.builder(
                  itemCount: _results.length,
                  itemBuilder: (context, index) {
                    final item = _results[index];

                    return ListTile(
                      title: Text(
                        item.address ?? '',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      onTap: () {
                        widget.onSelected(item);
                        Navigator.pop(context);
                      },
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}