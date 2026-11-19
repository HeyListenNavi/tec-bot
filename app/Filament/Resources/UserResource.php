<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Hash;



class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios y Roles';
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Institución';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
	    ->schema([
                Forms\Components\Section::make('Datos del Usuario')
		    ->columns(2)
	            ->schema([
			TextInput::make('name')
			    ->label('Nombre')
                            ->required(),
		        TextInput::make("email")
                            ->email()
		            ->label('Correo electrónico')
                            ->required(),
                        Select::make('department_id')
                            ->relationship('department', 'name')
                            ->label('Departamento')
                            ->searchable()
                            ->preload(),
			Select::make("roles")
                            ->label('Rol')
			    ->relationship(name: "roles", titleAttribute: "name")
                            ->preload(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Usuario Activo')
                            ->default(true),
		    ]),
	        Forms\Components\Section::make('Cambiar contraseña')
                     ->columns(2)
	             ->schema([
			TextInput::make('password')
                            ->label('Contraseña')
			    ->password()
			    ->revealable()
			    ->required(fn ($context) => $context === 'create')
			    ->dehydrated(fn ($state) => filled($state))
			    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
			    ->same('password_confirmation'),
		       TextInput::make('password_confirmation')
			    ->label('Confirmar contraseña')
			    ->password()
			    ->label('Confirmar contraseña')
			    ->dehydrated(false), 
		     ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
		Tables\Columns\TextColumn::make('name')
		    ->label('Nombre de Usuario')
		    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('info')
                    ->placeholder('General / Todos'),
	    	Tables\Columns\TextColumn::make('roles.name')
		    ->label('Rol')
		    ->badge('primary')
		    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
		    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
