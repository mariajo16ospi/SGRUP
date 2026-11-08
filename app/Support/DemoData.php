<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\BudgetLine;
use App\Models\BudgetTransfer;
use App\Models\CurvePoint;
use App\Models\MonthlyProgress;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos de demostración en memoria (sin base de datos), tomados de los mockups
 * de app/mockups. Se usan modelos Eloquent sin guardar para que, cuando se
 * conecte PostgreSQL, las vistas no tengan que cambiar.
 */
class DemoData
{
    private static ?self $instance = null;

    public Collection $users;
    public Collection $projects;
    public Collection $purchases;
    public Collection $progress;
    public Collection $transfers;
    public Collection $alerts;
    public Collection $products;

    /** Último mes con ejecución reportada. */
    private Carbon $lastReportedMonth;

    public static function get(): self
    {
        return self::$instance ??= new self;
    }

    public static function project(int|string $id): Project
    {
        return self::get()->projects->firstWhere('id', (int) $id) ?? abort(404);
    }

    public static function user(?int $id): ?User
    {
        return $id ? self::get()->users->firstWhere('id', $id) : null;
    }

    private function __construct()
    {
        $this->lastReportedMonth = Carbon::create(2026, 9, 1);
        $this->buildUsers();
        $this->buildProjects();
        $this->buildRequests();
        $this->buildAlerts();
        $this->buildProducts();
    }

    private function make(string $class, array $attrs, int $id): mixed
    {
        $m = new $class;
        $m->forceFill(['id' => $id] + $attrs);
        $m->exists = true;
        $m->syncOriginal();

        return $m;
    }

    private function buildUsers(): void
    {
        $this->users = collect([
            ['laura', 'Laura Méndez', 'laura.mendez@fet.edu.co', 'investigador', 'Grupo de investigación GITEC'],
            ['andres', 'Andrés Perdomo', 'andres.perdomo@fet.edu.co', 'investigador', 'Ingeniería Eléctrica'],
            ['paola', 'Paola Cuéllar', 'paola.cuellar@fet.edu.co', 'investigador', 'Administración Turística'],
            ['marcela', 'Dra. Marcela Trujillo', 'vicerrectoria.investigacion@fet.edu.co', 'vicerrectoria', 'Vicerrectoría de Investigación y Extensión'],
            ['carlos', 'Carlos Rojas', 'financiera@fet.edu.co', 'financiero', 'Dirección Financiera'],
            ['daniel', 'Daniel Rojas', 'daniel_rojasar@fet.edu.co', 'admin', 'Oficina TIC'],
        ])->mapWithKeys(fn ($u, $i) => [$u[0] => $this->make(User::class, [
            'name' => $u[1], 'email' => $u[2], 'role' => $u[3], 'department' => $u[4],
            'role_description' => User::ROLE_DESCRIPTIONS[$u[3]], 'active' => true,
        ], $i + 1)]);
    }

