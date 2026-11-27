<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Patterns\Decorator\ExtraCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(ExtraCatalog $catalog): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('active', true)],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['string', 'distinct', Rule::in($catalog->codes())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Selecione um produto.',
            'product_id.integer' => 'Produto inválido.',
            'product_id.exists' => 'O produto selecionado não está disponível.',
            'extras.array' => 'Os complementos informados são inválidos.',
            'extras.*.string' => 'Complemento inválido.',
            'extras.*.distinct' => 'Cada complemento pode ser selecionado apenas uma vez.',
            'extras.*.in' => 'Complemento desconhecido.',
        ];
    }

    /** @return array<int, callable> */
    public function after(ExtraCatalog $catalog): array
    {
        return [function (Validator $validator) use ($catalog): void {
            if ($validator->errors()->has('product_id') || $validator->errors()->has('extras')) {
                return;
            }

            $product = Product::query()->find($this->input('product_id'));
            $allowed = $catalog->allowedFor($product->code);

            foreach ((array) $this->input('extras', []) as $index => $code) {
                if (is_string($code) && $catalog->find($code) !== null && ! isset($allowed[$code])) {
                    $validator->errors()->add("extras.$index", 'Este complemento não está disponível para o produto selecionado.');
                }
            }
        }];
    }
}
