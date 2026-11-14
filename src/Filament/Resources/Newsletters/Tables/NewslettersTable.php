<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\Newsletters\Tables;

use Filagora\Newsletters\Enums\NewsletterStatus;
use Filagora\Newsletters\Models\Newsletter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NewslettersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (NewsletterStatus|string|null $state): string => $state instanceof NewsletterStatus ? $state->label() : ucfirst((string) $state))
                    ->color(static fn (NewsletterStatus|string|null $state): string => $state instanceof NewsletterStatus ? $state->color() : 'gray')
                    ->sortable(),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('creator.email')
                    ->label('Created by')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(NewsletterStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Newsletter $record): bool => $record->status->isEditable()),
                DeleteAction::make()
                    ->visible(fn (Newsletter $record): bool => $record->isDeletable()),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeCells);
    }
}
