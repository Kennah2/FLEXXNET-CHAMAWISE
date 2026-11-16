import 'package:chama_wise/core/models/chama.dart';
import 'package:chama_wise/core/models/contribution.dart';
import 'package:chama_wise/core/models/withdrawal.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Chama', () {
    test('parses the API shape including my_roles and my_permissions', () {
      final chama = Chama.fromJson({
        'id': 1,
        'name': 'Ken and Frank Savings Group',
        'slug': 'ken-and-frank-savings-group',
        'description': null,
        'group_type': 'savings',
        'currency': 'KES',
        'status': 'active',
        'formation_date': '2026-10-01',
        'my_roles': ['chairperson'],
        'my_permissions': ['chama.manage-finances', 'chama.view-reports'],
      });

      expect(chama.name, 'Ken and Frank Savings Group');
      expect(chama.myRoles, ['chairperson']);
      expect(chama.canLikelyManageFinances, isTrue);
    });

    test('defaults my_roles to empty when absent, rather than throwing', () {
      final chama = Chama.fromJson({
        'id': 1,
        'name': 'X',
        'slug': 'x',
        'group_type': 'savings',
        'currency': 'KES',
        'status': 'active',
      });

      expect(chama.myRoles, isEmpty);
      expect(chama.canLikelyManageFinances, isFalse);
    });
  });

  group('ContributionsOverview', () {
    test('parses obligations, payments and pending verifications', () {
      final overview = ContributionsOverview.fromJson({
        'can_manage_finances': true,
        'outstanding_cents': 10000,
        'outstanding_formatted': 'KES 100.00',
        'obligations': [
          {
            'id': 1,
            'schedule_name': 'Daily savings',
            'due_at': '2026-11-11T20:59:59+00:00',
            'outstanding_cents': 10000,
            'outstanding_formatted': 'KES 100.00',
            'status': 'pending',
            'penalty_amount_cents': 0,
            'penalty_status': 'none',
          },
        ],
        'payments': [],
        'pending_verifications': [
          {
            'id': 5,
            'user': {'name': 'Frank'},
            'amount_formatted': 'KES 50.00',
            'payment_method_label': 'Cash',
            'paid_at': null,
            'status': 'pending_verification',
          },
        ],
      });

      expect(overview.canManageFinances, isTrue);
      expect(overview.obligations, hasLength(1));
      expect(overview.obligations.first.hasPenalty, isFalse);
      expect(overview.pendingVerifications.single.userName, 'Frank');
    });
  });

  group('WithdrawalsOverview', () {
    test('parses my_balance_formatted and all three request queues', () {
      final overview = WithdrawalsOverview.fromJson({
        'can_manage_finances': false,
        'my_balance_formatted': 'KES 100.00',
        'my_requests': [
          {
            'id': 1,
            'amount_formatted': 'KES 40.00',
            'required_approvals': 2,
            'approvals_count': 0,
            'status': 'pending',
          },
        ],
        'pending_approvals': [],
        'ready_to_pay': [],
      });

      expect(overview.myBalanceFormatted, 'KES 100.00');
      expect(overview.myRequests.single.status, 'pending');
      expect(overview.pendingApprovals, isEmpty);
    });
  });
}
