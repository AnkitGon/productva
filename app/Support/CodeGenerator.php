<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class CodeGenerator
{
    /**
     * Generate the next unique code for a given table, column, and prefix.
     *
     * @param string $table
     * @param string $column
     * @param string $prefix
     * @param int $orgId
     * @param int|null $plantId
     * @return string
     */
    public static function generate(string $table, string $column, string $prefix, int $orgId, ?int $plantId = null): string
    {
        $query = DB::table($table)->where('organization_id', $orgId);
        
        if ($plantId !== null) {
            $query->where('plant_id', $plantId);
        }

        // Fetch all codes (including soft deleted ones to avoid unique constraint violations)
        $codes = $query->pluck($column)->all();

        $maxNum = 0;
        $regex = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        
        foreach ($codes as $code) {
            if ($code && preg_match($regex, $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $nextNum = $maxNum + 1;
        
        return $prefix . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
    }
}
