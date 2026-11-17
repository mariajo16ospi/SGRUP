<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Catálogo de entidades contratantes y aliadas. */
class EntityTest extends TestCase
{
    private const LAURA = 1, MARCELA = 4, CARLOS = 5;

    private function entity(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alcaldía de Rivera', 'nit' => '891.180.111-2', 'type' => 'publica', 'city' => 'Rivera',
            'legal_rep' => 'Ana Torres', 'contact_email' => 'planeacion@rivera.gov.co', 'active' => 1,
        ], $overrides);
    }

    public function test_catalog_lists_the_example_entities(): void
    {
        $this->withSession(['demo_user' => self::MARCELA]);
        $this->get('/entidades')->assertOk()->assertSee('Entidades contratantes y aliadas')->assertSee('Gobernación del Huila');
        $this->get('/entidades?ver=2')->assertOk()->assertSee('Documentos obligatorios · 3 de 5')->assertSee('Monitoreo agrícola con drones');
        $this->get('/entidades?filtro=completas')->assertSee('Alcaldía de Neiva')->assertDontSee('Asociación Hotelera del Huila');

        // Financiera también entra; el investigador no
        $this->withSession(['demo_user' => self::CARLOS]);
        $this->get('/entidades')->assertOk();
        $this->withSession(['demo_user' => self::LAURA]);
        $this->get('/entidades')->assertForbidden();
    }

    public function test_create_entity_upload_rut_and_use_it_in_a_project(): void
    {
        Storage::fake();
        $this->withSession(['demo_user' => self::MARCELA]);

        $this->post('/entidades', $this->entity(['nit' => '800.100.002-2']))->assertSessionHasErrors('nit'); // NIT de la Gobernación
        $this->post('/entidades', $this->entity(['nit' => 'abc']))->assertSessionHasErrors('nit');
        $this->post('/entidades', $this->entity())->assertSessionHasNoErrors()->assertRedirect('/entidades?ver=101');

        $this->get('/entidades?ver=101')->assertSee('Alcaldía de Rivera')->assertSee('0 de 5');
        $this->post('/entidades/101/documentos', ['type' => 'RUT', 'file' => UploadedFile::fake()->create('rut.pdf', 80, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->get('/entidades?ver=101')->assertSee('1 de 5')->assertSee('rut.pdf');

        // Aparece en el buscador del nuevo proyecto y se puede elegir
        $this->get('/vicerrectoria/proyectos?modal=nuevo')->assertSee('Alcaldía de Rivera · NIT 891.180.111-2');
        $this->post('/vicerrectoria/proyectos', [
            'name' => 'Parques biosaludables', 'code' => 'CONV-031-2026', 'type' => 'extension', 'modality' => 'convenio', 'entity_id' => 101,
            'coordinator_id' => self::LAURA, 'start' => '2026-12-01', 'end' => '2027-06-30', 'value' => 120_000_000, 'general_objective' => 'Objetivo.',
            'split' => ['Talento humano' => 40, 'Equipos' => 20, 'Materiales e insumos' => 12, 'Viajes y transporte' => 8,
                'Capacitaciones y eventos' => 10, 'Servicios técnicos' => 6, 'Gastos administrativos' => 4],
        ])->assertSessionHasNoErrors();
        $this->get('/vicerrectoria/proyectos/100')->assertSee('Alcaldía de Rivera');

        // Una entidad que no está en el catálogo no se acepta
        $this->post('/vicerrectoria/proyectos', ['entity_id' => 999])->assertSessionHasErrors('entity_id');
    }

    public function test_renaming_an_entity_updates_its_projects(): void
    {
        $this->withSession(['demo_user' => self::MARCELA]);
        $this->put('/entidades/2', $this->entity(['name' => 'Departamento del Huila', 'nit' => '800.100.002-2', 'city' => 'Neiva']))
            ->assertSessionHasNoErrors();
        $this->get('/vicerrectoria/proyectos/2')->assertSee('Departamento del Huila')->assertDontSee('Gobernación del Huila');

        // Inactiva: ya no se ofrece para proyectos nuevos
        $this->put('/entidades/9', $this->entity(['name' => 'Alcaldía de Garzón', 'nit' => '800.100.009-9', 'city' => 'Garzón', 'active' => 0]))
            ->assertSessionHasNoErrors();
        $this->get('/vicerrectoria/proyectos?modal=nuevo')->assertDontSee('Alcaldía de Garzón · NIT');
    }
}
