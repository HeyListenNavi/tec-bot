<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class WhatsappApiNotificationService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $instance;
    protected string $templateName;
    protected string $templateLang;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('services.whatsapp.url', ''), '/');
        $this->apiKey = (string) config('services.whatsapp.key', '');
        $this->instance = (string) config('services.whatsapp.instance', 'tecbot');
        $this->templateName = (string) env('WHATSAPP_TEMPLATE_APPROVED', '');
        $this->templateLang = (string) env('WHATSAPP_TEMPLATE_LANG', 'es');
    }

    /**
     * Envía una notificación automática por WhatsApp (vía Evolution API) cuando el estado, etapa u observaciones del trámite de un alumno cambian.
     */
    public function sendProcessStatusNotification(Applicant $applicant, ?string $customMessage = null): bool
    {
        $applicant->loadMissing(['currentStage', 'department']);

        if ($customMessage) {
            $message = $customMessage;
        } else {
            $statusLabel = match ($applicant->process_status) {
                'in_progress' => 'En Proceso',
                'approved', 'staff_approved' => 'Aprobado / Finalizado',
                'rejected', 'staff_rejected' => 'Con Observaciones / Rechazado',
                'requires_revision' => 'Documentación o Acción Pendiente',
                'canceled' => 'Cancelado',
                default => $applicant->process_status,
            };

            $processName = $applicant->process_type ?: 'Trámite Académico/Administrativo';
            $stageName = $applicant->currentStage?->name ?: 'Revisión';
            $deptName = $applicant->department?->name ? " ({$applicant->department->name})" : '';

            $message = "🔔 *Actualización de Trámite - TecBot ITT*\n\n";
            $message .= "Hola *{$applicant->applicant_name}*, hay una actualización en tu proceso:\n";
            $message .= "• *Trámite:* {$processName}{$deptName}\n";
            $message .= "• *Etapa actual:* {$stageName}\n";
            $message .= "• *Estado:* {$statusLabel}\n";

            if (!empty($applicant->rejection_reason)) {
                $message .= "• *Observaciones / Acción requerida:* {$applicant->rejection_reason}\n";
            }

            $message .= "\nSi tienes dudas sobre los requisitos o pasos a seguir, puedes responder a este mensaje.";
        }

        return $this->sendCustomMessage($applicant, $message);
    }

    public function sendGroupSelectionLink(Applicant $applicant)
    {
        $message = "🔔 *TecBot - Instituto Tecnológico de Tijuana*\n\n";
        $message .= "¡Felicidades, *{$applicant->applicant_name}*! Tu trámite (" . ($applicant->process_type ?: 'Proceso Institucional') . ") ha sido *Aprobado / Finalizado* satisfactoriamente. 🎉";

        if (!empty($applicant->rejection_reason)) {
            $message .= "\n\n*Indicaciones finales:* {$applicant->rejection_reason}";
        }

        return $this->sendCustomMessage($applicant, $message, 'enviar_link_de_entrevista');
    }

    public function sendCurrentQuestion(Applicant $applicant)
    {
        $currentQuestion = $applicant->currentQuestion;

        if (!$currentQuestion) {
            return $this->sendProcessStatusNotification($applicant);
        }

        $message = $currentQuestion->question_text;

        return $this->sendCustomMessage($applicant, $message, 'enviar_pregunta', ['pregunta' => $message]);
    }

    public function sendSuccessInfo(Applicant $applicant)
    {
        if (!$applicant->group) {
            return $this->sendProcessStatusNotification($applicant);
        }

        $message = "Felicidades! La cita fue registrada con éxito.\n" .
            "Tu cita es el día: " . $applicant->group->date_time->toDateString() . "\n" .
            "A las: " . $applicant->group->date_time->toTimeString() . "\n" .
            "Con dirección: " . $applicant->group->location . "\n";

        return $this->sendCustomMessage($applicant, $message, 'enviar_informacion_de_entrevista');
    }

    public function sendCustomMessage(Applicant $applicant, string $message, ?string $templateName = null, array $parameters = []): bool
    {
        if (!$applicant->conversation) {
            $applicant->conversation()->create([
                'user_name' => $applicant->applicant_name,
            ]);
            $applicant->load('conversation');
        }

        Message::create([
            'conversation_id' => $applicant->conversation->id,
            'phone' => $applicant->chat_id,
            'message' => $message,
            'role' => 'assistant',
            'name' => $applicant->applicant_name,
        ]);

        // Con Evolution API podemos enviar texto directo sin límite de 24h de plantillas de Meta
        return $this->sendText($applicant->chat_id, $message);
    }

    protected function sendText(string $recipientId, string $message): bool
    {
        if (empty($this->apiUrl)) {
            Log::warning("WHATSAPP_URL no configurada. Mensaje registrado en BD para {$recipientId}.");
            return false;
        }

        try {
            $cleanNumber = str_replace('@s.whatsapp.net', '', $recipientId);

            // Soporte para Evolution API (/message/sendText/{instance})
            $url = str_contains($this->apiUrl, 'graph.facebook.com')
                ? "{$this->apiUrl}/messages"
                : "{$this->apiUrl}/message/sendText/{$this->instance}";

            if (str_contains($this->apiUrl, 'graph.facebook.com')) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'type' => 'text',
                    'to' => $cleanNumber,
                    'text' => ['body' => $message],
                ]);
            } else {
                $response = Http::withHeaders([
                    'apikey' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($url, [
                    'number' => $cleanNumber,
                    'text' => $message,
                ]);
            }

            if ($response->successful()) {
                Log::info("Mensaje de texto enviado vía Evolution API a {$cleanNumber}.");
                return true;
            }

            Log::error("Error al enviar mensaje a {$cleanNumber}: status " . $response->status());
            return false;
        } catch (\Exception $e) {
            Log::critical("Excepción al enviar mensaje con Evolution API: " . $e->getMessage());
            return false;
        }
    }

    public function sendTemplate(Applicant $applicant, ?string $templateName = null, array $parameters = [])
    {
        return $this->sendCustomMessage($applicant, '[NOTIFICACIÓN] ' . ($templateName ?? 'Actualización'));
    }
}
