<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Funciones que faltaban en la demo: nuevo proyecto, edición, revisión de propuestas, devoluciones, notificaciones y vista previa. */
class DemoCompletionTest extends TestCase
{
    private const LAURA = 1, MARCELA = 4, CARLOS = 5, DANIEL = 6;

    private function newProject(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Huertas escolares en Rivera', 'code' => 'CONV-030-2026', 'type' => 'extension', 'modality' => 'convenio',
            'entity_id' => 1, 'coordinator_id' => self::LAURA, 'start' => '2026-12-01', 'end' => '2027-09-30',
            'value' => 300_000_000, 'general_objective' => 'Implementar huertas escolares en las sedes rurales de Rivera.',
            'split' => ['Talento humano' => 42, 'Equipos' => 18, 'Materiales e insumos' => 12, 'Viajes y transporte' => 8,
                'Capacitaciones y eventos' => 10, 'Servicios técnicos' => 6, 'Gastos administrativos' => 4],
            'products' => [
                ['name' => 'Diagnóstico de sedes', 'type' => 'Documento', 'target' => 1, 'due' => '2027-02-15'],
                ['name' => '', 'type' => 'Capacitación', 'target' => '', 'due' => ''],
            ],
        ], $overrides);
    }

    public function test_vicerrectoria_creates_a_project(): void
    {
        $this->withSession(['demo_user' => self::MARCELA]);
        $this->get('/vicerrectoria/proyectos?modal=nuevo')->assertOk()->assertSee('Nuevo proyecto / contrato');

        $this->post('/vicerrectoria/proyectos', $this->newProject(['split' => ['Talento humano' => 50] + $this->newProject()['split']]))
            ->assertSessionHasErrors('split');
        $this->post('/vicerrectoria/proyectos', $this->newProject(['code' => 'CTO-045-2026']))->assertSessionHasErrors('code');

        $this->post('/vicerrectoria/proyectos', $this->newProject())
            ->assertSessionHasNoErrors()->assertRedirect('/vicerrectoria/proyectos/100');

        $this->get('/vicerrectoria/proyectos')->assertSee('Huertas escolares en Rivera');
        $this->get('/vicerrectoria/proyectos/100')->assertOk()->assertSee('Huertas escolares en Rivera')->assertSee('Preinversión');
        $this->get('/vicerrectoria/proyectos/100/presupuesto')->assertOk()->assertSee('$126.000.000'); // 42 % de Talento humano
        $this->get('/vicerrectoria/proyectos/100/objetivos')->assertOk()->assertSee('Diagnóstico de sedes');
        $this->get('/vicerrectoria/proyectos/100/documentos')->assertOk()->assertSee('0 de 4 completos');
        $this->get('/vicerrectoria/proyectos/100/trazabilidad')->assertOk()->assertSee('Registró el Convenio CONV-030-2026');

        // La coordinadora lo ve en su portal
        $this->withSession(['demo_user' => self::LAURA]);
        $this->get('/investigador')->assertOk()->assertSee('Huertas escolares en Rivera');
        $this->get('/investigador/proyectos/100')->assertOk();
    }

    public function test_vicerrectoria_edits_a_project(): void
    {
        $this->withSession(['demo_user' => self::MARCELA]);
        $this->get('/vicerrectoria/proyectos/2/presupuesto?modal=editar')->assertOk()->assertSee('Editar proyecto');

        $this->put('/vicerrectoria/proyectos/2', [
            'name' => 'Monitoreo agrícola con drones (fase II)', 'code' => 'CTO-045-2026', 'type' => 'extension', 'modality' => 'contrato',
            'entity_id' => 2, 'coordinator_id' => self::LAURA, 'general_objective' => 'Nuevo objetivo.', 'tab' => 'presupuesto',
        ])->assertSessionHasNoErrors()->assertRedirect('/vicerrectoria/proyectos/2/presupuesto');

        $this->get('/vicerrectoria/proyectos/2')->assertSee('Monitoreo agrícola con drones (fase II)');
        $this->get('/vicerrectoria/proyectos/2/trazabilidad')->assertSee('Editó los datos del proyecto')->assertSee('nombre y objetivo general');
        // Un código que ya usa otro proyecto se rechaza
        $this->put('/vicerrectoria/proyectos/2', ['code' => 'CONV-018-2026'] + ['name' => 'X', 'type' => 'extension', 'modality' => 'contrato',
            'entity_id' => 2, 'coordinator_id' => self::LAURA, 'general_objective' => 'Z'])->assertSessionHasErrors('code');
    }

    public function test_proposed_project_review_approve_and_reject(): void
    {
        $this->withSession(['demo_user' => self::MARCELA]);
        $this->get('/vicerrectoria/aprobaciones')->assertOk()->assertDontSee('Disponible en la siguiente fase');
        $this->get('/vicerrectoria/aprobaciones?revisar=9')->assertOk()->assertSee('Proyecto propuesto · PROP-001-2026')->assertSee('Aprobar proyecto');
        $this->get('/vicerrectoria/aprobaciones/exportar')->assertOk()->assertDownload('aprobaciones-pendientes-' . today()->format('Ymd') . '.csv');

        $this->post('/vicerrectoria/aprobaciones/proyecto/9/rechazar')->assertSessionHasErrors('reason');
        $this->post('/vicerrectoria/aprobaciones/proyecto/9/aprobar')->assertSessionHasNoErrors();
        $this->get('/vicerrectoria/proyectos')->assertSee('Diagnóstico TIC en colegios rurales');
        $this->get('/vicerrectoria/proyectos/9')->assertOk();
        $this->post('/vicerrectoria/aprobaciones/proyecto/9/aprobar')->assertStatus(409);
    }

    public function test_rejected_proposal_leaves_the_inbox(): void
    {
        $this->withSession(['demo_user' => self::MARCELA]);
        $this->post('/vicerrectoria/aprobaciones/proyecto/9/rechazar', ['reason' => 'No hay cupo presupuestal en 2027.'])->assertSessionHasNoErrors();
        $this->get('/vicerrectoria/aprobaciones?tipo=proyecto')->assertSee('No hay solicitudes pendientes de este tipo');
        $this->get('/vicerrectoria/proyectos/9')->assertNotFound();
    }

    public function test_purchase_returned_by_finance_goes_back_to_vicerrectoria(): void
    {
        $this->withSession(['demo_user' => self::CARLOS]);
        $this->post('/financiera/compras/SC-0230/devolver', ['reason' => 'Falta documentación soporte', 'notes' => 'Adjuntar la cotización firmada.'])
            ->assertSessionHasNoErrors();

        $this->withSession(['demo_user' => self::MARCELA]);
        $this->get('/vicerrectoria/aprobaciones?tipo=compra')->assertSee('SC-0230')->assertSee('Devuelta por el área financiera');
        $this->get('/vicerrectoria/compras?ver=SC-0230')->assertSee('El área financiera la devolvió: revísala de nuevo')->assertSee('Solicitar ajuste');
        $this->post('/vicerrectoria/aprobaciones/compra/2/aprobar', ['comment' => 'Ya se adjuntó'])->assertSessionHasNoErrors();

        $this->withSession(['demo_user' => self::CARLOS]);
        $this->get('/financiera/compras?ver=SC-0230')->assertSee('Te corresponde: expedir CDP');
    }

    public function test_notifications_panel_for_each_role(): void
    {
        $this->withSession(['demo_user' => self::MARCELA]);
        $this->get('/vicerrectoria')->assertSee('class="notif"', false)->assertSee('Nuevo avance por revisar')->assertSee('Marcar como leídas');
        $this->from('/vicerrectoria')->post('/notificaciones/leidas')->assertRedirect('/vicerrectoria');
        $this->get('/vicerrectoria')->assertDontSee('Marcar como leídas');

        $this->withSession(['demo_user' => self::CARLOS]);
        $this->get('/financiera')->assertSee('CDP por expedir')->assertSee('Ver todas las alertas');

        $this->withSession(['demo_user' => self::LAURA]);
        $this->get('/investigador')->assertSee('Recordatorio de avance');

        $this->withSession(['demo_user' => self::DANIEL]);
        $this->get('/administracion')->assertSee('Ver toda la auditoría');
    }

    public function test_document_preview(): void
    {
        Storage::fake();
        $this->withSession(['demo_user' => self::CARLOS]);
        $this->get('/financiera/documentos?ver=d1')->assertOk()->assertSee('Documento de ejemplo: no tiene archivo para descargar.');

        $this->withSession(['demo_user' => self::MARCELA]);
        $this->post('/vicerrectoria/proyectos/5/documentos', ['type' => 'Informe técnico', 'file' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $id = collect(\App\Support\DemoState::get('fin_docs_new'))->last()['id'];
        $this->get("/vicerrectoria/proyectos/5/documentos?ver={$id}")->assertOk()->assertSee('<iframe', false)->assertSee('Descargar PDF');
        $this->get("/documentos/{$id}/ver")->assertOk();

        // Gestión de residuos (5) la coordina Andrés: Laura no puede abrir sus archivos
        $this->withSession(['demo_user' => self::LAURA]);
        $this->get("/documentos/{$id}/descargar")->assertForbidden();
    }
}
