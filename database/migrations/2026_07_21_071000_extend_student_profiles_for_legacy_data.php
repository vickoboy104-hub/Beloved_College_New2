<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('students'));

        Schema::table('students', function (Blueprint $table) use ($columns): void {
            $stringColumns = [
                'boarding_status', 'house', 'gender', 'place_of_birth', 'nationality',
                'lga', 'blood_group', 'state_of_origin', 'religion', 'guardian_name',
                'guardian_phone', 'parents_occupation', 'office_residence_phone',
                'previous_school', 'previous_class', 'doctor_name', 'doctor_phone',
            ];

            foreach ($stringColumns as $column) {
                if (! isset($columns[$column])) {
                    $table->string($column)->nullable();
                }
            }

            if (! isset($columns['date_of_birth'])) {
                $table->date('date_of_birth')->nullable();
            }

            foreach (['address', 'medical_notes', 'physical_notes', 'doctor_address'] as $column) {
                if (! isset($columns[$column])) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        // Student history fields are intentionally preserved.
    }
};
