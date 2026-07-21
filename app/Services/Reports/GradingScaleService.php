<?php

namespace App\Services\Reports;

class GradingScaleService
{
    /**
     * @return array{grade: string, remark: string}
     */
    public function classify(float $percentage): array
    {
        return match (true) {
            $percentage >= 70 => ['grade' => 'A', 'remark' => 'Excellent'],
            $percentage >= 60 => ['grade' => 'B', 'remark' => 'Very Good'],
            $percentage >= 50 => ['grade' => 'C', 'remark' => 'Good'],
            $percentage >= 45 => ['grade' => 'D', 'remark' => 'Fair'],
            $percentage >= 40 => ['grade' => 'E', 'remark' => 'Pass'],
            default => ['grade' => 'F', 'remark' => 'Fail'],
        };
    }
}
