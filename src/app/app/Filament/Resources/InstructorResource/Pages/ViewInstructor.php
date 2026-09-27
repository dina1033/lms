<?php

namespace App\Filament\Resources\InstructorResource\Pages;

use App\Filament\Resources\InstructorResource;
use App\Queries\InstructorBalanceQuery;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use App\Filament\Resources\InstructorResource\RelationManagers\PayoutsRelationManager;

class ViewInstructor extends ViewRecord
{
    protected static string $resource = InstructorResource::class;

    public function getBalance(): array
    {
        $balance = app(InstructorBalanceQuery::class)
            ->forInstructor($this->record);

        return [
            'earned' => $balance->totalEarnedMinor,
            'paid' => $balance->totalPaidMinor,
            'outstanding' => $balance->outstandingMinor,
        ];
    }
    public function getRelationManagers(): array
    {
        return [
            PayoutsRelationManager::class,
        ];
    }
}