<?php

namespace App\Models\Concerns;

use App\Support\CodeGenerator;

trait AutoGeneratesCode
{
    /**
     * Boot the trait and register the creating event handler.
     */
    public static function bootAutoGeneratesCode(): void
    {
        static::creating(function ($model) {
            $table = $model->getTable();
            $column = ($table === 'employees') ? 'employee_code' : 'code';

            if (empty($model->{$column})) {
                $orgId = $model->organization_id;
                $plantId = $model->plant_id ?? null;

                if (! $orgId && auth()->check()) {
                    $orgId = auth()->user()->organization_id;
                }
                if ($plantId === null && isset($model->plant_id) && auth()->check()) {
                    $plantId = auth()->user()->active_plant_id;
                }

                if ($orgId) {
                    $prefix = match ($table) {
                        'employees' => 'EMP',
                        'departments' => 'DEPT',
                        'shifts' => 'SHF',
                        'product_categories' => 'CAT',
                        'units_of_measure' => 'UOM',
                        'operations' => 'OP',
                        'work_centers' => 'WC',
                        'machines' => 'MC',
                        'warehouses' => 'WH',
                        'warehouse_locations' => 'LOC',
                        'warehouse_types' => 'WHT',
                        default => null,
                    };

                    if ($prefix) {
                        $isPlantWise = in_array($table, [
                            'employees', 'departments', 'shifts', 'work_centers',
                            'machines', 'warehouses', 'warehouse_locations',
                        ]);

                        $genPlantId = $isPlantWise ? $plantId : null;

                        $model->{$column} = CodeGenerator::generate($table, $column, $prefix, (int) $orgId, $genPlantId);
                    }
                }
            }
        });
    }
}
