<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Stage;
use Exception;
use Illuminate\Support\Facades\Log;

class ApplicantActions
{
    public static function resetApplicant(Applicant $applicant): void
    {
        Log::info("Reiniciando el trámite para el alumno con ID {$applicant->id}.");
        $notificationService = new WhatsappApiNotificationService();

        $firstStage = Stage::orderBy('order')->first();

        if (is_null($firstStage)) {
            $message = "Lo sentimos, no se puede reiniciar el trámite. No se encontraron etapas configuradas.";
            $notificationService->sendCustomMessage($applicant, $message);
            return;
        }

        $firstQuestion = $firstStage->questions()->orderBy('order')->first();

        try {
            if ($applicant->conversation) {
                $applicant->conversation->messages()->delete();
            }
        } catch (Exception $e) {
            Log::error("Error al eliminar mensajes de conversación para el alumno ID {$applicant->id}: " . $e->getMessage());
        }

        $applicant->responses()->delete();

        $applicant->update([
            "current_stage_id" => $firstStage->id,
            "current_question_id" => $firstQuestion ? $firstQuestion->id : null,
            "process_status" => "in_progress",
            "rejection_reason" => null,
            "group_id" => null,
        ]);

        $resetMessage = "🔔 *TecBot - ITT*\nHola *{$applicant->applicant_name}*, tu trámite ha sido reiniciado a la etapa inicial (*{$firstStage->name}*). Puedes consultar el estado de tu proceso en cualquier momento por este medio.";
        $notificationService->sendCustomMessage($applicant, $resetMessage);
    }

    public static function approveStage(Applicant $applicant): void
    {
        Log::info("Avanzando etapa actual para el alumno con ID {$applicant->id}.");
        $notificationService = new WhatsappApiNotificationService();

        $currentStage = $applicant->currentStage;

        if (is_null($currentStage)) {
            return;
        }

        $nextStage = Stage::where("order", ">", $currentStage->order)
            ->orderBy('order')
            ->first();

        if (is_null($nextStage)) {
            $applicant->update([
                "process_status" => "staff_approved",
            ]);
            $notificationService->sendGroupSelectionLink($applicant);
            return;
        }

        $firstQuestion = $nextStage->questions()->orderBy('order')->first();

        $applicant->update([
            "current_stage_id" => $nextStage->id,
            "current_question_id" => $firstQuestion ? $firstQuestion->id : null,
            "process_status" => "in_progress",
        ]);

        $notificationService->sendProcessStatusNotification($applicant);
    }

    public static function reSendCurrentQuestion(Applicant $applicant): void
    {
        $notificationService = new WhatsappApiNotificationService();
        $notificationService->sendProcessStatusNotification($applicant);
    }

    public static function reSendGroupSelectionLink(Applicant $applicant): void
    {
        $notificationService = new WhatsappApiNotificationService();

        $applicant->update([
            "process_status" => "staff_approved",
            "group_id" => null,
        ]);

        $notificationService->sendGroupSelectionLink($applicant);
    }

    public static function approveApplicantFinal(Applicant $applicant): void
    {
        Log::info("Finalizando/aprobando trámite del alumno ID {$applicant->id}.");
        $notificationService = new WhatsappApiNotificationService();

        $lastStage = Stage::orderByDesc('order')->first();

        $applicant->update([
            "current_stage_id" => $lastStage ? $lastStage->id : $applicant->current_stage_id,
            "process_status" => "staff_approved",
            "group_id" => null,
        ]);

        $notificationService->sendGroupSelectionLink($applicant);
    }

    public static function rejectApplicant(Applicant $applicant, string $reason): void
    {
        Log::info("Registrando observación/rechazo para el alumno ID {$applicant->id}.");

        $applicant->update([
            "process_status" => "requires_revision",
            "rejection_reason" => $reason,
        ]);

        $notificationService = new WhatsappApiNotificationService();
        $notificationService->sendProcessStatusNotification($applicant);
    }

    public static function sendCustomMessage(Applicant $applicant, string $message): void
    {
        Log::info("Enviando mensaje personalizado al alumno ID {$applicant->id}.");
        $notificationService = new WhatsappApiNotificationService();
        $notificationService->sendCustomMessage($applicant, $message);
    }
}
