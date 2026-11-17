{{-- Buscador de entidad del catálogo. Requiere $entities, $selected (id) y $id (id del campo). --}}
@php $current = $entities->firstWhere('id', (int) $selected); @endphp
<div class="field">
    <label for="{{ $id }}">Entidad contratante / aliada *</label>
    <input id="{{ $id }}" list="{{ $id }}-list" value="{{ $current ? "{$current['name']} · NIT {$current['nit']}" : '' }}"
           placeholder="Escribe para buscar por nombre o NIT…" autocomplete="off" data-entity-search="{{ $id }}-value">
    <input type="hidden" id="{{ $id }}-value" name="entity_id" value="{{ $current['id'] ?? '' }}">
    <datalist id="{{ $id }}-list">
        @foreach ($entities as $e)<option value="{{ $e['name'] }} · NIT {{ $e['nit'] }}" data-id="{{ $e['id'] }}"></option>@endforeach
    </datalist>
    @error('entity_id')<span class="error">{{ $message }}</span>@enderror
    <span class="modal-note">¿No aparece? <a href="{{ route('entidades.index', ['modal' => 'nueva']) }}" target="_blank" rel="noopener">Créala en Entidades</a> (se abre en otra pestaña) y luego recarga esta página.</span>
</div>
<script>
    (() => {
        const input = document.getElementById(@json($id));
        const hidden = document.getElementById(input.dataset.entitySearch);
        const sync = () => {
            const opt = [...input.list.options].find((o) => o.value === input.value);
            hidden.value = opt ? opt.dataset.id : '';
            input.setCustomValidity(opt || input.value === '' ? '' : 'Elige una entidad de la lista.');
        };
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
    })();
</script>
