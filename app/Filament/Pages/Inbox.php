<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ApplicantResource;
use App\Models\Applicant;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class Inbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $title = 'Trámites con Acción Pendiente';

    protected static ?string $navigationLabel = 'Acciones Pendientes';

    protected static ?string $navigationGroup = 'Estudiantes';

    protected static string $view = 'filament.pages.inbox';

    public static function getNavigationBadge(): ?string
    {
        return Applicant::where('process_status', 'requires_revision')->count() ?: null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Applicant::query()
                    ->where('process_status', 'requires_revision')
                    ->latest('updated_at')
            )
            ->columns([
                TextColumn::make('curp')
                    ->label('Matrícula')
                    ->searchable(),

                TextColumn::make('applicant_name')
                    ->label('Alumno')
                    ->searchable(),

                TextColumn::make('process_type')
                    ->label('Trámite')
                    ->searchable(),

                TextColumn::make('chat_id')
                    ->label('Número de Telefono')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        return str_starts_with($state, '521') ? substr($state, 3) : $state;
                    })
                    ->url(fn($state) => 'https://wa.me/' . $state)
                    ->openUrlInNewTab()
                    ->searchable(),

                TextColumn::make('currentStage.name')
                    ->label('Etapa Actual')
                    ->badge()
                    ->icon('heroicon-m-pause')
                    ->color('gray'),

                TextColumn::make('rejection_reason')
                    ->label('Requisito / Observación')
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('updated_at')
                    ->label('Tiempo en espera')
                    ->since()
                    ->sortable()
                    ->color('warning')
                    ->icon('heroicon-m-clock'),
            ])
            ->actions([
                Action::make('markAsRead')
                    ->label('Continuar Trámite')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Reanudar Trámite')
                    ->modalDescription('El trámite del alumno volverá al estado "En Proceso" y se le notificará por WhatsApp.')
                    ->action(function (Applicant $record) {
                        $record->update(['process_status' => 'in_progress']);
                        app(\App\Services\WhatsappApiNotificationService::class)->sendProcessStatusNotification($record);

                        Notification::make()
                            ->title('Trámite actualizado y alumno notificado')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('markSelectedAsRead')
                        ->label('Marcar seleccionados como listos')
                        ->icon('heroicon-m-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each->update(['process_status' => 'in_progress']);
                            Notification::make()->title('Aplicantes procesados')->success()->send();
                        }),
                ]),
            ])
            ->recordUrl(fn($record) => ApplicantResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('¡Todo al día!')
            ->emptyStateDescription('No hay aplicantes que requieran revisión manual por el momento.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}
