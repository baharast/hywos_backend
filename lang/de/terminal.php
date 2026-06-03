<?php

return [

    'login_method' => [
        'tan'       => 'TAN-Nummer',
        'chip_card' => 'Fahrer-Chipkarte',
    ],

    'security_risk_level' => [
        'critical' => 'Kritisch',
        'high'     => 'Hoch',
        'medium'   => 'Mittel',
        'low'      => 'Niedrig',
        'info'     => 'Info',
    ],

    'security_review_status' => [
        'unreviewed'     => 'Nicht überprüft',
        'in_review'      => 'In Überprüfung',
        'reviewed'       => 'Überprüft',
        'false_positive' => 'Falsch positiv',
    ],

    'session_state' => [
        'idle'           => 'Bereit',
        'active'         => 'Aktiv',
        'denied'         => 'Abgelehnt',
        'needs_operator' => 'Bediener erforderlich',
        'device_fault'   => 'Gerätestörung',
        'service_mode'   => 'Wartungsmodus',
    ],

    'current_screen' => [
        'driver_login'           => 'Fahrer-Anmeldung',
        'trailer_identification' => 'Anhängeridentifikation',
        'tractor_machine_plate'  => 'Zugmaschinen-/Maschinenkennzeichen',
        'task_selection'         => 'Aufgabenauswahl',
        'instruction'            => 'Anweisung',
        'documents'              => 'Dokumente',
        'exit_validation'        => 'Ausfahrtvalidierung',
    ],

    'touchpoint' => [
        'entry_gate'      => 'Einfahrtstor',
        'driver_terminal' => 'Leitstand / Fahrerterminal',
        'exit_gate'       => 'Ausfahrtstor',
    ],

    'reader_kind' => [
        'entry_gate_personal'         => 'Einfahrtstor Personal-RFID-Leser',
        'exit_id_reader'              => 'Ausfahrt-Identifikationsleser',
        'driver_terminal_reader'      => 'Fahrerterminal RFID/Chip-Leser',
        'filling_bay_reader'          => 'Befüllbucht RFID-Leser',
        'trailer_registration_reader' => 'Anhänger-Chip-Leser (Registrierung)',
        'other'                       => 'Sonstiger Leser',
    ],

    'reader_linked_process' => [
        'entry'                => 'Einfahrt',
        'exit'                 => 'Ausfahrt',
        'terminal_login'       => 'Terminal-Anmeldung',
        'loading_release'      => 'Befüllungsfreigabe',
        'trailer_registration' => 'Anhängerregistrierung',
        'unknown'              => 'Unbekannt',
    ],

    'supported_media' => [
        'driver_chip'        => 'Fahrer-Chip',
        'tan_input'          => 'TAN-Eingabe',
        'trailer_chip'       => 'Anhänger-Chip',
        'rfid_authorization' => 'RFID-Autorisierung',
    ],

    'terminal_panel_kind' => [
        'entry_gate'        => 'Einfahrtstor',
        'exit_gate'         => 'Ausfahrtstor',
        'driver_terminal'   => 'Fahrerterminal',
        'filling_hmi_panel' => 'Befüll-HMI-Panel',
        'operator_client'   => 'Bediener-Client',
        'other'             => 'Sonstige',
    ],

    'login_result_message' => [
        'success'               => 'Identität bestätigt.',
        'training_required'     => 'Sicherheitsschulung ist vor dem Betriebszugang erforderlich.',
        'invalid'               => 'Identifikation ungültig. Bitte Nummer prüfen oder Terminal-Support kontaktieren.',
        'expired'               => 'Diese TAN ist abgelaufen. Bitte Terminal-Support kontaktieren.',
        'used'                  => 'Diese TAN wurde bereits verwendet. Bitte Terminal-Support kontaktieren.',
        'revoked'               => 'Diese TAN wurde widerrufen. Bitte Terminal-Support kontaktieren.',
        'pending'               => 'Diese TAN ist noch nicht gültig. Bitte später erneut versuchen.',
        'driver_blocked'        => 'Zugang nicht erlaubt. Bitte Terminal-Support kontaktieren.',
        'driver_inactive'       => 'Zugang nicht erlaubt. Bitte Terminal-Support kontaktieren.',
        'chip_blocked'          => 'Chipkarte ist gesperrt. Bitte Terminal-Support kontaktieren.',
        'chip_unassigned'       => 'Chipkarte nicht erkannt. Bitte Terminal-Support kontaktieren.',
        'reader_offline'        => 'Chip-Leser nicht verfügbar. TAN verwenden oder Terminal-Support kontaktieren.',
        'terminal_offline'      => 'Terminal ist offline. Bitte Terminal-Support kontaktieren.',
        'terminal_service_mode' => 'Terminal befindet sich im Wartungsmodus. Bitte Terminal-Support kontaktieren.',
        'backend_unavailable'   => 'Systemverbindung nicht verfügbar. Bitte Terminal-Support kontaktieren.',
        'too_many_attempts'     => 'Zu viele Versuche. Bitte Terminal-Support kontaktieren.',
        'default'               => 'Anmeldung fehlgeschlagen. Bitte Terminal-Support kontaktieren.',
    ],

    'messages' => [
        // GateTerminalMonitorController
        'sessions_retrieved'          => 'Terminalsitzungen abgerufen',
        'session_not_found'           => 'Terminalsitzung nicht gefunden',
        'session_retrieved'           => 'Terminalsitzung abgerufen',
        'touchpoint_board'            => 'Touchpoint-Board abgerufen',
        'no_issues'                   => 'Keine aktiven Tor- oder Terminalprobleme',

        // SafetyTrainingController
        'modules_retrieved'           => 'Sicherheitsschulungsmodule abgerufen',
        'module_retrieved'            => 'Sicherheitsschulungsmodul abgerufen',
        'exam_retrieved'              => 'Sicherheitsschulungsprüfung abgerufen',
        'module_not_found'            => 'Sicherheitsschulungsmodul nicht gefunden',
        'session_not_found'           => 'Sitzung nicht gefunden',
        'session_no_driver'           => 'Sitzung hat keinen Fahrer — Prüfung kann nicht bewertet werden',
        'session_no_driver_progress'  => 'Sitzung hat keinen Fahrer — Schulungsfortschritt kann nicht gespeichert werden',
        'driver_not_found'            => 'Fahrer für Sitzung nicht gefunden',
        'training_recorded'           => 'Schulungsmodul gespeichert',
        'exam_passed'                 => 'Sicherheitswissenstest bestanden',
        'exam_failed'                 => 'Sicherheitswissenstest nicht bestanden',

        // TerminalAuthController
        'terminal_not_found'          => 'Terminal nicht gefunden',
        'terminal_config'             => 'Terminalkonfiguration abgerufen',
        'session_retrieved'           => 'Sitzung abgerufen',
        'session_ended'               => 'Sitzung beendet',
        'language_updated'            => 'Sprache aktualisiert',
        'safety_info_retrieved'       => 'Sicherheitshinweise abgerufen',
        'safety_info_title'           => 'Sicherheitshinweise',
        'safety_rule_1'               => 'Kein Rauchen, offene Flammen oder Zündquellen in H2-Bereichen.',
        'safety_rule_2'               => 'Alle Gas-, Feuer- und Evakuierungsalarme beachten.',
        'safety_rule_3'               => 'Nur in erlaubten Fahrerbereichen aufhalten.',
        'safety_rule_4'               => 'Vorgeschriebene PSA tragen, wo angewiesen.',
        'safety_rule_5'               => 'Anweisungen von Terminal und Bediener befolgen.',
    ],

];
