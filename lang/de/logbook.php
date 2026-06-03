<?php

return [

    'category' => [
        'safety_note'     => 'Sicherheitsnotiz',
        'operations_note' => 'Betriebsnotiz',
        'manual_action'   => 'Manuelle Maßnahme',
        'handover_note'   => 'Übergabenotiz',
        'device_follow_up' => 'Gerätenachverfolgung',
        'near_miss'       => 'Beinaheunfall',
        'information'     => 'Information',
    ],

    'severity' => [
        'critical' => 'Kritisch',
        'high'     => 'Hoch',
        'medium'   => 'Mittel',
        'low'      => 'Niedrig',
        'info'     => 'Info',
    ],

    'follow_up_status' => [
        'open'        => 'Offen',
        'in_progress' => 'In Bearbeitung',
        'done'        => 'Erledigt',
        'cancelled'   => 'Storniert',
        'overdue'     => 'Überfällig',
    ],

    'area' => [
        'filling_station_01' => 'Befüllstation 01',
        'filling_station_02' => 'Befüllstation 02',
        'filling_station_03' => 'Befüllstation 03',
        'filling_station_04' => 'Befüllstation 04',
        'filling_station_05' => 'Befüllstation 05',
        'filling_station_06' => 'Befüllstation 06',
        'entry_gate'         => 'Einfahrtstor',
        'exit_gate'          => 'Ausfahrtstor',
        'analysis'           => 'Analyse',
        'compressor'         => 'Kompressor',
        'electrolyzer'       => 'Elektrolyseur',
        'control_room'       => 'Leitstand',
        'server_network'     => 'Server / Netzwerk',
    ],

    'audit_action_type' => [
        'create'               => 'Erstellen',
        'update'               => 'Aktualisieren',
        'block'                => 'Sperren',
        'unblock'              => 'Entsperren',
        'approve'              => 'Genehmigen',
        'reject'               => 'Ablehnen',
        'override'             => 'Überschreiben',
        'print'                => 'Drucken',
        'reprint'              => 'Nachdrucken',
        'assign'               => 'Zuweisen',
        'unassign'             => 'Zuweisung aufheben',
        'configuration_change' => 'Konfigurationsänderung',
        'other'                => 'Sonstige',
    ],

    'audit_approval_status' => [
        'not_required' => 'Nicht erforderlich',
        'pending'      => 'Ausstehend',
        'approved'     => 'Genehmigt',
        'rejected'     => 'Abgelehnt',
    ],

    'audit_change_category' => [
        'master_data'         => 'Stammdaten',
        'process_status'      => 'Prozessstatus',
        'manual_override'     => 'Manuelle Überschreibung',
        'quality_decision'    => 'Qualitätsentscheidung',
        'document'            => 'Dokument',
        'security_role'       => 'Sicherheit / Rolle',
        'configuration'       => 'Konfiguration',
        'device_service_mode' => 'Gerätewartungsmodus',
    ],

    'audit_retention_class' => [
        'operational'            => 'Betrieblich',
        'quality_critical'       => 'Qualitätskritisch',
        'security_critical'      => 'Sicherheitskritisch',
        'configuration_critical' => 'Konfigurationskritisch',
    ],

    'messages' => [
        // LogbookController
        'entries_retrieved'        => 'Logbucheinträge abgerufen',
        'entry_not_found'          => 'Logbucheintrag nicht gefunden',
        'entry_retrieved'          => 'Logbucheintrag-Detail abgerufen',
        'entry_created'            => 'Logbucheintrag erstellt',
        'entry_corrected'          => 'Logbucheintrag korrigiert',
        'follow_up_added'          => 'Nachverfolgung hinzugefügt',
        'follow_up_done'           => 'Nachverfolgung als erledigt markiert',

        // AuditTrailController
        'audit_retrieved'          => 'Prüfpfad abgerufen',
        'audit_record_not_found'   => 'Prüfprotokoll-Eintrag nicht gefunden',
        'audit_record_detail'      => 'Prüfprotokoll-Detail abgerufen',
    ],

];
