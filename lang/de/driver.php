<?php

return [

    'status' => [
        'active'   => 'Aktiv',
        'inactive' => 'Inaktiv',
        'blocked'  => 'Gesperrt',
        'unknown'  => 'Unbekannt',
    ],

    'training_status' => [
        'valid'        => 'Gültig',
        'expired'      => 'Abgelaufen',
        'missing'      => 'Fehlend',
        'not_required' => 'Nicht erforderlich',
        'unknown'      => 'Unbekannt',
    ],

    'identification_status' => [
        'chip_assigned' => 'Chip zugewiesen',
        'tan_available' => 'TAN verfügbar',
        'missing'       => 'Fehlend',
        'blocked'       => 'Gesperrt',
        'expired'       => 'Abgelaufen',
    ],

    'task' => [
        'trailer_filling'                => 'Tankwagen befüllen',
        'park_trailer'                   => 'Anhänger abstellen',
        'pickup_loaded_trailer'          => 'Beladenen Anhänger abholen',
        'pickup_empty_trailer_then_load' => 'Leeren Anhänger abholen und befüllen',
        'documents_exit'                 => 'Dokumente & Ausfahrt',
        'exit_only'                      => 'Nur Ausfahrt',
    ],

    'messages' => [
        'retrieved'       => 'Fahrer abgerufen',
        'show'            => 'Fahrer abgerufen',
        'created'         => 'Fahrer erstellt',
        'updated'         => 'Fahrer aktualisiert',
        'blocked'         => 'Fahrer gesperrt',
        'unblocked'       => 'Fahrer entsperrt',
        'auth_media'      => 'Ausweismedien abgerufen',
        'tan_created'     => 'TAN erstellt',
        'plant_visits'    => 'Pflanzenbesuche abgerufen (Platzhalter)',
        'events_audit'    => 'Ereignisse & Prüfprotokoll des Fahrers abgerufen',
        'export_prepared' => 'Fahrerexport vorbereitet',
        'not_found'       => 'Fahrer nicht gefunden',
        'already_blocked' => 'Fahrer ist bereits gesperrt',
        'not_blocked'     => 'Fahrer ist nicht gesperrt',
        'id_mismatch'     => 'driver_id im Anfragekörper stimmt nicht mit der URL überein',
    ],

    'validation' => [
        'expires_at_after' => 'expires_at muss ein zukünftiges Datum/Uhrzeit sein.',
    ],

    'workflow' => [
        'session_not_found'      => 'Sitzung nicht gefunden',
        'no_orders'              => 'Keine Aufträge für diese Sitzung',
        'orders_retrieved'       => 'Fahrer-Aufträge abgerufen',
        'trailer_info_recorded'  => 'Anhängerinfo gespeichert',
        'trailer_check_recorded' => 'Anhänger-Check gespeichert',
        'tractor_plate_recorded' => 'Zugmaschinenkennzeichen gespeichert',
        'task_selected'          => 'Aufgabe gewählt',
        'order_confirmed'        => 'Auftrag bestätigt',
        'bay_line_assignment'    => 'Bucht-Zuweisung abgerufen',
        'parking_assignment'     => 'Parkplatzzuweisung abgerufen',
        'delivery_note_printed'  => 'Lieferschein gedruckt',
        'workflow_completed'     => 'Workflow abgeschlossen',
        'session_no_driver'      => 'Sitzung hat keinen Fahrer; Auftrag kann nicht bestätigt werden.',
        'order_not_eligible'     => 'Gewählter Auftrag ist nicht in der fahrerseitigen Liste für diese Aufgabe.',
        'no_order_confirmed'     => 'Kein Auftrag für diese Sitzung bestätigt; nichts zu drucken.',
    ],

];