    private function buildProjects(): void
    {
        $rows = [
            ['tic', 'CONV-018-2026', 'Fortalecimiento TIC en zona rural', 'extension', 'convenio', 'Alcaldía de Neiva', 'laura', '2026-01-15', '2026-12-15', 980_000_000, 811_660_000, 74, 'inversion', 'activo', 'en_tiempo', 'Mejorar la conectividad y las competencias digitales de las sedes educativas rurales de Neiva.'],
            ['drones', 'CTO-045-2026', 'Monitoreo agrícola con drones', 'extension', 'contrato', 'Gobernación del Huila', 'laura', '2026-02-01', '2027-01-31', 1_250_000_000, 898_640_000, 44, 'inversion', 'activo', 'sobrecosto', 'Implementar un sistema de monitoreo de cultivos de café y cacao con tecnología aérea.'],
            ['energia', 'INV-007-2026', 'Laboratorio de energías renovables', 'investigacion', 'recursos_propios', 'FET – Recursos propios', 'laura', '2026-06-01', '2027-06-30', 420_000_000, 39_650_000, 25, 'inversion', 'activo', 'en_tiempo', 'Diseñar y poner en marcha un laboratorio de energías renovables para docencia e investigación aplicada.'],
            ['emprende', 'CONV-009-2026', 'Capacitación en emprendimiento juvenil', 'extension', 'convenio', 'Cámara de Comercio del Huila', 'laura', '2026-01-10', '2026-11-30', 180_000_000, 129_090_000, 90, 'operacion', 'activo', 'en_tiempo', 'Formar a jóvenes del Huila en la creación y validación de modelos de negocio.'],
            ['residuos', 'CONV-022-2026', 'Gestión integral de residuos sólidos', 'extension', 'convenio', 'Alcaldía de Pitalito', 'andres', '2026-03-01', '2027-02-28', 640_000_000, 345_190_000, 49, 'inversion', 'activo', 'sobrecosto', 'Formular e implementar el plan de gestión integral de residuos sólidos del municipio.'],
            ['tatacoa', 'CTO-031-2026', 'Turismo sostenible Desierto de la Tatacoa', 'extension', 'contrato', 'Asociación Hotelera del Huila', 'paola', '2026-08-01', '2026-11-15', 310_000_000, 108_030_000, 13, 'inversion', 'activo', 'retraso', 'Diseñar rutas de turismo sostenible y fortalecer a los prestadores de servicios del desierto.'],
            ['colegios', 'CONV-011-2025', 'Diagnóstico TIC en colegios públicos', 'extension', 'convenio', 'Secretaría de Educación del Huila', 'andres', '2025-02-01', '2025-11-30', 260_000_000, 213_200_000, 100, 'operacion', 'liquidado', 'en_tiempo', 'Diagnosticar la infraestructura y apropiación TIC de los colegios públicos del departamento.'],
            ['riego', 'INV-003-2025', 'Prototipo de riego inteligente IoT', 'investigacion', 'convocatoria', 'Minciencias', 'laura', '2025-03-01', '2025-12-15', 150_000_000, 123_000_000, 100, 'operacion', 'liquidado', 'en_tiempo', 'Desarrollar un prototipo de riego automatizado con sensores IoT para pequeños productores.'],
            ['garzon', 'PROP-001-2026', 'Diagnóstico TIC en colegios rurales', 'extension', 'convenio', 'Alcaldía de Garzón', 'paola', '2027-01-15', '2027-12-15', 260_000_000, 0, 0, 'preinversion', 'propuesto', 'en_tiempo', 'Diagnosticar la conectividad y el uso de TIC en los colegios rurales de Garzón.'],
        ];

        $this->projects = collect();
        $lineId = 1;
        foreach ($rows as $i => [$key, $code, $name, $type, $mod, $entity, $coord, $start, $end, $value, $exec, $tech, $phase, $status, $health, $obj]) {
            $s = Carbon::parse($start);
            $e = Carbon::parse($end);
            $days = $s->diffInDays($e);
            $p = $this->make(Project::class, [
                'code' => $code, 'name' => $name, 'type' => $type, 'modality' => $mod, 'entity' => $entity,
                'coordinator_id' => $this->users[$coord]->id, 'start_date' => $s, 'end_date' => $e,
                'preinvestment_end' => $key === 'drones' ? Carbon::parse('2026-03-28') : $s->copy()->addDays((int) round($days * 0.15)),
                'investment_end' => $key === 'drones' ? Carbon::parse('2026-11-28') : $s->copy()->addDays((int) round($days * 0.83)),
                'value' => $value, 'executed_amount' => $exec, 'technical_progress' => $tech,
                'phase' => $phase, 'status' => $status, 'health' => $health, 'general_objective' => $obj,
                'created_at' => $key === 'garzon' ? now()->subDays(2) : Carbon::parse($start),
            ], $i + 1);
            $p->setRelation('coordinator', $this->users[$coord]);

            // Rubros (mockups FIN 03 y FIN 04)
            $split = $this->budgetSplit($key, $status, $value, $exec);
            $lines = collect();
            foreach ($split as [$lineName, $budget, $spent]) {
                $lines[$lineName] = $this->make(BudgetLine::class, [
                    'project_id' => $p->id, 'name' => $lineName, 'budgeted' => $budget * 1e6, 'executed' => $spent * 1e6, 'committed' => 0,
                ], $lineId++);
            }
            $p->setRelation('budgetLines', $lines);

            // Curva S
            $p->setRelation('curvePoints', match ($key) {
                'garzon' => collect(),
                'drones' => $this->curve($p, [1, 2, 7, 15, 30, 50, 69, 84.04, 94, 98, 99.6, 100], [0.4272, 1.8592, 7.2128, 14.9472, 27.4736, 43.4192, 59.3648, 71.8912]),
                default => $this->curve($p, null, null, self::PLANNED_TO_DATE[$key] ?? null),
            });

            $this->projects[$key] = $p;
        }
    }

