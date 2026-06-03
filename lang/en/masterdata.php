<?php

return [

    'product_spec_status' => [
        'draft'    => 'Draft',
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'retired'  => 'Retired',
    ],

    'gas_component' => [
        'H2'  => 'Hydrogen',
        'O2'  => 'Oxygen',
        'N2'  => 'Nitrogen',
        'CH4' => 'Methane',
        'CO'  => 'Carbon monoxide',
        'CO2' => 'Carbon dioxide',
    ],

    'export_category' => [
        'drivers'                      => 'Drivers',
        'trailers'                     => 'Trailers',
        'tractors_vehicles'            => 'Tractors / Vehicles',
        'customers'                    => 'Customers',
        'freight_forwarders_carriers'  => 'Freight Forwarders / Carriers',
        'chip_cards'                   => 'Chip Cards',
        'tans'                         => 'TANs',
    ],

    'export_job_status' => [
        'queued'     => 'Queued',
        'generating' => 'Generating',
        'ready'      => 'Ready',
        'failed'     => 'Failed',
        'expired'    => 'Expired',
    ],

    'document_lifecycle_status' => [
        'pending'         => 'Pending',
        'generated'       => 'Generated',
        'queued_for_print' => 'Queued for Print',
        'printed'         => 'Printed',
        'print_failed'    => 'Print Failed',
        'reprinted'       => 'Reprinted',
        'handed_over'     => 'Handed Over',
        'blocked'         => 'Blocked',
        'invalidated'     => 'Invalidated',
        'cancelled'       => 'Cancelled',
    ],

    'document_print_status' => [
        'not_requested' => 'Not Requested',
        'queued'        => 'Queued',
        'printing'      => 'Printing',
        'printed'       => 'Printed',
        'failed'        => 'Failed',
        'reprinted'     => 'Reprinted',
    ],

    'document_type' => [
        'certificate'   => 'Certificate',
        'delivery_note' => 'Delivery Note',
        'qm_document'   => 'QM Document',
    ],

    'sap_result_status' => [
        'success'  => 'Success',
        'pending'  => 'Pending',
        'failed'   => 'Failed',
        'resolved' => 'Resolved',
    ],

    'sap_feedback_type' => [
        'order_status'  => 'Order Status',
        'quantity'      => 'Quantity',
        'quality'       => 'Quality',
        'document'      => 'Document',
        'completion'    => 'Completion',
        'cancellation'  => 'Cancellation',
    ],

    'sap_handling_status' => [
        'no_action_needed'      => 'No Action Needed',
        'auto_retry_scheduled'  => 'Auto Retry Scheduled',
        'needs_support'         => 'Needs Support',
        'resolved'              => 'Resolved',
    ],

    'sap_sync_direction' => [
        'import' => 'Import',
        'export' => 'Export',
    ],

    'carrier_approval_state' => [
        'approved'    => 'Approved',
        'pending'     => 'Pending',
        'suspended'   => 'Suspended',
        'rejected'    => 'Rejected',
    ],

    'carrier_status' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'blocked'  => 'Blocked',
    ],

    'messages' => [
        // CarrierController
        'carriers_retrieved'         => 'Carriers retrieved',
        'carrier_not_found'          => 'Carrier not found',
        'carrier_retrieved'          => 'Carrier retrieved',
        'carrier_created'            => 'Carrier created',
        'carrier_updated'            => 'Carrier updated',
        'carrier_blocked'            => 'Carrier blocked',
        'carrier_unblocked'          => 'Carrier unblocked',
        'carrier_already_blocked'    => 'Carrier is already blocked',
        'carrier_not_blocked'        => 'Carrier is not blocked',
        'carrier_export'             => 'Carrier export prepared',
        'carrier_trailers'           => 'Carrier trailers retrieved',
        'carrier_vehicles'           => 'Carrier vehicles retrieved',
        'carrier_drivers'            => 'Carrier drivers retrieved (best-effort match against companies FK; cross-link pending)',
        'carrier_events_audit'       => 'Carrier events & audit retrieved',
        'carrier_orders_placeholder' => 'Carrier orders retrieved (placeholder)',
        'carrier_visits_placeholder' => 'Carrier plant visits retrieved (placeholder)',

        // CompanyController
        'companies_retrieved'  => 'Companies retrieved',
        'company_not_found'    => 'Company not found',
        'company_retrieved'    => 'Company retrieved',
        'company_created'      => 'Company created',
        'company_updated'      => 'Company updated',
        'company_activated'    => 'Company activated',
        'company_deactivated'  => 'Company deactivated',
        'company_deleted'      => 'Company deleted',

        // CustomerController
        'customers_retrieved'       => 'Customers retrieved',
        'customer_not_found'        => 'Customer not found',
        'customer_retrieved'        => 'Customer retrieved',
        'customer_created'          => 'Customer created',
        'customer_updated'          => 'Customer updated',
        'customer_blocked'          => 'Customer blocked',
        'customer_unblocked'        => 'Customer unblocked',
        'customer_activated'        => 'Customer activated',
        'customer_deactivated'      => 'Customer deactivated',
        'customer_already_blocked'  => 'Customer is already blocked',
        'customer_not_blocked'      => 'Customer is not blocked',

        // UserController
        'users_retrieved'           => 'Users retrieved',
        'user_not_found'            => 'User not found',
        'user_retrieved'            => 'User retrieved',
        'user_created'              => 'User created',
        'user_updated'              => 'User updated',
        'user_enabled'              => 'User enabled',
        'user_disabled'             => 'User disabled',
        'user_locked'               => 'User locked',
        'user_unlocked'             => 'User unlocked',
        'user_roles_updated'        => 'User roles updated',
        'user_access_reset'         => 'Access reset triggered. Existing sessions and API tokens were revoked.',
        'user_already_enabled'      => 'User is already enabled',
        'user_already_disabled'     => 'User is already disabled',
        'user_already_locked'       => 'User is already locked',
        'user_not_locked'           => 'User is not locked',

        // ProductSpecificationController
        'specs_retrieved'           => 'Product specifications retrieved',
        'spec_not_found'            => 'Specification not found',
        'spec_retrieved'            => 'Product specification retrieved',
        'spec_created'              => 'Product specification created',
        'spec_updated'              => 'Product specification updated',
        'spec_activated'            => 'Product specification activated',
        'spec_retired'              => 'Product specification retired',
        'spec_not_editable'         => 'Specification is not editable in its current status.',
        'spec_incomplete'           => 'Cannot activate: not all 6 components are configured.',
        'spec_cannot_activate'      => 'Cannot activate from current status.',
        'spec_cannot_retire'        => 'Cannot retire from current status.',
        'gas_limit_added'           => 'Gas limit row added',
        'gas_limit_updated'         => 'Gas limit row updated',
        'gas_limit_not_found'       => 'Gas limit row not found on this specification.',
        'gas_limit_exists'          => 'A gas-limit row already exists for this component on this specification.',

        // CalibrationProfileController
        'profiles_retrieved'        => 'Calibration profiles retrieved',
        'profile_not_found'         => 'Profile not found',
        'profile_retrieved'         => 'Calibration profile retrieved',
        'profile_created'           => 'Calibration profile created',
        'profile_updated'           => 'Calibration profile updated',
        'profile_activated'         => 'Calibration profile activated',
        'profile_retired'           => 'Calibration profile retired',
        'profile_not_editable'      => 'Profile is not editable in its current status.',
        'profile_incomplete'        => 'Cannot activate: not all 6 components are configured.',
        'profile_cannot_activate'   => 'Cannot activate from current status.',
        'profile_cannot_retire'     => 'Cannot retire from current status.',
        'cal_component_added'       => 'Calibration component added',
        'cal_component_updated'     => 'Calibration component updated',
        'cal_component_not_found'   => 'Component row not found on this profile.',
        'cal_component_exists'      => 'A component row already exists for this gas on this profile.',

        // SapSyncController
        'sap_records_retrieved'     => 'SAP sync records retrieved',
        'sap_record_not_found'      => 'SAP sync record not found',
        'sap_record_retrieved'      => 'SAP sync record retrieved',

        // MasterDataExportController
        'export_jobs_retrieved'     => 'Export jobs retrieved',
        'export_job_not_found'      => 'Export job not found',
        'export_job_retrieved'      => 'Export job retrieved',
        'export_job_created'        => 'Export job created',
        'export_ready'              => 'Export ready',
        'export_retried'            => 'Export retried',
        'export_not_ready'          => 'Export not ready',
        'export_not_retryable'      => 'Only failed jobs can be retried',
        'export_expired'            => 'Export has expired',
        'export_file_missing'       => 'Export file is missing',

        // OperationalDocumentController
        'documents_retrieved'       => 'Operational documents retrieved',
        'document_not_found'        => 'Document not found',
        'document_retrieved'        => 'Document retrieved',
        'document_queued'           => 'Document queued for print',
        'document_reprinted'        => 'Document reprint queued',
        'document_handed_over'      => 'Document marked handed over',
        'document_invalidated'      => 'Document invalidated',
        'document_cancelled'        => 'Document cancelled',
        'document_preview'          => 'Document preview retrieved',
        'document_print_history'    => 'Print history retrieved',
        'document_no_preview'       => 'No preview file available for this document yet.',

        // ReportsController
        'reports_retrieved'         => 'Reports hub retrieved',
        'report_not_found'          => 'Report not found',
        'report_retrieved'          => 'Report retrieved',
        'report_drill_down'         => 'Drill-down rows retrieved',
        'report_export'             => 'Report export prepared',
    ],

];
