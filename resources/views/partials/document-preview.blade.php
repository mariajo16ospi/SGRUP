{{-- Modal «Vista previa de documento». $doc: documento de FinanceStore · $close: URL para cerrar --}}
@php
    $hasFile = $doc['path'] && \Illuminate\Support\Facades\Storage::exists($doc['path']);
    $ext = strtolower($doc['ext']);
    $isImage = in_array($ext, ['png', 'jpg', 'jpeg'], true);
@endphp
<div class="modal-backdrop">
    <div class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="dp-title">
        <div class="modal-head">
            <div>
                <h2 id="dp-title">{{ $doc['name'] }}</h2>
                <p>{{ $doc['category'] }} · subido por {{ $doc['by'] }} el {{ date_es($doc['date']) }} · {{ number_format($doc['size'], 1, ',', '.') }} MB
                    · {{ $doc['validated'] ? 'Validado' : 'En revisión' }}</p>
            </div>
            <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
        </div>
        <div class="doc-preview">
            @if ($hasFile && $ext === 'pdf')
                <iframe class="doc-frame" src="{{ route('documentos.ver', $doc['id']) }}" title="{{ $doc['name'] }}"></iframe>
            @elseif ($hasFile && $isImage)
                <img class="doc-image" src="{{ route('documentos.ver', $doc['id']) }}" alt="{{ $doc['name'] }}">
            @else
                <div class="doc-sheet">
                    <h3>{{ $doc['type'] }}</h3>
                    <p>{{ $doc['project']->contractLabel() }} {{ $doc['project']->code }} · {{ $doc['project']->name }}</p>
                    @foreach ([100, 92, 100, 80, 96, 86, 100, 70] as $w)<i style="width:{{ $w }}%"></i>@endforeach
                </div>
            @endif
        </div>
        <div class="modal-foot">
            @unless ($hasFile)
                <span class="modal-note" style="margin-right:auto">{{ $doc['path'] ? 'El archivo ya no está disponible.' : 'Documento de ejemplo: no tiene archivo para descargar.' }}</span>
            @endunless
            <a class="btn" href="{{ $close }}">Cerrar</a>
            @if ($hasFile)
                <a class="btn btn-primary" href="{{ route('documentos.descargar', $doc['id']) }}"><x-icon name="download" size="16" />Descargar {{ strtoupper($doc['ext']) }}</a>
            @endif
        </div>
    </div>
</div>
