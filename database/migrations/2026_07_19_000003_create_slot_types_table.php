<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $slotTypes = [
        ['key' => 'vocals', 'name' => 'Vocals', 'sort_order' => 10],
        ['key' => 'lead_guitar', 'name' => 'Lead Guitar', 'sort_order' => 20],
        ['key' => 'rhythm_guitar', 'name' => 'Rhythm Guitar', 'sort_order' => 30],
        ['key' => 'bass', 'name' => 'Bass', 'sort_order' => 40],
        ['key' => 'drums', 'name' => 'Drums', 'sort_order' => 50],
        ['key' => 'keys', 'name' => 'Keys', 'sort_order' => 60],
        ['key' => 'other', 'name' => 'Other', 'sort_order' => 70],
    ];

    private array $conflicts = [
        ['lead_guitar', 'bass'],
        ['rhythm_guitar', 'bass'],
        ['drums', 'keys'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('slot_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('slot_type_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_type_id')->constrained('slot_types')->cascadeOnDelete();
            $table->foreignId('conflicting_slot_type_id')->constrained('slot_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['slot_type_id', 'conflicting_slot_type_id'], 'slot_type_conflicts_unique');
        });

        $this->seedSlotTypes();
        $this->seedConflicts();
        $this->convertSlotNameColumnsToString();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->convertSlotNameColumnsToEnum();

        Schema::dropIfExists('slot_type_conflicts');
        Schema::dropIfExists('slot_types');
    }

    private function seedSlotTypes(): void
    {
        $now = now();

        DB::table('slot_types')->insert(array_map(fn (array $slotType) => [
            ...$slotType,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->slotTypes));
    }

    private function seedConflicts(): void
    {
        $slotTypeIds = DB::table('slot_types')->pluck('id', 'key');
        $now = now();
        $rows = [];

        foreach ($this->conflicts as [$slotTypeKey, $conflictingSlotTypeKey]) {
            if (! isset($slotTypeIds[$slotTypeKey], $slotTypeIds[$conflictingSlotTypeKey])) {
                continue;
            }

            foreach ([[$slotTypeKey, $conflictingSlotTypeKey], [$conflictingSlotTypeKey, $slotTypeKey]] as [$sourceKey, $targetKey]) {
                $rows[$sourceKey.'->'.$targetKey] = [
                    'slot_type_id' => $slotTypeIds[$sourceKey],
                    'conflicting_slot_type_id' => $slotTypeIds[$targetKey],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('slot_type_conflicts')->insert(array_values($rows));
        }
    }

    private function convertSlotNameColumnsToString(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE slots MODIFY name VARCHAR(64) NOT NULL');
            DB::statement('ALTER TABLE band_template_slots MODIFY name VARCHAR(64) NOT NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE slots DROP CONSTRAINT IF EXISTS slots_name_check');
            DB::statement('ALTER TABLE slots ALTER COLUMN name TYPE VARCHAR(64)');
            DB::statement('ALTER TABLE band_template_slots DROP CONSTRAINT IF EXISTS band_template_slots_name_check');
            DB::statement('ALTER TABLE band_template_slots ALTER COLUMN name TYPE VARCHAR(64)');
        }
    }

    private function convertSlotNameColumnsToEnum(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $values = "'vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'keys', 'other'";

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE slots MODIFY name ENUM($values) NOT NULL");
            DB::statement("ALTER TABLE band_template_slots MODIFY name ENUM($values) NOT NULL");
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE slots ADD CONSTRAINT slots_name_check CHECK (name IN ($values))");
            DB::statement("ALTER TABLE band_template_slots ADD CONSTRAINT band_template_slots_name_check CHECK (name IN ($values))");
        }
    }
};
