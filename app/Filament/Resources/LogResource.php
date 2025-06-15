<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogResource\Pages;
use App\Filament\Resources\LogResource\RelationManagers;
use App\Models\Log;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LogResource extends Resource
{
    protected static ?string $model = Log::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('id')->disabled(),

                TextInput::make('source')->label('Source')->disabled(),
                TextInput::make('destination')->label('Destination')->disabled(),
                TextInput::make('host')->label('Host')->disabled(),
                Textarea::make('data_sent')->label('Data Sent')->disabled(),
                Textarea::make('data_received')->label('Data Received')->disabled(),
                DateTimePicker::make('sent_at')->label('Sent At')->disabled(),
                DateTimePicker::make('received_at')->label('Received At')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                BadgeColumn::make('source')
                    ->colors(['primary']),
                BadgeColumn::make('destination'),
                TextColumn::make('host')
                    ->label('Host'),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => fn($state) => $state === 'OK',
                        'danger' => fn($state) => $state === 'Failed',
                    ]),
                TextColumn::make('data_sent')
                    ->label('Data Sent')
                    ->limit(50) // Show only first 50 characters
                    ->tooltip(fn($record) => $record->data_sent),

                TextColumn::make('data_received')
                    ->label('Data Received')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->data_received),
                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime(),

                TextColumn::make('received_at')
                    ->label('Received At')
                    ->dateTime(),
                TextColumn::make('message')
                    ->label('Message')
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(10) // Paginate results
            ->recordUrl(fn($record) => LogResource::getUrl('view', ['record' => $record])) // 👈 Click row to view details
            ->filters([
                //
            ])
            ->actions([
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogs::route('/'),
            'view' => Pages\ViewLog::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
