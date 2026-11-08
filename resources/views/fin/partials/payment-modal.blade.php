{{-- Modal «Registrar pago». $item: code, title, subtitle, cdp, value, supplier · $close: URL de cancelar · $back: control|compras --}}
@php use App\Support\FinanceStore; @endphp
<div class="modal-backdrop">
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="pay-title" method="post" enctype="multipart/form-data"
          action="{{ route('fin.pago.store', $item['code']) }}">
        @csrf
        <input type="hidden" name="back" value="{{ $back }}">
        <div class="modal-head">
            <div>
                <h2 id="pay-title">Registrar pago · {{ str_starts_with($item['code'], 'CUOTA-') ? 'Cuota ' . substr($item['code'], 6) : $item['code'] }}</h2>
                <p>{{ $item['title'] }} · {{ $item['subtitle'] }}</p>
            </div>
            <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
        </div>
        <div class="modal-body">
            <div class="box" style="margin-top:0">
                <dl class="kv">
                    @if ($item['cdp'])<dt>CDP asociado</dt><dd>{{ $item['cdp'] }}</dd>@endif
                    <dt>Valor comprometido</dt><dd>{{ money($item['value']) }}</dd>
                </dl>
            </div>
            <div class="field">
                <label for="supplier">{{ $item['cdp'] ? 'Proveedor *' : 'Entidad que desembolsa' }}</label>
                <input id="supplier" name="supplier" value="{{ old('supplier', $item['supplier']) }}" placeholder="Razón social · NIT" @if($item['cdp']) required @endif>
                @error('supplier')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="row">
                <div class="field">
                    <label for="amount">Valor pagado *</label>
                    <input id="amount" name="amount" type="number" min="1" step="1" value="{{ old('amount', (int) $item['value']) }}" required>
                    @error('amount')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="date">Fecha de pago *</label>
                    <input id="date" name="date" type="date" value="{{ old('date', today()->toDateString()) }}" required>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="method">Medio de pago *</label>
                    <select id="method" name="method">
                        @foreach (FinanceStore::PAYMENT_METHODS as $m)<option @selected(old('method') === $m)>{{ $m }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="voucher">N.º comprobante de egreso *</label>
                    <input id="voucher" name="voucher" value="{{ old('voucher', $voucher) }}" required>
                    @error('voucher')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="field">
                <label for="files">Adjuntar comprobante y factura (PDF)</label>
                <input id="files" name="files[]" type="file" accept=".pdf" multiple style="height:auto;padding:8px">
                <span class="modal-note">Máx. 10 MB por archivo.</span>
            </div>
        </div>
        <div class="modal-foot">
            <a class="btn" href="{{ $close }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">Registrar pago</button>
        </div>
    </form>
</div>
