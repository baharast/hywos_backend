<?php

return [

    'order_status' => [
        'draft'            => 'Entwurf',
        'needs_assignment' => 'Zuweisung erforderlich',
        'ready'            => 'Bereit',
        'in_progress'      => 'In Bearbeitung',
        'completed'        => 'Abgeschlossen',
        'blocked'          => 'Gesperrt',
        'cancelled'        => 'Storniert',
    ],

    'loading_status' => [
        'assigned_ready_for_bay'         => 'Zugewiesen · Bucht bereit',
        'waiting_pre_analysis'           => 'Warte auf Voranalyse',
        'ready_for_loading'              => 'Befüllbereit',
        'loading'                        => 'Befüllung läuft',
        'paused_waiting'                 => 'Pausiert / Wartend',
        'completed'                      => 'Abgeschlossen',
        'waiting_main_analysis'          => 'Warte auf Hauptanalyse',
        'quality_blocked'                => 'Qualität gesperrt',
        'documents_pending'              => 'Dokumente ausstehend',
        'clarification_required'         => 'Klärung erforderlich',
        'fault_device_issue'             => 'Störung / Geräteproblem',
    ],

    'bay_status' => [
        'available'                   => 'Verfügbar',
        'reserved'                    => 'Reserviert',
        'loading'                     => 'Befüllung läuft',
        'waiting_analysis'            => 'Warte auf Analyse',
        'completed_waiting_documents' => 'Abgeschlossen · Warte auf Dokumente',
        'fault_blocked'               => 'Störung / Gesperrt',
        'maintenance_offline'         => 'Wartung / Offline',
    ],

    'assignment_state' => [
        'assigned'     => 'Zugewiesen',
        'not_assigned' => 'Nicht zugewiesen',
        'not_required' => 'Nicht erforderlich',
    ],

    'order_source' => [
        'sap'    => 'SAP',
        'manual' => 'Manuell',
    ],

    'blocking_impact' => [
        'entry_blocked'        => 'Einfahrt gesperrt',
        'registration_blocked' => 'Registrierung gesperrt',
        'parking_blocked'      => 'Parken gesperrt',
        'loading_blocked'      => 'Befüllung gesperrt',
        'documents_blocked'    => 'Dokumente gesperrt',
        'exit_blocked'         => 'Ausfahrt gesperrt',
        'none'                 => 'Kein Hindernis',
    ],

    'issue_badge' => [
        'clarification'  => 'Klärung',
        'quality_blocked' => 'Qualität gesperrt',
        'fault'           => 'Störung',
        'waiting'         => 'Wartend',
        'critical_alarm'  => 'Kritischer Alarm',
    ],

    'action_path' => [
        'open_clarification' => 'Klärungsfall öffnen',
        'open_analysis'      => 'Analyse & Qualität öffnen',
        'open_device'        => 'Gerätedetail öffnen',
        'open_documents'     => 'Dokumente & Berichte öffnen',
        'open_visit'         => 'Werksbesuch öffnen',
        'none'               => 'Keine Aktion',
    ],

    'reference_link' => [
        'loading_order' => 'Beladungsauftrag',
        'plant_visit'   => 'Werksbesuch',
        'event_journal' => 'Ereignisprotokoll',
        'audit_trail'   => 'Prüfpfad',
    ],

    'analysis_summary' => [
        'decision_owner' => 'Analyse & Qualität',
        'note'           => 'Nur Leseansicht. Entscheidungen werden in Analyse & Qualität getroffen.',
    ],

    'documents' => [
        'pending'   => 'Dokumente ausstehend — Siehe Dokumente & Berichte.',
        'blocked'   => 'Dokumente durch Qualitätsentscheidung gesperrt.',
        'available' => 'Dokumente verfügbar.',
    ],

    'fault_reason_fallback' => 'Station / Gerätestörung.',

    'what_happening' => [
        'assigned_ready_for_bay'   => 'Befüllung :where zugewiesen; warte auf Voranalyse-Auslöser.',
        'waiting_pre_analysis'     => 'Warte auf Voranalyseergebnis vor Freigabe an :where.',
        'ready_for_loading'        => 'Für Befüllung an :where freigegeben; warte auf Start am Panel.',
        'loading'                  => 'Befüllung läuft an :where.',
        'paused_waiting'           => 'Befüllung an :where pausiert — Bedienerprüfung erforderlich.',
        'completed'                => 'Physische Befüllung an :where abgeschlossen.',
        'waiting_main_analysis'    => 'Warte auf Hauptanalyseergebnis nach Befüllung an :where.',
        'quality_blocked'          => 'Qualitätsentscheidung sperrt Dokumente/Ausfahrt für Befüllung an :where.',
        'documents_pending'        => 'Befüllung an :where abgeschlossen — Dokumente noch nicht bereit.',
        'clarification_required'   => 'Befüllung an :where gesperrt — Klärungsfall offen.',
        'fault_device_issue'       => 'Station / Gerätestörung beeinträchtigt Befüllung an :where.',
        'default'                  => 'Befüllung an :where in unbekanntem Zustand — bitte prüfen.',
    ],

    'messages' => [
        // LoadingControlController
        'loadings_retrieved'    => 'Aktive Beladungen abgerufen',
        'loading_not_found'     => 'Beladung nicht gefunden',
        'loading_retrieved'     => 'Beladung abgerufen',
        'selected_details'      => 'Ausgewählte Beladungsdetails abgerufen',
        'station_view'          => 'Stationsansicht abgerufen',
        'note_added'            => 'Notiz hinzugefügt',
        'loading_events'        => 'Beladungsereignisse abgerufen',
        'loading_audit'         => 'Beladungsprüfpfad abgerufen',

        // LoadingOrderController
        'orders_retrieved'           => 'Beladungsaufträge abgerufen',
        'order_retrieved'            => 'Beladungsauftrag abgerufen',
        'order_created'              => 'Beladungsauftrag erstellt',
        'order_updated'              => 'Beladungsauftrag aktualisiert',
        'order_blocked'              => 'Beladungsauftrag gesperrt',
        'order_unblocked'            => 'Beladungsauftrag entsperrt',
        'order_cancelled'            => 'Beladungsauftrag storniert',
        'order_not_found'            => 'Beladungsauftrag nicht gefunden',
        'order_already_blocked'      => 'Beladungsauftrag ist bereits gesperrt',
        'order_not_blocked'          => 'Beladungsauftrag ist nicht gesperrt',
        'order_already_cancelled'    => 'Beladungsauftrag ist bereits storniert',
        'cancel_locked'              => 'Stornierung nicht möglich: Auftrag hat einen aktiven Werksbesuch oder Beladungsvorgang.',
        'customer_not_found'         => 'Kunde nicht gefunden',
        'customer_blocked'           => 'Beladungsauftrag kann nicht für einen gesperrten oder inaktiven Kunden erstellt werden.',
        'driver_assigned'            => 'Fahrer zugewiesen',
        'driver_unassigned'          => 'Fahrerzuweisung aufgehoben',
        'driver_not_found'           => 'Fahrer nicht gefunden',
        'driver_blocked'             => 'Gesperrter Fahrer kann nicht zugewiesen werden.',
        'driver_inactive'            => 'Inaktiver Fahrer kann nicht zugewiesen werden.',
        'trailer_assigned'           => 'Anhänger zugewiesen',
        'trailer_unassigned'         => 'Anhängerzuweisung aufgehoben',
        'trailer_not_found'          => 'Anhänger nicht gefunden',
        'trailer_not_assignable'     => 'Anhänger kann nicht zugewiesen werden.',
        'trailer_no_credential'      => 'Anhänger hat keinen gültigen Chip oder TAN.',
        'trailer_blocked'            => 'Gesperrter Anhänger kann nicht zugewiesen werden.',
        'trailer_inactive'           => 'Inaktiver oder archivierter Anhänger kann nicht zugewiesen werden.',
        'bay_not_found'              => 'Bucht nicht gefunden',
        'bay_inactive'               => 'Inaktive Bucht kann nicht zugewiesen werden.',
        'bay_occupied'               => 'Diese Bucht ist bereits besetzt.',
        'sap_fields_locked'          => 'Lokale Änderung an SAP-verwalteten Feldern abgelehnt',
        'order_locked'               => 'Dieser Beladungsauftrag ist durch eine aktive Ausführung gesperrt.',
        'order_locked_fields'        => 'Dieser Beladungsauftrag ist durch eine aktive Ausführung gesperrt. Die aufgeführten Felder können nicht geändert werden.',
        'events_audit_retrieved'     => 'Ereignisse & Prüfpfad des Beladungsauftrags abgerufen',

        // BayLineController
        'bays_retrieved'   => 'Buchten abgerufen',
        'bay_created'      => 'Bucht erstellt',
        'bay_retrieved'    => 'Bucht abgerufen',
        'bay_updated'      => 'Bucht aktualisiert',
        'bay_activated'    => 'Bucht aktiviert',
        'bay_deactivated'  => 'Bucht deaktiviert',
        'bay_not_found_bl' => 'Bucht nicht gefunden',
    ],

];
