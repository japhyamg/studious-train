<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\RiskLevel;
use App\Models\Setting;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Seed Phase 5 configuration for existing installs (idempotent):
     *  - risk-level TAT defaults (5.1)
     *  - runtime settings for CTR / STR filing SLA / audit retention (5.4, 5.5)
     *  - maker-checker + MI report permissions (5.2, 5.6)
     */
    public function up(): void
    {
        // 5.1 — TAT/SLA hours per risk level (only fill gaps)
        $defaults = config('governance.sla.risk_level_defaults', []);
        foreach ($defaults as $label => $hours) {
            RiskLevel::where('label', $label)
                ->whereNull('case_tat_hours')
                ->update(['case_tat_hours' => $hours]);
        }

        // 5.4 / 5.5 — runtime settings (defaults, only create when missing)
        $settings = [
            'ctr_threshold_individual' => (string) config('governance.ctr.threshold_individual', 5000000),
            'ctr_threshold_corporate'  => (string) config('governance.ctr.threshold_corporate', 10000000),
            'ctr_cash_channels'        => implode(',', config('governance.ctr.cash_channels', ['atm', 'bank', 'cash'])),
            'str_filing_sla_days'      => (string) config('governance.filing.str_sla_days', 5),
            'audit_retention_days'     => (string) config('governance.audit.retention_days', 1825),
            'case_sla_enabled'         => config('governance.sla.enabled', true) ? 'true' : 'false',
        ];
        foreach ($settings as $name => $value) {
            Setting::firstOrCreate(['name' => $name], ['value' => $value]);
        }

        // 5.2 / 5.4 / 5.6 — permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            ['name' => 'case-disposition-approve', 'category' => 'Case Management'],
            ['name' => 'case-file', 'category' => 'Case Management'],
            ['name' => 'mi-reports', 'category' => 'Case Management'],
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], ['category' => $perm['category']]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $supervisor = Role::firstOrCreate(['name' => 'supervisor']);
        $supervisor->givePermissionTo([
            'case-disposition-approve', 'case-file', 'mi-reports',
        ]);

        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->givePermissionTo(['mi-reports']);
    }

    public function down(): void
    {
        // Permissions are intentionally left for the seeder to manage.
    }
};
