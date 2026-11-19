<?php

namespace App\Filament\Resources\ApplicantResource\Pages;

use App\Filament\Resources\ApplicantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewApplicant extends ViewRecord
{
    protected static string $resource = ApplicantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('goToWhatsapp')
                ->label('WhatsApp Directo')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->url(function () {
                    $number = $this->record->chat_id;
                    $text = "Hola {$this->record->applicant_name}, te contactamos del Instituto Tecnológico de Tijuana respecto a tu trámite.";
                    $encodedMessage = urlencode($text);

                    return "https://wa.me/{$number}?text={$encodedMessage}";
                })
                ->openUrlInNewTab(),

            Actions\EditAction::make(),
        ];
    }
}
