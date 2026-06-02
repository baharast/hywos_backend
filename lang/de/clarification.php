<?php

return [

    'status' => [
        'open'               => 'Offen',
        'in_progress'        => 'In Bearbeitung',
        'waiting_for_owner'  => 'Wartet auf Verantwortlichen',
        'resolved'           => 'Gelöst',
        'closed'             => 'Geschlossen',
    ],

    'severity' => [
        'low'      => 'Niedrig',
        'normal'   => 'Normal',
        'high'     => 'Hoch',
        'critical' => 'Kritisch',
    ],

    'source' => [
        'order_matching'   => 'Auftragsabgleich',
        'gate_terminal'    => 'Tor / Terminal',
        'parking'          => 'Parken',
        'loading_control'  => 'Befüllsteuerung',
        'analysis_quality' => 'Analyse / Qualität',
        'documents'        => 'Dokumente / Druck',
        'device_interface' => 'Gerät / Schnittstelle',
        'exit'             => 'Ausfahrt',
    ],

    'primary_action' => [
        'resolve_trailer_identification' => 'Anhängeridentifikation klären',
        'fix_order_assignment'           => 'Auftrag / Zuweisung korrigieren',
        'resolve_parking_confirmation'   => 'Parkbestätigung klären',
        'route_to_owner'                 => 'An Verantwortlichen weiterleiten',
        'route_to_analysis'              => 'An Analyse & Qualität weiterleiten',
        'route_to_documents'             => 'An Dokumente weiterleiten',
        'resolve_exit_blocker'           => 'Ausfahrtsperre klären',
        'resolve_case'                   => 'Fall lösen',
        'none'                           => 'Keine Aktion verfügbar',
    ],

    'entity_type' => [
        'plant_visit'      => 'Werksbesuch',
        'loading_order'    => 'Beladungsauftrag',
        'parking_slot'     => 'Parkplatz',
        'bay_line'         => 'Befüllbucht',
        'driver'           => 'Fahrer',
        'trailer'          => 'Anhänger',
        'tractor_vehicle'  => 'Zugmaschine',
        'chip_card'        => 'Chipkarte',
        'tan'              => 'TAN',
        'sap_sync_record'  => 'SAP-Synchronisierungsdatensatz',
    ],

    'messages' => [
        'cases_retrieved'        => 'Klärungsfälle abgerufen',
        'case_retrieved'         => 'Klärungsfall abgerufen',
        'case_created'           => 'Klärungsfall erstellt',
        'case_not_found'         => 'Klärungsfall nicht gefunden',
        'case_acknowledged'      => 'Klärungsfall bestätigt',
        'case_assigned'          => 'Klärungsfall zugewiesen',
        'case_waiting_for_owner' => 'Klärungsfall wartet auf Verantwortlichen',
        'case_resolved'          => 'Klärungsfall gelöst',
        'case_closed'            => 'Klärungsfall geschlossen',
    ],

    'validation' => [
        'resolution_note_required' => 'Eine Lösungsnotiz ist erforderlich, um einen Klärungsfall abzuschließen.',
        'resolution_note_min'      => 'Die Lösungsnotiz muss mindestens 3 Zeichen lang sein.',
    ],

];
