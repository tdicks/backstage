<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'enable_social_logins'],
            [
                'name' => 'Enable Social Logins',
                'input_type' => 'checkbox',
                'value' => '1',
            ]
        );
    }
}
