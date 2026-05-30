<?php
/**
 * Credit Score Engine
 * 
 * A tenant-aware, dynamic credit score calculator that reads ALL configuration
 * from the system_settings table. Zero hardcoded values.
 * 
 * Responsibilities:
 *   1. getStartingScore()           — Returns the tenant's initial credit score for new borrowers
 *   2. calculateRepaymentBonus()    — Score increase after a successful repayment cycle
 *   3. calculateLatePenalty()       — Score decrease after a late payment
 *   4. evaluateUpgradeBonuses()     — Milestone bonuses (clean streaks, cycle counts)
 *   5. evaluateDowngradePenalties() — Penalty triggers (overdue thresholds)
 * 
 * This engine is "Logic Only" — it returns score deltas but does NOT save to the database.
 * The calling API file is responsible for persisting the results.
 */

class CreditScoreEngine
{
    private array $core = [];
    private array $upgradeRules = [];
    private array $downgradeRules = [];
    private string $tenantId;

    /**
     * Initialize the engine by loading the tenant's scoring settings from system_settings.
     *
     * @param PDO|mysqli $db          Database connection (supports both PDO and mysqli)
     * @param string     $tenantId    The tenant whose settings to load
     * @throws RuntimeException       If no settings are found for this tenant
     */
    public function __construct($db, string $tenantId)
    {
        $this->tenantId = $tenantId;

        $settingsJson = $this->fetchSettingValue($db, $tenantId, 'policy_console_credit_limits');

        if ($settingsJson === null || $settingsJson === '') {
            throw new RuntimeException(
                "CreditScoreEngine: No 'policy_console_credit_limits' settings found for tenant '{$tenantId}'. "
                . "Please configure scoring in the Admin Panel → Policy Console → Credit & Limits tab."
            );
        }

        $settings = json_decode($settingsJson, true);
        if (!is_array($settings)) {
            throw new RuntimeException(
                "CreditScoreEngine: Invalid JSON in 'policy_console_credit_limits' for tenant '{$tenantId}'."
            );
        }

        $scoringSetup           = $settings['scoring_setup'] ?? [];
        $this->core             = $scoringSetup['core'] ?? [];
        $this->upgradeRules     = $scoringSetup['detailed_rules']['upgrade'] ?? [];
        $this->downgradeRules   = $scoringSetup['detailed_rules']['downgrade'] ?? [];
    }

    // ─── PUBLIC API ──────────────────────────────────────────────────────────────

    /**
     * Get the tenant's starting credit score for new borrowers.
     *
     * @return int
     */
    public function getStartingScore(): int
    {
        return (int) ($this->core['starting_credit_score'] ?? 0);
    }

    /**
     * Calculate the score bonus for a successful repayment cycle.
     *
     * @return array {
     *     'points'  => int,  // Points to add
     *     'reason'  => string,
     * }
     */
    public function calculateRepaymentBonus(): array
    {
        $bonus = (int) ($this->core['repayment_score_bonus'] ?? 0);

        return [
            'points' => $bonus,
            'reason' => "Successful repayment cycle: +{$bonus} points",
        ];
    }

    /**
     * Calculate the score penalty for a late payment.
     *
     * @return array {
     *     'points'  => int,  // Points to subtract (returned as positive number)
     *     'reason'  => string,
     * }
     */
    public function calculateLatePenalty(): array
    {
        $penalty = (int) ($this->core['late_payment_score_penalty'] ?? 0);

        return [
            'points' => $penalty,
            'reason' => "Late payment penalty: -{$penalty} points",
        ];
    }

