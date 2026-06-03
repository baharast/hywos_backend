<?php

return [

    'product_spec_status' => [
        'draft'    => 'Entwurf',
        'active'   => 'Aktiv',
        'inactive' => 'Inaktiv',
        'retired'  => 'Außer Betrieb',
    ],

    'gas_component' => [
        'H2'  => 'Wasserstoff',
        'O2'  => 'Sauerstoff',
        'N2'  => 'Stickstoff',
        'CH4' => 'Methan',
        'CO'  => 'Kohlenmonoxid',
        'CO2' => 'Kohlendioxid',
    ],

    'export_category' => [
        'drivers'                     => 'Fahrer',
        'trailers'                    => 'Anhänger',
        'tractors_vehicles'           => 'Zugmaschinen / Fahrzeuge',
        'customers'                   => 'Kunden',
        'freight_forwarders_carriers' => 'Spediteure / Frachtführer',
        'chip_cards'                  => 'Chipkarten',
        'tans'                        => 'TANs',
    ],

    'export_job_status' => [
        'queued'     => 'In Warteschlange',
        'generating' => 'Wird generiert',
        'ready'      => 'Bereit',
        'failed'     => 'Fehlgeschlagen',
        'expired'    => 'Abgelaufen',
    ],

    'document_lifecycle_status' => [
        'pending'          => 'Ausstehend',
        'generated'        => 'Generiert',
        'queued_for_print' => 'Druckwarteschlange',
        'printed'          => 'Gedruckt',
        'print_failed'     => 'Druckfehler',
        'reprinted'        => 'Nachgedruckt',
        'handed_over'      => 'Übergeben',
        'blocked'          => 'Gesperrt',
        'invalidated'      => 'Ungültig erklärt',
        'cancelled'        => 'Storniert',
    ],

    'document_print_status' => [
        'not_requested' => 'Nicht angefordert',
        'queued'        => 'In Warteschlange',
        'printing'      => 'Druckt',
        'printed'       => 'Gedruckt',
        'failed'        => 'Fehlgeschlagen',
        'reprinted'     => 'Nachgedruckt',
    ],

    'document_type' => [
        'certificate'   => 'Zertifikat',
        'delivery_note' => 'Lieferschein',
        'qm_document'   => 'QM-Dokument',
    ],

    'sap_result_status' => [
        'success'  => 'Erfolgreich',
        'pending'  => 'Ausstehend',
        'failed'   => 'Fehlgeschlagen',
        'resolved' => 'Gelöst',
    ],

    'sap_feedback_type' => [
        'order_status' => 'Auftragsstatus',
        'quantity'     => 'Menge',
        'quality'      => 'Qualität',
        'document'     => 'Dokument',
        'completion'   => 'Abschluss',
        'cancellation' => 'Stornierung',
    ],

    'sap_handling_status' => [
        'no_action_needed'     => 'Keine Maßnahme erforderlich',
        'auto_retry_scheduled' => 'Automatischer Neuversuch geplant',
        'needs_support'        => 'Support erforderlich',
        'resolved'             => 'Gelöst',
    ],

    'sap_sync_direction' => [
        'import' => 'Import',
        'export' => 'Export',
    ],

    'carrier_approval_state' => [
        'approved'  => 'Genehmigt',
        'pending'   => 'Ausstehend',
        'suspended' => 'Ausgesetzt',
        'rejected'  => 'Abgelehnt',
    ],

    'carrier_status' => [
        'active'   => 'Aktiv',
        'inactive' => 'Inaktiv',
        'blocked'  => 'Gesperrt',
    ],

    'messages' => [
        // CarrierController
        'carriers_retrieved'         => 'Spediteure abgerufen',
        'carrier_not_found'          => 'Spediteur nicht gefunden',
        'carrier_retrieved'          => 'Spediteur abgerufen',
        'carrier_created'            => 'Spediteur erstellt',
        'carrier_updated'            => 'Spediteur aktualisiert',
        'carrier_blocked'            => 'Spediteur gesperrt',
        'carrier_unblocked'          => 'Spediteur entsperrt',
        'carrier_already_blocked'    => 'Spediteur ist bereits gesperrt',
        'carrier_not_blocked'        => 'Spediteur ist nicht gesperrt',
        'carrier_export'             => 'Spediteurexport vorbereitet',
        'carrier_trailers'           => 'Spediteur-Anhänger abgerufen',
        'carrier_vehicles'           => 'Spediteur-Fahrzeuge abgerufen',
        'carrier_drivers'            => 'Spediteur-Fahrer abgerufen',
        'carrier_events_audit'       => 'Ereignisse & Prüfprotokoll des Spediteurs abgerufen',
        'carrier_orders_placeholder' => 'Spediteur-Aufträge abgerufen (Platzhalter)',
        'carrier_visits_placeholder' => 'Spediteur-Werksbesuche abgerufen (Platzhalter)',

        // CompanyController
        'companies_retrieved' => 'Unternehmen abgerufen',
        'company_not_found'   => 'Unternehmen nicht gefunden',
        'company_retrieved'   => 'Unternehmen abgerufen',
        'company_created'     => 'Unternehmen erstellt',
        'company_updated'     => 'Unternehmen aktualisiert',
        'company_activated'   => 'Unternehmen aktiviert',
        'company_deactivated' => 'Unternehmen deaktiviert',
        'company_deleted'     => 'Unternehmen gelöscht',

        // CustomerController
        'customers_retrieved'      => 'Kunden abgerufen',
        'customer_not_found'       => 'Kunde nicht gefunden',
        'customer_retrieved'       => 'Kunde abgerufen',
        'customer_created'         => 'Kunde erstellt',
        'customer_updated'         => 'Kunde aktualisiert',
        'customer_blocked'         => 'Kunde gesperrt',
        'customer_unblocked'       => 'Kunde entsperrt',
        'customer_activated'       => 'Kunde aktiviert',
        'customer_deactivated'     => 'Kunde deaktiviert',
        'customer_already_blocked' => 'Kunde ist bereits gesperrt',
        'customer_not_blocked'     => 'Kunde ist nicht gesperrt',

        // UserController
        'users_retrieved'      => 'Benutzer abgerufen',
        'user_not_found'       => 'Benutzer nicht gefunden',
        'user_retrieved'       => 'Benutzer abgerufen',
        'user_created'         => 'Benutzer erstellt',
        'user_updated'         => 'Benutzer aktualisiert',
        'user_enabled'         => 'Benutzer aktiviert',
        'user_disabled'        => 'Benutzer deaktiviert',
        'user_locked'          => 'Benutzer gesperrt',
        'user_unlocked'        => 'Benutzer entsperrt',
        'user_roles_updated'   => 'Benutzerrollen aktualisiert',
        'user_access_reset'    => 'Zugriffsreset durchgeführt. Bestehende Sitzungen und API-Token wurden widerrufen.',
        'user_already_enabled' => 'Benutzer ist bereits aktiviert',
        'user_already_disabled' => 'Benutzer ist bereits deaktiviert',
        'user_already_locked'  => 'Benutzer ist bereits gesperrt',
        'user_not_locked'      => 'Benutzer ist nicht gesperrt',

        // ProductSpecificationController
        'specs_retrieved'         => 'Produktspezifikationen abgerufen',
        'spec_not_found'          => 'Spezifikation nicht gefunden',
        'spec_retrieved'          => 'Produktspezifikation abgerufen',
        'spec_created'            => 'Produktspezifikation erstellt',
        'spec_updated'            => 'Produktspezifikation aktualisiert',
        'spec_activated'          => 'Produktspezifikation aktiviert',
        'spec_retired'            => 'Produktspezifikation außer Betrieb gesetzt',
        'spec_not_editable'       => 'Spezifikation ist im aktuellen Status nicht bearbeitbar.',
        'spec_incomplete'         => 'Aktivierung nicht möglich: nicht alle 6 Komponenten sind konfiguriert.',
        'spec_cannot_activate'    => 'Aktivierung vom aktuellen Status nicht möglich.',
        'spec_cannot_retire'      => 'Außerbetriebnahme vom aktuellen Status nicht möglich.',
        'gas_limit_added'         => 'Gaslimit-Zeile hinzugefügt',
        'gas_limit_updated'       => 'Gaslimit-Zeile aktualisiert',
        'gas_limit_not_found'     => 'Gaslimit-Zeile nicht in dieser Spezifikation gefunden.',
        'gas_limit_exists'        => 'Für dieses Gas existiert bereits eine Gaslimit-Zeile in dieser Spezifikation.',

        // CalibrationProfileController
        'profiles_retrieved'      => 'Kalibrierprofile abgerufen',
        'profile_not_found'       => 'Profil nicht gefunden',
        'profile_retrieved'       => 'Kalibrierprofil abgerufen',
        'profile_created'         => 'Kalibrierprofil erstellt',
        'profile_updated'         => 'Kalibrierprofil aktualisiert',
        'profile_activated'       => 'Kalibrierprofil aktiviert',
        'profile_retired'         => 'Kalibrierprofil außer Betrieb gesetzt',
        'profile_not_editable'    => 'Profil ist im aktuellen Status nicht bearbeitbar.',
        'profile_incomplete'      => 'Aktivierung nicht möglich: nicht alle 6 Komponenten konfiguriert.',
        'profile_cannot_activate' => 'Aktivierung vom aktuellen Status nicht möglich.',
        'profile_cannot_retire'   => 'Außerbetriebnahme vom aktuellen Status nicht möglich.',
        'cal_component_added'     => 'Kalibrierkomponente hinzugefügt',
        'cal_component_updated'   => 'Kalibrierkomponente aktualisiert',
        'cal_component_not_found' => 'Komponentenzeile in diesem Profil nicht gefunden.',
        'cal_component_exists'    => 'Für dieses Gas existiert bereits eine Komponentenzeile in diesem Profil.',

        // SapSyncController
        'sap_records_retrieved' => 'SAP-Synchronisierungsdatensätze abgerufen',
        'sap_record_not_found'  => 'SAP-Synchronisierungsdatensatz nicht gefunden',
        'sap_record_retrieved'  => 'SAP-Synchronisierungsdatensatz abgerufen',

        // MasterDataExportController
        'export_jobs_retrieved' => 'Exportaufträge abgerufen',
        'export_job_not_found'  => 'Exportauftrag nicht gefunden',
        'export_job_retrieved'  => 'Exportauftrag abgerufen',
        'export_job_created'    => 'Exportauftrag erstellt',
        'export_ready'          => 'Export bereit',
        'export_retried'        => 'Export erneut versucht',
        'export_not_ready'      => 'Export noch nicht bereit',
        'export_not_retryable'  => 'Nur fehlgeschlagene Aufträge können wiederholt werden',
        'export_expired'        => 'Export ist abgelaufen',
        'export_file_missing'   => 'Exportdatei fehlt',

        // OperationalDocumentController
        'documents_retrieved'    => 'Betriebsdokumente abgerufen',
        'document_not_found'     => 'Dokument nicht gefunden',
        'document_retrieved'     => 'Dokument abgerufen',
        'document_queued'        => 'Dokument in Druckwarteschlange',
        'document_reprinted'     => 'Dokument-Nachdruck eingereiht',
        'document_handed_over'   => 'Dokument als übergeben markiert',
        'document_invalidated'   => 'Dokument ungültig erklärt',
        'document_cancelled'     => 'Dokument storniert',
        'document_preview'       => 'Dokumentvorschau abgerufen',
        'document_print_history' => 'Druckhistorie abgerufen',
        'document_no_preview'    => 'Noch keine Vorschaudatei für dieses Dokument verfügbar.',

        // ReportsController
        'reports_retrieved' => 'Berichtszentrum abgerufen',
        'report_not_found'  => 'Bericht nicht gefunden',
        'report_retrieved'  => 'Bericht abgerufen',
        'report_drill_down' => 'Detailzeilen abgerufen',
        'report_export'     => 'Berichtsexport vorbereitet',
    ],

];
