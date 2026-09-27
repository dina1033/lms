<?php

namespace App\Filament\Resources\InstructorResource\RelationManagers;

use App\Enums\PayoutStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Payout History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period Start')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('Period End')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (PayoutStatus $state) => $state->value
                    ),

                Tables\Columns\TextColumn::make('provider_payout_reference')
                    ->label('Provider Reference')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->defaultSort('period_start', 'desc')
            ->actions([])
            ->bulkActions([]);
    }
}