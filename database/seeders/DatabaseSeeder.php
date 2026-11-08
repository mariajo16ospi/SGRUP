<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\BudgetTransfer;
use App\Models\CurvePoint;
use App\Models\MonthlyProgress;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de demostración tomados de los mockups de SGRUP (app/mockups).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Último mes con ejecución reportada. */
    private Carbon $lastReportedMonth;

    public function run(): void
    {
        $this->lastReportedMonth = Carbon::create(2026, 9, 1);

        $u = [];
        foreach ([
            ['laura', 'Laura Méndez', 'laura.mendez@fet.edu.co', 'investigador', 'Ejecuta el proyecto o contrato: carga el avance mensual, registra gastos, solicita compras y sube documentos.'],
            ['andres', 'Andrés Perdomo', 'andres.perdomo@fet.edu.co', 'investigador', null],
            ['paola', 'Paola Cuéllar', 'paola.cuellar@fet.edu.co', 'investigador', null],
            ['marcela', 'Dra. Marcela Trujillo', 'vicerrectoria.investigacion@fet.edu.co', 'vicerrectoria', 'Aprueba compras, avances mensuales, traslados y proyectos nuevos; hace seguimiento a todos los proyectos.'],
            ['carlos', 'Carlos Rojas', 'financiera@fet.edu.co', 'financiero', 'Expide CDP, valida compras, registra pagos y controla el presupuesto.'],
            ['daniel', 'Daniel Rojas', 'daniel_rojasar@fet.edu.co', 'admin', 'Gestiona usuarios, roles, parámetros del sistema y auditoría.'],
        ] as [$key, $name, $email, $role, $desc]) {
            $u[$key] = User::create([
                'name' => $name, 'email' => $email, 'role' => $role,
                'role_description' => $desc, 'password' => 'sgrup2026',
            ]);
        }

        $p = [];
        foreach ([
            ['tic', 'CONV-018-2026', 'Fortalecimiento TIC en zona rural', 'extension', 'convenio', 'Alcaldía de Neiva', 'laura', '2026-01-15', '2026-12-15', 980_000_000, 811_860_000, 74, 'inversion', 'activo', 'en_tiempo', 'Mejorar la conectividad y las competencias digitales de las sedes educativas rurales de Neiva.'],
            ['drones', 'CTO-045-2026', 'Monitoreo agrícola con drones', 'extension', 'contrato', 'Gobernación del Huila', 'laura', '2026-02-01', '2027-01-31', 1_250_000_000, 898_640_000, 44, 'inversion', 'activo', 'sobrecosto', 'Implementar un sistema de monitoreo de cultivos de café y cacao con tecnología aérea.'],
            ['energia', 'INV-007-2026', 'Laboratorio de energías renovables', 'investigacion', 'recursos_propios', 'FET – Recursos propios', 'andres', '2026-06-01', '2027-06-30', 420_000_000, 37_800_000, 25, 'inversion', 'activo', 'en_tiempo', 'Diseñar y poner en marcha un laboratorio de energías renovables para docencia e investigación aplicada.'],
            ['emprende', 'CONV-009-2026', 'Capacitación en emprendimiento juvenil', 'extension', 'convenio', 'Cámara de Comercio del Huila', 'laura', '2026-01-10', '2026-11-30', 180_000_000, 129_600_000, 90, 'operacion', 'activo', 'en_tiempo', 'Formar a jóvenes del Huila en la creación y validación de modelos de negocio.'],
            ['residuos', 'CONV-022-2026', 'Gestión integral de residuos sólidos', 'extension', 'convenio', 'Alcaldía de Pitalito', 'andres', '2026-03-01', '2027-02-28', 640_000_000, 345_600_000, 49, 'inversion', 'activo', 'sobrecosto', 'Formular e implementar el plan de gestión integral de residuos sólidos del municipio.'],
            ['tatacoa', 'CTO-031-2026', 'Turismo sostenible Desierto de la Tatacoa', 'extension', 'contrato', 'Asociación Hotelera del Huila', 'paola', '2026-08-01', '2026-11-15', 310_000_000, 108_500_000, 13, 'inversion', 'activo', 'retraso', 'Diseñar rutas de turismo sostenible y fortalecer a los prestadores de servicios del desierto.'],
            ['colegios', 'CONV-011-2025', 'Diagnóstico TIC en colegios públicos', 'extension', 'convenio', 'Secretaría de Educación del Huila', 'andres', '2025-02-01', '2025-11-30', 260_000_000, 213_200_000, 100, 'operacion', 'liquidado', 'en_tiempo', 'Diagnosticar la infraestructura y apropiación TIC de los colegios públicos del departamento.'],
            ['riego', 'INV-003-2025', 'Prototipo de riego inteligente IoT', 'investigacion', 'convocatoria', 'Minciencias', 'laura', '2025-03-01', '2025-12-15', 150_000_000, 123_000_000, 100, 'operacion', 'liquidado', 'en_tiempo', 'Desarrollar un prototipo de riego automatizado con sensores IoT para pequeños productores.'],
            ['garzon', 'PROP-001-2026', 'Diagnóstico TIC en colegios rurales', 'extension', 'convenio', 'Alcaldía de Garzón', 'paola', '2027-01-15', '2027-12-15', 260_000_000, 0, 0, 'preinversion', 'propuesto', 'en_tiempo', 'Diagnosticar la conectividad y el uso de TIC en los colegios rurales de Garzón.'],
        ] as [$key, $code, $name, $type, $mod, $entity, $coord, $start, $end, $value, $exec, $tech, $phase, $status, $health, $obj]) {
            $s = Carbon::parse($start);
            $e = Carbon::parse($end);
            $days = $s->diffInDays($e);
            $p[$key] = Project::create([
                'code' => $code, 'name' => $name, 'type' => $type, 'modality' => $mod, 'entity' => $entity,
                'coordinator_id' => $u[$coord]->id, 'start_date' => $s, 'end_date' => $e,
                'preinvestment_end' => $s->copy()->addDays((int) round($days * 0.15)),
                'investment_end' => $s->copy()->addDays((int) round($days * 0.83)),
                'value' => $value, 'executed_amount' => $exec, 'technical_progress' => $tech,
                'phase' => $phase, 'status' => $status, 'health' => $health, 'general_objective' => $obj,
            ]);
        }
        // Fechas de fase exactas del mockup VIC·04
        $p['drones']->update(['preinvestment_end' => '2026-03-28', 'investment_end' => '2026-11-28']);

        // Rubros
        $lines = [];
        foreach ($p as $key => $project) {
            $split = $key === 'drones'
                ? [['Personal', 520, 400], ['Equipos', 320, 300], ['Viáticos', 90, 60], ['Materiales e insumos', 180, 88.64], ['Servicios técnicos', 140, 50]]
                : null;
            if (! $split) {
                $v = $project->value / 1_000_000;
                $ratio = $project->value > 0 ? $project->executed_amount / $project->value : 0;
                $split = collect([['Personal', .45], ['Equipos', .25], ['Viáticos', .08], ['Materiales e insumos', .12], ['Servicios técnicos', .10]])
                    ->map(fn ($r) => [$r[0], round($v * $r[1], 2), round($v * $r[1] * $ratio, 2)])->all();
            }
            foreach ($split as [$name, $budget, $executed]) {
                $lines[$key][$name] = $project->budgetLines()->create([
                    'name' => $name, 'budgeted' => $budget * 1_000_000, 'executed' => $executed * 1_000_000,
                ]);
            }
        }

        // Curva S
        $this->curve($p['drones'], [1, 2, 7, 15, 30, 50, 69, 84.04, 94, 98, 99.6, 100], [1, 2, 7, 14, 27, 44, 59, 71.89]);
        foreach (['tic', 'energia', 'emprende', 'residuos', 'tatacoa', 'colegios', 'riego'] as $key) {
            $this->curve($p[$key]);
        }

        $ago = fn (int $d) => now()->subDays($d);

        // Compras
        foreach ([
            ['SC-0231', 'drones', 'Equipos', 'laura', 'Dron multiespectral DJI Mavic 3M', 25_000_000, 20_000_000, 'en_vicerrectoria', 19],
            ['SC-0230', 'drones', 'Materiales e insumos', 'laura', 'Kit de sensores de humedad de suelo (x20)', 4_800_000, null, 'en_financiera', 22],
            ['SC-0228', 'emprende', 'Materiales e insumos', 'laura', 'Refrigerios taller de emprendimiento (200 personas)', 1_200_000, null, 'aprobada', 30],
            ['SC-0225', 'energia', 'Equipos', 'andres', 'Paneles solares 3 kW con inversor', 18_500_000, null, 'en_financiera', 25],
            ['SC-0234', 'tatacoa', 'Servicios técnicos', 'paola', 'Alquiler de auditorio para talleres', 3_500_000, null, 'en_vicerrectoria', 17],
            ['SC-0236', 'residuos', 'Equipos', 'andres', 'Licencia ArcGIS Pro (1 año)', 3_800_000, null, 'en_vicerrectoria', 4],
        ] as [$code, $proj, $line, $req, $desc, $value, $orig, $status, $days]) {
            PurchaseRequest::create([
                'code' => $code, 'project_id' => $p[$proj]->id, 'budget_line_id' => $lines[$proj][$line]->id,
                'requester_id' => $u[$req]->id, 'description' => $desc, 'value' => $value,
                'original_value' => $orig, 'status' => $status, 'submitted_at' => $ago($days),
            ]);
        }

        // Avances mensuales pendientes de revisión
        foreach ([
            ['drones', 'laura', 44, 6, 4, 156_580_000, 35],
            ['tic', 'laura', 74, 8, 6, 88_400_000, 9],
        ] as [$proj, $by, $tech, $delta, $supports, $amount, $days]) {
            MonthlyProgress::create([
                'project_id' => $p[$proj]->id, 'submitted_by' => $u[$by]->id, 'period' => '2026-09-01',
                'technical_progress' => $tech, 'technical_delta' => $delta, 'supports_count' => $supports,
                'amount' => $amount, 'status' => 'pendiente', 'submitted_at' => $ago($days),
            ]);
        }
        // Andrés Perdomo aparece como solicitante del avance de TIC en el mockup VIC·01
        MonthlyProgress::where('project_id', $p['tic']->id)->update(['submitted_by' => $u['andres']->id]);

        BudgetTransfer::create([
            'code' => 'TP-0012', 'project_id' => $p['drones']->id,
            'from_line_id' => $lines['drones']['Viáticos']->id, 'to_line_id' => $lines['drones']['Equipos']->id,
            'requester_id' => $u['laura']->id, 'amount' => 5_000_000,
            'reason' => 'Para cubrir el sobrecosto de SC-0231', 'status' => 'pendiente', 'submitted_at' => $ago(12),
        ]);
        // La solicitud del proyecto nuevo tiene 2 días
        $p['garzon']->update(['created_at' => $ago(2)]);

        // Alertas: 19 activas, 10 críticas, 5 requieren gestión de Vicerrectoría
        foreach ([
            ['drones', 'critica', 'sobrecosto', 'Sobrecosto en compra SC-0231', 'Dron multiespectral DJI Mavic 3M: pasó de $20M a $25M (+25%)', true],
            ['drones', 'critica', 'presupuesto', 'Presupuesto insuficiente para SC-0231', 'Rubro Equipos: disponible $20.000.000, solicitado $25.000.000', true],
            ['drones', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Adquisición de dron multiespectral (0%) · venció el 30 may 2026', false],
            ['drones', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Capacitación a productores (83%) · venció el 30 ago 2026', false],
            ['drones', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Visitas técnicas a fincas (83%) · venció el 30 sep 2026', false],
            ['drones', 'advertencia', 'rezago', 'Avance técnico rezagado', 'Monitoreo agrícola con drones: 44% de avance con 72% del tiempo transcurrido', false],
            ['drones', 'advertencia', 'ejecucion', 'Ejecución financiera mayor al avance técnico', 'Monitoreo agrícola con drones: 72% ejecutado vs. 44% técnico', false],
            ['residuos', 'critica', 'sobrecosto', 'Sobrecosto en rubro Servicios técnicos', 'Gestión integral de residuos sólidos: ejecución 54% vs. 49% técnico', false],
            ['residuos', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Caracterización de residuos (70%) · venció el 15 sep 2026', false],
            ['tatacoa', 'critica', 'rezago', 'Proyecto con retraso', 'Turismo sostenible Desierto de la Tatacoa: 13% de avance con 79% del tiempo transcurrido', true],
            ['tatacoa', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Inventario de atractivos turísticos (40%) · venció el 30 sep 2026', false],
            ['tatacoa', 'advertencia', 'ejecucion', 'Ejecución financiera mayor al avance técnico', 'Turismo sostenible Desierto de la Tatacoa: 35% ejecutado vs. 13% técnico', false],
            ['tatacoa', 'advertencia', 'vencimiento', 'Contrato próximo a vencer', 'CTO-031-2026 finaliza el 15 nov 2026', true],
            ['residuos', 'advertencia', 'ejecucion', 'Ejecución financiera mayor al avance técnico', 'Gestión integral de residuos sólidos: 54% ejecutado vs. 49% técnico', false],
            ['tic', 'advertencia', 'ejecucion', 'Ejecución financiera mayor al avance técnico', 'Fortalecimiento TIC en zona rural: 83% ejecutado vs. 74% técnico', false],
            ['tic', 'advertencia', 'avance', 'Avance mensual pendiente de revisión', 'Avance de septiembre 2026 enviado por Andrés Perdomo', true],
            ['energia', 'advertencia', 'rezago', 'Avance técnico rezagado', 'Laboratorio de energías renovables: 25% de avance con 32% del tiempo transcurrido', false],
            ['energia', 'advertencia', 'ejecucion', 'Compra pendiente en área financiera', 'SC-0225 Paneles solares 3 kW con inversor espera CDP', false],
            ['emprende', 'advertencia', 'vencimiento', 'Proyecto próximo a finalizar', 'CONV-009-2026 finaliza el 30 nov 2026: preparar informe final', false],
        ] as $i => [$proj, $sev, $kind, $title, $detail, $action]) {
            Alert::create([
                'project_id' => $p[$proj]->id, 'severity' => $sev, 'kind' => $kind, 'title' => $title,
                'detail' => $detail, 'requires_action' => $action,
                'created_at' => now()->subMinutes(count($p) * 10 - $i),
            ]);
        }
    }

    /**
     * Genera los puntos mensuales de la Curva S.
     * $planned y $executed son porcentajes acumulados del valor del proyecto; si se omiten
     * se usa una curva logística y la ejecución se escala hasta executed_amount.
     */
    private function curve(Project $project, ?array $planned = null, ?array $executed = null): void
    {
        $months = [];
        for ($m = $project->start_date->copy()->startOfMonth(); $m <= $project->end_date; $m->addMonth()) {
            $months[] = $m->copy();
        }
        $n = count($months);
        $logistic = function (float $x) {
            $l = fn ($t) => 1 / (1 + exp(-8 * ($t - 0.5)));

            return ($l($x) - $l(0)) / ($l(1) - $l(0)) * 100;
        };
        $planned ??= array_map(fn ($i) => $logistic(($i + 1) / $n), range(0, $n - 1));

        if ($executed === null) {
            $reported = $project->status === 'liquidado'
                ? $n
                : count(array_filter($months, fn ($m) => $m <= $this->lastReportedMonth));
            $last = $planned[max(0, $reported - 1)] ?: 1;
            $target = $project->executed_amount / $project->value * 100;
            $executed = array_map(fn ($i) => $planned[$i] / $last * $target, range(0, $reported - 1));
        }

        foreach ($months as $i => $month) {
            CurvePoint::create([
                'project_id' => $project->id,
                'month' => $month,
                'planned_cumulative' => round($project->value * $planned[$i] / 100, 2),
                'executed_cumulative' => isset($executed[$i]) ? round($project->value * $executed[$i] / 100, 2) : null,
            ]);
        }
    }
}
