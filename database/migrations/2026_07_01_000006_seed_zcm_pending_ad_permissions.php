<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['zcm_pending_ad_access', 'zcm_pending_ad_review', 'zcm_pending_ad_export'] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['title' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $adminRoleId = DB::table('roles')->where('title', 'Admin')->value('id') ?: 1;
        $permissionIds = DB::table('permissions')
            ->whereIn('title', ['zcm_pending_ad_access', 'zcm_pending_ad_review', 'zcm_pending_ad_export'])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('title', ['zcm_pending_ad_access', 'zcm_pending_ad_review', 'zcm_pending_ad_export'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
