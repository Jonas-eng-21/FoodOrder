<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayOrderRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            // Reais with up to 2 decimals ("30", "30.5", "30,50"); no sign, exponent or thousands separator.
            'received_amount' => ['required_if:method,cash', 'nullable', 'string', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'method.required' => 'Selecione a forma de pagamento.',
            'method.enum' => 'Forma de pagamento inválida.',
            'received_amount.required_if' => 'Informe o valor recebido para pagamento em dinheiro.',
            'received_amount.string' => 'Valor recebido inválido.',
            'received_amount.regex' => 'Informe o valor recebido em reais, com até 2 casas decimais (ex.: 30,00).',
        ];
    }

    public function method(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('method'));
    }

    /** Received amount in cents; only meaningful for cash, ignored for the other methods. */
    public function receivedCents(): ?int
    {
        $received = $this->validated('received_amount');

        return $this->method() === PaymentMethod::Cash && $received !== null ? Money::parseReais($received) : null;
    }
}