    /**
     * Evaluate milestone-based upgrade bonuses.
     * 
     * Checks the borrower's repayment history against the tenant's upgrade rules
     * and returns all applicable bonuses.
     *
     * @param int $successfulCycles     Total successful repayment cycles completed
     * @param int $latePaymentsInPeriod Late payments within the review period
     * @param bool $hasActiveOverdue    Whether the borrower currently has overdue loans
     * @return array List of applicable bonuses, each with 'rule', 'points', 'reason'
     */
    public function evaluateUpgradeBonuses(
        int $successfulCycles,
        int $latePaymentsInPeriod,
        bool $hasActiveOverdue,
        array $ruleTriggers = []
    ): array {
        $bonuses = [];
        $now = time();

        // Rule 1: Successful Repayment Cycles milestone (CUMULATIVE gating)
        $cycleRule = $this->upgradeRules['successful_repayment_cycles'] ?? [];
        if (!empty($cycleRule['enabled'])) {
            $requiredCycles = (int) ($cycleRule['required_cycles'] ?? 3);
            $scorePoints    = (int) ($cycleRule['score_points'] ?? 0);

            $lastTrigger = $ruleTriggers['upgrade.successful_repayment_cycles'] ?? null;
            $lastCount = (int) ($lastTrigger['cycle_count_at_trigger'] ?? 0);
            $minRequired = $lastCount > 0 ? ($lastCount + $requiredCycles) : $requiredCycles;

            if ($successfulCycles >= $minRequired) {
                $bonuses[] = [
                    'rule'   => 'successful_repayment_cycles',
                    'trigger_key' => 'upgrade.successful_repayment_cycles',
                    'points' => $scorePoints,
                    'reason' => "Completed {$successfulCycles}/{$minRequired} required cycles: +{$scorePoints} points",
                    'meta'   => ['cycle_count_at_trigger' => $successfulCycles],
                ];
            }
        }

        // Rule 2: Maximum Late Payments Review (TIME-BASED cooldown)
        $lateReviewRule = $this->upgradeRules['maximum_late_payments_review'] ?? [];
        if (!empty($lateReviewRule['enabled'])) {
            $maxAllowed  = (int) ($lateReviewRule['maximum_allowed'] ?? 1);
            $scorePoints = (int) ($lateReviewRule['score_points'] ?? 0);
            $cooldownDays = (int) ($lateReviewRule['review_period_days'] ?? 30);
            if ($cooldownDays <= 0) $cooldownDays = 30;

            if ($latePaymentsInPeriod <= $maxAllowed
                && $this->isCooldownElapsed($ruleTriggers['upgrade.maximum_late_payments_review'] ?? null, $cooldownDays, $now)
            ) {
                $bonuses[] = [
                    'rule'   => 'maximum_late_payments_review',
                    'trigger_key' => 'upgrade.maximum_late_payments_review',
                    'points' => $scorePoints,
                    'reason' => "Late payments ({$latePaymentsInPeriod}) within allowed maximum ({$maxAllowed}): +{$scorePoints} points",
                ];
            }
        }

        // Rule 3: No Active Overdue (TIME-BASED cooldown)
        $overdueRule = $this->upgradeRules['no_active_overdue'] ?? [];
        if (!empty($overdueRule['enabled'])) {
            $scorePoints = (int) ($overdueRule['score_points'] ?? 0);
            $cooldownDays = (int) ($overdueRule['review_period_days'] ?? 30);
            if ($cooldownDays <= 0) $cooldownDays = 30;

            if (!$hasActiveOverdue
                && $this->isCooldownElapsed($ruleTriggers['upgrade.no_active_overdue'] ?? null, $cooldownDays, $now)
            ) {
                $bonuses[] = [
                    'rule'   => 'no_active_overdue',
                    'trigger_key' => 'upgrade.no_active_overdue',
                    'points' => $scorePoints,
                    'reason' => "No active overdue loans: +{$scorePoints} points",
                ];
            }
        }

        return $bonuses;
    }

    /**
     * Check whether a rule's cooldown period has elapsed since last trigger.
     */
    private function isCooldownElapsed(?array $lastTrigger, int $cooldownDays, int $now): bool
    {
        if (!$lastTrigger || empty($lastTrigger['last_triggered_at'])) {
            return true;
        }
        $lastTs = strtotime($lastTrigger['last_triggered_at']);
        if ($lastTs === false) return true;
        return ($now - $lastTs) >= ($cooldownDays * 86400);
    }

