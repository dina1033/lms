<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstructorResource\Pages;
use App\Models\Instructor;
use App\Queries\InstructorBalanceQuery;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InstructorResource extends Resource
{
    protected static ?string $model = Instructor::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Instructor Balances';

    protected static ?string $modelLabel = 'Instructor';

    protected static ?string $pluralModelLabel = 'Instructor Balances';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Instructor')
                    ->searchable()
                    ->sortable(),
    
                Tables\Columns\TextColumn::make('total_earned')
                    ->label('Total Earned')
                    ->state(function (Instructor $record): int {
                        return app(InstructorBalanceQuery::class)
                            ->forInstructor($record)
                            ->totalEarnedMinor;
                    })
                    ->sortable(),
    
                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->state(function (Instructor $record): int {
                        return app(InstructorBalanceQuery::class)
                            ->forInstructor($record)
                            ->totalPaidMinor;
                    })
                    ->sortable(),
    
                Tables\Columns\TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->state(function (Instructor $record): int {
                        return app(InstructorBalanceQuery::class)
                            ->forInstructor($record)
                            ->outstandingMinor;
                    })
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstructors::route('/'),
            'view' => Pages\ViewInstructor::route('/{record}'),
        ];
    }
}