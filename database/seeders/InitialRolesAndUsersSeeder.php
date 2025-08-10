<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InitialRolesAndUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin role for Filament (admin guard)
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'admin']
        );

        // Optionally: give all permissions to Super Admin
        $superAdminRole->syncPermissions(Permission::all());

        // Create Manager role for Filament (admin guard)
        $managerRole = Role::firstOrCreate(
            ['name' => 'Manager', 'guard_name' => 'admin']
        );

        // Create Landlord role for default web guard
        $landlordRole = Role::firstOrCreate(
            ['name' => 'Landlord', 'guard_name' => 'web']
        );

        // Create Tenant role for default web guard
        $tenantRole = Role::firstOrCreate(
            ['name' => 'Tenant', 'guard_name' => 'web']
        );

        // Create initial Super Admin user for Filament
        $adminUser = User::firstOrCreate(
            ['email' => 'suvnkr.xaphene@gmail.com'],
            [
                'name' => 'Subhankar Sarkar',
                'password' => Hash::make('secret'), // Change this
            ]
        );

        // Assign Super Admin role (admin guard)
        $adminUser->assignRole($superAdminRole);

        // You can also create a sample Manager user
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password'), // Change this
            ]
        );

        $managerUser->assignRole($managerRole);
    }
}
