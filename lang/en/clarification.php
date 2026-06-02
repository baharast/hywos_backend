<?php

return [

    'status' => [
        'open'               => 'Open',
        'in_progress'        => 'In Progress',
        'waiting_for_owner'  => 'Waiting for Owner',
        'resolved'           => 'Resolved',
        'closed'             => 'Closed',
    ],

    'severity' => [
        'low'      => 'Low',
        'normal'   => 'Normal',
        'high'     => 'High',
        'critical' => 'Critical',
    ],

    'source' => [
        'order_matching'   => 'Order Matching',
        'gate_terminal'    => 'Gate / Terminal',
        'parking'          => 'Parking',
        'loading_control'  => 'Loading Control',
        'analysis_quality' => 'Analysis / Quality',
        'documents'        => 'Documents / Print',
        'device_interface' => 'Device / Interface',
        'exit'             => 'Exit',
    ],

    'primary_action' => [
        'resolve_trailer_identification' => 'Resolve Trailer Identification',
        'fix_order_assignment'           => 'Fix Order / Assignment',
        'resolve_parking_confirmation'   => 'Resolve Parking Confirmation',
        'route_to_owner'                 => 'Route to Owner',
        'route_to_analysis'              => 'Route to Analysis & Quality',
        'route_to_documents'             => 'Route to Documents',
        'resolve_exit_blocker'           => 'Resolve Exit Blocker',
        'resolve_case'                   => 'Resolve Case',
        'none'                           => 'No action available',
    ],

    'entity_type' => [
        'plant_visit'      => 'Plant Visit',
        'loading_order'    => 'Loading Order',
        'parking_slot'     => 'Parking Slot',
        'bay_line'         => 'Bay Line',
        'driver'           => 'Driver',
        'trailer'          => 'Trailer',
        'tractor_vehicle'  => 'Tractor Vehicle',
        'chip_card'        => 'Chip Card',
        'tan'              => 'TAN',
        'sap_sync_record'  => 'SAP Sync Record',
    ],

    'messages' => [
        'cases_retrieved'        => 'Clarification cases retrieved',
        'case_retrieved'         => 'Clarification case retrieved',
        'case_created'           => 'Clarification case created',
        'case_not_found'         => 'Clarification case not found',
        'case_acknowledged'      => 'Clarification case acknowledged',
        'case_assigned'          => 'Clarification case assigned',
        'case_waiting_for_owner' => 'Clarification case moved to waiting for owner',
        'case_resolved'          => 'Clarification case resolved',
        'case_closed'            => 'Clarification case closed',
    ],

    'validation' => [
        'resolution_note_required' => 'A resolution note is required to resolve a clarification case.',
        'resolution_note_min'      => 'Resolution note must be at least 3 characters.',
    ],

];
