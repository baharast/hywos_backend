<?php

return [

    'category' => [
        'safety_note'     => 'Safety note',
        'operations_note' => 'Operations note',
        'manual_action'   => 'Manual action',
        'handover_note'   => 'Handover note',
        'device_follow_up' => 'Device follow-up',
        'near_miss'       => 'Near miss',
        'information'     => 'Information',
    ],

    'severity' => [
        'critical' => 'Critical',
        'high'     => 'High',
        'medium'   => 'Medium',
        'low'      => 'Low',
        'info'     => 'Info',
    ],

    'follow_up_status' => [
        'open'        => 'Open',
        'in_progress' => 'In progress',
        'done'        => 'Done',
        'cancelled'   => 'Cancelled',
        'overdue'     => 'Overdue',
    ],

    'area' => [
        'filling_station_01' => 'Filling Station 01',
        'filling_station_02' => 'Filling Station 02',
        'filling_station_03' => 'Filling Station 03',
        'filling_station_04' => 'Filling Station 04',
        'filling_station_05' => 'Filling Station 05',
        'filling_station_06' => 'Filling Station 06',
        'entry_gate'         => 'Entry Gate',
        'exit_gate'          => 'Exit Gate',
        'analysis'           => 'Analysis',
        'compressor'         => 'Compressor',
        'electrolyzer'       => 'Electrolyzer',
        'control_room'       => 'Control Room',
        'server_network'     => 'Server / Network',
    ],

    'audit_action_type' => [
        'create'               => 'Create',
        'update'               => 'Update',
        'block'                => 'Block',
        'unblock'              => 'Unblock',
        'approve'              => 'Approve',
        'reject'               => 'Reject',
        'override'             => 'Override',
        'print'                => 'Print',
        'reprint'              => 'Reprint',
        'assign'               => 'Assign',
        'unassign'             => 'Unassign',
        'configuration_change' => 'Configuration change',
        'other'                => 'Other',
    ],

    'audit_approval_status' => [
        'not_required' => 'Not required',
        'pending'      => 'Pending',
        'approved'     => 'Approved',
        'rejected'     => 'Rejected',
    ],

    'audit_change_category' => [
        'master_data'        => 'Master data',
        'process_status'     => 'Process status',
        'manual_override'    => 'Manual override',
        'quality_decision'   => 'Quality decision',
        'document'           => 'Document',
        'security_role'      => 'Security / role',
        'configuration'      => 'Configuration',
        'device_service_mode' => 'Device service mode',
    ],

    'audit_retention_class' => [
        'operational'            => 'Operational',
        'quality_critical'       => 'Quality-critical',
        'security_critical'      => 'Security-critical',
        'configuration_critical' => 'Configuration-critical',
    ],

    'messages' => [
        // LogbookController
        'entries_retrieved'        => 'Logbook entries retrieved',
        'entry_not_found'          => 'Logbook entry not found',
        'entry_retrieved'          => 'Logbook entry detail retrieved',
        'entry_created'            => 'Logbook entry created',
        'entry_corrected'          => 'Logbook entry corrected',
        'follow_up_added'          => 'Follow-up added',
        'follow_up_done'           => 'Follow-up marked done',

        // AuditTrailController
        'audit_retrieved'          => 'Audit trail retrieved',
        'audit_record_not_found'   => 'Audit record not found',
        'audit_record_detail'      => 'Audit record detail retrieved',
    ],

];
