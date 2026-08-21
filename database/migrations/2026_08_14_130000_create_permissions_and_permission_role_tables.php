<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('module');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
                $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
                $table->primary(['permission_id', 'role_id']);
                $table->timestamps();
            });
        }

        $allPermissions = [
            // Administration
            ['code' => 'manage_system_settings', 'name' => 'Manage System Settings', 'module' => 'Administration', 'description' => 'Configure app branding, Meta API credentials, and general system settings.'],
            ['code' => 'manage_users', 'name' => 'Manage Users', 'module' => 'Administration', 'description' => 'Create, edit, deactivate, and delete user accounts.'],
            ['code' => 'manage_roles', 'name' => 'Manage Roles & Permissions', 'module' => 'Administration', 'description' => 'Create and customize roles and access level checkboxes.'],
            ['code' => 'manage_departments', 'name' => 'Manage Departments', 'module' => 'Administration', 'description' => 'Create and manage organizational departments.'],
            ['code' => 'manage_banks', 'name' => 'Manage Banks & Institutions', 'module' => 'Administration', 'description' => 'Create and manage banking institution profiles.'],

            // Clients
            ['code' => 'view_clients', 'name' => 'View Clients', 'module' => 'Clients', 'description' => 'Access and view client directory listings.'],
            ['code' => 'create_clients', 'name' => 'Create Clients', 'module' => 'Clients', 'description' => 'Add new client records to the CRM.'],
            ['code' => 'edit_clients', 'name' => 'Edit Clients', 'module' => 'Clients', 'description' => 'Modify existing client information.'],
            ['code' => 'delete_clients', 'name' => 'Delete Clients', 'module' => 'Clients', 'description' => 'Delete client records from the system.'],
            ['code' => 'import_clients', 'name' => 'Import Clients (CSV)', 'module' => 'Clients', 'description' => 'Upload bulk client records via CSV files.'],
            ['code' => 'bypass_bank_scoping', 'name' => 'Access All Banks Data', 'module' => 'Clients', 'description' => 'Bypass bank assignment restrictions and view data across all banks.'],
            ['code' => 'portfolio_scoped_only', 'name' => 'Restrict to Assigned Portfolio', 'module' => 'Clients', 'description' => 'Limit data view strictly to clients explicitly assigned to the user.'],
            ['code' => 'view_all_imported_clients', 'name' => 'View Clients Imported by Other Users', 'module' => 'Clients', 'description' => 'View and select client records imported by any user across all departments and batches.'],

            // Campaigns & Communications
            ['code' => 'view_campaigns', 'name' => 'View Campaigns', 'module' => 'Campaigns & Messaging', 'description' => 'View marketing campaigns and campaign delivery metrics.'],
            ['code' => 'create_campaigns', 'name' => 'Create & Launch Campaigns', 'module' => 'Campaigns & Messaging', 'description' => 'Build, schedule, and launch marketing messaging campaigns.'],
            ['code' => 'edit_campaigns', 'name' => 'Edit Campaigns', 'module' => 'Campaigns & Messaging', 'description' => 'Update campaign details and recipient selections.'],
            ['code' => 'delete_campaigns', 'name' => 'Delete Campaigns', 'module' => 'Campaigns & Messaging', 'description' => 'Remove campaign records.'],
            ['code' => 'send_whatsapp', 'name' => 'Send Direct WhatsApp Messages', 'module' => 'Campaigns & Messaging', 'description' => 'Send single or template WhatsApp messages directly to clients.'],
            ['code' => 'view_live_chat', 'name' => 'Access Live Chat', 'module' => 'Campaigns & Messaging', 'description' => 'View and respond to incoming WhatsApp conversations.'],

            // Account & Settings
            ['code' => 'settings_user_account', 'name' => 'Access User Account Settings', 'module' => 'Account & Settings', 'description' => 'Access personal profile, security, and preference configurations.'],
            ['code' => 'settings_system', 'name' => 'Access System Settings', 'module' => 'Account & Settings', 'description' => 'Access system-wide application, brand, and malware scanner configurations.'],
            ['code' => 'settings_meta_whatsapp', 'name' => 'Access Meta WhatsApp Settings', 'module' => 'Account & Settings', 'description' => 'Access Meta Cloud API credentials, webhook tokens, and number health configurations.'],
            ['code' => 'settings_waba_profile', 'name' => 'Access WABA Profile Settings', 'module' => 'Account & Settings', 'description' => 'Access and manage WhatsApp Business Account profiles.'],
            ['code' => 'settings_waba_numbers', 'name' => 'Access WABA Numbers Settings', 'module' => 'Account & Settings', 'description' => 'Access and manage WABA registered phone numbers.'],
            ['code' => 'settings_waba_templates', 'name' => 'Access WABA Templates Settings', 'module' => 'Account & Settings', 'description' => 'Access and manage WhatsApp message templates.'],

            // Security & Compliance
            ['code' => 'view_audit_logs', 'name' => 'View Audit Logs', 'module' => 'Security & Audit', 'description' => 'Access system audit trail logs and action histories.'],
            ['code' => 'view_audit_logs_role_only', 'name' => 'View Audit Logs (Own Activity Only)', 'module' => 'Security & Audit', 'description' => 'Limit audit log visibility strictly to activities of the currently logged-in user.'],
            ['code' => 'view_audit_logs_all_users', 'name' => 'View All Users Audit Logs', 'module' => 'Security & Audit', 'description' => 'View audit and activity logs for all users across the system.'],
            ['code' => 'view_security_incidents', 'name' => 'View Security Incidents', 'module' => 'Security & Audit', 'description' => 'View logged security events and alerts.'],
            ['code' => 'manage_security_incidents', 'name' => 'Manage Security Incidents', 'module' => 'Security & Audit', 'description' => 'Resolve, comment on, and manage security incidents.'],
            ['code' => 'view_compliance_console', 'name' => 'View Compliance Console', 'module' => 'Security & Audit', 'description' => 'Access compliance governance metrics.'],
            ['code' => 'manage_compliance_console', 'name' => 'Manage Compliance Policies', 'module' => 'Security & Audit', 'description' => 'Update compliance parameters and policies.'],
            ['code' => 'request_exports', 'name' => 'Request Data Exports', 'module' => 'Security & Audit', 'description' => 'Request sensitive data CSV/Excel exports.'],
            ['code' => 'approve_exports', 'name' => 'Approve Data Exports', 'module' => 'Security & Audit', 'description' => 'Review and approve export requests submitted by users.'],
            ['code' => 'bypass_export_approval', 'name' => 'Direct Instant Data Export', 'module' => 'Security & Audit', 'description' => 'Export data immediately without requiring compliance approval.'],

            // Automation & Workflows
            ['code' => 'manage_auto_replies', 'name' => 'Manage Auto Replies', 'module' => 'Automation & Workflows', 'description' => 'Configure automated WhatsApp keyword response rules.'],
            ['code' => 'manage_whatsapp_flows', 'name' => 'Manage WhatsApp Flows', 'module' => 'Automation & Workflows', 'description' => 'Create and configure interactive WhatsApp Flow forms.'],
        ];

        foreach ($allPermissions as $p) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $p['code']],
                array_merge($p, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // Map default permissions to existing system roles
        $permissionIdsByCode = DB::table('permissions')->pluck('id', 'code');
        $roleIdsByCode = DB::table('roles')->pluck('id', 'code');

        $defaultRolePermissions = [
            'SUPER_ADMIN' => array_keys($permissionIdsByCode->all()),
            'ADMIN' => array_keys($permissionIdsByCode->all()),
            'MANAGER' => [
                'view_clients', 'create_clients', 'edit_clients', 'import_clients',
                'view_campaigns', 'create_campaigns', 'edit_campaigns', 'send_whatsapp', 'view_live_chat',
                'view_audit_logs', 'view_security_incidents', 'view_compliance_console', 'request_exports', 'approve_exports',
                'manage_auto_replies', 'manage_whatsapp_flows', 'settings_user_account',
            ],
            'CALL_CENTRE_MANAGER' => [
                'view_clients', 'create_clients', 'edit_clients', 'import_clients',
                'view_campaigns', 'create_campaigns', 'edit_campaigns', 'send_whatsapp', 'view_live_chat',
                'view_audit_logs', 'view_security_incidents', 'view_compliance_console', 'request_exports',
                'manage_auto_replies', 'manage_whatsapp_flows', 'settings_user_account',
            ],
            'TEAM_LEADER' => [
                'view_clients', 'create_clients', 'edit_clients',
                'view_campaigns', 'create_campaigns', 'send_whatsapp', 'view_live_chat',
                'view_security_incidents', 'view_compliance_console', 'request_exports', 'settings_user_account',
            ],
            'AGENT' => [
                'view_clients', 'edit_clients', 'portfolio_scoped_only',
                'view_campaigns', 'send_whatsapp', 'view_live_chat', 'request_exports', 'settings_user_account',
            ],
            'STAFF' => [
                'view_clients', 'edit_clients', 'portfolio_scoped_only',
                'view_campaigns', 'send_whatsapp', 'view_live_chat', 'request_exports', 'settings_user_account',
            ],
            'AUDITOR' => [
                'view_audit_logs', 'view_security_incidents', 'view_compliance_console', 'request_exports', 'settings_user_account',
            ],
            'COMPLIANCE_OFFICER' => [
                'view_audit_logs', 'view_security_incidents', 'manage_security_incidents',
                'view_compliance_console', 'manage_compliance_console', 'request_exports', 'approve_exports', 'settings_user_account',
            ],
            'READ_ONLY_REVIEWER' => [
                'view_audit_logs', 'view_security_incidents', 'view_compliance_console', 'settings_user_account',
            ],
        ];

        foreach ($defaultRolePermissions as $roleCode => $permCodes) {
            $roleId = $roleIdsByCode[$roleCode] ?? null;
            if (!$roleId) continue;

            foreach ($permCodes as $pCode) {
                $permId = $permissionIdsByCode[$pCode] ?? null;
                if ($permId) {
                    DB::table('permission_role')->updateOrInsert([
                        'permission_id' => $permId,
                        'role_id' => $roleId,
                    ], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
};
