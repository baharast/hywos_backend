<?php

return [

    'status' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'blocked'  => 'Blocked',
        'unknown'  => 'Unknown',
    ],

    'training_status' => [
        'valid'        => 'Valid',
        'expired'      => 'Expired',
        'missing'      => 'Missing',
        'not_required' => 'Not required',
        'unknown'      => 'Unknown',
    ],

    'identification_status' => [
        'chip_assigned' => 'Chip assigned',
        'tan_available' => 'TAN available',
        'missing'       => 'Missing',
        'blocked'       => 'Blocked',
        'expired'       => 'Expired',
    ],

    'task' => [
        'trailer_filling'                => 'Trailer Filling',
        'park_trailer'                   => 'Park Trailer',
        'pickup_loaded_trailer'          => 'Pick Up Loaded Trailer',
        'pickup_empty_trailer_then_load' => 'Pick Up Empty Trailer Then Load',
        'documents_exit'                 => 'Documents & Exit',
        'exit_only'                      => 'Exit Only',
    ],

    'messages' => [
        'retrieved'       => 'Drivers retrieved',
        'show'            => 'Driver retrieved',
        'created'         => 'Driver created',
        'updated'         => 'Driver updated',
        'blocked'         => 'Driver blocked',
        'unblocked'       => 'Driver unblocked',
        'auth_media'      => 'Auth media retrieved',
        'tan_created'     => 'TAN created',
        'plant_visits'    => 'Plant visits retrieved (placeholder)',
        'events_audit'    => 'Driver events & audit retrieved',
        'export_prepared' => 'Driver export prepared',
        'not_found'       => 'Driver not found',
        'already_blocked' => 'Driver already blocked',
        'not_blocked'     => 'Driver is not blocked',
        'id_mismatch'     => 'driver_id in body does not match URL',
    ],

    'validation' => [
        'expires_at_after' => 'expires_at must be a future date/time.',
    ],

];
