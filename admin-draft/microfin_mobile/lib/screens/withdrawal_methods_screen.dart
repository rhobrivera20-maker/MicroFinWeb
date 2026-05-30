import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../theme.dart';
import '../main.dart';
import '../utils/app_dialogs.dart';

class WithdrawalMethodsScreen extends StatefulWidget {
  const WithdrawalMethodsScreen({super.key});

  @override
  State<WithdrawalMethodsScreen> createState() => _WithdrawalMethodsScreenState();
}

class _WithdrawalMethodsScreenState extends State<WithdrawalMethodsScreen> {
  List<Map<String, dynamic>> _savedMethods = [];
  
  // Form controllers for adding new method
  String _selectedMethod = 'GCash';
  final _gcashNumberCtrl = TextEditingController();
  final _bankNameCtrl = TextEditingController();
  final _accountNumberCtrl = TextEditingController();
  final _accountNameCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadPreferences();
  }

  @override
  void dispose() {
    _gcashNumberCtrl.dispose();
    _bankNameCtrl.dispose();
    _accountNumberCtrl.dispose();
    _accountNameCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadPreferences() async {
    final prefs = await SharedPreferences.getInstance();
    final savedData = prefs.getString('withdrawal_preferences');
    
    if (savedData != null) {
      try {
        final data = jsonDecode(savedData);
        if (data is List) {
          if (mounted) {
            setState(() {
              _savedMethods = (data as List).cast<Map<String, dynamic>>();
            });
          }
        }
      } catch (e) {
        // If parsing fails, use empty list
      }
    }
  }

  Future<void> _savePreferences() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('withdrawal_preferences', jsonEncode(_savedMethods));
    
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.check_circle, color: activeTenant.value.themePrimaryColor, size: 48),
            const SizedBox(height: 16),
            const Text(
              'Method Saved',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            const Text(
              'Your withdrawal method has been added.',
              style: TextStyle(fontSize: 14, color: Color(0xFF6B7280)),
              textAlign: TextAlign.center,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _addMethod() {
    // Validate based on selected method
    if (_selectedMethod == 'GCash' && _gcashNumberCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter your GCash number')),
      );
      return;
    }
    if (_selectedMethod == 'Bank Transfer') {
      if (_bankNameCtrl.text.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please enter your bank name')),
        );
        return;
      }
      if (_accountNumberCtrl.text.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please enter your account number')),
        );
        return;
      }
      if (_accountNameCtrl.text.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please enter the account holder name')),
        );
        return;
      }
    }

    final newMethod = {
      'id': DateTime.now().millisecondsSinceEpoch.toString(),
      'method': _selectedMethod,
      'gcash_number': _gcashNumberCtrl.text.trim(),
      'bank_name': _bankNameCtrl.text.trim(),
      'account_number': _accountNumberCtrl.text.trim(),
      'account_name': _accountNameCtrl.text.trim(),
      'updated_at': DateTime.now().toIso8601String(),
    };

    setState(() {
      _savedMethods.add(newMethod);
    });

    // Clear form
    _gcashNumberCtrl.clear();
    _bankNameCtrl.clear();
    _accountNumberCtrl.clear();
    _accountNameCtrl.clear();

    _savePreferences();
  }

  void _deleteMethod(String id) {
    setState(() {
      _savedMethods.removeWhere((m) => m['id'] == id);
    });
    _savePreferences();
  }

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;

    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        backgroundColor: AppColors.card,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: AppColors.textMain),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Withdrawal Methods',
          style: TextStyle(
            color: AppColors.textMain,
            fontWeight: FontWeight.w700,
            fontSize: 18,
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          // Info banner
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: primary.withOpacity(0.2)),
            ),
            child: Row(
              children: [
                Icon(Icons.info_outline_rounded, color: primary, size: 20),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Add multiple withdrawal methods for faster loan disbursements.',
                    style: TextStyle(
                      fontSize: 13,
                      color: AppColors.textSecondary,
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Saved methods list
          if (_savedMethods.isNotEmpty) ...[
            Text(
              'Saved Methods',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: AppColors.textMain,
              ),
            ),
            const SizedBox(height: 12),
            ..._savedMethods.map((method) => _buildSavedMethodCard(method, primary)),
            const SizedBox(height: 24),
          ],

          // Add new method section
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.card,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Add New Method',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textMain,
                  ),
                ),
                const SizedBox(height: 16),

                // Method selection
                _styledDropdown<String>(
                  icon: Icons.payment_outlined,
                  hint: '— Select Method —',
                  value: _selectedMethod,
                  items: ['GCash', 'Bank Transfer', 'Cash Pickup']
                      .map((c) => DropdownMenuItem<String>(value: c, child: Text(c)))
                      .toList(),
                  onChanged: (v) => setState(() => _selectedMethod = v ?? 'GCash'),
                ),
                const SizedBox(height: 16),

                // Dynamic fields based on selection
                if (_selectedMethod == 'GCash') ...[
                  _buildTextField(
                    'GCash Number',
                    'Enter your GCash number',
                    _gcashNumberCtrl,
                    keyboardType: TextInputType.phone,
                    icon: Icons.phone,
                  ),
                ] else if (_selectedMethod == 'Bank Transfer') ...[
                  _buildTextField(
                    'Bank Name',
                    'e.g., BPI, BDO, Metrobank',
                    _bankNameCtrl,
                    icon: Icons.business,
                  ),
                  const SizedBox(height: 16),
                  _buildTextField(
                    'Account Number',
                    'Enter your account number',
                    _accountNumberCtrl,
                    keyboardType: TextInputType.number,
                    icon: Icons.numbers,
                  ),
                  const SizedBox(height: 16),
                  _buildTextField(
                    'Account Holder Name',
                    'Name as it appears on the account',
                    _accountNameCtrl,
                    icon: Icons.person,
                  ),
                ] else if (_selectedMethod == 'Cash Pickup') ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: primary.withOpacity(0.05),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: primary.withOpacity(0.2)),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.info_outline, color: primary, size: 16),
                        const SizedBox(width: 8),
                        const Expanded(
                          child: Text(
                            'Visit our branch to pick up your cash disbursement. Bring a valid ID.',
                            style: TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 16),

                // Add button
                ElevatedButton(
                  onPressed: _addMethod,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 0,
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.add_rounded, size: 18),
                      SizedBox(width: 8),
                      Text(
                        'Add Method',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSavedMethodCard(Map<String, dynamic> method, Color primary) {
    final methodType = method['method'] as String;
    IconData icon;
    String details;

    switch (methodType) {
      case 'GCash':
        icon = Icons.account_balance_wallet;
        details = method['gcash_number'] ?? '';
        break;
      case 'Bank Transfer':
        icon = Icons.account_balance;
        details = '${method['bank_name'] ?? ''} - ${method['account_number'] ?? ''}';
        break;
      case 'Cash Pickup':
        icon = Icons.store;
        details = 'Pick up at branch';
        break;
      default:
        icon = Icons.payment;
        details = '';
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: primary, size: 20),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  methodType,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textMain,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  details,
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.delete_outline, color: Color(0xFFEF4444)),
            onPressed: () => _deleteMethod(method['id'] as String),
          ),
        ],
      ),
    );
  }

  Widget _styledDropdown<T>({
    required IconData icon,
    required String hint,
    required T? value,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<T>(
          isExpanded: true,
          hint: Text(
            hint,
            style: TextStyle(color: AppColors.textSecondary, fontSize: 14),
          ),
          value: value,
          items: items,
          onChanged: onChanged,
          icon: const Icon(Icons.keyboard_arrow_down_rounded),
          style: TextStyle(
            color: AppColors.textMain,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }

  Widget _buildMethodOption(
    String method,
    String description,
    IconData icon,
    Color primary,
  ) {
    final isSelected = _selectedMethod == method;

    return GestureDetector(
      onTap: () => setState(() => _selectedMethod = method),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSelected ? primary.withOpacity(0.1) : AppColors.card,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? primary : AppColors.border,
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: isSelected ? primary : AppColors.border.withOpacity(0.3),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                icon,
                color: isSelected ? Colors.white : AppColors.textSecondary,
                size: 20,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    method,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: isSelected ? primary : AppColors.textMain,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    description,
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
            if (isSelected)
              Icon(Icons.check_circle, color: primary, size: 24)
            else
              Icon(Icons.radio_button_unchecked, color: AppColors.border, size: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildTextField(
    String label,
    String hint,
    TextEditingController controller, {
    TextInputType? keyboardType,
    IconData? icon,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.textMain,
          ),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: controller,
          keyboardType: keyboardType,
          style: TextStyle(
            fontSize: 15,
            color: AppColors.textMain,
          ),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(
              color: AppColors.textSecondary,
            ),
            prefixIcon: icon != null ? Icon(icon, color: AppColors.textSecondary) : null,
            filled: true,
            fillColor: AppColors.card,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: AppColors.border),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: AppColors.border),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: activeTenant.value.themePrimaryColor),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          ),
        ),
      ],
    );
  }
}
