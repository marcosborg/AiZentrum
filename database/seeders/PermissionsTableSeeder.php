<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            [
                'id'    => 1,
                'title' => 'user_management_access',
            ],
            [
                'id'    => 2,
                'title' => 'permission_create',
            ],
            [
                'id'    => 3,
                'title' => 'permission_edit',
            ],
            [
                'id'    => 4,
                'title' => 'permission_show',
            ],
            [
                'id'    => 5,
                'title' => 'permission_delete',
            ],
            [
                'id'    => 6,
                'title' => 'permission_access',
            ],
            [
                'id'    => 7,
                'title' => 'role_create',
            ],
            [
                'id'    => 8,
                'title' => 'role_edit',
            ],
            [
                'id'    => 9,
                'title' => 'role_show',
            ],
            [
                'id'    => 10,
                'title' => 'role_delete',
            ],
            [
                'id'    => 11,
                'title' => 'role_access',
            ],
            [
                'id'    => 12,
                'title' => 'user_create',
            ],
            [
                'id'    => 13,
                'title' => 'user_edit',
            ],
            [
                'id'    => 14,
                'title' => 'user_show',
            ],
            [
                'id'    => 15,
                'title' => 'user_delete',
            ],
            [
                'id'    => 16,
                'title' => 'user_access',
            ],
            [
                'id'    => 17,
                'title' => 'assistant_create',
            ],
            [
                'id'    => 18,
                'title' => 'assistant_edit',
            ],
            [
                'id'    => 19,
                'title' => 'assistant_show',
            ],
            [
                'id'    => 20,
                'title' => 'assistant_delete',
            ],
            [
                'id'    => 21,
                'title' => 'assistant_access',
            ],
            [
                'id'    => 22,
                'title' => 'openai_create',
            ],
            [
                'id'    => 23,
                'title' => 'openai_edit',
            ],
            [
                'id'    => 24,
                'title' => 'openai_show',
            ],
            [
                'id'    => 25,
                'title' => 'openai_delete',
            ],
            [
                'id'    => 26,
                'title' => 'openai_access',
            ],
            [
                'id'    => 27,
                'title' => 'instruction_create',
            ],
            [
                'id'    => 28,
                'title' => 'instruction_edit',
            ],
            [
                'id'    => 29,
                'title' => 'instruction_show',
            ],
            [
                'id'    => 30,
                'title' => 'instruction_delete',
            ],
            [
                'id'    => 31,
                'title' => 'instruction_access',
            ],
            [
                'id'    => 32,
                'title' => 'training_create',
            ],
            [
                'id'    => 33,
                'title' => 'training_edit',
            ],
            [
                'id'    => 34,
                'title' => 'training_show',
            ],
            [
                'id'    => 35,
                'title' => 'training_delete',
            ],
            [
                'id'    => 36,
                'title' => 'training_access',
            ],
            [
                'id'    => 37,
                'title' => 'project_create',
            ],
            [
                'id'    => 38,
                'title' => 'project_edit',
            ],
            [
                'id'    => 39,
                'title' => 'project_show',
            ],
            [
                'id'    => 40,
                'title' => 'project_delete',
            ],
            [
                'id'    => 41,
                'title' => 'project_access',
            ],
            [
                'id'    => 42,
                'title' => 'log_create',
            ],
            [
                'id'    => 43,
                'title' => 'log_edit',
            ],
            [
                'id'    => 44,
                'title' => 'log_show',
            ],
            [
                'id'    => 45,
                'title' => 'log_delete',
            ],
            [
                'id'    => 46,
                'title' => 'log_access',
            ],
            [
                'id'    => 47,
                'title' => 'log_menu_access',
            ],
            [
                'id'    => 48,
                'title' => 'log_message_create',
            ],
            [
                'id'    => 49,
                'title' => 'log_message_edit',
            ],
            [
                'id'    => 50,
                'title' => 'log_message_show',
            ],
            [
                'id'    => 51,
                'title' => 'log_message_delete',
            ],
            [
                'id'    => 52,
                'title' => 'log_message_access',
            ],
            [
                'id'    => 53,
                'title' => 'log_history_create',
            ],
            [
                'id'    => 54,
                'title' => 'log_history_edit',
            ],
            [
                'id'    => 55,
                'title' => 'log_history_show',
            ],
            [
                'id'    => 56,
                'title' => 'log_history_delete',
            ],
            [
                'id'    => 57,
                'title' => 'log_history_access',
            ],
            [
                'id'    => 58,
                'title' => 'form_menu_access',
            ],
            [
                'id'    => 59,
                'title' => 'form_create',
            ],
            [
                'id'    => 60,
                'title' => 'form_edit',
            ],
            [
                'id'    => 61,
                'title' => 'form_show',
            ],
            [
                'id'    => 62,
                'title' => 'form_delete',
            ],
            [
                'id'    => 63,
                'title' => 'form_access',
            ],
            [
                'id'    => 64,
                'title' => 'form_field_create',
            ],
            [
                'id'    => 65,
                'title' => 'form_field_edit',
            ],
            [
                'id'    => 66,
                'title' => 'form_field_show',
            ],
            [
                'id'    => 67,
                'title' => 'form_field_delete',
            ],
            [
                'id'    => 68,
                'title' => 'form_field_access',
            ],
            [
                'id'    => 69,
                'title' => 'form_data_create',
            ],
            [
                'id'    => 70,
                'title' => 'form_data_edit',
            ],
            [
                'id'    => 71,
                'title' => 'form_data_show',
            ],
            [
                'id'    => 72,
                'title' => 'form_data_delete',
            ],
            [
                'id'    => 73,
                'title' => 'form_data_access',
            ],
            [
                'id'    => 74,
                'title' => 'profile_password_edit',
            ],
            [
                'id'    => 75,
                'title' => 'zcm_access',
            ],
            [
                'id'    => 76,
                'title' => 'zcm_pending_ad_access',
            ],
            [
                'id'    => 77,
                'title' => 'zcm_pending_ad_review',
            ],
            [
                'id'    => 78,
                'title' => 'zcm_pending_ad_export',
            ],
        ];

        Permission::insert($permissions);
    }
}
