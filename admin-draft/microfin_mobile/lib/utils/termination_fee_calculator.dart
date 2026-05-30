import 'dart:convert';
import 'package:http/http.dart' as http;
import 'api_config.dart';
import '../main.dart';

/// Termination Fee Calculator
/// Calculates early settlement fees by calling the backend API
class TerminationFeeCalculator {
  /// Calculate termination fee by calling backend API
  /// 
  /// Parameters:
  /// - loanNumber: The loan number
  /// 
  /// Returns a Future with a map containing:
  /// - 'fee': The calculated fee amount (can be negative for rebates)
  /// - 'description': Human-readable description of the fee
  /// - 'totalSettlement': The total amount to pay (remaining balance + fee)
  /// - 'rebate': The rebate amount
  /// - 'remaining_balance': The remaining balance
  /// - 'remaining_principal': The remaining principal
  static Future<Map<String, dynamic>> calculateFee({
    required String loanNumber,
  }) async {
    final tenantId = activeTenant.value.id;
    
    final url = Uri.parse(
      ApiConfig.getUrl('api_calculate_termination_fee.php')
    );
    
    try {
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'loan_number': loanNumber,
          'tenant_id': tenantId,
        }),
      );
      
      final data = jsonDecode(response.body);
      
      if (data['success'] == true) {
        final result = data['data'];
        return {
          'fee': result['fee'] as double,
          'description': result['description'] as String,
          'totalSettlement': result['total_settlement'] as double,
          'rebate': result['rebate'] as double,
          'remaining_balance': result['remaining_balance'] as double,
          'remaining_principal': result['remaining_principal'] as double,
        };
      } else {
        throw Exception(data['message'] ?? 'Failed to calculate fee');
      }
    } catch (e) {
      // Fallback to local calculation if API fails
      return _calculateFeeLocally(loanNumber);
    }
  }

  /// Fallback local calculation (used only if API fails)
  static Map<String, dynamic> _calculateFeeLocally(String loanNumber) {
    // This is a fallback - in production, API should always be used
    return {
      'fee': 0.0,
      'description': 'Calculation failed - please try again',
      'totalSettlement': 0.0,
      'rebate': 0.0,
      'remaining_balance': 0.0,
      'remaining_principal': 0.0,
    };
  }

  /// Get a human-readable description of the fee type and value
  /// (for display in UI without calculating the actual fee)
  static String getFeeDescription({
    required String feeType,
    required double feeValue,
  }) {
    switch (feeType) {
      case 'remaining_balance_pct':
        return '${feeValue.toStringAsFixed(2)}% of remaining balance';
      case 'remaining_principal_pct':
        return '${feeValue.toStringAsFixed(2)}% of remaining principal';
      case 'fixed':
        return '₱${feeValue.toStringAsFixed(2)}';
      case 'rebate_only':
        return 'Rebate only (no fee)';
      case 'rebate_plus_pct':
        return 'Rebate + ${feeValue.toStringAsFixed(2)}%';
      case 'rebate_plus_fixed':
        return 'Rebate + ₱${feeValue.toStringAsFixed(2)}';
      case 'no_early_settlement_changes':
        return 'Not applicable';
      default:
        return 'N/A';
    }
  }

  /// Check if early settlement is available for this fee type
  static bool isEarlySettlementAvailable(String feeType) {
    return feeType != 'no_early_settlement_changes';
  }
}
