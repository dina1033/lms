<?php

namespace App\Contracts;

final readonly class InstructorBalance
{
    public function __construct(
        public int $totalEarnedMinor,
        public int $totalPaidMinor,
        public int $outstandingMinor,
    ) {}
}