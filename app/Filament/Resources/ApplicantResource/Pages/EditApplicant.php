<?php

namespace App\Filament\Resources\ApplicantResource\Pages;

use App\Filament\Resources\ApplicantResource;
use App\Services\WhatsappApiNotificationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditApplicant extends EditRecord
{
    protected static string $resource = ApplicantResource::class;

    protected bool $shouldNotifyStudent = false;

    protected function beforeSave(): void
    {
        // Si cambia la etapa, el estado o las observaciones del trámite, notificamos automáticamente al alumno por WhatsApp
        $dirty = $this->record->getDirty();
        if (
            array_key_exists('process_status', $dirty) ||
            array_key_exists('current_stage_id', $dirty) ||
            array_key_exists('rejection_reason', $dirty)
        ) {
            $this->shouldNotifyStudent = true;
        }
    }

    protected function afterSave(): void
    {
        if ($this->shouldNotifyStudent) {
            app(WhatsappApiNotificationService::class)->sendProcessStatusNotification($this->record);

            Notification::make()
                ->title('Notificación automática enviada al alumno por WhatsApp')
                ->success()
                ->send();
        }
    }

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

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
