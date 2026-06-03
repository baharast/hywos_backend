<?php

return [

    'trailer_status' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'blocked'  => 'Blocked',
        'archived' => 'Archived',
    ],

    'vehicle_status' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'blocked'  => 'Blocked',
        'archived' => 'Archived',
    ],

    'vehicle_type' => [
        'tractor_unit'    => 'Tractor unit',
        'truck'           => 'Truck',
        'service_vehicle' => 'Service vehicle',
        'other'           => 'Other',
    ],

    'trailer_chip_state' => [
        'assigned'     => 'Chip assigned',
        'missing'      => 'Missing',
        'blocked'      => 'Blocked',
        'mismatch'     => 'Mismatch',
        'not_required' => 'Not required',
    ],

    'trailer_credential_kind' => [
        'chip' => 'Chip',
        'tan'  => 'TAN',
        'both' => 'Chip + TAN',
        'none' => 'No credential',
    ],

    'chip_lifecycle_status' => [
        'active'    => 'Active',
        'blocked'   => 'Blocked',
        'lost'      => 'Lost',
        'defective' => 'Defective',
        'expired'   => 'Expired',
        'replaced'  => 'Replaced',
        'archived'  => 'Archived',
    ],

    'chip_type' => [
        'driver_card'       => 'Driver card',
        'trailer_chip_tag'  => 'Trailer chip tag',
        'service_test'      => 'Service / test card',
    ],

    'tan_status' => [
        'active'   => 'Active',
        'pending'  => 'Pending',
        'expired'  => 'Expired',
        'consumed' => 'Consumed',
        'revoked'  => 'Revoked',
        'blocked'  => 'Blocked',
    ],

    'tan_purpose' => [
        'gate_entry' => 'Gate entry',
        'filling'    => 'Filling station',
        'general'    => 'General',
    ],

    'tan_usage_state' => [
        'unused'         => 'Unused',
        'consumed'       => 'Consumed',
        'failed'         => 'Failed',
        'blocked'        => 'Blocked',
        'not_applicable' => 'Not applicable',
    ],

    'chip_assignment_state' => [
        'unassigned' => 'Unassigned',
        'assigned'   => 'Assigned',
        'replaced'   => 'Replaced',
        'archived'   => 'Archived',
    ],

    'chip_usage_result' => [
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'unknown'  => 'Unknown',
        'blocked'  => 'Blocked',
        'expired'  => 'Expired',
        'mismatch' => 'Mismatch',
        'failed'   => 'Failed',
    ],

    'coupling_state' => [
        'coupled'     => 'Coupled',
        'not_coupled' => 'Not coupled',
        'unknown'     => 'Unknown',
    ],

    'identification_result' => [
        'accepted'         => 'Accepted',
        'denied'           => 'Denied',
        'unknown_chip'     => 'Unknown chip',
        'multiple_matches' => 'Multiple matches',
        'reader_error'     => 'Reader error',
        'unknown'          => 'No recent event',
    ],

    'messages' => [
        // TrailerController
        'trailers_retrieved'        => 'Trailers retrieved',
        'trailer_not_found'         => 'Trailer not found',
        'trailer_retrieved'         => 'Trailer retrieved',
        'trailer_created'           => 'Trailer created',
        'trailer_updated'           => 'Trailer updated',
        'trailer_blocked'           => 'Trailer blocked',
        'trailer_unblocked'         => 'Trailer unblocked',
        'trailer_events_audit'      => 'Trailer events & audit retrieved',
        'trailer_already_blocked'   => 'Trailer already blocked',
        'trailer_not_blocked'       => 'Trailer is not blocked',
        'trailer_export'            => 'Trailer export prepared',
        'trailer_loadings'          => 'Loadings retrieved',
        'auth_media'                => 'Auth media retrieved',
        'plant_visits_placeholder'  => 'Plant visits retrieved (placeholder)',
        'documents_placeholder'     => 'Documents retrieved (placeholder)',

        // TractorVehicleController
        'vehicles_retrieved'        => 'Vehicles retrieved',
        'vehicle_not_found'         => 'Vehicle not found',
        'vehicle_retrieved'         => 'Vehicle retrieved',
        'vehicle_created'           => 'Vehicle created',
        'vehicle_updated'           => 'Vehicle updated',
        'vehicle_blocked'           => 'Vehicle blocked',
        'vehicle_unblocked'         => 'Vehicle unblocked',
        'vehicle_already_blocked'   => 'Vehicle already blocked',
        'vehicle_not_blocked'       => 'Vehicle is not blocked',
        'vehicle_export'            => 'Vehicle export prepared',
        'couplings'                 => 'Couplings retrieved',
        'vehicle_events_audit'      => 'Events + audit retrieved',
        'license_plate_updated'     => 'License plate updated. Historical operational snapshots remain unchanged.',
        'clarifications_placeholder' => 'Clarifications placeholder',

        // ChipCardController
        'cards_retrieved'           => 'Chip cards retrieved',
        'card_not_found'            => 'Chip card not found',
        'card_retrieved'            => 'Chip card retrieved',
        'card_registered'           => 'Chip card registered',
        'card_updated'              => 'Chip card updated',
        'card_archived'             => 'Chip card archived',
        'card_assigned'             => 'Chip card assigned',
        'card_unassigned'           => 'Chip card unassigned',
        'card_blocked'              => 'Chip card blocked',
        'card_unblocked'            => 'Chip card unblocked',
        'card_lost'                 => 'Chip card marked lost',
        'card_defective'            => 'Chip card marked defective',
        'card_replaced'             => 'Chip card replaced',
        'card_events_audit'         => 'Chip card events & audit retrieved',
        'card_export'               => 'Chip card export prepared',
        'assignment_history'        => 'Assignment history retrieved',
        'usage_history'             => 'Usage history retrieved',
        'card_already_blocked'      => 'Chip card already blocked',
        'card_not_assigned'         => 'Chip card is not assigned',
        'card_not_blocked'          => 'Chip card is not blocked',
        'card_not_active'           => 'Chip card not active',
        'card_archived_locked'      => 'Archived chip cards cannot be updated',
        'card_cannot_archive'       => 'Cannot archive an assigned chip card — unassign first',
        'card_type_mismatch'        => 'Card type mismatch',
        'card_already_assigned'     => 'Chip card already assigned',
        'entity_has_card'           => 'Entity already has an active chip card of this type',
        'replacement_not_found'     => 'Replacement chip card not found',
        'replacement_not_active'    => 'Replacement card is not active',
        'replacement_not_unassigned' => 'Replacement card is not unassigned',
        'replacement_must_differ'   => 'Replacement card must differ from original',
        'replacement_type_mismatch' => 'Replacement card type does not match',
        'registered_with_assignment' => 'Registered with assignment',
        'unassigned_label'          => 'Unassigned',
        'auto_unassigned'           => 'Auto-unassigned due to lost report: :detail',
        'carried_over'              => 'Carried over from card :detail',

        // TanController
        'tans_retrieved'            => 'TANs retrieved',
        'tan_not_found'             => 'TAN not found',
        'tan_retrieved'             => 'TAN retrieved',
        'tan_generated'             => 'TAN generated',
        'tan_revoked'               => 'TAN revoked',
        'tan_expired'               => 'TAN expired',
        'tan_events_audit'          => 'TAN events & audit retrieved',
        'tan_export'                => 'TAN export prepared',
        'tan_usage_history'         => 'TAN usage history retrieved',
        'tan_already_revoked'       => 'TAN already revoked',
        'tan_already_expired'       => 'TAN already expired',
        'tan_consumed_not_revokable' => 'Consumed TANs cannot be revoked',
        'driver_already_has_tan'    => 'Driver already has an active TAN',
        'security_events_placeholder' => 'Security events retrieved (placeholder)',
    ],

];
