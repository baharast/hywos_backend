<?php

return [

    'device_type' => [
        'plc'                     => 'SPS',
        'hmi_panel'               => 'HMI-Panel',
        'smart_gate_controller'   => 'Smart-Torsteuerung',
        'printer'                 => 'Drucker',
        'rfid_reader'             => 'RFID-Leser',
        'trailer_chip_reader'     => 'Anhänger-Chip-Leser',
        'compressor_controller'   => 'Kompressorsteuerung',
        'electrolyzer_controller' => 'Elektrolyseursteuerung',
        'analyzer'                => 'Analysator',
        'network_device'          => 'Netzwerkgerät',
        'server'                  => 'Server',
        'rio_cabinet'             => 'Remote-I/O-Schrank',
        'safety_device'           => 'Sicherheitsgerät',
    ],

    'device_health' => [
        'online'       => 'Online',
        'warning'      => 'Warnung',
        'alarm'        => 'Alarm',
        'fault'        => 'Störung',
        'offline'      => 'Offline',
        'service_mode' => 'Wartungsmodus',
        'unknown'      => 'Unbekannt',
    ],

    'device_criticality' => [
        'critical' => 'Kritisch',
        'high'     => 'Hoch',
        'medium'   => 'Mittel',
        'low'      => 'Niedrig',
    ],

    'device_subsystem' => [
        'filling'         => 'Befüllung',
        'gate'            => 'Tor',
        'driver_terminal' => 'Fahrerterminal',
        'analysis'        => 'Analyse',
        'compressor'      => 'Kompressor',
        'electrolyzer'    => 'Elektrolyseur',
        'storage'         => 'Speicher',
        'air'             => 'Luft',
        'nitrogen'        => 'Stickstoff',
        'printing'        => 'Druck',
        'sap'             => 'SAP',
        'cloud'           => 'Cloud',
        'network'         => 'Netzwerk',
        'integration'     => 'Integration',
        'safety'          => 'Sicherheit',
    ],

    'physical_location' => [
        'entry_gate'             => 'Einfahrtstor',
        'exit_gate'              => 'Ausfahrtstor',
        'driver_terminal'        => 'Fahrerterminal',
        'filling_bay_01'         => 'Befüllbucht 01',
        'filling_bay_02'         => 'Befüllbucht 02',
        'filling_bay_03'         => 'Befüllbucht 03',
        'filling_bay_04'         => 'Befüllbucht 04',
        'filling_bay_05'         => 'Befüllbucht 05',
        'filling_bay_06'         => 'Befüllbucht 06',
        'control_room'           => 'Leitstand',
        'technical_room'         => 'Technikraum',
        'on_site_server_zone'    => 'Server-Zone vor Ort',
        'compressor_container'   => 'Kompressorcontainer',
        'electrolyzer_container' => 'Elektrolyseurcontainer',
        'storage_area'           => 'Lagerbereich',
        'utility_area'           => 'Versorgungsbereich',
    ],

    'connection_test_result' => [
        'passed'        => 'Bestanden',
        'failed'        => 'Fehlgeschlagen',
        'timeout'       => 'Zeitüberschreitung',
        'not_supported' => 'Nicht unterstützt',
    ],

    'interface_status' => [
        'online'   => 'Online',
        'warning'  => 'Warnung',
        'fault'    => 'Störung',
        'offline'  => 'Offline',
        'disabled' => 'Deaktiviert',
        'unknown'  => 'Unbekannt',
    ],

    'interface_family' => [
        'sap'                => 'SAP',
        'plc_fieldbus'       => 'SPS / Feldbus',
        'analysis_interface' => 'Analyse-Schnittstelle',
        'gate_ethernet'      => 'Tor-Ethernet',
        'printer_service'    => 'Druckerdienst',
        'cloud_sync'         => 'Cloud-Synchronisierung',
        'vpn_remote_support' => 'VPN / Fernwartung',
        'mail_notification'  => 'E-Mail / Benachrichtigung',
    ],

    'interface_protocol' => [
        'sap_rfc_idoc'    => 'SAP RFC / IDoc / OData (TBD)',
        'opcua'           => 'OPC UA',
        'modbus_tcp'      => 'Modbus TCP',
        'profinet'        => 'Profinet',
        'profibus'        => 'Profibus',
        'ethernet'        => 'Ethernet',
        'https'           => 'HTTPS',
        'printer_local'   => 'Drucker (lokal)',
        'printer_network' => 'Drucker (Netzwerk)',
        'vpn'             => 'VPN',
    ],

    'interface_direction' => [
        'inbound'       => 'Eingehend',
        'outbound'      => 'Ausgehend',
        'bidirectional' => 'Bidirektional',
    ],

    'interface_blocking_level' => [
        'critical'       => 'Kritisch',
        'operational'    => 'Betrieblich',
        'reporting_only' => 'Nur Berichterstattung',
        'non_blocking'   => 'Nicht blockierend',
    ],

    'printer_job_status' => [
        'queued'   => 'In Warteschlange',
        'printing' => 'Druckt',
        'printed'  => 'Gedruckt',
        'failed'   => 'Fehlgeschlagen',
        'rerouted' => 'Umgeleitet',
    ],

    'printer_queue_state' => [
        'idle'         => 'Bereit',
        'printing'     => 'Druckt',
        'paused'       => 'Pausiert',
        'offline'      => 'Offline',
        'error'        => 'Fehler',
    ],

    'plc_connection_state' => [
        'connected'    => 'Verbunden',
        'disconnected' => 'Getrennt',
        'error'        => 'Fehler',
        'unknown'      => 'Unbekannt',
    ],

    'messages' => [
        // HardwareDeviceController
        'devices_retrieved'        => 'Hardwaregeräte abgerufen',
        'device_not_found'         => 'Hardwaregerät nicht gefunden',
        'device_retrieved'         => 'Hardwaregerät abgerufen',
        'service_mode_set'         => 'Wartungsmodus aktiviert',
        'service_mode_restored'    => 'Gerät aus Wartungsmodus wiederhergestellt',
        'connection_test_recorded' => 'Verbindungstest protokolliert',
        'device_events_retrieved'  => 'Ereignisse abgerufen',
        'already_in_service_mode'  => 'Gerät befindet sich bereits im Wartungsmodus.',
        'not_in_service_mode'      => 'Gerät befindet sich nicht im Wartungsmodus.',
        'test_not_supported'       => 'Verbindungstest wird für Sicherheitsgeräte nicht unterstützt.',

        // InterfaceHealthController
        'interfaces_retrieved'     => 'Schnittstellen abgerufen',
        'interface_not_found'      => 'Schnittstelle nicht gefunden',
        'interface_retrieved'      => 'Schnittstelle abgerufen',
        'retry_requested'          => 'Neuversuch angefordert',
        'interface_disabled'       => 'Schnittstelle ist deaktiviert — vor dem Neuversuch aktivieren.',
        'retry_not_supported'      => 'Neuversuch wird für Mail-/Benachrichtigungsschnittstellen nicht unterstützt',

        // CardReaderTabController
        'readers_retrieved'        => 'Kartenleser abgerufen',
        'reader_not_found'         => 'Kartenleser nicht gefunden',
        'reader_detail'            => 'Kartenleser-Detail abgerufen',

        // PlcHealthTabController
        'plc_endpoints_retrieved'  => 'SPS / OPC-UA-Endpunkte abgerufen',
        'plc_endpoint_not_found'   => 'SPS-Endpunkt nicht gefunden',
        'plc_endpoint_detail'      => 'SPS-Endpunkt-Detail abgerufen',

        // PrinterTabController
        'printers_retrieved'       => 'Drucker abgerufen',
        'printer_not_found'        => 'Drucker nicht gefunden',
        'printer_detail'           => 'Drucker-Detail abgerufen',
        'printer_job_feed'         => 'Drucker-Auftragsliste abgerufen',
        'print_retry_queued'       => 'Druckauftrag-Neuversuch eingereiht',
        'print_rerouted'           => 'Druckauftrag umgeleitet',
        'print_action_failed'      => 'Druckauftragsaktion fehlgeschlagen',
        'print_attempt_not_found'  => 'Druckversuch nicht gefunden',
        'print_not_retryable'      => 'Druckversuch befindet sich nicht in einem wiederholbaren Zustand',
        'print_not_reroutable'     => 'Nur fehlgeschlagene Druckversuche können umgeleitet werden',
        'replacement_invalid'      => 'Ersatzdrucker ist nicht verwendbar (muss vorhanden, kein Wartungsmodus, kein Fehler/Offline sein).',
        'replacement_must_differ'  => 'Ersatzdrucker muss vom Original abweichen.',
        'print_wrong_printer'      => 'Druckversuch gehört nicht zu diesem Drucker',

        // TerminalPanelTabController
        'terminals_retrieved'      => 'Terminals & Panels abgerufen',
        'terminal_not_found'       => 'Hardwaregerät nicht gefunden',
        'terminal_detail'          => 'Terminal / Panel-Detail abgerufen',
    ],

];
