<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Adm;
use App\Http\Controllers\Fin;
use App\Http\Controllers\Inv;
use App\Http\Controllers\Vic;
use App\Support\DemoData;
use Illuminate\Support\Facades\Route;

// Versión de diseño: los proyectos salen de DemoData, no de la base de datos.
Route::bind('project', fn ($id) => DemoData::project($id));

Route::redirect('/', '/login');

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('demo.auth')->group(function () {
    // Secciones aún no construidas (siguientes fases)
    Route::get('/pendiente', fn () => view('pending'))->name('pendiente');
    Route::get('/documentos/{id}/descargar', [Fin\FinanceController::class, 'download'])->name('documentos.descargar');

    Route::prefix('administracion')->name('adm.')->middleware('role:admin')->controller(Adm\AdminController::class)->group(function () {
        Route::get('/', 'users')->name('usuarios');
        Route::post('/usuarios', 'storeUser')->name('usuarios.store');
        Route::put('/usuarios/{id}', 'updateUser')->whereNumber('id')->name('usuarios.update');
        Route::post('/restablecer', 'reset')->name('reset');

        Route::get('/parametros', 'parameters')->name('parametros');
        Route::post('/parametros/rubros', 'storeItem')->name('rubros.store');
        Route::put('/parametros/rubros/{code}', 'updateItem')->where('code', 'R-\d{2}')->name('rubros.update');
        Route::post('/parametros/reglas/{key}', 'toggleRule')->name('reglas.toggle');

        Route::get('/auditoria', 'audit')->name('auditoria');
        Route::get('/auditoria/exportar', 'exportAudit')->name('auditoria.export');
    });

    Route::prefix('financiera')->name('fin.')->middleware('role:financiero')->controller(Fin\FinanceController::class)->group(function () {
        $code = '(SC-\d{4}|CUOTA-\d+)';
        Route::get('/', 'control')->name('control');
        Route::get('/exportar', 'exportControl')->name('control.export');
        Route::get('/pagos/{code}', 'paymentForm')->where('code', $code)->name('pago.form');

        Route::get('/compras', 'purchases')->name('compras');
        Route::post('/compras/{code}/cdp', 'issueCdp')->where('code', 'SC-\d{4}')->name('compras.cdp');
        Route::post('/compras/{code}/devolver', 'returnPurchase')->where('code', 'SC-\d{4}')->name('compras.devolver');
        Route::post('/pagos/{code}', 'registerPayment')->where('code', $code)->name('pago.store');

        Route::get('/presupuesto', 'budget')->name('presupuesto');
        Route::get('/presupuesto/exportar', 'exportBudget')->name('presupuesto.export');
        Route::get('/proyectos/{project}', 'projectBudget')->whereNumber('project')->name('proyecto');
        Route::get('/proyectos/{project}/movimientos', 'exportMovements')->whereNumber('project')->name('proyecto.movimientos');
        Route::post('/proyectos/{project}/traslados', 'requestTransfer')->whereNumber('project')->name('proyecto.traslado');

        Route::get('/documentos', 'documents')->name('documentos');
        Route::post('/documentos', 'upload')->name('documentos.subir');
        Route::post('/documentos/{id}/validar', 'validateDocument')->name('documentos.validar');
        Route::get('/documentos/paquete-rup', 'rupPackage')->name('documentos.rup');

        Route::get('/alertas', 'alerts')->name('alertas');
    });

    Route::prefix('investigador')->name('inv.')->middleware('role:investigador')->group(function () {
        Route::controller(Inv\InvestigatorController::class)->group(function () {
            Route::get('/', 'home')->name('home');
            Route::get('/cargar-avance', 'loadReport')->name('cargar');
            Route::get('/compras', 'purchases')->name('compras');
            Route::post('/compras', 'storePurchase')->name('compras.store');
            Route::put('/compras/{code}', 'resubmit')->where('code', 'SC-\d{4}')->name('compras.reenviar');
            Route::post('/compras/{code}/retirar', 'withdraw')->where('code', 'SC-\d{4}')->name('compras.retirar');
            Route::get('/documentos', 'documents')->name('documentos');
            Route::post('/documentos', 'upload')->name('documentos.subir');
            Route::get('/documentos/paquete-rup', 'rupPackage')->name('documentos.rup');
            Route::get('/cronograma', 'schedule')->name('cronograma');
            Route::get('/alertas', 'alerts')->name('alertas');
        });

        // Pestañas del proyecto, compartidas con Vicerrectoría (INV 02–06, 11 y 12)
        Route::controller(Vic\ProjectController::class)->prefix('/proyectos/{project}')->whereNumber('project')->name('proyectos.')->group(function () {
            Route::get('/', 'show')->name('show');
            Route::get('/objetivos', 'objectives')->name('objetivos');
            Route::post('/objetivos', 'storeProduct')->name('productos.store');
            Route::put('/objetivos/{id}', 'updateProduct')->whereNumber('id')->name('productos.update');
            Route::get('/presupuesto', 'budget')->name('presupuesto');
            Route::post('/presupuesto/traslados', 'transfer')->name('traslado');
            Route::get('/presupuesto/movimientos', 'exportMovements')->name('movimientos');
            Route::get('/avances', 'progress')->name('avances');
            Route::post('/avances', 'storeReport')->name('avances.store');
            Route::get('/compras', 'purchases')->name('compras');
            Route::get('/documentos', 'documents')->name('documentos');
            Route::post('/documentos', 'upload')->name('documentos.subir');
            Route::get('/trazabilidad', 'timeline')->name('trazabilidad');
        });
    });

    Route::prefix('vicerrectoria')->name('vic.')->middleware('role:vicerrectoria')->group(function () {
        Route::get('/', Vic\PanelController::class)->name('panel');
        Route::get('/aprobaciones', [Vic\ApprovalController::class, 'index'])->name('aprobaciones');
        Route::post('/aprobaciones/{type}/{id}/aprobar', [Vic\ApprovalController::class, 'approve'])
            ->whereIn('type', ['compra', 'avance', 'traslado', 'proyecto'])->whereNumber('id')->name('aprobaciones.aprobar');

        Route::get('/proyectos', [Vic\ProjectController::class, 'index'])->name('proyectos.index');
        Route::get('/proyectos/exportar', [Vic\ProjectController::class, 'export'])->name('proyectos.export');
        Route::get('/proyectos/{project}', [Vic\ProjectController::class, 'show'])->whereNumber('project')->name('proyectos.show');
        Route::post('/proyectos/{project}/siguiente-fase', [Vic\ProjectController::class, 'advancePhase'])->whereNumber('project')->name('proyectos.fase');
        Route::post('/proyectos/{project}/liquidar', [Vic\ProjectController::class, 'liquidate'])->whereNumber('project')->name('proyectos.liquidar');

        // Pestañas del proyecto (VIC 05–10)
        Route::controller(Vic\ProjectController::class)->prefix('/proyectos/{project}')->whereNumber('project')->name('proyectos.')->group(function () {
            Route::get('/objetivos', 'objectives')->name('objetivos');
            Route::post('/objetivos', 'storeProduct')->name('productos.store');
            Route::get('/presupuesto', 'budget')->name('presupuesto');
            Route::post('/presupuesto/traslados', 'transfer')->name('traslado');
            Route::get('/presupuesto/movimientos', 'exportMovements')->name('movimientos');
            Route::get('/avances', 'progress')->name('avances');
            Route::post('/avances/{id}', 'decideProgress')->whereNumber('id')->name('avances.decidir');
            Route::get('/compras', 'purchases')->name('compras');
            Route::get('/documentos', 'documents')->name('documentos');
            Route::post('/documentos', 'upload')->name('documentos.subir');
            Route::post('/documentos/{id}/validar', 'validateDocument')->name('documentos.validar');
            Route::get('/certificacion', 'certificate')->name('certificacion');
            Route::get('/trazabilidad', 'timeline')->name('trazabilidad');
        });

        Route::get('/compras', [Vic\PurchaseController::class, 'index'])->name('compras');
        Route::post('/compras/{code}/decidir', [Vic\PurchaseController::class, 'decide'])->where('code', 'SC-\d{4}')->name('compras.decidir');

        Route::controller(Vic\MonitoringController::class)->group(function () {
            Route::get('/cronograma', 'schedule')->name('cronograma');
            Route::get('/seguimiento', 'tracking')->name('seguimiento');
            Route::get('/reportes', 'reports')->name('reportes');
            Route::get('/reportes/informe', 'printable')->name('reportes.informe');
            Route::get('/reportes/{report}', 'exportReport')->whereIn('report', ['consolidado', 'rubros', 'compras', 'experiencia'])->name('reportes.export');
            Route::get('/alertas', 'alerts')->name('alertas');
        });
    });
});
