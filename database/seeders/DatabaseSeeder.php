<?php

namespace Database\Seeders;

use App\Models\BandTemplate;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SettingsSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_admin' => true,
            'bio' => 'Default Backstage admin account.',
        ]);

        $template = BandTemplate::query()->create([
            'name' => 'Classic 5-Piece',
        ]);

        foreach (['vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums'] as $slotName) {
            $template->slots()->create(['name' => $slotName]);
        }

        $template = BandTemplate::query()->create([
            'name' => 'Power Trio',
        ]);

        foreach (['vocals', 'lead_guitar', 'bass', 'drums'] as $slotName) {
            $template->slots()->create(['name' => $slotName]);
        }

        $template = BandTemplate::query()->create([
            'name' => 'Open Jam',
        ]);

        foreach (Slot::NAMES as $slotName) {
            $template->slots()->create(['name' => $slotName]);
        }
    }
}