    public const BUDGET_LINES = ['Talento humano', 'Equipos', 'Materiales e insumos', 'Viajes y transporte', 'Capacitaciones y eventos', 'Servicios técnicos', 'Gastos administrativos'];

    /** Planeado acumulado a sep 2026 (% del valor), mockup FIN 03. */
    private const PLANNED_TO_DATE = ['tic' => 93.29, 'energia' => 12.52, 'emprende' => 97.22, 'residuos' => 69.06, 'tatacoa' => 50.0];

    /**
     * Reparte el presupuesto en los 7 rubros. Monitoreo con drones usa los valores
     * exactos del mockup FIN 04; los demás proyectos activos reparten el resto de los
     * totales por rubro de FIN 03 en proporción a su valor.
     *
     * @return array<int, array{0: string, 1: float, 2: float}> [rubro, asignado M, ejecutado M]
     */
    private function budgetSplit(string $key, string $status, float $value, float $exec): array
    {
        if ($key === 'drones') {
            return [['Talento humano', 710, 450.04], ['Equipos', 40, 20], ['Materiales e insumos', 150, 128.58], ['Viajes y transporte', 100, 85.74],
                ['Capacitaciones y eventos', 125, 107.14], ['Servicios técnicos', 75, 64.28], ['Gastos administrativos', 50, 42.86]];
        }
        // Total por rubro de FIN 03 menos lo de drones, en millones (suma 2.530)
        $shares = $status === 'activo'
            ? [1063, 455, 304, 202, 253, 152, 101]
            : [1189, 329, 304, 202, 253, 152, 101];
        $total = array_sum($shares);
        $ratio = $value > 0 ? $exec / $value : 0;
        $split = [];
        foreach (self::BUDGET_LINES as $i => $name) {
            $budget = $value / 1e6 * $shares[$i] / $total;
            $split[] = [$name, round($budget, 2), round($budget * $ratio, 2)];
        }
        // Gestión de residuos: el rubro Viajes y transporte quedó sobreejecutado (alerta de FIN 06)
        if ($key === 'residuos') {
            $extra = 79.97 - $split[3][2];
            $split[3][2] = 79.97;
            $split[0][2] = round($split[0][2] - $extra, 2);
        }

        return $split;
    }