    /**
     * Evaluate penalty triggers for score downgrades.
     *
     * @param int $latePaymentsInPeriod Late payments within the review period
     * @param int $maxOverdueDays       Maximum consecutive overdue days
     * @return array List of applicable penalties, each with 'rule', 'points', 'reason'
     */
    public function evaluateDowngradePenalties(
        int $latePaymentsInPeriod,
        int $maxOverdueDays,
        array $ruleTriggers = []
    ): array {
        $penalties = [];
        $now = time();

        // Rule 1: Late Payments Review (TIME-BASED cooldown)
        $lateRule = $this->downgradeRules['late_payments_review'] ?? [];
        if (!empty($lateRule['enabled'])) {
            $triggerCount = (int) ($lateRule['trigger_count'] ?? 2);
            $scorePoints  = (int) ($lateRule['score_points'] ?? 0);
            $cooldownDays = (int) ($lateRule['review_period_days'] ?? 30);
            if ($cooldownDays <= 0) $cooldownDays = 30;

            if ($latePaymentsInPeriod >= $triggerCount
                && $this->isCooldownElapsed($ruleTriggers['downgrade.late_payments_review'] ?? null, $cooldownDays, $now)
            ) {
                $penalties[] = [
                    'rule'   => 'late_payments_review',
                    'trigger_key' => 'downgrade.late_payments_review',
                    'points' => $scorePoints,
                    'reason' => "Late payments ({$latePaymentsInPeriod}) hit trigger ({$triggerCount}): -{$scorePoints} points",
                ];
            }
        }

        // Rule 2: Overdue Days Threshold (TIME-BASED cooldown, default 30 days)
        $overdueRule = $this->downgradeRules['overdue_days_threshold'] ?? [];
        if (!empty($overdueRule['enabled'])) {
            $dayThreshold = (int) ($overdueRule['days'] ?? 15);
            $scorePoints  = (int) ($overdueRule['score_points'] ?? 0);
            $cooldownDays = (int) ($overdueRule['review_period_days'] ?? 30);
            if ($cooldownDays <= 0) $cooldownDays = 30;

            if ($maxOverdueDays >= $dayThreshold
                && $this->isCooldownElapsed($ruleTriggers['downgrade.overdue_days_threshold'] ?? null, $cooldownDays, $now)
            ) {
                $penalties[] = [
                    'rule'   => 'overdue_days_threshold',
                    'trigger_key' => 'downgrade.overdue_days_threshold',
                    'points' => $scorePoints,
                    'reason' => "Overdue {$maxOverdueDays} days (threshold: {$dayThreshold}): -{$scorePoints} points",
                ];
            }
        }

        return $penalties;
    }

    /**
     * Calculate the net score change given a borrower's current behavior metrics.
     * This is a convenience method that combines all upgrade and downgrade evaluations.
     *
     * @param int  $successfulCycles
     * @param int  $latePaymentsInPeriod
     * @param bool $hasActiveOverdue
     * @param int  $maxOverdueDays
     * @return array {
     *     'net_change' => int,
     *     'bonuses'    => array,
     *     'penalties'  => array,
     *     'breakdown'  => string,
     * }
     */
    public function calculateNetScoreChange(
        int $successfulCycles,
        int $latePaymentsInPeriod,
        bool $hasActiveOverdue,
        int $maxOverdueDays,
        array $ruleTriggers = []
    ): array {
        $bonuses   = $this->evaluateUpgradeBonuses($successfulCycles, $latePaymentsInPeriod, $hasActiveOverdue, $ruleTriggers);
        $penalties = $this->evaluateDowngradePenalties($latePaymentsInPeriod, $maxOverdueDays, $ruleTriggers);

        $totalBonus   = array_sum(array_column($bonuses, 'points'));
        $totalPenalty = array_sum(array_column($penalties, 'points'));
        $netChange    = $totalBonus - $totalPenalty;

        $parts = [];
        foreach ($bonuses as $b) {
            $parts[] = "+{$b['points']} ({$b['rule']})";
        }
        foreach ($penalties as $p) {
            $parts[] = "-{$p['points']} ({$p['rule']})";
        }

        return [
            'net_change' => $netChange,
            'bonuses'    => $bonuses,
            'penalties'  => $penalties,
            'breakdown'  => implode(', ', $parts) ?: 'No score changes triggered.',
        ];
    }

