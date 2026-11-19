<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Document;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Stage;

class HomesofHopeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $deptEscolares = Department::firstOrCreate(
            ['name' => 'Servicios Escolares'],
            [
                'description' => 'Departamento encargado de constancias, credenciales, seguro facultativo, kardex y trámites escolares.',
                'is_active' => true,
            ]
        );

        $deptVinculacion = Department::firstOrCreate(
            ['name' => 'Gestión Tecnológica y Vinculación'],
            [
                'description' => 'Departamento encargado del Servicio Social, Residencias Profesionales y visitas industriales.',
                'is_active' => true,
            ]
        );

        $deptDivEstudios = Department::firstOrCreate(
            ['name' => 'División de Estudios Profesionales'],
            [
                'description' => 'Coordinaciones de carrera, reinscripciones, cargas académicas, traslados y titulación.',
                'is_active' => true,
            ]
        );

        $adminUser = User::factory()->create([
            'name' => 'Admin TecBot',
            'email' => 'admin@admin.com',
            'password' => bcrypt('admin'),
            'department_id' => $deptEscolares->id,
        ]);

        User::factory()->create([
            'name' => 'Mario Borda',
            'email' => 'mario.borda@ywamsdb.org',
            'password' => bcrypt('1q2w3e4r'),
            'department_id' => $deptVinculacion->id,
        ]);

        $mariaUser = User::factory()->create([
            'name' => 'María (Servicios Escolares)',
            'email' => 'maria@tijuana.tecnm.mx',
            'password' => bcrypt('admin'),
            'department_id' => $deptEscolares->id,
        ]);

        User::factory()->create([
            'name' => 'Coordinación Académica',
            'email' => 'coordinacion@tijuana.tecnm.mx',
            'password' => bcrypt('admin'),
            'department_id' => $deptDivEstudios->id,
        ]);

        User::factory()->create([
            'name' => 'Montserrat Gonzales',
            'email' => 'montserrat.gonzalez@ywamsdb.org',
            'password' => bcrypt('V8l4d2s0'),
            'department_id' => $deptEscolares->id,
        ]);

        $stage0 = Stage::create([
            'department_id' => $deptEscolares->id,
            'name' => 'Etapa 1: Recepción de Solicitud',
            'order' => 1,
            'approval_message' => 'Tu solicitud de trámite ha sido recibida y registrada en el sistema del ITT.',
            'rejection_message' => 'Tu solicitud presenta datos incompletos.',
            'requires_evaluatio_message' => 'El personal del departamento está revisando tu solicitud.',
        ]);
        
        $stage1 = Stage::create([
            'department_id' => $deptEscolares->id,
            'name' => 'Etapa 2: Revisión de Documentación',
            'order' => 2,
            'approval_message' => 'Tu documentación ha sido validada correctamente por el departamento.',
            'rejection_message' => 'Tienes documentos o requisitos pendientes por entregar.',
            'requires_evaluatio_message' => 'Tu trámite se encuentra en revisión documental por el departamento.',
        ]);
        
        $stage2 = Stage::create([
            'department_id' => $deptVinculacion->id,
            'name' => 'Etapa 3: Validación por Departamento',
            'order' => 3,
            'approval_message' => 'El departamento ha autorizado tu trámite. Ya estamos en la etapa final.',
        ]);
        
        $stage3 = Stage::create([
            'department_id' => $deptEscolares->id,
            'name' => 'Etapa 4: Trámite Finalizado',
            'order' => 4,
            'approval_message' => '¡Tu trámite en el Instituto Tecnológico de Tijuana ha concluido satisfactoriamente!',
        ]);

        // Documentos institucionales PDF de ejemplo con texto extraído para TecBot
        Document::create([
            'department_id' => $deptVinculacion->id,
            'user_id' => $adminUser->id,
            'title' => 'Guía Oficial de Servicio Social ITT',
            'description' => 'Requisitos, créditos mínimos, duración y documentos para iniciar y liberar el Servicio Social.',
            'extracted_text' => "INSTITUTO TECNOLÓGICO DE TIJUANA - GUÍA DE SERVICIO SOCIAL.\n"
                . "1. Requisitos para iniciar el Servicio Social:\n"
                . "- Haber aprobado al menos el 70% de los créditos de la retícula escolar (Kardex oficial).\n"
                . "- Asistir al curso de inducción de Servicio Social organizado por Gestión Tecnológica y Vinculación.\n"
                . "- Entregar Solicitud de Servicio Social, Carta Compromiso y Plan de Trabajo.\n"
                . "- Obtener y entregar la Carta de Aceptación membretada y sellada por la dependencia receptora.\n"
                . "2. Duración y Reportes:\n"
                . "- El Servicio Social tiene una duración obligatoria de 480 horas en un periodo mínimo de 6 meses y máximo de 2 años.\n"
                . "- Se deben entregar 3 reportes bimestrales y una evaluación final para obtener la Carta de Liberación.",
            'is_active' => true,
        ]);

        Document::create([
            'department_id' => $deptVinculacion->id,
            'user_id' => $adminUser->id,
            'title' => 'Lineamientos de Residencias Profesionales ITT',
            'description' => 'Requisitos y procedimiento para cursar y acreditar la Residencia Profesional.',
            'extracted_text' => "INSTITUTO TECNOLÓGICO DE TIJUANA - RESIDENCIAS PROFESIONALES.\n"
                . "1. Requisitos:\n"
                . "- Tener acreditado el Servicio Social y todas las Actividades Complementarias (5 créditos).\n"
                . "- Haber aprobado al menos el 80% de los créditos de su plan de estudios.\n"
                . "- No contar con ninguna asignatura en condición de Curso Especial.\n"
                . "2. Duración: 500 horas en un periodo de 4 a 6 meses.\n"
                . "3. Documentos requeridos: Anteproyecto de Residencia Profesional avalado por asesor interno, Carta de Presentación y Carta de Aceptación de la empresa.",
            'is_active' => true,
        ]);

        Document::create([
            'department_id' => $deptEscolares->id,
            'user_id' => $mariaUser->id,
            'title' => 'Trámites Frecuentes de Servicios Escolares ITT',
            'description' => 'Horarios de atención, emisión de constancias, reposición de credencial y seguro facultativo IMSS.',
            'extracted_text' => "SERVICIOS ESCOLARES - INSTITUTO TECNOLÓGICO DE TIJUANA.\n"
                . "- Horario de atención en ventanilla: Lunes a Viernes de 8:00 a 19:00 hrs (Unidad Tomás Aquino y Otay).\n"
                . "- Constancia de Estudios / Kardex sellado: Solicitar en el portal SIE o en ventanilla presentando recibo de pago de recursos financieros. Tiempo de entrega: 24 a 48 horas hábiles.\n"
                . "- Reposición de Credencial de Estudiante: Realizar pago en caja o transferencia referenciada y acudir al módulo de fotografía de Servicios Escolares con identificación.\n"
                . "- Seguro Facultativo (IMSS): Todos los estudiantes inscritos tienen derecho al seguro médico escolar. Deben generar su vigencia de derechos y entregar su Número de Seguridad Social (NSS).",
            'is_active' => true,
        ]);

        // Alumno de ejemplo descrito en Proyecto.md (Juan Pérez - Servicio Social)
        Applicant::create([
            'department_id' => $deptEscolares->id,
            'chat_id' => '5216641234567',
            'applicant_name' => 'Juan Pérez',
            'curp' => '21211234',
            'process_type' => 'Servicio Social',
            'gender' => 'man',
            'current_stage_id' => $stage1->id,
            'assigned_user_id' => $mariaUser->id,
            'process_status' => 'requires_revision',
            'rejection_reason' => 'Documentación pendiente: Falta completar y entregar el requisito "Carta de aceptación".',
        ]);

        Applicant::create([
            'department_id' => $deptVinculacion->id,
            'chat_id' => '5216642345678',
            'applicant_name' => 'Ana Sofía Martínez',
            'curp' => '21211890',
            'process_type' => 'Residencia Profesional',
            'gender' => 'woman',
            'current_stage_id' => $stage2->id,
            'assigned_user_id' => $adminUser->id,
            'process_status' => 'in_progress',
            'rejection_reason' => null,
        ]);

        Applicant::create([
            'department_id' => $deptDivEstudios->id,
            'chat_id' => '5216643456789',
            'applicant_name' => 'Carlos Eduardo Ramírez',
            'curp' => '20210455',
            'process_type' => 'Liberación de Titulación Integral',
            'gender' => 'man',
            'current_stage_id' => $stage3->id,
            'assigned_user_id' => $adminUser->id,
            'process_status' => 'staff_approved',
            'rejection_reason' => 'Trámite liberado. Puedes pasar a Servicios Escolares por tu constancia.',
        ]);

        $stage0->questions()->createMany([
            [
                'question_text' => 'Para comenzar, ¿me confirma que usted es la persona que desea aplicar para una casa?',
                'order' => 1,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is_not',
                        'value' => 'la persona que quiere aplicar'
                    ],
                ],
            ],
            [
                'question_text' => 'Al aceptar este documento, usted otorga su consentimiento a “Juventud Con Una Misión (JUCUM) A.C.” para el uso y divulgación de la información aquí proporcionada, declarando bajo protesta de decir verdad que los datos son correctos. Asimismo, en caso de recibir la ayuda solicitada, usted deslinda a dicha asociación de toda responsabilidad civil, laboral o penal, tanto durante como después de la construcción, por cualquier incidente que pudiera afectar la integridad física o personal propia, de terceros y/o por pérdidas materiales. ¿Está de acuerdo?',
                'order' => 2,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is',
                        'value' => 'no esta de acuerdo'
                    ]
                ]
            ]
        ]);

        $stage1->questions()->createMany([
            [
                'question_text' => 'Perfecto, ahora cuéntame, ¿tienes hijos menores de 16 años que vivan actualmente contigo?',
                'order' => 1,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is_less_than',
                        'value' => '1 hijo'
                    ],
                    [
                        'rule' => 'human_if',
                        'operator' => 'is',
                        'value' => 'personas menores pero no hijos'
                    ]
                ],
            ],
            [
                'question_text' => 'Gracias. ¿En qué ciudad está ubicado tu terreno?',
                'order' => 2,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'does_not_contain',
                        'value' => 'Tijuana o Rosarito'
                    ],
                ],
            ],
            [
                'question_text' => '¿Y en qué colonia se encuentra exactamente tu terreno?',
                'order' => 3,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'contains',
                        'value' => '10 de Mayo, La Presa, Cañón Cordero3 de Octubre, Lago Sur, Constitucion,Altiplano, Las Abejas, Fracc. Vista Azul,Alamar, Las Alondras, La Mision,Aleman, Las Fuentes, Lucio Blanco,Altamira, Las Torres, Misión del Mar 1ra. y 2da. Secc,Amparo Sanchez, Leandro Valle, Poliducto,Anabel, Libertad, Riviera San Carlos ,Anexa del Rio, Linda Vista, Angeles del Pacifico, Loma Bonita, Azcona, Lomas de la Presa, Azteca, Lomas de Tlatelolco, Bonilla , Lomas del Encinal, Buena Vista, Lomas del Matamoros, Buenos Aires  , Lomas del Refugio, Camino Verde, Lomas del Valle, Campestre Murua, Lomas Taurinas, Campos, Lomas Virreyes, Cañadas del Florido, Lopez Leyva, Cañon de la Pedrera, Los Altos, Cañon de la Raza, Los Venados, Cañon del Padre, Maclovio Rojas, Cañón del Sainz, Madero Sur, Cañon el Salado, Manantial, Cañon Miramar, Marbella, Cañon Palmas, Mariano Matamoros, Castillo Centro, Mexico, Cerro Colorado, Milenio 2000, Chapultepec, Morelos, Chihuahua, Nido de las Aguilas, Chosa, Niños Heroes, Ciudad Jardin, Nueva Aurora, Colinas de Baja California, Nueva Aurora, Colinas de Elyahu, Nueva Tijuana, Colinas de la Mesa, Nuevo Milenio, Colinas de la Presa, Obrera, Colonia del Rio, Ojo de Agua, Corona del Mar, Orizaba, Costa Dorada, Osuna Millan, Cuesta Blanca, Otay, Del Rio, Panteon, Delicias 1, 2, 3, Paseos del Florido, Durango, Pedregal de Santa Julia, Ejido Francisco Villa, Planicie, Ejido Javier Rojo Gómez, Poblado Ejido Matamoros, Ejido Matamoros, Pontevedra, El Dorado, Porticos de San Antonio, El Encino, Praderas de la Mesa, El Florido (1, 2,3, y 4 secció), Presa Rodriguez, El Lago, Presidentes , El Laurel, Puerta del Sol, El Niño, Rancho 3 Piedras, El Pipila, Rancho el Encinal, El Ranchito, Real de San Francisco, El Refugio, Reforma, El Rosario, Ribera del Bosque, El Rubi, Rio Vista, El Tecolote, Roma, El Valle, Rubio, Emiliano Zapata, San Angel, Emperadores, San Luis, Estrella del Pacifico, San Pablo, Fausto Gonzales, Sanchez Taboada, Flores Magon, Soler, Fraccionamiento Valle Dorado, Tecnologico, Francisco Villa, Terrazas de San Antonio, Generacion 2000, Terrazas Del Valle, Granjas Buenos Aires, Tomas Aquino, Granjas Division del Norte, Urbivillas del Prado , Granjas Familiares del Matamoros, Valle Bonito, Granjas Familiares la Nueva Esperanza de Otay, Valle de las Palmas, Granjas Familiares Unidas, Valle Imperial, Granjas Princesas del Sol, Valle Verde, Hacienda las Delicias, Valle Vista, Lomas del Encinal, Venustiano Carranza, Milenio 2000, Verona Residencial, Nueva Aurora , Villa del Alamo, Nuevo Milenio, Villa Floresta, Osuna Millan, Villa Fontana, Pedregal de Santa Julia, Villa Urrutia, Porticos de San Antonio, Villas de Baja California, San Angel, Villas del campo, Terrazas de San Antonio, Villas del Campo, Valle Imperial, Villas del Sol, Valle Verde, Viñedos Casa Blanca, Venustiano Carranza, Vista Alamar, Villas del campo, Vista de Palmillas, Granjas Princesas del Sol, Vista del Valle, Guaycura, Xochimilco Solidaridad, Guerrero, Zona Centro, Hacienda las Delicias, Zona Norte, La Cuestecita, Zone Este, La Esperanza, La Libertad, La Morita'
                    ]
                ]
            ],
            [
                'question_text' => '¿Cuánto tiempo llevas con este terreno?',
                'order' => 4,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is_less_than',
                        'value' => '1 año o 12 meses'
                    ],
                ],
            ],
            [
                'question_text' => '¿En qué mes y año diste la primera mensualidad de tu terreno?',
                'order' => 5,
            ],
            [
                'question_text' => '¿Cuál es tu estado civil?',
                'order' => 6,
            ],
            [
                'question_text' => '¿A nombre de quién(es) está el contrato?',
                'order' => 7,
                'approval_criteria' => [
                    [
                        'rule' => 'approve_if',
                        'operator' => 'is',
                        'value' => 'estan casados y esta al nombre de cualquier padre'
                    ],
                    [
                        'rule' => 'approve_if',
                        'operator' => 'is',
                        'value' => 'estan en union libre y esta al nombre de la mamá'
                    ],
                    [
                        'rule' => 'approve_if',
                        'operator' => 'is',
                        'value' => 'estan en union libre y esta al nombre del padre y es padre de uno de los hijos'
                    ],
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is',
                        'value' => 'estan separados y esta a nombre del esposo o esposa que abandono el hogar'
                    ],
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is',
                        'value' => 'esta a nombre de una tercera persona que esta incluida en el contrato'
                    ],
                ],
            ],
            [
                'question_text' => '¿Cuál fue el costo total del terreno?',
                'order' => 8,
            ],
            [
                'question_text' => '¿Cuánto diste de enganche por el terreno?',
                'order' => 9,
            ],
            [
                'question_text' => '¿Cuánto pagas de mensualidad por el terreno?',
                'order' => 10,
            ],
            [
                'question_text' => '¿El precio es en Pesos Mexicanos o Dolares?',
                'order' => 11,
            ],
            [
                'question_text' => '¿En qué fecha diste la última mensualidad del terreno?',
                'order' => 12,
                'approval_criteria' => [
                    [
                        'rule' => 'human_if',
                        'operator' => 'is_before',
                        'value' => 'han pasado mas de 3 meses'
                    ],
                ],
            ],
            [
                'question_text' => '¿Estás al corriente en tus mensualidades del terreno?',
                'order' => 13,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is',
                        'value' => 'no esta al corriente'
                    ],
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is',
                        'value' => 'debe mas de 3 pagos'
                    ],
                ],
            ],
            [
                'question_text' => 'Por último en esta sección, ¿Vives en tu terreno o en la misma colonia?',
                'order' => 14,
                'approval_criteria' => [
                    [
                        'rule' => 'reject_if',
                        'operator' => 'is',
                        'value' => 'la respuesta es no'
                    ]
                ]
            ],
        ]);

        $stage2->questions()->createMany([
            [
                'question_text' => 'Ahora hablemos de tus hijos. ¿Asisten a la escuela actualmente?',
                'order' => 1,
            ],
            [
                'question_text' => '¿En qué grado está cada uno de ellos?',
                'order' => 2,
            ],
            [
                'question_text' => '¿Alguno de ellos tiene alguna necesidad especial?',
                'order' => 3,
            ],
        ]);

        $stage3->questions()->createMany([
            [
                'question_text' => 'Pasemos a tu información personal. ¿Me puedes decir tu nombre, por favor?',
                'order' => 1,
            ],
            [
                'question_text' => '¿Cuál es tu edad?',
                'order' => 2,
            ],
            [
                'question_text' => '¿En que estado y país naciste?',
                'order' => 3,
            ],
            [
                'question_text' => '¿Hasta qué grado estuviste en la escuela?',
                'order' => 4,
            ],
            [
                'question_text' => '¿En qué trabajas actualmente?',
                'order' => 5,
            ],
            [
                'question_text' => '¿Cuánto ganas a la semana?',
                'order' => 6,
            ],
            [
                'question_text' => '¿Estás esperando un bebé? de ser así , ¿cuántos meses tienes?',
                'order' => 7,
            ],
            [
                'question_text' => '¿Tienes visa para los Estados Unidos?',
                'order' => 8,
            ],
            [
                'question_text' => '¿Recientemente, fuiste deportado de los Estados Unidos?',
                'order' => 9,
            ],

            [
                'question_text' => '¿Tienes pareja?, ¿cuál es su nombre?',
                'order' => 10,
            ],
            [
                'question_text' => '¿Cuál es su edad?',
                'order' => 11,
            ],
            [
                'question_text' => '¿Dónde nació (estado y país)?',
                'order' => 12,
            ],
            [
                'question_text' => '¿Cuál es el nivel de estudios de tu pareja?',
                'order' => 13,
            ],
            [
                'question_text' => '¿Trabaja actualmente, en qué?',
                'order' => 14,
            ],
            [
                'question_text' => '¿Cuánto gana a la semana?',
                'order' => 15,
            ],
            [
                'question_text' => '¿Tiene visa para los Estados Unidos?',
                'order' => 16,
            ],
            [
                'question_text' => 'A tu pareja, ¿la han deportado recientemente de los Estados Unidos?',
                'order' => 17,
            ],
            [
                'question_text' => 'Me puedes dar el nombre y fecha de nacimiento de cada uno de tus hijos',
                'order' => 18,
            ],
            [
                'question_text' => '¿En que país nacieron tus hijos?',
                'order' => 19,
            ],
            [
                'question_text' => '¿Tus hijos estudian? Si es así, ¿en qué grado está cada uno?',
                'order' => 20,
            ],
            [
                'question_text' => '¿Tus hijos son residentes o tienen visa para los Estados Unidos?',
                'order' => 21,
            ],

            [
                'question_text' => 'Si se les llega a construir, ¿quiénes más vivirían en la casa de esperanza? Por favor, dame sus nombres, edades y relación con ustedes',
                'order' => 22,
            ],
            [
                'question_text' => '¿Existen problemas de adicción en la familia? Si es así, ¿cuáles?',
                'order' => 23,
            ],
            [
                'question_text' => '¿Alguien en la familia habla algún dialecto?, ¿cuál?',
                'order' => 24,
            ],

            [
                'question_text' => 'Por último, ¿puedes enviar las coordenadas del terreno? Aquí hay un enlace de cómo hacerlo: https://ejemplo.com/',
                'order' => 25,
            ],
        ]);
    }
}