    private function buildRequests(): void
    {
        $p = $this->projects;
        $u = $this->users;
        $ago = fn (int $d) => now()->subDays($d);

        // Mockups VIC 01, FIN 01 y FIN 02
        $this->purchases = collect([
            ['SC-0231', 'drones', 'Equipos', 'laura', 'Dron multiespectral DJI Mavic 3M', 25_000_000, 20_000_000, 'en_vicerrectoria', '2026-10-14', null],
            ['SC-0230', 'drones', 'Materiales e insumos', 'laura', 'Kit de sensores de humedad de suelo (x20)', 4_800_000, 4_800_000, 'en_financiera', '2026-10-09', null],
            ['SC-0228', 'emprende', 'Capacitaciones y eventos', 'laura', 'Refrigerios taller de emprendimiento (200 personas)', 1_200_000, null, 'aprobada', '2026-10-02', 'CDP-2026-0399'],
            ['SC-0225', 'energia', 'Equipos', 'andres', 'Paneles solares 3 kW con inversor', 18_500_000, null, 'en_financiera', '2026-10-01', 'CDP-2026-0402'],
            ['SC-0234', 'tatacoa', 'Capacitaciones y eventos', 'paola', 'Alquiler de auditorio para talleres', 3_500_000, null, 'en_vicerrectoria', '2026-10-16', null],
            ['SC-0236', 'residuos', 'Equipos', 'andres', 'Licencia ArcGIS Pro (1 año)', 3_800_000, null, 'en_vicerrectoria', '2026-10-30', null],
            ['SC-0237', 'emprende', 'Capacitaciones y eventos', 'laura', 'Material didáctico para talleres', 3_800_000, null, 'en_financiera', '2026-10-20', null],
            ['SC-0221', 'residuos', 'Viajes y transporte', 'andres', 'Transporte para visitas a instituciones', 2_300_000, null, 'pagada', '2026-09-12', 'CDP-2026-0371'],
            ['SC-0219', 'residuos', 'Equipos', 'andres', 'Puntos ecológicos (25 unidades)', 42_000_000, 36_000_000, 'requiere_ajuste', '2026-09-05', null],
            ['SC-0212', 'tic', 'Equipos', 'laura', 'Computadores portátiles para puntos digitales (x24)', 62_000_000, null, 'pagada', '2026-05-20', 'CDP-2026-0288'],
            ['SC-0201', 'drones', 'Equipos', 'laura', 'Cámara térmica para inspección de cultivos', 20_000_000, null, 'pagada', '2026-04-10', 'CDP-2026-0241'],
            ['SC-0209', 'emprende', 'Materiales e insumos', 'laura', 'Material didáctico (kits de emprendimiento)', 2_900_000, null, 'rechazada', '2026-04-03', null],
        ])->map(function ($r, $i) use ($p, $u) {
            [$code, $proj, $line, $req, $desc, $value, $orig, $status, $date, $cdp] = $r;
            $m = $this->make(PurchaseRequest::class, [
                'code' => $code, 'project_id' => $p[$proj]->id, 'budget_line_id' => $p[$proj]->budgetLines[$line]->id,
                'requester_id' => $u[$req]->id, 'description' => $desc, 'value' => $value, 'original_value' => $orig,
                'status' => $status, 'submitted_at' => Carbon::parse($date . ' 09:00'), 'cdp' => $cdp,
            ], $i + 1);
            $m->setRelation('project', $p[$proj]);
            $m->setRelation('budgetLine', $p[$proj]->budgetLines[$line]);
            $m->setRelation('requester', $u[$req]);

            return $m;
        });

        $this->progress = collect([
            ['drones', 'laura', 44, 6, 4, 156_580_000, 35],
            ['tic', 'andres', 74, 8, 6, 88_400_000, 9],
        ])->map(function ($r, $i) use ($p, $u, $ago) {
            [$proj, $by, $tech, $delta, $supports, $amount, $days] = $r;
            $m = $this->make(MonthlyProgress::class, [
                'project_id' => $p[$proj]->id, 'submitted_by' => $u[$by]->id, 'period' => Carbon::parse('2026-09-01'),
                'technical_progress' => $tech, 'technical_delta' => $delta, 'supports_count' => $supports,
                'amount' => $amount, 'status' => 'pendiente', 'submitted_at' => $ago($days),
            ], $i + 1);
            $m->setRelation('project', $p[$proj]);
            $m->setRelation('submitter', $u[$by]);

            return $m;
        });

        $t = $this->make(BudgetTransfer::class, [
            'code' => 'TP-0012', 'project_id' => $p['drones']->id, 'amount' => 5_000_000,
            'reason' => 'Para cubrir el sobrecosto de SC-0231', 'status' => 'pendiente', 'submitted_at' => $ago(12),
        ], 1);
        $t->setRelation('project', $p['drones']);
        $t->setRelation('fromLine', $p['drones']->budgetLines['Viajes y transporte']);
        $t->setRelation('toLine', $p['drones']->budgetLines['Equipos']);
        $t->setRelation('requester', $u['laura']);
        $this->transfers = collect([$t]);
    }

