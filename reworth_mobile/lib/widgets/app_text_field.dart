import 'package:flutter/material.dart';
import '../utils/app_colors.dart';
import '../utils/app_text_styles.dart';
import '../utils/app_constants.dart';

class AppTextField extends StatefulWidget {
  final String hint;
  final IconData prefixIcon;
  final bool isPassword;
  final TextEditingController? controller;
  final TextInputType keyboardType;
  final String? Function(String?)? validator;
  final int maxLines;

  const AppTextField({
    super.key,
    required this.hint,
    required this.prefixIcon,
    this.isPassword = false,
    this.controller,
    this.keyboardType = TextInputType.text,
    this.validator,
    this.maxLines = 1,
  });

  @override
  State<AppTextField> createState() => _AppTextFieldState();
}

class _AppTextFieldState extends State<AppTextField> {
  bool _obscure = true;
  bool _isFocused = false;

  @override
  Widget build(BuildContext context) {
    return Focus(
      onFocusChange: (focused) => setState(() => _isFocused = focused),
      child: TextFormField(
        controller: widget.controller,
        obscureText: widget.isPassword && _obscure,
        keyboardType: widget.keyboardType,
        validator: widget.validator,
        style: AppTextStyles.inputText,
        maxLines: widget.maxLines,
        decoration: InputDecoration(
          hintText: widget.hint,
          hintStyle: AppTextStyles.hintText,
          prefixIcon: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Icon(
              widget.prefixIcon,
              size: 20,
              color: _isFocused ? AppColors.secondary : AppColors.inputIcon,
            ),
          ),
          prefixIconConstraints: const BoxConstraints(minWidth: 52),
          suffixIcon: widget.isPassword
              ? IconButton(
                  onPressed: () => setState(() => _obscure = !_obscure),
                  icon: Icon(
                    _obscure
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                    size: 20,
                    color: AppColors.inputIcon,
                  ),
                )
              : null,
          filled: true,
          fillColor: AppColors.white,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: AppConstants.paddingM,
            vertical: 16,
          ),
          constraints: const BoxConstraints(
            minHeight: AppConstants.inputHeight,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
            borderSide: const BorderSide(
              color: AppColors.inputBorder,
              width: 1,
            ),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
            borderSide: const BorderSide(
              color: AppColors.secondary,
              width: 1.5,
            ),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
            borderSide: const BorderSide(
              color: Colors.redAccent,
              width: 1,
            ),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppConstants.radiusXL),
            borderSide: const BorderSide(
              color: Colors.redAccent,
              width: 1.5,
            ),
          ),
        ),
      ),
    );
  }
}