    /**
     * Get the full loaded configuration (for audit/snapshot purposes).
     *
     * @return array
     */
    public function getConfigSnapshot(): array
    {
        return [
            'tenant_id'       => $this->tenantId,
            'core'            => $this->core,
            'upgrade_rules'   => $this->upgradeRules,
            'downgrade_rules' => $this->downgradeRules,
            'snapshot_time'   => date('Y-m-d H:i:s'),
        ];
    }

    // ─── DATABASE HELPER METHODS ─────────────────────────────────────────────────────

    /**
     * Calculate successful repayment cycles for a client.
     * Counts fully paid loans from the loans table.
     *
     * @param PDO|mysqli $db        Database connection
     * @param string     $tenantId  Tenant ID
     * @param int        $clientId  Client ID
     * @return int Number of successful repayment cycles (fully paid loans)
     */
    public static function calculateSuccessfulCycles($db, string $tenantId, int $clientId): int
    {
        if ($db instanceof \PDO) {
            $stmt = $db->prepare('
                SELECT COUNT(*) as count
                FROM loans
                WHERE tenant_id = ? AND client_id = ? AND loan_status = ?
            ');
            $stmt->execute([$tenantId, $clientId, 'Fully Paid']);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        }

        if ($db instanceof \mysqli) {
            $stmt = $db->prepare('
                SELECT COUNT(*) as count
                FROM loans
                WHERE tenant_id = ? AND client_id = ? AND loan_status = ?
            ');
            $stmt->bind_param('sis', $tenantId, $clientId, $status);
            $status = 'Fully Paid';
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int) ($result['count'] ?? 0);
        }

        throw new RuntimeException('CreditScoreEngine: Unsupported database connection type.');
    }

    /**
     * Count late payments within a review period from amortization_schedule.
     *
     * @param PDO|mysqli $db           Database connection
     * @param string     $tenantId     Tenant ID
     * @param int        $clientId     Client ID
     * @param int        $reviewDays   Review period in days
     * @return int Number of late payments within the review period
     */
    public static function countLatePaymentsInPeriod($db, string $tenantId, int $clientId, int $reviewDays): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$reviewDays} days"));

        if ($db instanceof \PDO) {
            $stmt = $db->prepare('
                SELECT COUNT(*) as count
                FROM amortization_schedule as_sched
                JOIN loans l ON as_sched.loan_id = l.loan_id
                WHERE l.tenant_id = ? AND l.client_id = ?
                  AND as_sched.due_date >= ?
                  AND as_sched.payment_status = ? AND as_sched.days_late > 0
            ');
            $stmt->execute([$tenantId, $clientId, $cutoffDate, 'Late']);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        }

        if ($db instanceof \mysqli) {
            $stmt = $db->prepare('
                SELECT COUNT(*) as count
                FROM amortization_schedule as_sched
                JOIN loans l ON as_sched.loan_id = l.loan_id
                WHERE l.tenant_id = ? AND l.client_id = ?
                  AND as_sched.due_date >= ?
                  AND as_sched.payment_status = ? AND as_sched.days_late > 0
            ');
            $stmt->bind_param('siss', $tenantId, $clientId, $cutoffDate, $status);
            $status = 'Late';
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int) ($result['count'] ?? 0);
        }

        throw new RuntimeException('CreditScoreEngine: Unsupported database connection type.');
    }

