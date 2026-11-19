<?php

use App\Models\Applicant;
use App\Models\Department;
use App\Models\Document;
use App\Models\Stage;
use App\Models\User;
use App\Services\WhatsappApiNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('se pueden crear departamentos y documentos institucionales PDF para TecBot', function () {
    $dept = Department::create([
        'name' => 'Servicios Escolares',
        'description' => 'Trámites escolares ITT',
        'is_active' => true,
    ]);

    $doc = Document::create([
        'department_id' => $dept->id,
        'title' => 'Guía de Servicio Social ITT',
        'description' => 'Requisitos de Servicio Social',
        'extracted_text' => 'Para iniciar el Servicio Social en el ITT se requiere el 70% de créditos aprobados y Carta de Aceptación.',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/bot/documents');

    $response->assertStatus(200)
        ->assertJsonPath('count', 1)
        ->assertJsonPath('documents.0.title', 'Guía de Servicio Social ITT')
        ->assertJsonPath('documents.0.department', 'Servicios Escolares');
});

test('el endpoint applicant-status devuelve el estado del trámite, matrícula, etapa y observaciones del alumno', function () {
    $dept = Department::create([
        'name' => 'Servicios Escolares',
        'description' => 'Atención escolar',
        'is_active' => true,
    ]);

    $stage = Stage::create([
        'department_id' => $dept->id,
        'name' => 'Documentación Pendiente',
        'order' => 2,
    ]);

    Applicant::create([
        'department_id' => $dept->id,
        'chat_id' => '5216641234567',
        'applicant_name' => 'Juan Pérez',
        'curp' => '21211234',
        'process_type' => 'Servicio Social',
        'current_stage_id' => $stage->id,
        'process_status' => 'requires_revision',
        'rejection_reason' => 'Falta entregar Carta de Aceptación.',
    ]);

    $response = $this->getJson('/api/bot/applicants/applicant-status/5216641234567@s.whatsapp.net');

    $response->assertStatus(200)
        ->assertJsonPath('applicant_name', 'Juan Pérez')
        ->assertJsonPath('matricula', '21211234')
        ->assertJsonPath('process_type', 'Servicio Social')
        ->assertJsonPath('department', 'Servicios Escolares')
        ->assertJsonPath('current_stage_name', 'Documentación Pendiente')
        ->assertJsonPath('status', 'requires_revision')
        ->assertJsonPath('observations', 'Falta entregar Carta de Aceptación.');
});

test('el endpoint lookup permite buscar y vincular un alumno por su matrícula desde WhatsApp', function () {
    $dept = Department::create(['name' => 'Gestión Tecnológica y Vinculación']);
    $stage = Stage::create(['name' => 'En Validación', 'order' => 1]);

    Applicant::create([
        'department_id' => $dept->id,
        'chat_id' => '5216640000000',
        'applicant_name' => 'Ana López',
        'curp' => '22210999',
        'process_type' => 'Residencia Profesional',
        'current_stage_id' => $stage->id,
        'process_status' => 'in_progress',
    ]);

    $response = $this->postJson('/api/bot/applicants/lookup', [
        'matricula' => '22210999',
        'chat_id' => '5216649876543@s.whatsapp.net',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('found', true)
        ->assertJsonPath('applicant_name', 'Ana López')
        ->assertJsonPath('process_type', 'Residencia Profesional');

    $this->assertDatabaseHas('applicants', [
        'curp' => '22210999',
        'chat_id' => '5216649876543',
    ]);
});

test('WhatsappApiNotificationService envía notificaciones automáticas vía Evolution API cuando cambia el estado del trámite', function () {
    config([
        'services.whatsapp.url' => 'http://evolution-api.local:8080',
        'services.whatsapp.key' => 'test-evolution-key',
        'services.whatsapp.instance' => 'tecbot',
    ]);

    Http::fake([
        'http://evolution-api.local:8080/message/sendText/tecbot' => Http::response(['status' => 'PENDING'], 201),
    ]);

    $dept = Department::create(['name' => 'Servicios Escolares']);
    $stage = Stage::create(['name' => 'Revisión Documental', 'order' => 1]);

    $applicant = Applicant::create([
        'department_id' => $dept->id,
        'chat_id' => '5216641234567',
        'applicant_name' => 'Juan Pérez',
        'curp' => '21211234',
        'process_type' => 'Servicio Social',
        'current_stage_id' => $stage->id,
        'process_status' => 'requires_revision',
        'rejection_reason' => 'Carta de aceptación pendiente.',
    ]);

    $service = new WhatsappApiNotificationService();
    $sent = $service->sendProcessStatusNotification($applicant);

    expect($sent)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'http://evolution-api.local:8080/message/sendText/tecbot'
            && $request->hasHeader('apikey', 'test-evolution-key')
            && $request['number'] === '5216641234567'
            && str_contains($request['text'], 'Servicio Social')
            && str_contains($request['text'], 'Carta de aceptación pendiente.');
    });
});

test('el archivo Homes of Hope.json de n8n es un JSON válido y está configurado con NVIDIA NIM y Evolution API', function () {
    $jsonContent = file_get_contents(base_path('Homes of Hope.json'));
    $data = json_decode($jsonContent, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toContain('TecBot')
        ->and($jsonContent)->toContain('https://integrate.api.nvidia.com/v1')
        ->and($jsonContent)->toContain('/message/sendText/')
        ->and($jsonContent)->toContain('/documents');
});
