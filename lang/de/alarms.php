<?php

return [

    'severity' => [
        'critical' => 'Kritisch',
        'high'     => 'Hoch',
        'medium'   => 'Mittel',
        'low'      => 'Niedrig',
        'info'     => 'Info',
    ],

    'status' => [
        'active'                 => 'Aktiv',
        'acknowledged'           => 'Bestätigt',
        'assigned'               => 'Zugewiesen',
        'in_progress'            => 'In Bearbeitung',
        'resolved_pending_close' => 'Gelöst (Abschluss ausstehend)',
        'closed'                 => 'Geschlossen',
        'suppressed_maintenance' => 'Unterdrückt / Wartung',
    ],

    'category' => [
        'safety'               => 'Sicherheit',
        'loading_process'      => 'Befüllung / Prozess',
        'quality_analysis'     => 'Qualität / Analyse',
        'gate_access'          => 'Tor / Zugang',
        'device_communication' => 'Gerät / Kommunikation',
        'document_printer'     => 'Dokument / Drucker',
        'interface_sap'        => 'Schnittstelle / SAP',
    ],

    'blocking_impact' => [
        'blocks_loading'            => 'Befüllung gesperrt',
        'blocks_gate_entry'         => 'Toreinfahrt gesperrt',
        'blocks_exit'               => 'Ausfahrt gesperrt',
        'blocks_documents'          => 'Dokumente gesperrt',
        'analysis_approval_blocked' => 'Analysefreigabe gesperrt',
        'warning_only'              => 'Nur Warnung',
    ],

    'source_type' => [
        'station_plc'             => 'Stations-SPS',
        'analysis_plc'            => 'Analyse-SPS',
        'central_plc'             => 'Zentral-SPS',
        'gate_smart_panel'        => 'Tor-Smartpanel',
        'compressor_controller'   => 'Kompressorsteuerung',
        'electrolyzer_controller' => 'Elektrolyseursteuerung',
        'printer'                 => 'Drucker',
        'sap_connector'           => 'SAP-Konnektor',
        'other'                   => 'Sonstige',
    ],

    'owner_role' => [
        'operator'            => 'Bediener',
        'it_support'          => 'IT / Support',
        'analysis_specialist' => 'Analysespezialist',
        'dispatcher'          => 'Disponent',
        'admin'               => 'Administrator',
    ],

    'event_severity' => [
        'info'     => 'Info',
        'warning'  => 'Warnung',
        'danger'   => 'Gefahr',
        'critical' => 'Kritisch',
    ],

    'journal_category' => [
        'gate_access'          => 'Tor / Zugang',
        'driver_terminal'      => 'Fahrerterminal',
        'filling_station'      => 'Befüllstation',
        'loading'              => 'Befüllung',
        'analysis'             => 'Analyse',
        'document_print'       => 'Dokument / Druck',
        'device_communication' => 'Gerät / Kommunikation',
        'interface_sap'        => 'Schnittstelle / SAP',
        'security'             => 'Sicherheit',
        'system'               => 'System',
        'audit_reference'      => 'Prüfreferenz',
    ],

    'journal_result' => [
        'info'      => 'Info',
        'warning'   => 'Warnung',
        'error'     => 'Fehler',
        'success'   => 'Erfolgreich',
        'denied'    => 'Abgelehnt',
        'failed'    => 'Fehlgeschlagen',
        'completed' => 'Abgeschlossen',
        'timeout'   => 'Zeitüberschreitung',
        'blocked'   => 'Gesperrt',
        'started'   => 'Gestartet',
        'stopped'   => 'Gestoppt',
    ],

    'security_category' => [
        'authentication'    => 'Authentifizierung',
        'authorization'     => 'Autorisierung',
        'account_lifecycle' => 'Kontolebenzyklus',
        'role_permission'   => 'Rolle / Berechtigung',
        'chip_tan_access'   => 'Chip / TAN-Zugang',
        'session'           => 'Sitzung',
        'api_integration'   => 'API / Integration',
    ],

    'security_result' => [
        'success' => 'Erfolgreich',
        'failed'  => 'Fehlgeschlagen',
        'denied'  => 'Abgelehnt',
        'locked'  => 'Gesperrt',
        'expired' => 'Abgelaufen',
        'blocked' => 'Blockiert',
        'unknown' => 'Unbekannt',
    ],

    'messages' => [
        'alarms_retrieved'          => 'Aktive Alarme abgerufen',
        'alarm_not_found'           => 'Alarm nicht gefunden',
        'alarm_detail'              => 'Alarmdetail abgerufen',
        'alarm_acknowledged'        => 'Alarm bestätigt',
        'alarm_assigned'            => 'Alarm zugewiesen',
        'alarm_in_progress'         => 'Alarm in Bearbeitung',
        'alarm_resolved'            => 'Alarm gelöst (Abschluss ausstehend)',
        'alarm_closed'              => 'Alarm geschlossen',
        'journal_retrieved'         => 'Ereignisprotokoll abgerufen',
        'event_not_found'           => 'Ereignis nicht gefunden',
        'event_detail'              => 'Ereignisdetail abgerufen',
        'security_retrieved'        => 'Sicherheitsereignisse abgerufen',
        'security_not_found'        => 'Sicherheitsereignis nicht gefunden',
        'security_detail'           => 'Sicherheitsereignisdetail abgerufen',
        'restricted_note'           => 'Eingeschränkte Sicherheitsdaten. Nicht an unbefugte Empfänger weitergeben.',
    ],

];
