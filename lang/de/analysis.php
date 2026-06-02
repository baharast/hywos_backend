<?php

return [

    'status' => [
        'not_started'         => 'Nicht gestartet',
        'pre_analysis_open'   => 'Voranalyse offen',
        'pre_analysis_ok'     => 'Voranalyse OK',
        'pre_analysis_failed' => 'Voranalyse fehlgeschlagen',
        'main_analysis_open'  => 'Hauptanalyse offen',
        'main_analysis_ok'    => 'Hauptanalyse OK',
        'functionally_nok'    => 'Funktional NOK',
        'technically_invalid' => 'Technisch ungültig',
        'quality_checked'     => 'Qualität geprüft',
    ],

    'active_status' => [
        'queued'            => 'In Warteschlange',
        'preparing'         => 'Wird vorbereitet',
        'purging'           => 'Wird gespült',
        'running'           => 'Läuft',
        'waiting_result'    => 'Warte auf Ergebnis',
        'result_received'   => 'Ergebnis erhalten',
        'waiting_decision'  => 'Warte auf Entscheidung',
        'invalid'           => 'Ungültig',
        'nok'               => 'NOK',
        'failed'            => 'Fehlgeschlagen',
        'on_hold'           => 'Zurückgestellt',
        'cancelled'         => 'Storniert',
        'closed'            => 'Geschlossen',
    ],

    'type' => [
        'pre_analysis'   => 'Voranalyse',
        'main_analysis'  => 'Hauptanalyse',
        'final_analysis' => 'Abschlussanalyse',
        'retest'         => 'Wiederholungstest',
    ],

    'user_action' => [
        'view_details'                   => 'Details anzeigen',
        'put_on_hold'                    => 'Zurückstellen',
        'request_repeat_analysis'        => 'Wiederholungsanalyse / Retest anfragen',
        'cancel_analysis'                => 'Analyse stornieren',
        'release_loading'                => 'Befüllung freigeben',
        'reject_loading_block_trailer'   => 'Befüllung ablehnen / Anhänger sperren',
        'open_fault_case_manual_check'   => 'Fehlerfall öffnen / manuelle Prüfung',
        'repeat_measurement'             => 'Messung wiederholen',
        'manual_functional_approval'     => 'Manuelle Funktionsgenehmigung',
        'open_related_result_record'     => 'Verknüpften Ergebnisdatensatz öffnen',
    ],

    'open_decision_status' => [
        'repeat_requested'     => 'Wiederholung angefordert',
        'pending_review'       => 'Prüfung ausstehend',
        'escalation_required'  => 'Eskalation erforderlich',
        'waiting_for_decision' => 'Warte auf Entscheidung',
    ],

    'closed_decision_status' => [
        'approved' => 'Genehmigt',
        'released' => 'Freigegeben',
        'blocked'  => 'Gesperrt',
        'rejected' => 'Abgelehnt',
        'closed'   => 'Geschlossen',
    ],

    'element_status' => [
        'waiting'         => 'Wartend',
        'received'        => 'Empfangen',
        'valid'           => 'Gültig',
        'nok'             => 'NOK (Limit überschritten)',
        'missing'         => 'Fehlend',
        'invalid'         => 'Ungültig',
        'not_transferred' => 'Nicht übertragen',
    ],

    'sampling_trigger' => [
        'before_loading'  => 'Vor Befüllung',
        'main_30_percent' => 'Haupt 30%',
        'main_60_percent' => 'Haupt 60%',
        'main_90_percent' => 'Haupt 90%',
        'after_loading'   => 'Nach Befüllung',
        'not_applicable'  => 'Nicht zutreffend',
    ],

    'result_status' => [
        'passed'     => 'Bestanden',
        'nok'        => 'NOK / Fehlgeschlagen',
        'invalid'    => 'Ungültig',
        'incomplete' => 'Unvollständig',
    ],

    'device_type' => [
        'orthosmart_analyser'     => 'OrthoSmart Analysator',
        'gas_warning_controller'  => 'GWA-REGARD3900 (Gaswarnanlage)',
        'sample_switching_module' => 'CGS-SAM1000DP2 (Probenwechsler)',
    ],

    'device_health' => [
        'healthy'     => 'Betriebsbereit',
        'warning'     => 'Warnung',
        'alarm'       => 'Alarm',
        'fault'       => 'Störung',
        'offline'     => 'Offline / Veraltet',
        'maintenance' => 'Wartung / Deaktiviert',
    ],

    'device_calibration' => [
        'valid'          => 'Gültig',
        'due_soon'       => 'Demnächst fällig',
        'overdue'        => 'Überfällig',
        'failed'         => 'Fehlgeschlagen',
        'not_configured' => 'Nicht konfiguriert',
    ],

    'device_run_state' => [
        'ready'           => 'Bereit',
        'preparing'       => 'Wird vorbereitet',
        'measuring'       => 'Messend',
        'result_transfer' => 'Ergebnis wird übertragen',
        'idle'            => 'Leerlauf',
        'fault'           => 'Störung',
    ],

    'calibration_profile_status' => [
        'draft'    => 'Entwurf',
        'active'   => 'Aktiv',
        'inactive' => 'Inaktiv',
        'retired'  => 'Außer Betrieb',
    ],

    'calibration_run_result' => [
        'pass'         => 'Bestanden',
        'fail'         => 'Fehlgeschlagen',
        'not_recorded' => 'Nicht protokolliert',
    ],

    'calibration_lockout' => [
        'warn_only'                 => 'Nur Warnung',
        'block_analysis_start'      => 'Analyse sperren',
        'block_release_certificate' => 'Freigabe / Zertifikat sperren',
    ],

    'messages' => [
        // ActiveAnalysisController
        'analyses_retrieved'            => 'Aktive Analysen abgerufen',
        'analysis_not_found'            => 'Analyse nicht gefunden',
        'analysis_retrieved'            => 'Analyse abgerufen',
        'analysis_on_hold'              => 'Analyse zurückgestellt',
        'analysis_cancelled'            => 'Analyse storniert',
        'analysis_released'             => 'Befüllung freigegeben',
        'analysis_rejected'             => 'Befüllung abgelehnt / Anhänger gesperrt',
        'analysis_fault_case'           => 'Fehlerfall geöffnet',
        'analysis_repeated'             => 'Wiederholung / Retest angefordert',
        'analysis_measurement_repeated' => 'Messung wiederholen eingereiht',
        'analysis_approved'             => 'Analyse manuell genehmigt',
        'action_not_allowed'            => 'Diese Aktion ist vom aktuellen Analysestatus nicht erlaubt.',
        'max_attempts_reached'          => 'Maximale Versuche erreicht; öffnen Sie stattdessen einen Fehlerfall.',
        'technical_repeat_used'         => 'Der technische Einzel-Retest wurde bereits verwendet; öffnen Sie einen Fehlerfall.',

        // AnalysisDeviceController
        'devices_retrieved'    => 'Analysegeräte abgerufen',
        'device_not_found'     => 'Analysegerät nicht gefunden',
        'device_retrieved'     => 'Analysegerät abgerufen',
        'devices_status_table' => 'Statustabelle der Analysegeräte abgerufen',
        'device_channels'      => 'Analysegerät-Kanäle abgerufen',
        'device_events'        => 'Analysegerät-Ereignisse abgerufen',
        'device_no_channels'   => 'Dieser Gerätetyp hat keine Kanäle',
        'gas_warning_channel'  => 'Gaswarnkanal',

        // QualityDecisionController
        'results_retrieved' => 'Ergebnisse abgerufen',
        'result_not_found'  => 'Ergebnis nicht gefunden',
        'result_retrieved'  => 'Ergebnis abgerufen',
    ],

];
