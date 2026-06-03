<?php

return [

    'device_type' => [
        'plc'                     => 'PLC',
        'hmi_panel'               => 'HMI Panel',
        'smart_gate_controller'   => 'Smart Gate Controller',
        'printer'                 => 'Printer',
        'rfid_reader'             => 'RFID Reader',
        'trailer_chip_reader'     => 'Trailer Chip Reader',
        'compressor_controller'   => 'Compressor Controller',
        'electrolyzer_controller' => 'Electrolyzer Controller',
        'analyzer'                => 'Analyzer',
        'network_device'          => 'Network Device',
        'server'                  => 'Server',
        'rio_cabinet'             => 'Remote I/O Cabinet',
        'safety_device'           => 'Safety Device',
    ],

    'device_health' => [
        'online'       => 'Online',
        'warning'      => 'Warning',
        'alarm'        => 'Alarm',
        'fault'        => 'Fault',
        'offline'      => 'Offline',
        'service_mode' => 'Service Mode',
        'unknown'      => 'Unknown',
    ],

    'device_criticality' => [
        'critical' => 'Critical',
        'high'     => 'High',
        'medium'   => 'Medium',
        'low'      => 'Low',
    ],

    'device_subsystem' => [
        'filling'         => 'Filling',
        'gate'            => 'Gate',
        'driver_terminal' => 'Driver Terminal',
        'analysis'        => 'Analysis',
        'compressor'      => 'Compressor',
        'electrolyzer'    => 'Electrolyzer',
        'storage'         => 'Storage',
        'air'             => 'Air',
        'nitrogen'        => 'Nitrogen',
        'printing'        => 'Printing',
        'sap'             => 'SAP',
        'cloud'           => 'Cloud',
        'network'         => 'Network',
        'integration'     => 'Integration',
        'safety'          => 'Safety',
    ],

    'physical_location' => [
        'entry_gate'             => 'Entry Gate',
        'exit_gate'              => 'Exit Gate',
        'driver_terminal'        => 'Driver Terminal',
        'filling_bay_01'         => 'Filling Bay 01',
        'filling_bay_02'         => 'Filling Bay 02',
        'filling_bay_03'         => 'Filling Bay 03',
        'filling_bay_04'         => 'Filling Bay 04',
        'filling_bay_05'         => 'Filling Bay 05',
        'filling_bay_06'         => 'Filling Bay 06',
        'control_room'           => 'Control Room',
        'technical_room'         => 'Technical Room',
        'on_site_server_zone'    => 'On-site Server Zone',
        'compressor_container'   => 'Compressor Container',
        'electrolyzer_container' => 'Electrolyzer Container',
        'storage_area'           => 'Storage Area',
        'utility_area'           => 'Utility Area',
    ],

    'connection_test_result' => [
        'passed'        => 'Passed',
        'failed'        => 'Failed',
        'timeout'       => 'Timeout',
        'not_supported' => 'Not supported',
    ],

    'interface_status' => [
        'online'   => 'Online',
        'warning'  => 'Warning',
        'fault'    => 'Fault',
        'offline'  => 'Offline',
        'disabled' => 'Disabled',
        'unknown'  => 'Unknown',
    ],

    'interface_family' => [
        'sap'                => 'SAP',
        'plc_fieldbus'       => 'PLC / Fieldbus',
        'analysis_interface' => 'Analysis Interface',
        'gate_ethernet'      => 'Gate Ethernet',
        'printer_service'    => 'Printer Service',
        'cloud_sync'         => 'Cloud Sync',
        'vpn_remote_support' => 'VPN / Remote Support',
        'mail_notification'  => 'Mail / Notification',
    ],

    'interface_protocol' => [
        'sap_rfc_idoc'   => 'SAP RFC / IDoc / OData (TBC)',
        'opcua'          => 'OPC UA',
        'modbus_tcp'     => 'Modbus TCP',
        'profinet'       => 'Profinet',
        'profibus'       => 'Profibus',
        'ethernet'       => 'Ethernet',
        'https'          => 'HTTPS',
        'printer_local'  => 'Printer (local)',
        'printer_network' => 'Printer (network)',
        'vpn'            => 'VPN',
    ],

    'interface_direction' => [
        'inbound'       => 'Inbound',
        'outbound'      => 'Outbound',
        'bidirectional' => 'Bidirectional',
    ],

    'interface_blocking_level' => [
        'critical'       => 'Critical',
        'operational'    => 'Operational',
        'reporting_only' => 'Reporting only',
        'non_blocking'   => 'Non-blocking',
    ],

    'printer_job_status' => [
        'queued'       => 'Queued',
        'printing'     => 'Printing',
        'printed'      => 'Printed',
        'failed'       => 'Failed',
        'rerouted'     => 'Rerouted',
    ],

    'printer_queue_state' => [
        'idle'         => 'Idle',
        'printing'     => 'Printing',
        'paused'       => 'Paused',
        'offline'      => 'Offline',
        'error'        => 'Error',
    ],

    'plc_connection_state' => [
        'connected'    => 'Connected',
        'disconnected' => 'Disconnected',
        'error'        => 'Error',
        'unknown'      => 'Unknown',
    ],

    'messages' => [
        // HardwareDeviceController
        'devices_retrieved'           => 'Hardware devices retrieved',
        'device_not_found'            => 'Hardware device not found',
        'device_retrieved'            => 'Hardware device retrieved',
        'service_mode_set'            => 'Service mode set',
        'service_mode_restored'       => 'Device restored from service mode',
        'connection_test_recorded'    => 'Connection test recorded',
        'device_events_retrieved'     => 'Events retrieved',
        'already_in_service_mode'     => 'Device is already in service mode.',
        'not_in_service_mode'         => 'Device is not in service mode.',
        'test_not_supported'          => 'Connection test is not supported for safety devices.',

        // InterfaceHealthController
        'interfaces_retrieved'        => 'Interfaces retrieved',
        'interface_not_found'         => 'Interface not found',
        'interface_retrieved'         => 'Interface retrieved',
        'retry_requested'             => 'Retry requested',
        'interface_disabled'          => 'Interface is disabled — re-enable it before retrying.',
        'retry_not_supported'         => 'Retry is not supported for mail / notification interfaces',

        // CardReaderTabController
        'readers_retrieved'           => 'Card readers retrieved',
        'reader_not_found'            => 'Card reader not found',
        'reader_detail'               => 'Card reader detail retrieved',

        // PlcHealthTabController
        'plc_endpoints_retrieved'     => 'PLC / OPC UA endpoints retrieved',
        'plc_endpoint_not_found'      => 'PLC endpoint not found',
        'plc_endpoint_detail'         => 'PLC endpoint detail retrieved',

        // PrinterTabController
        'printers_retrieved'          => 'Printers retrieved',
        'printer_not_found'           => 'Printer not found',
        'printer_detail'              => 'Printer detail retrieved',
        'printer_job_feed'            => 'Printer job feed retrieved',
        'print_retry_queued'          => 'Print job retry queued',
        'print_rerouted'              => 'Print job rerouted',
        'print_action_failed'         => 'Print job action failed',
        'print_attempt_not_found'     => 'Print attempt not found',
        'print_not_retryable'         => 'Print attempt is not in a retryable state',
        'print_not_reroutable'        => 'Only failed print attempts can be rerouted',
        'replacement_invalid'         => 'Replacement printer is not a usable printer (must exist, be in printer device_type, not in service mode, and not in fault/offline).',
        'replacement_must_differ'     => 'Replacement printer must differ from the original.',
        'print_wrong_printer'         => 'Print attempt does not belong to this printer',

        // TerminalPanelTabController
        'terminals_retrieved'         => 'Terminals & panels retrieved',
        'terminal_not_found'          => 'Hardware device not found',
        'terminal_detail'             => 'Terminal / panel detail retrieved',
    ],

];
