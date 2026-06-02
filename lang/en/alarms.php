<?php

return [

    'severity' => [
        'critical' => 'Critical',
        'high'     => 'High',
        'medium'   => 'Medium',
        'low'      => 'Low',
        'info'     => 'Info',
    ],

    'status' => [
        'active'                 => 'Active',
        'acknowledged'           => 'Acknowledged',
        'assigned'               => 'Assigned',
        'in_progress'            => 'In progress',
        'resolved_pending_close' => 'Resolved (pending close)',
        'closed'                 => 'Closed',
        'suppressed_maintenance' => 'Suppressed / maintenance',
    ],

    'category' => [
        'safety'               => 'Safety',
        'loading_process'      => 'Loading / Process',
        'quality_analysis'     => 'Quality / Analysis',
        'gate_access'          => 'Gate / Access',
        'device_communication' => 'Device / Communication',
        'document_printer'     => 'Document / Printer',
        'interface_sap'        => 'Interface / SAP',
    ],

    'blocking_impact' => [
        'blocks_loading'            => 'Blocks loading',
        'blocks_gate_entry'         => 'Blocks gate entry',
        'blocks_exit'               => 'Blocks exit',
        'blocks_documents'          => 'Blocks documents',
        'analysis_approval_blocked' => 'Analysis approval blocked',
        'warning_only'              => 'Warning only',
    ],

    'source_type' => [
        'station_plc'             => 'Station PLC',
        'analysis_plc'            => 'Analysis PLC',
        'central_plc'             => 'Central PLC',
        'gate_smart_panel'        => 'Gate smart panel',
        'compressor_controller'   => 'Compressor controller',
        'electrolyzer_controller' => 'Electrolyzer controller',
        'printer'                 => 'Printer',
        'sap_connector'           => 'SAP connector',
        'other'                   => 'Other',
    ],

    'owner_role' => [
        'operator'            => 'Operator',
        'it_support'          => 'IT / Support',
        'analysis_specialist' => 'Analysis specialist',
        'dispatcher'          => 'Dispatcher',
        'admin'               => 'Admin',
    ],

    'event_severity' => [
        'info'     => 'Info',
        'warning'  => 'Warning',
        'danger'   => 'Danger',
        'critical' => 'Critical',
    ],

    'journal_category' => [
        'gate_access'          => 'Gate / Access',
        'driver_terminal'      => 'Driver Terminal',
        'filling_station'      => 'Filling Station',
        'loading'              => 'Loading',
        'analysis'             => 'Analysis',
        'document_print'       => 'Document / Print',
        'device_communication' => 'Device / Communication',
        'interface_sap'        => 'Interface / SAP',
        'security'             => 'Security',
        'system'               => 'System',
        'audit_reference'      => 'Audit Reference',
    ],

    'journal_result' => [
        'info'      => 'Info',
        'warning'   => 'Warning',
        'error'     => 'Error',
        'success'   => 'Success',
        'denied'    => 'Denied',
        'failed'    => 'Failed',
        'completed' => 'Completed',
        'timeout'   => 'Timeout',
        'blocked'   => 'Blocked',
        'started'   => 'Started',
        'stopped'   => 'Stopped',
    ],

    'security_category' => [
        'authentication'    => 'Authentication',
        'authorization'     => 'Authorization',
        'account_lifecycle' => 'Account lifecycle',
        'role_permission'   => 'Role / permission',
        'chip_tan_access'   => 'Chip / TAN access',
        'session'           => 'Session',
        'api_integration'   => 'API / integration',
    ],

    'security_result' => [
        'success' => 'Success',
        'failed'  => 'Failed',
        'denied'  => 'Denied',
        'locked'  => 'Locked',
        'expired' => 'Expired',
        'blocked' => 'Blocked',
        'unknown' => 'Unknown',
    ],

    'messages' => [
        'alarms_retrieved'          => 'Active alarms retrieved',
        'alarm_not_found'           => 'Alarm not found',
        'alarm_detail'              => 'Alarm detail retrieved',
        'alarm_acknowledged'        => 'Alarm acknowledged',
        'alarm_assigned'            => 'Alarm assigned',
        'alarm_in_progress'         => 'Alarm marked in progress',
        'alarm_resolved'            => 'Alarm resolved (pending close)',
        'alarm_closed'              => 'Alarm closed',
        'journal_retrieved'         => 'Event journal retrieved',
        'event_not_found'           => 'Event not found',
        'event_detail'              => 'Event detail retrieved',
        'security_retrieved'        => 'Security events retrieved',
        'security_not_found'        => 'Security event not found',
        'security_detail'           => 'Security event detail retrieved',
        'restricted_note'           => 'Restricted security data. Do not export to unauthorized recipients.',
    ],

];
