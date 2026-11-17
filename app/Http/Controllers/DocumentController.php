<?php

namespace App\Http\Controllers;

use App\Support\FinanceStore;
use Illuminate\Support\Facades\Storage;

/** Descarga y vista previa de documentos (compartido por todos los roles). */
class DocumentController extends Controller
{
    public function __construct(private FinanceStore $finance) {}

    public function download(string $id)
    {
        $doc = $this->accessible($id);
        abort_unless($doc['path'] && Storage::exists($doc['path']), 404, 'Este documento es de ejemplo y no tiene archivo.');

        return Storage::download($doc['path'], $doc['name']);
    }

    /** El archivo en línea, para mostrarlo dentro de la vista previa (PDF o imagen). */
    public function inline(string $id)
    {
        $doc = $this->accessible($id);
        abort_unless($doc['path'] && Storage::exists($doc['path']), 404, 'Este documento es de ejemplo y no tiene archivo.');

        return Storage::response($doc['path'], $doc['name'], ['Content-Disposition' => 'inline']);
    }

    /** El investigador solo accede a los documentos de los proyectos a su cargo. */
    private function accessible(string $id): array
    {
        $doc = $this->finance->document($id);
        $user = auth()->user();
        abort_if($user->role === 'investigador' && $doc['project']->coordinator_id !== $user->id, 403, 'Este documento no es de un proyecto a tu cargo.');

        return $doc;
    }
}
