<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicantResource\Pages;
use App\Filament\Resources\ApplicantResource\RelationManagers;
use App\Models\Applicant;
use App\Models\Question;
use App\Services\ApplicantActions;
use App\Services\WhatsappApiNotificationService;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantResource extends Resource
{
    protected static ?string $model = Applicant::class;

    protected static ?string $modelLabel = 'Alumno / Trámite';
    protected static ?string $pluralModelLabel = 'Alumnos y Trámites';
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Estudiantes';

    protected static ?string $recordTitleAttribute = 'applicant_name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && !$user->hasRole('admin') && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return $query;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['applicant_name', 'curp', 'chat_id', 'process_type'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->applicant_name ?? 'Sin nombre';
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Matrícula' => $record->curp ?? 'N/A',
            'Trámite' => $record->process_type ?? 'General',
            'Teléfono' => str_starts_with((string) $record->chat_id, '521') ? substr($record->chat_id, 3) : $record->chat_id,
            'Estatus' => match ($record->process_status) {
                'in_progress' => 'En Proceso',
                'approved', 'staff_approved' => 'Finalizado / Aprobado',
                'rejected', 'staff_rejected' => 'Rechazado / Observado',
                'requires_revision' => 'Acción / Doc. Pendiente',
                'canceled' => 'Cancelado',
                default => 'Otro',
            },
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Estudiante')
                    ->description('Información mínima necesaria para identificar al alumno (Principio de Minimización de Datos).')
                    ->icon('heroicon-m-identification')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('applicant_name')
                            ->label('Nombre del Alumno')
                            ->required()
                            ->prefixIcon('heroicon-m-user')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('curp')
                            ->label('Matrícula / Número de Control')
                            ->prefixIcon('heroicon-m-finger-print')
                            ->maxLength(30)
                            ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : null)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->validationMessages([
                                'unique' => 'Esta matrícula ya está registrada.',
                            ]),

                        Forms\Components\TextInput::make('chat_id')
                            ->label('Teléfono (WhatsApp)')
                            ->required()
                            ->tel()
                            ->prefixIcon('heroicon-m-phone')
                            ->formatStateUsing(function ($state, string $operation) {
                                if (!$state) return '-';
                                if ($operation !== 'view') return $state;

                                return str_starts_with($state, '521') ? substr($state, 3) : $state;
                            }),
                    ]),

                Forms\Components\Section::make('Seguimiento del Proceso / Trámite')
                    ->description('Departamento responsable, trámite que realiza el estudiante y su estado actual.')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->label('Departamento')
                            ->default(fn () => auth()->user()?->department_id)
                            ->searchable()
                            ->preload()
                            ->prefixIcon('heroicon-m-building-office'),

                        Forms\Components\TextInput::make('process_type')
                            ->label('Trámite / Proceso')
                            ->placeholder('Ej. Servicio Social, Residencia Profesional, Titulación, Credencial')
                            ->prefixIcon('heroicon-m-document-check')
                            ->maxLength(150),

                        Forms\Components\Select::make('current_stage_id')
                            ->relationship('currentStage', 'name')
                            ->required()
                            ->label('Etapa Actual del Trámite')
                            ->native(false)
                            ->live(),

                        Forms\Components\Select::make('assigned_user_id')
                            ->relationship('assignedUser', 'name')
                            ->label('Responsable Asignado')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id())
                            ->prefixIcon('heroicon-m-user-circle'),

                        Forms\Components\ToggleButtons::make('process_status')
                            ->label('Estado del Trámite')
                            ->options([
                                'in_progress' => 'En Proceso',
                                'requires_revision' => 'Doc. / Acción Pendiente',
                                'approved' => 'Aprobado (Auto)',
                                'staff_approved' => 'Finalizado / Aprobado',
                                'staff_rejected' => 'Rechazado / Con Observación',
                                'canceled' => 'Cancelado',
                            ])
                            ->icons([
                                'in_progress' => 'heroicon-m-arrow-path',
                                'requires_revision' => 'heroicon-m-exclamation-triangle',
                                'approved' => 'heroicon-m-sparkles',
                                'staff_approved' => 'heroicon-m-check-badge',
                                'staff_rejected' => 'heroicon-m-x-circle',
                                'canceled' => 'heroicon-m-x-mark',
                            ])
                            ->colors([
                                'in_progress' => 'info',
                                'requires_revision' => 'warning',
                                'approved' => 'success',
                                'staff_approved' => 'success',
                                'staff_rejected' => 'danger',
                                'canceled' => 'gray',
                            ])
                            ->inline()
                            ->required()
                            ->columnSpanFull()
                            ->live(),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Observaciones / Requisitos o Acciones Pendientes del Alumno')
                            ->placeholder('Ej. Falta entregar Carta de Aceptación firmada y Kardex actualizado...')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('El agente de IA informará esta observación al alumno cuando consulte su trámite por WhatsApp y se incluirá en las notificaciones automáticas.'),
                    ]),

                Forms\Components\Actions::make([
                    Action::make('notifyStatusWhatsapp')
                        ->label('Notificar Estado por WhatsApp')
                        ->icon('heroicon-o-bell-alert')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Enviar actualización de trámite por WhatsApp')
                        ->modalDescription('Se enviará una notificación automática por WhatsApp (Evolution API) informando al alumno la etapa actual, estado y observaciones de su trámite.')
                        ->action(function (Applicant $record) {
                            $sent = app(WhatsappApiNotificationService::class)->sendProcessStatusNotification($record);
                            Notification::make()
                                ->title($sent ? 'Notificación enviada por WhatsApp' : 'Notificación registrada en el historial de mensajes')
                                ->success()
                                ->send();
                        }),

                    Action::make('approveStage')
                        ->label('Avanzar a siguiente etapa')
                        ->icon('heroicon-o-forward')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Avanzar a la siguiente etapa del trámite')
                        ->modalDescription('¿Confirmas avanzar el trámite a la siguiente etapa y notificar automáticamente al estudiante por WhatsApp?')
                        ->modalSubmitActionLabel('Sí, avanzar etapa')
                        ->action(fn (Applicant $record) => ApplicantActions::approveStage($record)),

                    Action::make('approveFinal')
                        ->label('Finalizar / Aprobar Trámite')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Finalizar trámite del estudiante')
                        ->modalDescription('Esta acción marcará el trámite como finalizado/aprobado y enviará la notificación de conclusión por WhatsApp al alumno.')
                        ->action(fn (Applicant $record) => ApplicantActions::approveApplicantFinal($record)),

                    Action::make('sendCustomMessage')
                        ->label('Enviar mensaje directo')
                        ->icon('heroicon-o-chat-bubble-bottom-center-text')
                        ->form([
                            Forms\Components\Textarea::make('message')
                                ->label('Mensaje de WhatsApp')
                                ->required()
                                ->rows(4)
                                ->placeholder('Escribe el aviso o indicación para el alumno...'),
                        ])
                        ->modalHeading('Enviar mensaje por WhatsApp al alumno')
                        ->action(function (array $data, Applicant $record) {
                            ApplicantActions::sendCustomMessage($record, $data['message']);
                            Notification::make()->title('Mensaje enviado al estudiante')->success()->send();
                        }),

                    Action::make('rejectApplicant')
                        ->label('Solicitar Requisito / Observación')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('predefined_reason')
                                ->label('Tipo de requisito o acción pendiente')
                                ->options([
                                    'Carta de aceptación pendiente de entrega' => 'Carta de aceptación pendiente de entrega',
                                    'Kardex o constancia de créditos incompleta' => 'Kardex o constancia de créditos incompleta',
                                    'Formato de solicitud sin firma' => 'Formato de solicitud sin firma',
                                    'Pago de derechos pendiente de validación' => 'Pago de derechos pendiente de validación',
                                    'Acudir a ventanilla del departamento' => 'Acudir a ventanilla del departamento',
                                    'other' => 'Otro (Especificar)',
                                ])
                                ->required()
                                ->native(false)
                                ->live(),

                            Forms\Components\Textarea::make('reason')
                                ->label('Detalle de la observación / documento faltante')
                                ->required(fn (Get $get) => $get('predefined_reason') === 'other')
                                ->visible(fn (Get $get) => $get('predefined_reason') === 'other')
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Notificar acción o documento pendiente al alumno')
                        ->action(function (array $data, Applicant $record) {
                            ApplicantActions::rejectApplicant(
                                $record,
                                $data['predefined_reason'] === 'other' ? $data['reason'] : $data['predefined_reason']
                            );
                            Notification::make()->title('Estado actualizado y alumno notificado')->warning()->send();
                        }),

                    Action::make('restartApplicant')
                        ->label('Reiniciar Trámite')
                        ->icon('heroicon-o-arrow-path')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reiniciar trámite del alumno')
                        ->modalDescription('¿Estás seguro de regresar el trámite a la etapa inicial?')
                        ->action(fn (Applicant $record) => ApplicantActions::resetApplicant($record)),
                ])
                    ->fullWidth()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('curp')
                    ->label('Matrícula')
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : 'S/M')
                    ->color('primary')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('applicant_name')
                    ->label('Alumno')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Applicant $record): ?string => $record->department?->name),

                TextColumn::make('process_type')
                    ->label('Trámite')
                    ->placeholder('Trámite General')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('chat_id')
                    ->label('Teléfono')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';

                        return str_starts_with($state, '521') ? substr($state, 3) : $state;
                    })
                    ->url(fn ($state) => 'https://wa.me/' . $state)
                    ->openUrlInNewTab(),

                TextColumn::make('currentStage.name')
                    ->label('Etapa Actual')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('process_status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in_progress' => 'En Proceso',
                        'approved', 'staff_approved' => 'Finalizado',
                        'rejected', 'staff_rejected' => 'Con Observación',
                        'requires_revision' => 'Doc. Pendiente',
                        'canceled' => 'Cancelado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in_progress' => 'info',
                        'approved', 'staff_approved' => 'success',
                        'rejected', 'staff_rejected' => 'danger',
                        'requires_revision' => 'warning',
                        'canceled' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'in_progress' => 'heroicon-m-arrow-path',
                        'approved', 'staff_approved' => 'heroicon-m-check-badge',
                        'rejected', 'staff_rejected' => 'heroicon-m-x-circle',
                        'requires_revision' => 'heroicon-m-exclamation-triangle',
                        'canceled' => 'heroicon-m-x-mark',
                        default => 'heroicon-m-minus',
                    })
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Departamento'),

                Tables\Filters\SelectFilter::make('process_status')
                    ->label('Estado del Trámite')
                    ->options([
                        'in_progress' => 'En Proceso',
                        'requires_revision' => 'Doc. / Acción Pendiente',
                        'staff_approved' => 'Finalizado / Aprobado',
                        'staff_rejected' => 'Con Observación / Rechazado',
                        'canceled' => 'Cancelado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('gray'),
                    Tables\Actions\EditAction::make()->color('primary'),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            foreach ($records as $record) {
                                if ($record->conversation) {
                                    $record->conversation->delete();
                                }
                            }
                        }),
                    Tables\Actions\BulkAction::make('exportSelected')
                        ->label('Exportar a CSV')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->action(function (Collection $records) {
                            return new StreamedResponse(function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['Matricula', 'Alumno', 'Telefono', 'Tramite', 'Estado', 'Observaciones']);

                                $records->each(function ($applicant) use ($handle) {
                                    fputcsv($handle, [
                                        $applicant->curp,
                                        $applicant->applicant_name,
                                        $applicant->chat_id,
                                        $applicant->process_type,
                                        $applicant->process_status,
                                        $applicant->rejection_reason,
                                    ]);
                                });

                                fclose($handle);
                            }, 200, [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="alumnos-tramites-' . now()->format('Y-m-d') . '.csv"',
                            ]);
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ConversationRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplicants::route('/'),
            'create' => Pages\CreateApplicant::route('/create'),
            'view' => Pages\ViewApplicant::route('/{record}'),
            'edit' => Pages\EditApplicant::route('/{record}/edit'),
        ];
    }
}
