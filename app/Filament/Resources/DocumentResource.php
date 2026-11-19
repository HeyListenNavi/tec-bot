<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $modelLabel = 'Documento Institucional';
    protected static ?string $pluralModelLabel = 'Documentos';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Bot';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && !$user->hasRole('admin') && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Documento PDF')
                    ->description('Carga documentos PDF con información institucional para alimentar al agente de IA (TecBot).')
                    ->icon('heroicon-m-document-arrow-up')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título del Documento')
                            ->required()
                            ->maxLength(200)
                            ->placeholder('Ej. Guía y Requisitos de Servicio Social 2026'),

                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->label('Departamento')
                            ->default(fn () => auth()->user()?->department_id)
                            ->disabled(fn () => auth()->user() && !auth()->user()->hasRole('admin') && auth()->user()->department_id !== null)
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción / Tema')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Breve resumen de los temas o preguntas frecuentes que cubre este PDF...'),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Archivo PDF Institucional')
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull()
                            ->helperText('Al guardar el archivo PDF, el sistema extraerá automáticamente su texto para que TecBot lo consulte.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible para el Agente de IA')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Contenido de Conocimiento para la IA (Texto Extraído)')
                    ->description('Este texto se extrae automáticamente del PDF al guardar, o puedes editarlo/complementarlo directamente para optimizar las respuestas de TecBot.')
                    ->icon('heroicon-m-cpu-chip')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('extracted_text')
                            ->label('Texto de Consulta Institucional')
                            ->rows(10)
                            ->columnSpanFull()
                            ->placeholder('El texto del PDF aparecerá aquí automáticamente al guardar, o puedes pegar aquí la información oficial del documento...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-document-text'),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->wrap(),

                IconColumn::make('is_active')
                    ->label('Activo en IA')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Departamento'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo en IA'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
