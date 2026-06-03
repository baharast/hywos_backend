<?php

return [

    'login_method' => [
        'tan'       => 'TAN Number',
        'chip_card' => 'Driver Chip Card',
    ],

    'security_risk_level' => [
        'critical' => 'Critical',
        'high'     => 'High',
        'medium'   => 'Medium',
        'low'      => 'Low',
        'info'     => 'Info',
    ],

    'security_review_status' => [
        'unreviewed'     => 'Unreviewed',
        'in_review'      => 'In review',
        'reviewed'       => 'Reviewed',
        'false_positive' => 'False positive',
    ],

    'session_state' => [
        'idle'           => 'Idle',
        'active'         => 'Active',
        'denied'         => 'Denied',
        'needs_operator' => 'Needs Operator',
        'device_fault'   => 'Device Fault',
        'service_mode'   => 'Service Mode',
    ],

    'current_screen' => [
        'driver_login'            => 'Driver Login',
        'trailer_identification'  => 'Trailer Identification',
        'tractor_machine_plate'   => 'Tractor / Machine Plate',
        'task_selection'          => 'Task Selection',
        'instruction'             => 'Instruction',
        'documents'               => 'Documents',
        'exit_validation'         => 'Exit Validation',
    ],

    'touchpoint' => [
        'entry_gate'      => 'Entry Gate',
        'driver_terminal' => 'Control Room / Driver Terminal',
        'exit_gate'       => 'Exit Gate',
    ],

    'reader_kind' => [
        'entry_gate_personal'        => 'Entry Gate Personal RFID Reader',
        'exit_id_reader'             => 'Exit Identification Reader',
        'driver_terminal_reader'     => 'Driver Terminal RFID/Chip Reader',
        'filling_bay_reader'         => 'Filling Bay RFID Reader',
        'trailer_registration_reader' => 'Trailer-Chip Reader (Registration)',
        'other'                      => 'Other Reader',
    ],

    'reader_linked_process' => [
        'entry'               => 'Entry',
        'exit'                => 'Exit',
        'terminal_login'      => 'Terminal login',
        'loading_release'     => 'Loading release',
        'trailer_registration' => 'Trailer registration',
        'unknown'             => 'Unknown',
    ],

    'supported_media' => [
        'driver_chip'         => 'Driver chip',
        'tan_input'           => 'TAN input',
        'trailer_chip'        => 'Trailer chip',
        'rfid_authorization'  => 'RFID authorization',
    ],

    'terminal_panel_kind' => [
        'entry_gate'        => 'Entry Gate',
        'exit_gate'         => 'Exit Gate',
        'driver_terminal'   => 'Driver Terminal',
        'filling_hmi_panel' => 'Filling HMI Panel',
        'operator_client'   => 'Operator Client',
        'other'             => 'Other',
    ],

    'login_result_message' => [
        'success'               => 'Identity confirmed.',
        'training_required'     => 'Safety training is required before operational access.',
        'invalid'               => 'Identification is invalid. Please check the number or contact terminal support.',
        'expired'               => 'This TAN has expired. Please contact terminal support.',
        'used'                  => 'This TAN has already been used. Please contact terminal support.',
        'revoked'               => 'This TAN has been revoked. Please contact terminal support.',
        'pending'               => 'This TAN is not yet valid. Please try again later.',
        'driver_blocked'        => 'Access not permitted. Please contact terminal support.',
        'driver_inactive'       => 'Access not permitted. Please contact terminal support.',
        'chip_blocked'          => 'Chip card is blocked. Please contact terminal support.',
        'chip_unassigned'       => 'Chip card not recognized. Please contact terminal support.',
        'reader_offline'        => 'Chip reader unavailable. Use TAN or contact terminal support.',
        'terminal_offline'      => 'Terminal is offline. Please contact terminal support.',
        'terminal_service_mode' => 'Terminal is in service mode. Please contact terminal support.',
        'backend_unavailable'   => 'System connection unavailable. Please contact terminal support.',
        'too_many_attempts'     => 'Too many attempts. Please contact terminal support.',
        'default'               => 'Login failed. Please contact terminal support.',
    ],

    'messages' => [
        // GateTerminalMonitorController
        'sessions_retrieved'         => 'Terminal sessions retrieved',
        'session_not_found'          => 'Terminal session not found',
        'session_retrieved'          => 'Terminal session retrieved',
        'touchpoint_board'           => 'Touchpoint board retrieved',
        'no_issues'                  => 'No active gate or terminal issues',

        // SafetyTrainingController
        'modules_retrieved'          => 'Safety training modules retrieved',
        'module_retrieved'           => 'Safety training module retrieved',
        'exam_retrieved'             => 'Safety training exam retrieved',
        'module_not_found'           => 'Safety training module not found',
        'session_not_found'          => 'Session not found',
        'session_no_driver'          => 'Session has no driver attached — cannot grade exam',
        'session_no_driver_progress' => 'Session has no driver attached — cannot record training progress',
        'driver_not_found'           => 'Driver not found for session',
        'training_recorded'          => 'Training module recorded',
        'exam_passed'                => 'Safety knowledge check passed',
        'exam_failed'                => 'Safety knowledge check failed',

        // TerminalAuthController
        'terminal_not_found'         => 'Terminal not found',
        'terminal_config'            => 'Terminal configuration retrieved',
        'session_retrieved'          => 'Session retrieved',
        'session_ended'              => 'Session ended',
        'language_updated'           => 'Language updated',
        'safety_info_retrieved'      => 'Safety information retrieved',
        'safety_info_title'          => 'Safety Information',
        'safety_rule_1'              => 'No smoking, open flames or ignition sources in H2 areas.',
        'safety_rule_2'              => 'Observe all gas, fire and evacuation alarms.',
        'safety_rule_3'              => 'Stay in allowed driver areas only.',
        'safety_rule_4'              => 'Use required PPE where instructed.',
        'safety_rule_5'              => 'Follow terminal and operator instructions.',
    ],

];
