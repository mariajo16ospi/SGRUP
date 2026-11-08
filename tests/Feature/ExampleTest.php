<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_login_page_loads(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_vicerrectoria_screens_load(): void
    {
        $this->withSession(['demo_user' => 4]); // Dra. Marcela Trujillo

        $this->get('/vicerrectoria')->assertOk()->assertSee('Panel general');
        $this->get('/vicerrectoria/aprobaciones')->assertOk()->assertSee('Bandeja de aprobaciones');
        $this->get('/vicerrectoria/proyectos')->assertOk()->assertSee('Proyectos y contratos');
        $this->get('/vicerrectoria/proyectos/2')->assertOk()->assertSee('Monitoreo agrícola con drones');
    }

    public function test_vicerrectoria_new_screens_load(): void
    {
        $this->withSession(['demo_user' => 4]);

        $this->get('/vicerrectoria/proyectos/2/presupuesto')->assertOk()->assertSee('$259.960.000')->assertSee('Traslado presupuestal');
        $this->get('/vicerrectoria/proyectos/2/presupuesto?modal=traslado')->assertOk()->assertSee('Aprobar traslado');
        $this->get('/vicerrectoria/proyectos/2/objetivos')->assertOk()->assertSee('Cumplimiento de metas')->assertSee('44%')->assertSee('Plataforma web de monitoreo');
        $this->get('/vicerrectoria/proyectos/2/avances')->assertOk()->assertSee('Historial de avances')->assertSee('$199.320.000')->assertSee('$5.340.000');
        $this->get('/vicerrectoria/proyectos/2/avances?revisar=1')->assertOk()->assertSee('Revisar avance · septiembre 2026');
        $this->get('/vicerrectoria/proyectos/2/compras')->assertOk()->assertSee('Todas (3)');
        $this->get('/vicerrectoria/proyectos/2/documentos')->assertOk()->assertSee('5 documentos')->assertSee('2 de 4 completos');
        $this->get('/vicerrectoria/proyectos/2/trazabilidad')->assertOk()->assertSee('Creó la solicitud de compra SC-0231');
        $this->get('/vicerrectoria/proyectos/7/certificacion')->assertOk()->assertSee('Certificación de experiencia');
        $this->get('/vicerrectoria/proyectos/2/certificacion')->assertStatus(409);
        $this->get('/vicerrectoria/cronograma')->assertOk()->assertSee('Cronograma de proyectos');
        $this->get('/vicerrectoria/cronograma?liquidados=1')->assertOk()->assertSee('Prototipo de riego inteligente IoT');
        $this->get('/vicerrectoria/seguimiento')->assertOk()->assertSee('Todos (25)')->assertSee('Entregados (7)');
        $this->get('/vicerrectoria/compras')->assertOk()->assertSee('Gestión de compras')->assertSee('Presupuesto insuficiente');
        $this->get('/vicerrectoria/compras?ver=SC-0231&modal=rechazar')->assertOk()->assertSee('Esta acción es definitiva');
        $this->get('/vicerrectoria/reportes')->assertOk()->assertSee('$4.190M');
        $this->get('/vicerrectoria/reportes/informe')->assertOk()->assertSee('Informe de gestión de proyectos');
        foreach (['consolidado', 'rubros', 'compras', 'experiencia'] as $r) {
            $this->get("/vicerrectoria/reportes/{$r}")->assertOk();
        }
        $this->get('/vicerrectoria/alertas')->assertOk()->assertSee('Todas (19)');
    }

    public function test_vicerrectoria_approval_reaches_finance(): void
    {
        $this->withSession(['demo_user' => 4]);
        // SC-0231 excede el rubro: se aprueba un traslado y luego la compra
        $this->post('/vicerrectoria/aprobaciones/compra/1/aprobar')->assertSessionHasErrors('approval');
        $this->post('/vicerrectoria/proyectos/2/presupuesto/traslados', ['from' => 'Talento humano', 'to' => 'Equipos', 'amount' => 5_000_000])->assertSessionHasNoErrors();
        $this->post('/vicerrectoria/aprobaciones/compra/1/aprobar', ['comment' => 'Proceder'])->assertSessionHasNoErrors();
        $this->post('/vicerrectoria/compras/SC-0234/decidir', ['decision' => 'ajuste', 'reason' => 'Faltan cotizaciones o soportes', 'notes' => 'Adjuntar 2 cotizaciones'])->assertSessionHasNoErrors();
        $this->post('/vicerrectoria/proyectos/2/avances/1', ['decision' => 'devolver'])->assertSessionHasErrors('comment');
        $this->post('/vicerrectoria/proyectos/2/avances/1', ['decision' => 'aprobar'])->assertSessionHasNoErrors();
        $this->post('/vicerrectoria/proyectos/2/objetivos', ['name' => 'Manual de uso', 'type' => 'Documento', 'target' => 1, 'unit' => 'und.', 'due' => '2026-12-20'])->assertSessionHasNoErrors();
        $this->get('/vicerrectoria/proyectos/2/objetivos')->assertSee('Manual de uso');
        $this->get('/vicerrectoria/proyectos/2/trazabilidad')->assertSee('Aprobó el avance de septiembre 2026');

        // El Área financiera ve la compra aprobada y puede expedir el CDP
        $this->withSession(['demo_user' => 5]);
        $this->get('/financiera/compras?ver=SC-0231')->assertSee('Te corresponde: expedir CDP');
        $this->get('/financiera/compras?ver=SC-0234')->assertSee('Requiere ajuste');
    }

    public function test_investigator_screens(): void
    {
        $this->withSession(['demo_user' => 1]); // Laura Méndez

        $this->get('/login')->assertRedirect('/investigador');
        $this->get('/investigador')->assertOk()->assertSee('Hola, Laura')->assertSee('Mis pendientes')->assertSee('Cargar el avance de octubre');
        foreach (['', '/objetivos', '/presupuesto', '/avances', '/compras', '/documentos', '/trazabilidad'] as $tab) {
            $this->get("/investigador/proyectos/2{$tab}")->assertOk()->assertSee('Monitoreo agrícola con drones');
        }
        $this->get('/investigador/proyectos/2/avances?cargar=1')->assertOk()->assertSee('Cargar avance mensual')->assertSee('octubre 2026');
        $this->get('/investigador/proyectos/2/objetivos?actualizar=7')->assertOk()->assertSee('Actualizar avance del producto');
        $this->get('/investigador/proyectos/2/documentos')->assertOk()->assertDontSee('>Validar<', false);
        $this->get('/investigador/compras')->assertOk()->assertSee('Mis solicitudes de compra');
        $this->get('/investigador/compras?modal=nueva')->assertOk()->assertSee('Validación automática de presupuesto');
        $this->get('/investigador/documentos')->assertOk()->assertSee('Gestión documental')->assertDontSee('Turismo sostenible');
        $this->get('/investigador/cronograma')->assertOk()->assertDontSee('Gestión integral de residuos');
        $this->get('/investigador/alertas')->assertOk()->assertSee('Centro de alertas')->assertDontSee('Puntos ecológicos');
        $this->get('/investigador/cargar-avance')->assertRedirect();
        // Proyecto de otra persona (residuos, Andrés Perdomo) y pantallas de otros roles
        $this->get('/investigador/proyectos/5')->assertForbidden();
        $this->get('/financiera')->assertForbidden();
    }

    public function test_investigator_flow_with_vicerrectoria(): void
    {
        $this->withSession(['demo_user' => 1]);

        // Nueva solicitud: llega a la bandeja de Vicerrectoría
        $this->post('/investigador/compras', ['project_id' => 1, 'description' => 'Router satelital', 'original_value' => 3_000_000, 'line' => 'Equipos', 'value' => 3_500_000, 'justification' => 'Conectividad de puntos digitales'])
            ->assertSessionHasNoErrors();
        $this->post('/investigador/compras', ['project_id' => 5, 'description' => 'X', 'line' => 'Equipos', 'value' => 100_000, 'justification' => 'X'])->assertForbidden();

        // Carga el avance de octubre de drones y actualiza un producto
        $this->post('/investigador/proyectos/2/avances', ['period' => '2026-10-01', 'products' => [7 => 120], 'expenses' => [['line' => 'Talento humano', 'concept' => 'Honorarios', 'value' => 50_000_000]], 'notes' => 'Se completaron las capacitaciones.'])
            ->assertSessionHasNoErrors();
        $this->get('/investigador/proyectos/2/avances')->assertSee('Por revisar');
        $this->put('/investigador/proyectos/2/objetivos/10', ['done' => 40])->assertSessionHasNoErrors();
        $this->get('/investigador/proyectos/2/objetivos')->assertSee('40 / 40 visitas');

        $this->withSession(['demo_user' => 4]);
        $this->get('/vicerrectoria/aprobaciones')->assertSee('SC-0238 · Router satelital')->assertSee('Avance de octubre 2026');
        $this->post('/vicerrectoria/compras/SC-0238/decidir', ['decision' => 'ajuste', 'reason' => 'Faltan cotizaciones o soportes', 'notes' => 'Adjunta otra cotización'])->assertSessionHasNoErrors();

        // El investigador ve la devolución, la edita y la reenvía
        $this->withSession(['demo_user' => 1]);
        $this->get('/investigador')->assertSee('Ajustar la solicitud SC-0238');
        $this->put('/investigador/compras/SC-0238', ['description' => 'Router satelital', 'original_value' => 3_000_000, 'value' => 3_200_000, 'justification' => 'Se adjunta segunda cotización'])->assertSessionHasNoErrors();
        $this->get('/investigador/compras?ver=SC-0238')->assertSee('$3.200.000')->assertSee('En Vicerrectoría');
        $this->post('/investigador/compras/SC-0238/retirar')->assertSessionHasNoErrors();
        $this->get('/investigador/compras?ver=SC-0238')->assertSee('Retirada');
    }

    public function test_other_roles_cannot_open_vicerrectoria(): void
    {
        $this->withSession(['demo_user' => 1])->get('/vicerrectoria')->assertForbidden();
    }

    public function test_login_shows_one_user_per_role(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Laura Méndez')->assertSee('Dra. Marcela Trujillo')->assertSee('Carlos Rojas')->assertSee('Daniel Rojas')
            ->assertDontSee('Andrés Perdomo')->assertDontSee('Paola Cuéllar');
    }

    public function test_admin_screens(): void
    {
        $this->withSession(['demo_user' => 6]); // Daniel Rojas

        $this->get('/administracion')->assertOk()->assertSee('Matriz de permisos por rol')->assertSee('3 usuarios');
        $this->get('/administracion?modal=nuevo')->assertOk()->assertSee('Nuevo usuario');
        $this->get('/administracion/parametros')->assertOk()->assertSee('8 rubros activos')->assertSee('R-08');
        $this->get('/administracion/auditoria')->assertOk()->assertSee('Últimas 10 acciones');
        $this->get('/administracion/auditoria?modulo=Compras')->assertOk()->assertSee('Últimas 3 acciones');
        $this->get('/administracion/auditoria/exportar')->assertOk();
    }

    public function test_admin_creates_user_and_item(): void
    {
        $this->withSession(['demo_user' => 6]);

        $this->post('/administracion/usuarios', ['name' => 'Ana Ruiz', 'email' => 'ana@gmail.com', 'role' => 'financiero'])
            ->assertSessionHasErrors('email');
        $this->post('/administracion/usuarios', ['name' => 'Ana Ruiz', 'email' => 'ana.ruiz@fet.edu.co', 'role' => 'financiero', 'active' => '1'])
            ->assertSessionHasNoErrors();
        $this->get('/administracion')->assertSee('ana.ruiz@fet.edu.co')->assertSee('2 usuarios');

        $this->post('/administracion/parametros/rubros', ['code' => 'R-09', 'name' => 'Software y licencias', 'cdp' => '1', 'active' => '1'])
            ->assertSessionHasNoErrors();
        $this->get('/administracion/parametros')->assertSee('9 rubros activos');
        $this->get('/administracion/auditoria')->assertSee('Creó rubro');

        $this->post('/administracion/parametros/reglas/sobrecosto')->assertRedirect();
        $this->get('/administracion/parametros')->assertSee('aria-checked="false"', false);
    }

    public function test_finance_screens(): void
    {
        $this->withSession(['demo_user' => 5]); // Carlos Rojas

        $this->get('/financiera')->assertOk()->assertSee('Control financiero')->assertSee('$8.600.000 aprobados')->assertSee('$269.700.000 por girar');
        $this->get('/financiera/compras')->assertOk()->assertSee('Validación de compras')->assertSee('SC-0230');
        $this->get('/financiera/compras?ver=SC-0230&modal=cdp')->assertOk()->assertSee('Expedir CDP · SC-0230');
        $this->get('/financiera/presupuesto')->assertOk()->assertSee('$3.780M')->assertSee('$2.332M')->assertSee('$2.789M');
        $this->get('/financiera/proyectos/2')->assertOk()->assertSee('$259.960.000')->assertSee('$351.360.000');
        $this->get('/financiera/proyectos/2?modal=traslado')->assertOk()->assertSee('Solicitar traslado');
        $this->get('/financiera/documentos')->assertOk()->assertSee('Gestión documental')->assertSee('Mostrando 8 de 41');
        $this->get('/financiera/alertas')->assertOk()->assertSee('Todas (19)')->assertSee('Críticas (10)')->assertSee('Advertencias (8)');
        $this->get('/financiera/pagos/CUOTA-3')->assertOk()->assertSee('Registrar pago · Cuota 3');
        $this->get('/financiera/documentos/paquete-rup')->assertOk();
    }

    public function test_finance_cdp_and_payment_flow(): void
    {
        $this->withSession(['demo_user' => 5]);

        $this->post('/financiera/compras/SC-0230/cdp', ['cdp_number' => 'CDP-2026-0418', 'cdp_date' => '2026-11-03', 'source' => 'Contrato CTO-045-2026'])
            ->assertSessionHasNoErrors();
        $this->get('/financiera/compras?ver=SC-0230')->assertSee('Registrar pago');

        $this->post('/financiera/pagos/SC-0230', ['supplier' => 'Agro S.A.S.', 'amount' => 9_000_000, 'date' => '2026-11-03', 'method' => 'Transferencia bancaria', 'voucher' => 'CE-1'])
            ->assertSessionHasErrors('amount');
        $this->post('/financiera/pagos/SC-0230', ['supplier' => 'Agro S.A.S.', 'amount' => 4_800_000, 'date' => '2026-11-03', 'method' => 'Transferencia bancaria', 'voucher' => 'CE-1'])
            ->assertSessionHasNoErrors();
        // Materiales e insumos de drones: 128.580.000 ejecutado + 4.800.000 pagado
        $this->get('/financiera/proyectos/2')->assertSee('$133.380.000');

        $this->post('/financiera/proyectos/2/traslados', ['from' => 'Equipos', 'to' => 'Equipos', 'amount' => 1_000_000])->assertSessionHasErrors('to');
        $this->post('/financiera/proyectos/2/traslados', ['from' => 'Talento humano', 'to' => 'Equipos', 'amount' => 5_000_000])->assertSessionHasNoErrors();
        $this->get('/financiera/proyectos/2')->assertSee('TP-0013');

        $this->post('/financiera/documentos/d5/validar')->assertRedirect();
    }

    public function test_purchase_blocked_until_budget_transfer(): void
    {
        $this->withSession(['demo_user' => 4]);

        $this->post('/vicerrectoria/aprobaciones/compra/1/aprobar')->assertSessionHasErrors('approval');
        $this->post('/vicerrectoria/aprobaciones/traslado/12/aprobar')->assertSessionHasNoErrors();
        $this->post('/vicerrectoria/aprobaciones/compra/1/aprobar')->assertSessionHasNoErrors();
    }
}
