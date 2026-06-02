<?php

return [

    'order_status' => [
        'draft'            => 'Draft',
        'needs_assignment' => 'Needs Assignment',
        'ready'            => 'Ready',
        'in_progress'      => 'In Progress',
        'completed'        => 'Completed',
        'blocked'          => 'Blocked',
        'cancelled'        => 'Cancelled',
    ],

    'loading_status' => [
        'assigned_ready_for_bay'         => 'Assigned · Ready for Bay',
        'waiting_pre_analysis'           => 'Waiting for Pre-analysis',
        'ready_for_loading'              => 'Ready for Loading',
        'loading'                        => 'Loading',
        'paused_waiting'                 => 'Paused / Waiting',
        'completed'                      => 'Completed',
        'waiting_main_analysis'          => 'Waiting for Main Analysis',
        'quality_blocked'                => 'Quality Blocked',
        'documents_pending'              => 'Documents Pending',
        'clarification_required'         => 'Clarification Required',
        'fault_device_issue'             => 'Fault / Device Issue',
    ],

    'bay_status' => [
        'available'                   => 'Available',
        'reserved'                    => 'Reserved',
        'loading'                     => 'Loading',
        'waiting_analysis'            => 'Waiting for Analysis',
        'completed_waiting_documents' => 'Completed · Waiting Documents',
        'fault_blocked'               => 'Fault / Blocked',
        'maintenance_offline'         => 'Maintenance / Offline',
    ],

    'assignment_state' => [
        'assigned'     => 'Assigned',
        'not_assigned' => 'Not assigned',
        'not_required' => 'Not required',
    ],

    'order_source' => [
        'sap'    => 'SAP',
        'manual' => 'Manual',
    ],

    'blocking_impact' => [
        'entry_blocked'        => 'Entry blocked',
        'registration_blocked' => 'Registration blocked',
        'parking_blocked'      => 'Parking blocked',
        'loading_blocked'      => 'Loading blocked',
        'documents_blocked'    => 'Documents blocked',
        'exit_blocked'         => 'Exit blocked',
        'none'                 => 'No current block',
    ],

    'issue_badge' => [
        'clarification' => 'Clarification',
        'quality_blocked' => 'Quality Blocked',
        'fault'           => 'Fault',
        'waiting'         => 'Waiting',
        'critical_alarm'  => 'Critical Alarm',
    ],

    'action_path' => [
        'open_clarification' => 'Open Clarification Case',
        'open_analysis'      => 'Open Analysis & Quality',
        'open_device'        => 'Open Device Detail',
        'open_documents'     => 'Open Documents & Reports',
        'open_visit'         => 'Open Plant Visit',
        'none'               => 'No action',
    ],

    'reference_link' => [
        'loading_order' => 'Loading Order',
        'plant_visit'   => 'Plant Visit',
        'event_journal' => 'Event Journal',
        'audit_trail'   => 'Audit Trail',
    ],

    'analysis_summary' => [
        'decision_owner' => 'Analysis & Quality',
        'note'           => 'Read-only view. Decisions are made in Analysis & Quality.',
    ],

    'documents' => [
        'pending'   => 'Documents pending — see Documents & Reports.',
        'blocked'   => 'Documents blocked by quality decision.',
        'available' => 'Documents available.',
    ],

    'fault_reason_fallback' => 'Station / device fault.',

    'what_happening' => [
        'assigned_ready_for_bay'   => 'Loading is assigned to :where; awaiting pre-analysis trigger.',
        'waiting_pre_analysis'     => 'Waiting for pre-analysis result before release at :where.',
        'ready_for_loading'        => 'Released for loading at :where; awaiting start at the panel.',
        'loading'                  => 'Loading is in progress at :where.',
        'paused_waiting'           => 'Loading at :where is paused — operator review required.',
        'completed'                => 'Physical loading complete at :where.',
        'waiting_main_analysis'    => 'Waiting for main-analysis result after loading at :where.',
        'quality_blocked'          => 'Quality decision blocks documents/exit for the loading at :where.',
        'documents_pending'        => 'Loading complete at :where — documents not yet ready.',
        'clarification_required'   => 'Loading at :where is held — clarification case open.',
        'fault_device_issue'       => 'Station / device fault affecting the loading at :where.',
        'default'                  => 'Loading at :where is in an unknown state — investigate.',
    ],

    'messages' => [
        // LoadingControlController
        'loadings_retrieved'    => 'Active loadings retrieved',
        'loading_not_found'     => 'Loading not found',
        'loading_retrieved'     => 'Loading retrieved',
        'selected_details'      => 'Selected loading details retrieved',
        'station_view'          => 'Station view retrieved',
        'note_added'            => 'Note added',
        'loading_events'        => 'Loading events retrieved',
        'loading_audit'         => 'Loading audit retrieved',

        // LoadingOrderController
        'orders_retrieved'           => 'Loading orders retrieved',
        'order_retrieved'            => 'Loading order retrieved',
        'order_created'              => 'Loading order created',
        'order_updated'              => 'Loading order updated',
        'order_blocked'              => 'Loading order blocked',
        'order_unblocked'            => 'Loading order unblocked',
        'order_cancelled'            => 'Loading order cancelled',
        'order_not_found'            => 'Loading order not found',
        'order_already_blocked'      => 'Loading order is already blocked',
        'order_not_blocked'          => 'Loading order is not blocked',
        'order_already_cancelled'    => 'Loading order is already cancelled',
        'cancel_locked'              => 'Cannot cancel an order with an active plant visit or loading operation.',
        'customer_not_found'         => 'Customer not found',
        'customer_blocked'           => 'Cannot create loading order for a blocked or inactive customer.',
        'driver_assigned'            => 'Driver assigned',
        'driver_unassigned'          => 'Driver unassigned',
        'driver_not_found'           => 'Driver not found',
        'driver_blocked'             => 'Cannot assign a blocked driver.',
        'driver_inactive'            => 'Cannot assign an inactive driver.',
        'trailer_assigned'           => 'Trailer assigned',
        'trailer_unassigned'         => 'Trailer unassigned',
        'trailer_not_found'          => 'Trailer not found',
        'trailer_not_assignable'     => 'Trailer cannot be assigned.',
        'trailer_no_credential'      => 'Trailer has no usable chip or TAN.',
        'trailer_blocked'            => 'Cannot assign a blocked trailer.',
        'trailer_inactive'           => 'Cannot assign an inactive or archived trailer.',
        'bay_not_found'              => 'Bay line not found',
        'bay_inactive'               => 'Cannot assign an inactive bay line.',
        'bay_occupied'               => 'This bay line is already occupied.',
        'sap_fields_locked'          => 'Rejected local update on SAP-owned fields',
        'order_locked'               => 'This loading order is locked by an active execution.',
        'order_locked_fields'        => 'This loading order is locked by an active execution. The listed fields cannot be changed.',
        'events_audit_retrieved'     => 'Loading order events & audit retrieved',

        // BayLineController
        'bays_retrieved'   => 'BayLines retrieved',
        'bay_created'      => 'BayLine created',
        'bay_retrieved'    => 'BayLine retrieved',
        'bay_updated'      => 'BayLine updated',
        'bay_activated'    => 'BayLine activated',
        'bay_deactivated'  => 'BayLine deactivated',
        'bay_not_found_bl' => 'BayLine not found',
    ],

];
