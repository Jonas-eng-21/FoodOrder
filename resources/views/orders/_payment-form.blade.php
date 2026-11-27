<form method="POST" action="{{ route('orders.pay', $order) }}" id="payment-form" novalidate>
    @csrf
    <div class="alert alert-info" role="note">
        <strong>Pagamento simulado — nenhuma cobrança real será realizada.</strong>
        Nenhum dado de cartão ou de conta bancária é solicitado.
    </div>

    <fieldset class="mb-3" aria-describedby="payment-help">
        <legend class="h6 fw-bold">Forma de pagamento</legend>
        <p class="fo-muted small" id="payment-help">Subtotal do pedido: <span class="fo-money">{{ $order->formatted_subtotal }}</span></p>

        @error('method')<div class="text-danger fw-semibold mb-2" role="alert">{{ $message }}</div>@enderror

        <div class="form-check">
            <input class="form-check-input" type="radio" name="method" id="method-pix" value="pix" @checked(old('method') === 'pix')>
            <label class="form-check-label" for="method-pix"><strong>PIX</strong> — 5% de desconto</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="method" id="method-card" value="card" @checked(old('method') === 'card')>
            <label class="form-check-label" for="method-card"><strong>Cartão</strong> — valor integral</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="method" id="method-cash" value="cash" @checked(old('method') === 'cash')>
            <label class="form-check-label" for="method-cash"><strong>Dinheiro</strong> — valor integral, com troco</label>
        </div>
    </fieldset>

    <div class="mb-3" id="cash-field">
        <label class="form-label fw-semibold" for="received_amount">Valor recebido em dinheiro (R$)</label>
        <input class="form-control @error('received_amount') is-invalid @enderror" type="text" inputmode="decimal"
               name="received_amount" id="received_amount" value="{{ old('received_amount') }}" placeholder="30,00" autocomplete="off"
               aria-describedby="received-help @error('received_amount') received-error @enderror">
        @error('received_amount')<div class="invalid-feedback d-block" id="received-error">{{ $message }}</div>@enderror
        <div class="form-text" id="received-help">Necessário apenas para pagamento em dinheiro.</div>
    </div>

    <button type="submit" class="btn btn-primary w-100 w-sm-auto">Confirmar pagamento simulado</button>
</form>

<script>
    // Optional: show the cash field only for cash. Without JavaScript the field stays visible; the server validates everything.
    (function () {
        var field = document.getElementById('cash-field');
        var radios = document.querySelectorAll('#payment-form input[name="method"]');
        if (!field || !radios.length) { return; }
        function sync() {
            var cash = document.getElementById('method-cash').checked;
            field.hidden = !cash && !document.getElementById('received_amount').value;
        }
        radios.forEach(function (radio) { radio.addEventListener('change', sync); });
        sync();
    })();
</script>
