<?php

namespace App\Data;

use App\Models\StudentTermReport;

readonly class ReportPublicationResult
{
    public function __construct(
        public StudentTermReport $report,
        public ?string $checkerPin = null,
    ) {}
}
