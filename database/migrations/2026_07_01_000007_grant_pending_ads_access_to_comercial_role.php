<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['title' => 'zcm_pending_ad_access'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $permissionId = DB::table('permissions')
            ->where('title', 'zcm_pending_ad_access')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('title', ['Comercial', 'Comerciais'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('title', 'zcm_pending_ad_access')
            ->value('id');

        if (!$permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('title', ['Comercial', 'Comerciais'])
            ->pluck('id');

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
};
