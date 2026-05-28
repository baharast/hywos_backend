<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the 7 default roles + permission taxonomy from FillTrack Roles &
 * Permissions UX Spec-EN-V1 §2.1 + §10.2.
 *
 * Permission scheme is intentionally compact for demo: one base permission
 * per module group plus a `.manage` variant for the critical actions
 * called out in §10.2. Backend enforcement is NOT yet active across all
 * controllers (auth middleware is still disabled per project convention);
 * the permissions exist so the Roles & Permissions UI has real rows to
 * render and so role assignment carries semantic weight in audit logs.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<string,string[]> module → [permission codes] */
    public const PERMISSIONS_BY_MODULE = [
        'dashboard' => [
            'dashboard.view',
        ],
        'operations' => [
            'operations.view',
            'operations.manage',                 // resolve clarification, force advance
            'plant_visits.view',
            'plant_visits.manage',               // advance step, block, force-close
            'loading_control.view',
            'loading_control.manage',            // start/stop/pause/ESD (future)
            'gate_terminal.view',
            'gate_terminal.manage',              // force-open / service mode (future)
            'clarification_cases.view',
            'clarification_cases.manage',        // acknowledge / assign / resolve / close
        ],
        'orders' => [
            'loading_orders.view',
            'loading_orders.create',
            'loading_orders.update',
            'loading_orders.manage',             // cancel / block / assign driver / assign trailer
            'sap_sync.view',
        ],
        'analysis' => [
            'analysis.view',
            'analysis.manage',                   // approve / release / block / reject — critical
        ],
        'documents' => [
            'operational_documents.view',
            'operational_documents.print',
            'operational_documents.manage',      // reprint / hand-over / invalidate — critical
            'reports.view',
            'reports.export',
        ],
        'alarms' => [
            'alarms.view',
            'alarms.acknowledge',                // critical when alarm gates exit
            'event_journal.view',
            'audit_trail.view',
        ],
        'master_data' => [
            'drivers.view',
            'drivers.update',
            'drivers.manage',                    // block / unblock — critical
            'trailers.view',
            'trailers.update',
            'trailers.manage',
            'vehicles.view',
            'vehicles.update',
            'vehicles.manage',
            'customers.view',
            'customers.update',
            'customers.manage',
            'carriers.view',
            'carriers.update',
            'carriers.manage',
            'chip_cards.view',
            'chip_cards.manage',                 // assign / block / lost / defective — critical
            'tans.view',
            'tans.manage',                       // generate / revoke — critical
        ],
        'system' => [
            'devices.view',
            'devices.manage',
            'interface_health.view',
        ],
        // System & Devices (V1.4) — Hardware Devices + Interface Health
        // routes use these permissions. Distinct from the legacy
        // `devices.*` / `interface_health.view` triad above (those drive
        // existing role assignments and a future Hardware Devices CRUD).
        'system_devices' => [
            'system_devices.view',
            'system_devices.manage',
        ],
        'administration' => [
            'users.view',
            'users.manage',                      // create / disable / lock / reset-access — critical
            'roles.view',
            'roles.manage',                      // create role / toggle permission — critical
            'plant_configuration.view',
            'plant_configuration.manage',        // critical
            'companies.view',
            'companies.manage',
        ],
    ];

    /**
     * Role → permission mapping. `'*'` means full access (all permissions).
     * Per V1 §2.1, roles are: Admin, Operations Manager, Dispatcher /
     * Manager, Operator, Analysis Specialist, IT / Support, Auditor.
     */
    public const ROLE_PERMISSIONS = [
        'admin' => ['*'],

        'operations_manager' => [
            'dashboard.view',
            'operations.view', 'operations.manage',
            'plant_visits.view', 'plant_visits.manage',
            'loading_control.view',
            'gate_terminal.view',
            'clarification_cases.view', 'clarification_cases.manage',
            'loading_orders.view', 'loading_orders.update',
            'sap_sync.view',
            'analysis.view',
            'operational_documents.view', 'operational_documents.print',
            'reports.view', 'reports.export',
            'alarms.view', 'alarms.acknowledge',
            'event_journal.view', 'audit_trail.view',
            'drivers.view', 'trailers.view', 'vehicles.view',
            'customers.view', 'carriers.view',
            'chip_cards.view', 'tans.view',
            'system_devices.view', 'system_devices.manage',
        ],

        'dispatcher_manager' => [
            'dashboard.view',
            'operations.view',
            'plant_visits.view', 'plant_visits.manage',
            'loading_control.view',
            'gate_terminal.view',
            'clarification_cases.view', 'clarification_cases.manage',
            'loading_orders.view', 'loading_orders.create',
            'loading_orders.update', 'loading_orders.manage',
            'sap_sync.view',
            'operational_documents.view',
            'reports.view',
            'alarms.view',
            'event_journal.view',
            'drivers.view', 'drivers.update',
            'trailers.view', 'trailers.update',
            'vehicles.view', 'vehicles.update',
            'customers.view', 'carriers.view',
            'chip_cards.view', 'tans.view',
            'system_devices.view',
        ],

        'operator' => [
            'dashboard.view',
            'operations.view',
            'plant_visits.view',
            'loading_control.view', 'loading_control.manage',
            'gate_terminal.view',
            'clarification_cases.view',
            'loading_orders.view',
            'operational_documents.view', 'operational_documents.print',
            'reports.view',
            'alarms.view', 'alarms.acknowledge',
            'event_journal.view',
            'drivers.view', 'trailers.view', 'vehicles.view',
            'system_devices.view',
        ],

        'analysis_specialist' => [
            'dashboard.view',
            'operations.view',
            'plant_visits.view',
            'analysis.view', 'analysis.manage',
            'operational_documents.view',
            'reports.view',
            'event_journal.view', 'audit_trail.view',
            'alarms.view',
            'drivers.view', 'trailers.view',
            'system_devices.view',
        ],

        'it_support' => [
            'dashboard.view',
            'operations.view',
            'gate_terminal.view',
            'sap_sync.view',
            'alarms.view', 'event_journal.view', 'audit_trail.view',
            'devices.view', 'devices.manage', 'interface_health.view',
            'users.view', 'users.manage',
            'roles.view',
            'plant_configuration.view',
            'companies.view',
            // Master data read-only for support diagnostics
            'drivers.view', 'trailers.view', 'vehicles.view',
            'customers.view', 'carriers.view',
            'chip_cards.view', 'tans.view',
            'system_devices.view', 'system_devices.manage',
        ],

        'auditor' => [
            // Read-only across everything per V1 §10.1 "Auditor uses Audit Trail / Event Journal"
            'dashboard.view',
            'operations.view',
            'plant_visits.view',
            'loading_control.view',
            'gate_terminal.view',
            'clarification_cases.view',
            'loading_orders.view',
            'sap_sync.view',
            'analysis.view',
            'operational_documents.view',
            'reports.view', 'reports.export',
            'alarms.view',
            'event_journal.view', 'audit_trail.view',
            'drivers.view', 'trailers.view', 'vehicles.view',
            'customers.view', 'carriers.view',
            'chip_cards.view', 'tans.view',
            'devices.view', 'interface_health.view',
            'users.view', 'roles.view', 'plant_configuration.view', 'companies.view',
            'system_devices.view',
        ],
    ];

    public function run(): void
    {
        $allPermissions = [];
        foreach (self::PERMISSIONS_BY_MODULE as $codes) {
            $allPermissions = array_merge($allPermissions, $codes);
        }
        $allPermissions = array_values(array_unique($allPermissions));

        foreach ($allPermissions as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $resolved = $permissions === ['*']
                ? $allPermissions
                : $permissions;

            // syncPermissions makes the seed idempotent — re-running snaps
            // the role's set back to spec instead of accumulating drift.
            $role->syncPermissions($resolved);
        }
    }
}
