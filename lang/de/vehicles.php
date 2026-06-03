<?php

return [

    'trailer_status' => [
        'active'   => 'Aktiv',
        'inactive' => 'Inaktiv',
        'blocked'  => 'Gesperrt',
        'archived' => 'Archiviert',
    ],

    'vehicle_status' => [
        'active'   => 'Aktiv',
        'inactive' => 'Inaktiv',
        'blocked'  => 'Gesperrt',
        'archived' => 'Archiviert',
    ],

    'vehicle_type' => [
        'tractor_unit'    => 'Zugmaschine',
        'truck'           => 'LKW',
        'service_vehicle' => 'Servicefahrzeug',
        'other'           => 'Sonstige',
    ],

    'trailer_chip_state' => [
        'assigned'     => 'Chip zugewiesen',
        'missing'      => 'Fehlend',
        'blocked'      => 'Gesperrt',
        'mismatch'     => 'Nicht übereinstimmend',
        'not_required' => 'Nicht erforderlich',
    ],

    'trailer_credential_kind' => [
        'chip' => 'Chip',
        'tan'  => 'TAN',
        'both' => 'Chip + TAN',
        'none' => 'Kein Ausweis',
    ],

    'chip_lifecycle_status' => [
        'active'    => 'Aktiv',
        'blocked'   => 'Gesperrt',
        'lost'      => 'Verloren',
        'defective' => 'Defekt',
        'expired'   => 'Abgelaufen',
        'replaced'  => 'Ersetzt',
        'archived'  => 'Archiviert',
    ],

    'chip_type' => [
        'driver_card'      => 'Fahrerkarte',
        'trailer_chip_tag' => 'Anhänger-Chip-Etikett',
        'service_test'     => 'Service- / Testkarte',
    ],

    'tan_status' => [
        'active'   => 'Aktiv',
        'pending'  => 'Ausstehend',
        'expired'  => 'Abgelaufen',
        'consumed' => 'Verbraucht',
        'revoked'  => 'Widerrufen',
        'blocked'  => 'Gesperrt',
    ],

    'tan_purpose' => [
        'gate_entry' => 'Toreinfahrt',
        'filling'    => 'Befüllstation',
        'general'    => 'Allgemein',
    ],

    'tan_usage_state' => [
        'unused'         => 'Ungenutzt',
        'consumed'       => 'Verbraucht',
        'failed'         => 'Fehlgeschlagen',
        'blocked'        => 'Gesperrt',
        'not_applicable' => 'Nicht zutreffend',
    ],

    'chip_assignment_state' => [
        'unassigned' => 'Nicht zugewiesen',
        'assigned'   => 'Zugewiesen',
        'replaced'   => 'Ersetzt',
        'archived'   => 'Archiviert',
    ],

    'chip_usage_result' => [
        'accepted' => 'Akzeptiert',
        'rejected' => 'Abgelehnt',
        'unknown'  => 'Unbekannt',
        'blocked'  => 'Gesperrt',
        'expired'  => 'Abgelaufen',
        'mismatch' => 'Nicht übereinstimmend',
        'failed'   => 'Fehlgeschlagen',
    ],

    'coupling_state' => [
        'coupled'     => 'Gekoppelt',
        'not_coupled' => 'Nicht gekoppelt',
        'unknown'     => 'Unbekannt',
    ],

    'identification_result' => [
        'accepted'         => 'Akzeptiert',
        'denied'           => 'Abgelehnt',
        'unknown_chip'     => 'Unbekannter Chip',
        'multiple_matches' => 'Mehrere Treffer',
        'reader_error'     => 'Leserfehler',
        'unknown'          => 'Kein aktueller Vorgang',
    ],

    'messages' => [
        // TrailerController
        'trailers_retrieved'         => 'Anhänger abgerufen',
        'trailer_not_found'          => 'Anhänger nicht gefunden',
        'trailer_retrieved'          => 'Anhänger abgerufen',
        'trailer_created'            => 'Anhänger erstellt',
        'trailer_updated'            => 'Anhänger aktualisiert',
        'trailer_blocked'            => 'Anhänger gesperrt',
        'trailer_unblocked'          => 'Anhänger entsperrt',
        'trailer_events_audit'       => 'Ereignisse & Prüfprotokoll des Anhängers abgerufen',
        'trailer_already_blocked'    => 'Anhänger ist bereits gesperrt',
        'trailer_not_blocked'        => 'Anhänger ist nicht gesperrt',
        'trailer_export'             => 'Anhängerexport vorbereitet',
        'trailer_loadings'           => 'Beladungen abgerufen',
        'auth_media'                 => 'Ausweismedien abgerufen',
        'plant_visits_placeholder'   => 'Werksbesuche abgerufen (Platzhalter)',
        'documents_placeholder'      => 'Dokumente abgerufen (Platzhalter)',

        // TractorVehicleController
        'vehicles_retrieved'          => 'Zugmaschinen abgerufen',
        'vehicle_not_found'           => 'Zugmaschine nicht gefunden',
        'vehicle_retrieved'           => 'Zugmaschine abgerufen',
        'vehicle_created'             => 'Zugmaschine erstellt',
        'vehicle_updated'             => 'Zugmaschine aktualisiert',
        'vehicle_blocked'             => 'Zugmaschine gesperrt',
        'vehicle_unblocked'           => 'Zugmaschine entsperrt',
        'vehicle_already_blocked'     => 'Zugmaschine ist bereits gesperrt',
        'vehicle_not_blocked'         => 'Zugmaschine ist nicht gesperrt',
        'vehicle_export'              => 'Fahrzeugexport vorbereitet',
        'couplings'                   => 'Kupplungen abgerufen',
        'vehicle_events_audit'        => 'Ereignisse & Prüfprotokoll abgerufen',
        'license_plate_updated'       => 'Kennzeichen aktualisiert. Historische Betriebssnapshots bleiben unverändert.',
        'clarifications_placeholder'  => 'Klärungsfälle (Platzhalter)',

        // ChipCardController
        'cards_retrieved'            => 'Chipkarten abgerufen',
        'card_not_found'             => 'Chipkarte nicht gefunden',
        'card_retrieved'             => 'Chipkarte abgerufen',
        'card_registered'            => 'Chipkarte registriert',
        'card_updated'               => 'Chipkarte aktualisiert',
        'card_archived'              => 'Chipkarte archiviert',
        'card_assigned'              => 'Chipkarte zugewiesen',
        'card_unassigned'            => 'Chipkarte abgetrennt',
        'card_blocked'               => 'Chipkarte gesperrt',
        'card_unblocked'             => 'Chipkarte entsperrt',
        'card_lost'                  => 'Chipkarte als verloren gemeldet',
        'card_defective'             => 'Chipkarte als defekt markiert',
        'card_replaced'              => 'Chipkarte ersetzt',
        'card_events_audit'          => 'Ereignisse & Prüfprotokoll der Chipkarte abgerufen',
        'card_export'                => 'Chipkartenexport vorbereitet',
        'assignment_history'         => 'Zuweisungshistorie abgerufen',
        'usage_history'              => 'Nutzungshistorie abgerufen',
        'card_already_blocked'       => 'Chipkarte ist bereits gesperrt',
        'card_not_assigned'          => 'Chipkarte ist nicht zugewiesen',
        'card_not_blocked'           => 'Chipkarte ist nicht gesperrt',
        'card_not_active'            => 'Chipkarte ist nicht aktiv',
        'card_archived_locked'       => 'Archivierte Chipkarten können nicht aktualisiert werden',
        'card_cannot_archive'        => 'Zugewiesene Chipkarte kann nicht archiviert werden — zuerst abtrenne',
        'card_type_mismatch'         => 'Kartentyp stimmt nicht überein',
        'card_already_assigned'      => 'Chipkarte bereits zugewiesen',
        'entity_has_card'            => 'Entität hat bereits eine aktive Chipkarte dieses Typs',
        'replacement_not_found'      => 'Ersatzkarte nicht gefunden',
        'replacement_not_active'     => 'Ersatzkarte ist nicht aktiv',
        'replacement_not_unassigned' => 'Ersatzkarte ist nicht abgetrennt',
        'replacement_must_differ'    => 'Ersatzkarte muss sich von der Original unterscheiden',
        'replacement_type_mismatch'  => 'Kartentyp der Ersatzkarte stimmt nicht überein',
        'registered_with_assignment' => 'Mit Zuweisung registriert',
        'unassigned_label'           => 'Abgetrennt',
        'auto_unassigned'            => 'Automatisch abgetrennt durch Verlorenmeldung: :detail',
        'carried_over'               => 'Übertragen von Karte :detail',

        // TanController
        'tans_retrieved'              => 'TANs abgerufen',
        'tan_not_found'               => 'TAN nicht gefunden',
        'tan_retrieved'               => 'TAN abgerufen',
        'tan_generated'               => 'TAN generiert',
        'tan_revoked'                 => 'TAN widerrufen',
        'tan_expired'                 => 'TAN abgelaufen',
        'tan_events_audit'            => 'Ereignisse & Prüfprotokoll der TAN abgerufen',
        'tan_export'                  => 'TAN-Export vorbereitet',
        'tan_usage_history'           => 'TAN-Nutzungshistorie abgerufen',
        'tan_already_revoked'         => 'TAN ist bereits widerrufen',
        'tan_already_expired'         => 'TAN ist bereits abgelaufen',
        'tan_consumed_not_revokable'  => 'Verbrauchte TANs können nicht widerrufen werden',
        'driver_already_has_tan'      => 'Fahrer hat bereits eine aktive TAN',
        'security_events_placeholder' => 'Sicherheitsereignisse abgerufen (Platzhalter)',
    ],

    'inspection_state' => [
        'valid'         => 'Gültig',
        'expiring_soon' => 'Läuft bald ab',
        'expired'       => 'Abgelaufen',
        'missing'       => 'Fehlend',
    ],

    'technical_suitability' => [
        'approved'     => 'Genehmigt',
        'incomplete'   => 'Unvollständig',
        'not_suitable' => 'Nicht geeignet',
        'needs_review' => 'Prüfung erforderlich',
    ],

];
