<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Chat permissions
            'chat.view.own',
            'chat.view.all',
            'chat.create',
            'chat.update.own',
            'chat.update.all',
            'chat.delete.own',
            'chat.delete.all',

            // User management permissions
            'user.view',
            'user.create',
            'user.update',
            'user.delete',

            // Role management permissions
            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            // Permission management
            'permission.view',
            'permission.assign',

            // Admin menu access
            'admin.access',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Admin role - has all permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // User role - basic permissions
        $userRole = Role::create(['name' => 'user']);
        $userRole->givePermissionTo([
            'chat.view.own',
            'chat.create',
            'chat.update.own',
            'chat.delete.own',
        ]);

        // Create default admin user if doesn't exist
        $adminUser = \App\Models\User::firstOrCreate(
            ['email' => 'admin@larachat.test'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
            ]
        );
        $adminUser->assignRole('admin');

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Default admin: admin@larachat.test / password');
    }
}