    /** Centro de alertas, mockup FIN 06 (19 activas: 10 críticas, 8 advertencias, 1 informativa). */
    private function buildAlerts(): void
    {
        // [proyecto, severidad, tipo, título, detalle, requiere gestión de Vicerrectoría, financiera]
        $rows = [
            ['drones', 'critica', 'sobrecosto', 'Sobrecosto en compra SC-0231', 'Dron multiespectral DJI Mavic 3M: pasó de $20M a $25M (+25%)', true, true],
            ['drones', 'critica', 'presupuesto', 'Presupuesto insuficiente para SC-0231', 'Rubro Equipos: disponible $20.000.000, solicitado $25.000.000', true, true],
            ['drones', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Adquisición de dron multiespectral (0%) · venció el 30 may 2026', false, false],
            ['drones', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Capacitación a productores (83%) · venció el 30 ago 2026', false, false],
            ['drones', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Visitas técnicas a fincas (83%) · venció el 30 sep 2026', false, false],
            ['residuos', 'critica', 'rubro', 'Sobrecosto en rubro "Viajes y transporte"', 'Gestión integral de residuos sólidos: ejecutado + comprometido $79.970.000 de $51.200.000 (156%)', true, true],
            ['residuos', 'critica', 'sobrecosto', 'Sobrecosto en compra SC-0219', 'Puntos ecológicos (25 unidades): pasó de $36M a $42M (+17%)', true, true],
            ['residuos', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Caracterización de residuos (60%) · venció el 30 may 2026', false, false],
            ['residuos', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Puntos ecológicos instalados (68%) · venció el 30 ago 2026', false, false],
            ['residuos', 'critica', 'entrega_vencida', 'Producto con entrega vencida', 'Jornadas de sensibilización (67%) · venció el 15 oct 2026', false, false],
            ['drones', 'advertencia', 'rezago', 'Avance técnico rezagado', 'Monitoreo agrícola con drones: 44% de avance con 72% del tiempo transcurrido', false, false],
            ['drones', 'advertencia', 'ejecucion', 'Ejecución financiera mayor al avance técnico', 'Monitoreo agrícola con drones: 72% ejecutado vs. 44% técnico', false, true],
            ['emprende', 'advertencia', 'plazo', 'Entrega en 10 días', 'Mentorías a emprendimientos · Capacitación en emprendimiento juvenil', false, false],
            ['residuos', 'advertencia', 'rezago', 'Avance técnico rezagado', 'Gestión integral de residuos sólidos: 49% de avance con 64% del tiempo transcurrido', false, false],
            ['tatacoa', 'advertencia', 'vencimiento', 'El plazo del proyecto está por vencer', 'Turismo sostenible Desierto de la Tatacoa: finaliza en 26 días (15 nov 2026)', true, false],
            ['tatacoa', 'advertencia', 'plazo', 'Entrega en 4 días', 'Diagnóstico turístico · Turismo sostenible Desierto de la Tatacoa', false, false],
            ['tatacoa', 'advertencia', 'rezago', 'Avance técnico rezagado', 'Turismo sostenible Desierto de la Tatacoa: 13% de avance con 75% del tiempo transcurrido', false, false],
            ['tatacoa', 'advertencia', 'ejecucion', 'Ejecución financiera mayor al avance técnico', 'Turismo sostenible Desierto de la Tatacoa: 35% ejecutado vs. 13% técnico', false, true],
            ['residuos', 'informativa', 'reporte', 'Reporte mensual pendiente', 'Gestión integral de residuos sólidos: falta cargar el avance de septiembre 2026', false, false],
        ];
        $this->alerts = collect($rows)->map(function ($r, $i) {
            [$proj, $sev, $kind, $title, $detail, $action, $fin] = $r;
            $a = $this->make(Alert::class, [
                'project_id' => $this->projects[$proj]->id, 'severity' => $sev, 'kind' => $kind, 'title' => $title,
                'detail' => $detail, 'requires_action' => $action, 'financial' => $fin, 'resolved' => false,
            ], $i + 1);
            $a->setRelation('project', $this->projects[$proj]);

            return $a;
        });
    }

    /** Productos / metas de cada proyecto, mockups VIC 06 y VIC 12. El avance técnico es su promedio. */
    private function buildProducts(): void
    {
        // [proyecto, producto, tipo, logrado, meta, unidad, fecha de entrega]
        $rows = [
            ['emprende', 'Diseño curricular', 'Documento', 1, 1, 'und.', '2026-02-28'],
            ['tic', 'Diagnóstico de conectividad veredal', 'Documento', 1, 1, 'und.', '2026-03-15'],
            ['drones', 'Diagnóstico de cultivos', 'Documento', 1, 1, 'und.', '2026-03-15'],
            ['drones', 'Adquisición de dron multiespectral', 'Equipo', 0, 1, 'und.', '2026-05-30'],
            ['residuos', 'Caracterización de residuos', 'Documento', 3, 5, 'und.', '2026-05-30'],
            ['tic', 'Instalación de 12 puntos digitales', 'Equipo', 12, 12, 'und.', '2026-07-30'],
            ['drones', 'Capacitación a productores', 'Capacitación', 99, 120, 'personas', '2026-08-30'],
            ['energia', 'Estudio de prefactibilidad', 'Documento', 1, 1, 'und.', '2026-08-30'],
            ['residuos', 'Puntos ecológicos instalados', 'Equipo', 17, 25, 'und.', '2026-08-30'],
            ['drones', 'Visitas técnicas a fincas', 'Visita técnica', 33, 40, 'visitas', '2026-09-30'],
            ['emprende', 'Talleres de emprendimiento', 'Capacitación', 200, 200, 'personas', '2026-09-30'],
            ['residuos', 'Jornadas de sensibilización', 'Capacitación', 10, 15, 'personas', '2026-10-15'],
            ['tatacoa', 'Diagnóstico turístico', 'Documento', 0, 1, 'und.', '2026-10-24'],
            ['emprende', 'Mentorías a emprendimientos', 'Visita técnica', 12, 20, 'visitas', '2026-10-30'],
            ['tatacoa', 'Talleres con operadores', 'Capacitación', 6, 15, 'personas', '2026-11-05'],
            ['tatacoa', 'Guía de ruta sostenible', 'Publicación', 0, 1, 'und.', '2026-11-12'],
            ['tic', 'Capacitación en alfabetización digital', 'Capacitación', 154, 300, 'personas', '2026-11-15'],
            ['emprende', 'Feria de emprendimiento', 'Evento', 1, 1, 'und.', '2026-11-20'],
            ['drones', 'Plataforma web de monitoreo', 'Software', 0, 1, 'und.', '2026-11-30'],
            ['energia', 'Diseño del laboratorio', 'Documento', 0, 1, 'und.', '2026-11-30'],
            ['tic', 'Visitas de acompañamiento', 'Visita técnica', 22, 48, 'visitas', '2026-12-01'],
            ['drones', 'Informe final y socialización', 'Evento', 0, 1, 'und.', '2027-01-15'],
            ['residuos', 'Plan de manejo (PGIRS escolar)', 'Documento', 0, 1, 'und.', '2027-01-30'],
            ['energia', 'Paneles solares e inversores', 'Equipo', 0, 6, 'und.', '2027-02-28'],
            ['energia', 'Artículo científico', 'Publicación', 0, 1, 'und.', '2027-06-15'],
            // Proyectos liquidados: todo entregado
            ['colegios', 'Diagnóstico TIC por institución', 'Documento', 1, 1, 'und.', '2025-07-30'],
            ['colegios', 'Informe departamental', 'Documento', 1, 1, 'und.', '2025-11-15'],
            ['riego', 'Prototipo de riego IoT', 'Equipo', 1, 1, 'und.', '2025-09-30'],
            ['riego', 'Validación en finca piloto', 'Visita técnica', 6, 6, 'visitas', '2025-12-01'],
        ];
        $this->products = collect($rows)->map(fn ($r, $i) => [
            'id' => $i + 1, 'project_id' => $this->projects[$r[0]]->id, 'name' => $r[1], 'type' => $r[2],
            'done' => $r[3], 'target' => $r[4], 'unit' => $r[5], 'due' => Carbon::parse($r[6]),
        ]);
    }

    /** Puntos mensuales de la Curva S (porcentajes acumulados del valor del proyecto). */
    private function curve(Project $project, ?array $planned = null, ?array $executed = null, ?float $plannedToDate = null): Collection
    {
        $months = [];
        for ($m = $project->start_date->copy()->startOfMonth(); $m <= $project->end_date; $m->addMonth()) {
            $months[] = $m->copy();
        }
        $n = count($months);
        $l = fn ($t) => 1 / (1 + exp(-8 * ($t - 0.5)));
        $planned ??= array_map(fn ($i) => ($l(($i + 1) / $n) - $l(0)) / ($l(1) - $l(0)) * 100, range(0, $n - 1));

        // Ajusta la curva para que el planeado al último mes reportado sea el del mockup
        if ($plannedToDate !== null) {
            $idx = count(array_filter($months, fn ($m) => $m <= $this->lastReportedMonth)) - 1;
            $at = $planned[$idx] ?? null;
            if ($at > 0 && $at < 100) {
                $k = log($plannedToDate / 100) / log($at / 100);
                $planned = array_map(fn ($v) => 100 * pow($v / 100, $k), $planned);
            }
        }

        if ($executed === null) {
            $reported = $project->status === 'liquidado' ? $n : count(array_filter($months, fn ($m) => $m <= $this->lastReportedMonth));
            $executed = [];
            if ($reported > 0) {
                $last = $planned[$reported - 1] ?: 1;
                $target = $project->executed_amount / $project->value * 100;
                for ($i = 0; $i < $reported; $i++) {
                    $executed[] = $planned[$i] / $last * $target;
                }
            }
        }

        return collect($months)->map(fn ($month, $i) => $this->make(CurvePoint::class, [
            'project_id' => $project->id,
            'month' => $month,
            'planned_cumulative' => round($project->value * $planned[$i] / 100, 2),
            'executed_cumulative' => isset($executed[$i]) ? round($project->value * $executed[$i] / 100, 2) : null,
        ], $i + 1));
    }
}
