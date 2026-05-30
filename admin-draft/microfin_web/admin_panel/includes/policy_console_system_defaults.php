<?php

if (!function_exists('policy_console_system_defaults')) {
    function policy_console_system_defaults(): array
    {
        return [
            'credit_limits' => [
                'scoring_setup' => [
                    'core' => [
                        'starting_credit_score' => 320,
                        'repayment_score_bonus' => 5,
                        'late_payment_score_penalty' => 12,
                    ],
                    'detailed_rules' => [
                        'upgrade' => [
                            'successful_repayment_cycles' => [
                                'enabled' => true,
                                'required_cycles' => 3,
                                'score_points' => 5,
                            ],
                            'maximum_late_payments_review' => [
                                'enabled' => true,
                                'maximum_allowed' => 1,
                                'review_period_days' => 90,
                                'score_points' => 5,
                            ],
                            'no_active_overdue' => [
                                'enabled' => true,
                                'review_period_days' => 0,
                                'score_points' => 5,
                            ],
                        ],
                        'downgrade' => [
                            'late_payments_review' => [
                                'enabled' => true,
                                'trigger_count' => 2,
                                'review_period_days' => 90,
                                'score_points' => 12,
                            ],
                            'overdue_days_threshold' => [
                                'enabled' => true,
                                'days' => 15,
                                'score_points' => 25,
                            ],
                        ],
                    ],
                ],
                'score_bands' => [
                    'rows' => [
                        [
                            'id' => 'band_at_risk',
                            'label' => 'At-Risk',
                            'min_score' => 50,
                            'max_score' => 249,
                            'base_growth_percent' => 1.0,
                            'micro_percent_per_point' => 0.020,
                        ],
                        [
                            'id' => 'band_entry',
                            'label' => 'Entry',
                            'min_score' => 250,
                            'max_score' => 449,
                            'base_growth_percent' => 5.0,
                            'micro_percent_per_point' => 0.034,
                        ],
                        [
                            'id' => 'band_standard',
                            'label' => 'Standard',
                            'min_score' => 450,
                            'max_score' => 649,
                            'base_growth_percent' => 10.0,
                            'micro_percent_per_point' => 0.025,
                        ],
                        [
                            'id' => 'band_plus',
                            'label' => 'Plus',
                            'min_score' => 650,
                            'max_score' => 849,
                            'base_growth_percent' => 15.0,
                            'micro_percent_per_point' => 0.020,
                        ],
                        [
                            'id' => 'band_premium',
                            'label' => 'Premium',
                            'min_score' => 850,
                            'max_score' => null,
                            'base_growth_percent' => 18.0,
                            'micro_percent_per_point' => 0.010,
                        ],
                    ],
                ],
                'limit_assignment' => [
                    'initial_limit_type' => 'percentage',
                    'initial_limit_percent_of_income' => 40,
                    'initial_limit_flat_amount' => 5000,
                    'use_default_lending_cap' => false,
                    'default_lending_cap_amount' => 0,
                    'apply_score_changes_immediately' => true,
                ],
                'eligibility_rules' => [
                    'age_restrictions' => [
                        'enabled' => true,
                        'min_age' => 21,
                        'max_age' => 65,
                    ],
                    'employment_status' => [
                        'enabled' => true,
                        'eligible_statuses' => [
                            'full_time', 'part_time', 'contract', 'freelancer',
                            'self_employed', 'casual', 'retired', 'student', 'unemployed',
                        ],
                    ],
                    'minimum_income' => [
                        'enabled' => false,
                        'min_monthly_income' => 10000,
                    ],
                    'guarantor_required' => [
                        'enabled' => false,
                        'required_above_amount' => null,
                    ],
                    'collateral_required' => [
                        'enabled' => false,
                        'required_above_amount' => null,
                    ],
                ],
            ],
            'compliance_documents' => [
                'document_requirements' => array_map(
                    static function (array $category): array {
                        return [
                            'category_key' => $category['category_key'],
                            'label' => $category['label'],
                            'requirement' => $category['default_requirement'],
                            'document_options' => [],
                        ];
                    },
                    policy_console_compliance_document_categories()
                ),
            ],
        ];
    }
}

if (!function_exists('policy_console_credit_limits_system_defaults')) {
    function policy_console_credit_limits_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['credit_limits']) && is_array($defaults['credit_limits'])
            ? $defaults['credit_limits']
            : [];
    }
}

if (!function_exists('policy_console_compliance_documents_system_defaults')) {
    function policy_console_compliance_documents_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['compliance_documents']) && is_array($defaults['compliance_documents'])
            ? $defaults['compliance_documents']
            : [];
    }
}

if (!function_exists('policy_console_compliance_document_excluded_names')) {
    function policy_console_compliance_document_excluded_names(): array
    {
        return [
            'Valid ID Front',
            'Valid ID Back'
        ];
    }
}

if (!function_exists('policy_console_compliance_document_categories')) {
    function policy_console_compliance_document_categories(): array
    {
        return [
            [
                'category_key' => 'identity_document',
                'label' => 'Identity Document',
                'default_requirement' => 'required',
                'document_options' => [
                    ['document_name' => 'National ID (PhilID/ePhilID)', 'is_accepted' => false],
                    ['document_name' => 'Passport', 'is_accepted' => true],
                    ['document_name' => 'Driver\'s License', 'is_accepted' => true],
                    ['document_name' => 'UMID', 'is_accepted' => true],
                    ['document_name' => 'SSS ID', 'is_accepted' => true],
                    ['document_name' => 'GSIS e-Card', 'is_accepted' => true],
                    ['document_name' => 'PRC ID', 'is_accepted' => true],
                    ['document_name' => 'Postal ID', 'is_accepted' => true],
                    ['document_name' => 'Seaman\'s Book / SIRB', 'is_accepted' => true],
                    ['document_name' => 'Senior Citizen ID', 'is_accepted' => true],
                    ['document_name' => 'PWD ID', 'is_accepted' => true],
                    ['document_name' => 'Voter\'s ID', 'is_accepted' => true],
                    ['document_name' => 'NBI Clearance', 'is_accepted' => true],
                    ['document_name' => 'Police Clearance', 'is_accepted' => true],
                    ['document_name' => 'TIN ID', 'is_accepted' => true],
                    ['document_name' => 'School ID', 'is_accepted' => true],
                    ['document_name' => 'Company ID', 'is_accepted' => true],
                    ['document_name' => 'Barangay ID', 'is_accepted' => true],
                    ['document_name' => 'OFW ID', 'is_accepted' => true],
                    ['document_name' => 'OWWA ID', 'is_accepted' => true],
                    ['document_name' => 'IBP ID', 'is_accepted' => true],
                    ['document_name' => 'Government Office / GOCC ID', 'is_accepted' => true],
                ],
            ]
        ];
    }
}
