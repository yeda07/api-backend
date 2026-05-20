<?php

use App\Models\CrmEntity;
use App\Models\Opportunity;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->retarget([
            'products' => Product::class,
            'opportunities' => Opportunity::class,
        ]);
    }

    public function down(): void
    {
        $this->retarget([
            'products' => CrmEntity::class,
            'opportunities' => CrmEntity::class,
        ]);
    }

    private function retarget(array $moduleTargets): void
    {
        DB::table('custom_fields')
            ->whereIn('entity_type', [CrmEntity::class, Product::class, Opportunity::class])
            ->orderBy('id')
            ->get(['id', 'options'])
            ->each(function ($field) use ($moduleTargets) {
                $options = is_string($field->options)
                    ? json_decode($field->options, true)
                    : (array) $field->options;

                $module = $options['_module'] ?? null;

                if (!isset($moduleTargets[$module])) {
                    return;
                }

                DB::table('custom_fields')
                    ->where('id', $field->id)
                    ->update(['entity_type' => $moduleTargets[$module]]);
            });
    }
};