    /**
     * Check if client has active overdue loans.
     *
     * @param PDO|mysqli $db        Database connection
     * @param string     $tenantId  Tenant ID
     * @param int        $clientId  Client ID
     * @return bool True if client has active overdue loans
     */
    public static function hasActiveOverdue($db, string $tenantId, int $clientId): bool
    {
        if ($db instanceof \PDO) {
            $stmt = $db->prepare('
                SELECT COUNT(*) as count
                FROM loans
                WHERE tenant_id = ? AND client_id = ? AND loan_status = ? AND days_overdue > 0
            ');
            $stmt->execute([$tenantId, $clientId, 'Active']);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ((int) ($result['count'] ?? 0)) > 0;
        }

        if ($db instanceof \mysqli) {
            $stmt = $db->prepare('
                SELECT COUNT(*) as count
                FROM loans
                WHERE tenant_id = ? AND client_id = ? AND loan_status = ? AND days_overdue > 0
            ');
            $stmt->bind_param('sis', $tenantId, $clientId, $status);
            $status = 'Active';
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return ((int) ($result['count'] ?? 0)) > 0;
        }

        throw new RuntimeException('CreditScoreEngine: Unsupported database connection type.');
    }

    /**
     * Get maximum overdue days for active loans.
     *
     * @param PDO|mysqli $db        Database connection
     * @param string     $tenantId  Tenant ID
     * @param int        $clientId  Client ID
     * @return int Maximum overdue days (0 if no overdue loans)
     */
    public static function getMaxOverdueDays($db, string $tenantId, int $clientId): int
    {
        if ($db instanceof \PDO) {
            $stmt = $db->prepare('
                SELECT COALESCE(MAX(days_overdue), 0) as max_days
                FROM loans
                WHERE tenant_id = ? AND client_id = ? AND loan_status = ? AND days_overdue > 0
            ');
            $stmt->execute([$tenantId, $clientId, 'Active']);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['max_days'] ?? 0);
        }

        if ($db instanceof \mysqli) {
            $stmt = $db->prepare('
                SELECT COALESCE(MAX(days_overdue), 0) as max_days
                FROM loans
                WHERE tenant_id = ? AND client_id = ? AND loan_status = ? AND days_overdue > 0
            ');
            $stmt->bind_param('sis', $tenantId, $clientId, $status);
            $status = 'Active';
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int) ($result['max_days'] ?? 0);
        }

        throw new RuntimeException('CreditScoreEngine: Unsupported database connection type.');
    }

    /**
     * Calculate score changes for a client without persisting to database.
     * Returns old score, new score, bonuses, penalties, and new credit limit.
     *
     * @param PDO|mysqli $db        Database connection
     * @param string     $tenantId  Tenant ID
     * @param int        $clientId  Client ID
     * @return array {
     *     'old_score' => int,
     *     'new_score' => int,
     *     'net_change' => int,
     *     'bonuses' => array,
     *     'penalties' => array,
     *     'breakdown' => string,
     *     'new_limit' => float,
     *     'metrics' => array,
     *     'config_snapshot' => array
     * }
     */
    public function calculateScoreChanges($db, string $tenantId, int $clientId): array
    {
        // Get current score and existing trigger history from credit_scores table
        $currentScore = 0;
        $existingMetadata = [];
        if ($db instanceof \PDO) {
            $stmt = $db->prepare('
                SELECT credit_score, score_metadata
                FROM credit_scores
                WHERE tenant_id = ? AND client_id = ?
                ORDER BY computation_date DESC
                LIMIT 1
            ');
            $stmt->execute([$tenantId, $clientId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $currentScore = (int) ($result['credit_score'] ?? $this->getStartingScore());
            $existingMetadata = !empty($result['score_metadata'])
                ? (json_decode($result['score_metadata'], true) ?: [])
                : [];
        } elseif ($db instanceof \mysqli) {
            $stmt = $db->prepare('
                SELECT credit_score, score_metadata
                FROM credit_scores
                WHERE tenant_id = ? AND client_id = ?
                ORDER BY computation_date DESC
                LIMIT 1
            ');
            $stmt->bind_param('si', $tenantId, $clientId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $currentScore = (int) ($result['credit_score'] ?? $this->getStartingScore());
            $existingMetadata = !empty($result['score_metadata'])
                ? (json_decode($result['score_metadata'], true) ?: [])
                : [];
        }

        $ruleTriggers = $existingMetadata['rule_triggers'] ?? [];

        // Calculate metrics
        $successfulCycles = self::calculateSuccessfulCycles($db, $tenantId, $clientId);
        $reviewDays = (int) ($this->upgradeRules['maximum_late_payments_review']['review_period_days'] ?? 90);
        $latePaymentsInPeriod = self::countLatePaymentsInPeriod($db, $tenantId, $clientId, $reviewDays);
        $hasActiveOverdue = self::hasActiveOverdue($db, $tenantId, $clientId);
        $maxOverdueDays = self::getMaxOverdueDays($db, $tenantId, $clientId);

        // Calculate net score change (pass rule_triggers for gating)
        $scoreChange = $this->calculateNetScoreChange(
            $successfulCycles,
            $latePaymentsInPeriod,
            $hasActiveOverdue,
            $maxOverdueDays,
            $ruleTriggers
        );

        // Build updated rule_triggers map (merge new triggers into existing)
        $nowStr = date('Y-m-d H:i:s');
        $updatedTriggers = $ruleTriggers;
        foreach (array_merge($scoreChange['bonuses'], $scoreChange['penalties']) as $entry) {
            $key = $entry['trigger_key'] ?? null;
            if (!$key) continue;
            $prev = $updatedTriggers[$key] ?? [];
            $updatedTriggers[$key] = array_merge($prev, [
                'last_triggered_at' => $nowStr,
                'score_points_awarded' => $entry['points'] ?? 0,
                'times_triggered' => (int) ($prev['times_triggered'] ?? 0) + 1,
            ], $entry['meta'] ?? []);
        }

        $newScore = $currentScore + $scoreChange['net_change'];
        $newScore = max(0, $newScore); // Ensure score doesn't go below 0

        // Fetch monthly_income, current credit_limit, and policy_metadata.
        // The BASELINE limit (the original initial assessment limit) is stored in
        // clients.policy_metadata.limit_calculation.limit and is the FIXED reference for growth.
        // score_bands snapshot inside policy_metadata is IGNORED — always read fresh from system_settings.
        $monthlyIncome = 0;
        $currentLimit = 0;   // active limit currently on clients.credit_limit
        $baselineLimit = 0;  // original initial limit from policy_metadata
        $policyMetaRaw = '';
        if ($db instanceof \PDO) {
            $stmt = $db->prepare('SELECT monthly_income, credit_limit, policy_metadata FROM clients WHERE tenant_id = ? AND client_id = ? LIMIT 1');
            $stmt->execute([$tenantId, $clientId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $monthlyIncome = (float) ($result['monthly_income'] ?? 0);
            $currentLimit = (float) ($result['credit_limit'] ?? 0);
            $policyMetaRaw = (string) ($result['policy_metadata'] ?? '');
        } elseif ($db instanceof \mysqli) {
            $stmt = $db->prepare('SELECT monthly_income, credit_limit, policy_metadata FROM clients WHERE tenant_id = ? AND client_id = ? LIMIT 1');
            $stmt->bind_param('si', $tenantId, $clientId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            $monthlyIncome = (float) ($result['monthly_income'] ?? 0);
            $currentLimit = (float) ($result['credit_limit'] ?? 0);
            $policyMetaRaw = (string) ($result['policy_metadata'] ?? '');
        }

        $policyMeta = $policyMetaRaw !== '' ? (json_decode($policyMetaRaw, true) ?: []) : [];
        $baselineLimit = (float) ($policyMeta['limit_calculation']['limit']
            ?? $policyMeta['potential_limit']
            ?? $currentLimit);
        $startingScore = (int) ($policyMeta['limit_calculation']['starting_score']
            ?? $policyMeta['starting_score']
            ?? $this->getStartingScore());

        // Calculate new credit limit using MICROPERCENT ONLY formula.
        // Formula: Score_diff = current_score - starting_score
        //           Find band for current_score → get micro_percent_per_point
        //           Total_growth% = Score_diff × micro_percent_per_point
        //           new_limit = baseline × (1 + Total_growth% / 100)
        $newLimit = 0;
        try {
            // Load score_bands fresh from system_settings (policy_console_credit_limits)
            $settingsJson = $this->fetchSettingValue($db, $tenantId, 'policy_console_credit_limits');
            if ($settingsJson) {
                $settings = json_decode($settingsJson, true);
                $scoreBands = $settings['score_bands']['rows'] ?? [];

                // Find the band for the new score
                $microPercent = 0;
                foreach ($scoreBands as $band) {
                    $min = (int) ($band['min_score'] ?? 0);
                    $max = $band['max_score'] !== null ? (int) $band['max_score'] : PHP_INT_MAX;
                    if ($newScore >= $min && $newScore <= $max) {
                        $microPercent = (float) ($band['micro_percent_per_point'] ?? 0);
                        break;
                    }
                }

                // Calculate growth based on micropercent only
                $scoreDiff = $newScore - $startingScore;
                $totalGrowthPercent = $scoreDiff * $microPercent;
                $newLimit = $baselineLimit * (1 + $totalGrowthPercent / 100);
            }
        } catch (\Throwable $e) {
            $newLimit = 0;
        }

        // Determine if any upgrade/downgrade rule triggered.
        // Limit should only be updated if a rule fired AND the recalculated limit differs from the active one.
        $rulesTriggered = count($scoreChange['bonuses']) > 0 || count($scoreChange['penalties']) > 0;
        $shouldUpdateLimit = $rulesTriggered
            && $newLimit > 0
            && abs($newLimit - $currentLimit) > 0.01;

        // Merge updated rule_triggers into existing metadata for persistence
        $updatedMetadata = array_merge($existingMetadata, [
            'rule_triggers' => $updatedTriggers,
            'last_evaluation_at' => $nowStr,
        ]);

        return [
            'old_score' => $currentScore,
            'new_score' => $newScore,
            'net_change' => $scoreChange['net_change'],
            'bonuses' => $scoreChange['bonuses'],
            'penalties' => $scoreChange['penalties'],
            'breakdown' => $scoreChange['breakdown'],
            'old_limit' => $currentLimit,
            'baseline_limit' => $baselineLimit,
            'new_limit' => $newLimit,
            'should_update_limit' => $shouldUpdateLimit,
            'rules_triggered' => $rulesTriggered,
            'rule_triggers' => $updatedTriggers,
            'score_metadata' => $updatedMetadata,
            'metrics' => [
                'successful_cycles' => $successfulCycles,
                'late_payments_in_period' => $latePaymentsInPeriod,
                'has_active_overdue' => $hasActiveOverdue,
                'max_overdue_days' => $maxOverdueDays,
                'review_period_days' => $reviewDays,
            ],
            'config_snapshot' => $this->getConfigSnapshot(),
        ];
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────────────

    /**
     * Fetch a single setting_value from system_settings.
     * Supports both PDO and mysqli connections.
     */
    private function fetchSettingValue($db, string $tenantId, string $key): ?string
    {
        if ($db instanceof \PDO) {
            $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
            $stmt->execute([$tenantId, $key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string) $val : null;
        }

        if ($db instanceof \mysqli) {
            $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
            $stmt->bind_param('ss', $tenantId, $key);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row ? (string) $row['setting_value'] : null;
        }

        throw new RuntimeException('CreditScoreEngine: Unsupported database connection type.');
    }
}
