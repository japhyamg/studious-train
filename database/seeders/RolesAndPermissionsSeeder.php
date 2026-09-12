<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Cases
            ['name' => 'case-list', 'category' => 'Case Management'],
            ['name' => 'case-view', 'category' => 'Case Management'],
            ['name' => 'case-add-comment', 'category' => 'Case Management'],
            ['name' => 'case-close', 'category' => 'Case Management'],
            ['name' => 'case-performance', 'category' => 'Case Management'],
            ['name' => 'case-export', 'category' => 'Case Management'],
            ['name' => 'case-carrd', 'category' => 'Case Management'],
            ['name' => 'case-false-positive-dashboard', 'category' => 'Case Management'],
            ['name' => 'case-disposition-approve', 'category' => 'Case Management'],
            ['name' => 'case-file', 'category' => 'Case Management'],
            ['name' => 'mi-reports', 'category' => 'Case Management'],

            // Transactions
            ['name' => 'transaction-list', 'category' => 'Transactions'],

            // Rules
            ['name' => 'rule-list', 'category' => 'Rules'],
            ['name' => 'rule-create', 'category' => 'Rules'],
            ['name' => 'rule-update', 'category' => 'Rules'],
            ['name' => 'rule-delete', 'category' => 'Rules'],
            ['name' => 'rule-edit-value', 'category' => 'Rules'],

            // Customers
            ['name' => 'customer-list', 'category' => 'Customers'],
            ['name' => 'customer-create', 'category' => 'Customers'],
            ['name' => 'customer-update', 'category' => 'Customers'],
            ['name' => 'customer-delete', 'category' => 'Customers'],
            ['name' => 'customer-import', 'category' => 'Customers'],

            // Watchlist
            ['name' => 'nibss-watchlist-list', 'category' => 'Watchlist'],
            ['name' => 'nibss-watchlist-import', 'category' => 'Watchlist'],
            ['name' => 'internal-watchlist-list', 'category' => 'Watchlist'],
            ['name' => 'internal-watchlist-import', 'category' => 'Watchlist'],

            // Risk Rating
            ['name' => 'risk-rating-list', 'category' => 'Risk Rating'],
            ['name' => 'risk-rating-view', 'category' => 'Risk Rating'],
            ['name' => 'risk-rating-generate', 'category' => 'Risk Rating'],
            ['name' => 'risk-rating-delete', 'category' => 'Risk Rating'],
            ['name' => 'risk-rating-export', 'category' => 'Risk Rating'],
            ['name' => 'risk-level-list', 'category' => 'Risk Rating'],
            ['name' => 'risk-level-view', 'category' => 'Risk Rating'],
            ['name' => 'risk-level-create', 'category' => 'Risk Rating'],
            ['name' => 'risk-level-update', 'category' => 'Risk Rating'],
            ['name' => 'risk-level-delete', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-list', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-create', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-view', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-update', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-delete', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-template-import', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-template-download', 'category' => 'Risk Rating'],
            ['name' => 'risk-profile-template-delete', 'category' => 'Risk Rating'],

            // Team
            ['name' => 'team-list', 'category' => 'Team'],
            ['name' => 'team-view', 'category' => 'Team'],
            ['name' => 'team-create', 'category' => 'Team'],
            ['name' => 'team-update', 'category' => 'Team'],
            ['name' => 'team-delete', 'category' => 'Team'],
            ['name' => 'assign-rules', 'category' => 'Team'],
            ['name' => 'manage-roles', 'category' => 'Team'],

            // Settings
            ['name' => 'settings-manage', 'category' => 'Settings'],
            ['name' => 'audit-trail', 'category' => 'Settings'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['category' => $perm['category']]
            );
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $supervisor = Role::firstOrCreate(['name' => 'supervisor']);
        $supervisor->givePermissionTo([
            'case-list', 'case-view', 'case-add-comment', 'case-close', 'case-performance',
            'case-export', 'case-carrd', 'case-false-positive-dashboard',
            'case-disposition-approve', 'case-file', 'mi-reports',
            'transaction-list',
            'rule-list', 'rule-edit-value',
            'customer-list', 'customer-create', 'customer-update', 'customer-import',
            'nibss-watchlist-list', 'internal-watchlist-list',
            'risk-rating-list', 'risk-rating-view', 'risk-rating-generate', 'risk-rating-export',
            'team-list', 'team-view', 'assign-rules',
        ]);

        $reviewer = Role::firstOrCreate(['name' => 'reviewer']);
        $reviewer->givePermissionTo([
            'case-list', 'case-view', 'case-add-comment',
            'transaction-list',
            'rule-list',
            'customer-list',
            'nibss-watchlist-list', 'internal-watchlist-list',
        ]);

        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->givePermissionTo([
            'case-list', 'case-view', 'case-export', 'case-carrd',
            'transaction-list',
            'rule-list',
            'customer-list',
            'audit-trail',
            'mi-reports',
        ]);
    }
}
