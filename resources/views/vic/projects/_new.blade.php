{{-- Modal «Nuevo proyecto / contrato» (mockup M · Nuevo proyecto). Requiere $coordinators, $entities y $close. --}}
@php
    use App\Models\Project;
    use App\Support\DemoData;
    use App\Support\ProjectStore;
    use App\Support\ProjectTracking;
    $split = old('split', ProjectStore::DEFAULT_SPLIT);
    $products = old('products', [['name' => '', 'type' => 'Documento', 'target' => 1, 'due' => ''], ['name' => '', 'type' => 'Capacitación', 'target' => '', 'due' => '']]);
@endphp
<div class="modal-backdrop">
    <form class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="np-title" method="post" action="{{ route('vic.proyectos.store') }}">
        @csrf
        <input type="hidden" name="_form" value="nuevo-proyecto">
        <div class="modal-head">
            <div><h2 id="np-title">Nuevo proyecto / contrato</h2></div>
            <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
        </div>
        <div class="modal-body">
            <div class="field">
                <label for="name">Nombre del proyecto *</label>
                <input id="name" name="name" value="{{ old('name') }}" required autofocus>
                @error('name')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="grid-3-form">
                <div class="field">
                    <label for="code">Código / N.º contrato *</label>
                    <input id="code" name="code" value="{{ old('code') }}" placeholder="CTO-000-2026" required>
                    @error('code')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="type">Tipo *</label>
                    <select id="type" name="type">
                        @foreach (Project::TYPES as $k => $label)<option value="{{ $k }}" @selected(old('type', 'extension') === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="modality">Modalidad *</label>
                    <select id="modality" name="modality">
                        @foreach (Project::MODALITIES as $k => $label)<option value="{{ $k }}" @selected(old('modality', 'convenio') === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                @include('vic.projects._entity-picker', ['entities' => $entities, 'selected' => old('entity_id'), 'id' => 'entity'])
                <div class="field">
                    <label for="coordinator_id">Coordinador del proyecto *</label>
                    <select id="coordinator_id" name="coordinator_id">
                        @foreach ($coordinators as $u)<option value="{{ $u->id }}" @selected((int) old('coordinator_id') === $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid-3-form">
                <div class="field">
                    <label for="start">Fecha de inicio *</label>
                    <input id="start" name="start" type="date" value="{{ old('start') }}" required>
                    @error('start')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="end">Fecha de finalización *</label>
                    <input id="end" name="end" type="date" value="{{ old('end') }}" required>
                    @error('end')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="value">Valor total ($) *</label>
                    <input id="value" name="value" type="number" min="1000000" step="1" value="{{ old('value', 500000000) }}" required>
                    @error('value')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="field">
                <label for="general_objective">Objetivo general *</label>
                <textarea id="general_objective" name="general_objective" required>{{ old('general_objective') }}</textarea>
                @error('general_objective')<span class="error">{{ $message }}</span>@enderror
            </div>

            <h3 class="section-title">Distribución del presupuesto por rubros (%)</h3>
            <div class="split-grid">
                @foreach (DemoData::BUDGET_LINES as $i => $line)
                    <label for="split-{{ $i }}">{{ $line }}</label>
                    <input id="split-{{ $i }}" name="split[{{ $line }}]" type="number" min="0" max="100" step="0.5" value="{{ $split[$line] ?? 0 }}" data-split>
                @endforeach
            </div>
            <p class="modal-note">Total: <b id="split-total">100</b> %</p>
            @error('split')<span class="error" style="color:#a3261a;font-size:12px">{{ $message }}</span>@enderror

            <h3 class="section-title">Objetivos específicos y productos</h3>
            <div id="product-rows" style="display:flex;flex-direction:column;gap:8px">
                @foreach ($products as $i => $prod)
                    <div class="product-row">
                        <input name="products[{{ $i }}][name]" value="{{ $prod['name'] ?? '' }}" placeholder="Producto (ej. Capacitación a productores)" aria-label="Producto">
                        <select name="products[{{ $i }}][type]" aria-label="Tipo de producto">
                            @foreach (ProjectTracking::PRODUCT_TYPES as $t)<option @selected(($prod['type'] ?? '') === $t)>{{ $t }}</option>@endforeach
                        </select>
                        <input name="products[{{ $i }}][target]" type="number" min="1" value="{{ $prod['target'] ?? '' }}" placeholder="Meta" aria-label="Meta">
                        <input name="products[{{ $i }}][due]" type="date" value="{{ $prod['due'] ?? '' }}" aria-label="Fecha de entrega">
                    </div>
                @endforeach
            </div>
            @foreach ($errors->get('products.*') as $messages)<span class="error" style="color:#a3261a;font-size:12px">{{ $messages[0] }}</span>@break @endforeach
            <div><button class="btn btn-sm" type="button" id="add-product"><x-icon name="plus" size="14" />Agregar producto</button></div>
            <p class="modal-note">El sistema genera automáticamente la ruta de ejecución (preinversión, inversión y operación) y la curva S proyectada. Los productos sin nombre no se guardan.</p>
        </div>
        <div class="modal-foot">
            <a class="btn" href="{{ $close }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">Crear proyecto</button>
        </div>
    </form>
</div>
<script>
    (() => {
        const inputs = document.querySelectorAll('[data-split]');
        const total = document.getElementById('split-total');
        const sum = () => {
            const t = [...inputs].reduce((a, i) => a + (parseFloat(i.value) || 0), 0);
            total.textContent = Math.round(t * 100) / 100;
            total.style.color = Math.abs(t - 100) < 0.01 ? 'var(--green-700)' : 'var(--red)';
        };
        inputs.forEach((i) => i.addEventListener('input', sum));
        sum();

        const rows = document.getElementById('product-rows');
        document.getElementById('add-product').addEventListener('click', () => {
            const n = rows.children.length;
            const row = rows.lastElementChild.cloneNode(true);
            row.querySelectorAll('input, select').forEach((el) => {
                el.name = el.name.replace(/products\[\d+\]/, `products[${n}]`);
                if (el.tagName === 'INPUT') el.value = '';
            });
            rows.appendChild(row);
        });
    })();
</script>
