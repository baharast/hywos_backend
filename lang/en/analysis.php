<?php

return [

    'status' => [
        'not_started'         => 'Not started',
        'pre_analysis_open'   => 'Pre-analysis open',
        'pre_analysis_ok'     => 'Pre-analysis OK',
        'pre_analysis_failed' => 'Pre-analysis failed',
        'main_analysis_open'  => 'Main analysis open',
        'main_analysis_ok'    => 'Main analysis OK',
        'functionally_nok'    => 'Functionally NOK',
        'technically_invalid' => 'Technically invalid',
        'quality_checked'     => 'Quality checked',
    ],

    'active_status' => [
        'queued'            => 'Queued',
        'preparing'         => 'Preparing',
        'purging'           => 'Purging',
        'running'           => 'Running',
        'waiting_result'    => 'Waiting result',
        'result_received'   => 'Result received',
        'waiting_decision'  => 'Waiting decision',
        'invalid'           => 'Invalid',
        'nok'               => 'NOK',
        'failed'            => 'Failed',
        'on_hold'           => 'On hold',
        'cancelled'         => 'Cancelled',
        'closed'            => 'Closed',
    ],

    'type' => [
        'pre_analysis'   => 'Pre-analysis',
        'main_analysis'  => 'Main analysis',
        'final_analysis' => 'Final analysis',
        'retest'         => 'Retest',
    ],

    'user_action' => [
        'view_details'                   => 'View details',
        'put_on_hold'                    => 'Put on hold',
        'request_repeat_analysis'        => 'Request repeat analysis / retest',
        'cancel_analysis'                => 'Cancel analysis',
        'release_loading'                => 'Release loading',
        'reject_loading_block_trailer'   => 'Reject loading / block trailer',
        'open_fault_case_manual_check'   => 'Open fault case / manual check',
        'repeat_measurement'             => 'Repeat measurement',
        'manual_functional_approval'     => 'Manual functional approval',
        'open_related_result_record'     => 'Open related result record',
    ],

    'open_decision_status' => [
        'repeat_requested'    => 'Repeat requested',
        'pending_review'      => 'Pending review',
        'escalation_required' => 'Escalation required',
        'waiting_for_decision' => 'Waiting for decision',
    ],

    'closed_decision_status' => [
        'approved' => 'Approved',
        'released' => 'Released',
        'blocked'  => 'Blocked',
        'rejected' => 'Rejected',
        'closed'   => 'Closed',
    ],

    'element_status' => [
        'waiting'         => 'Waiting',
        'received'        => 'Received',
        'valid'           => 'Valid',
        'nok'             => 'NOK (failed limit)',
        'missing'         => 'Missing',
        'invalid'         => 'Invalid',
        'not_transferred' => 'Not transferred',
    ],

    'sampling_trigger' => [
        'before_loading'   => 'Before loading',
        'main_30_percent'  => 'Main 30%',
        'main_60_percent'  => 'Main 60%',
        'main_90_percent'  => 'Main 90%',
        'after_loading'    => 'After loading',
        'not_applicable'   => 'N/A',
    ],

    'result_status' => [
        'passed'     => 'Passed',
        'nok'        => 'NOK / Failed',
        'invalid'    => 'Invalid',
        'incomplete' => 'Incomplete',
    ],

    'device_type' => [
        'orthosmart_analyser'      => 'OrthoSmart Analyser',
        'gas_warning_controller'   => 'GWA-REGARD3900 (Gas Warning)',
        'sample_switching_module'  => 'CGS-SAM1000DP2 (Sample Switching)',
    ],

    'device_health' => [
        'healthy'     => 'Healthy',
        'warning'     => 'Warning',
        'alarm'       => 'Alarm',
        'fault'       => 'Fault',
        'offline'     => 'Offline / Stale',
        'maintenance' => 'Maintenance / Inhibited',
    ],

    'device_calibration' => [
        'valid'          => 'Valid',
        'due_soon'       => 'Due soon',
        'overdue'        => 'Overdue',
        'failed'         => 'Failed',
        'not_configured' => 'Not configured',
    ],

    'device_run_state' => [
        'ready'           => 'Ready',
        'preparing'       => 'Preparing',
        'measuring'       => 'Measuring',
        'result_transfer' => 'Transferring result',
        'idle'            => 'Idle',
        'fault'           => 'Fault',
    ],

    'calibration_profile_status' => [
        'draft'    => 'Draft',
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'retired'  => 'Retired',
    ],

    'calibration_run_result' => [
        'pass'         => 'Pass',
        'fail'         => 'Fail',
        'not_recorded' => 'Not recorded',
    ],

    'calibration_lockout' => [
        'warn_only'                  => 'Warn only',
        'block_analysis_start'       => 'Block analysis start',
        'block_release_certificate'  => 'Block release / certificate',
    ],

    'messages' => [
        // ActiveAnalysisController
        'analyses_retrieved'          => 'Active analyses retrieved',
        'analysis_not_found'          => 'Analysis not found',
        'analysis_retrieved'          => 'Analysis retrieved',
        'analysis_on_hold'            => 'Analysis on hold',
        'analysis_cancelled'          => 'Analysis cancelled',
        'analysis_released'           => 'Loading released',
        'analysis_rejected'           => 'Loading rejected / trailer blocked',
        'analysis_fault_case'         => 'Fault case opened',
        'analysis_repeated'           => 'Repeat / retest requested',
        'analysis_measurement_repeated' => 'Measurement repeat queued',
        'analysis_approved'           => 'Analysis manually approved',
        'action_not_allowed'          => 'This action is not allowed from the current analysis state.',
        'max_attempts_reached'        => 'Maximum allowed attempts reached; open a fault case instead.',
        'technical_repeat_used'       => 'The single technical repeat has already been used; open a fault case.',

        // AnalysisDeviceController
        'devices_retrieved'           => 'Analysis devices retrieved',
        'device_not_found'            => 'Analysis device not found',
        'device_retrieved'            => 'Analysis device retrieved',
        'devices_status_table'        => 'Analysis devices status table retrieved',
        'device_channels'             => 'Analysis device channels retrieved',
        'device_events'               => 'Analysis device events retrieved',
        'device_no_channels'          => 'This device type does not expose channels',
        'gas_warning_channel'         => 'Gas warning channel',

        // QualityDecisionController
        'results_retrieved'           => 'Results retrieved',
        'result_not_found'            => 'Result not found',
        'result_retrieved'            => 'Result retrieved',
    ],

    'certificate_impact' => [
        'allowed'      => 'Allowed',
        'blocked'      => 'Blocked',
        'generated'    => 'Generated',
        'not_required' => 'Not required',
    ],

    'certificate_mapping' => [
        'not_printed'     => 'Not printed',
        'certificate_row' => 'Certificate row',
        'delivery_note'   => 'Delivery note',
        'qm_document'     => 'QM document',
    ],

];
