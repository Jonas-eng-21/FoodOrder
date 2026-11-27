<?php

namespace Tests\Unit\Adapter;

use App\Exceptions\InvalidPaymentException;
use App\Patterns\Adapter\CardGatewayAdapter;
use App\Patterns\Adapter\CashPaymentGateway;
use App\Patterns\Adapter\LegacyCardGateway;
use App\Patterns\Adapter\LegacyPixGateway;
use App\Patterns\Adapter\PaymentGateway;
use App\Patterns\Adapter\PixGatewayAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GatewayAdaptersTest extends TestCase
{
    private function pixSpy(array $response): LegacyPixGateway
    {
        return new class($response) extends LegacyPixGateway
        {
            /** @var list<int> */
            public array $received = [];

            public function __construct(private readonly array $response) {}

            public function sendPix(int $amountCents): array
            {
                $this->received[] = $amountCents;

                return $this->response;
            }
        };
    }

    private function cardSpy(string $response): LegacyCardGateway
    {
        return new class($response) extends LegacyCardGateway
        {
            /** @var list<string> */
            public array $received = [];

            public function __construct(private readonly string $response) {}

            public function charge(string $amountDecimal): string
            {
                $this->received[] = $amountDecimal;

                return $this->response;
            }
        };
    }

    public function test_legacy_gateways_have_incompatible_contracts(): void
    {
        $pix = (new LegacyPixGateway)->sendPix(2565);
        $card = (new LegacyCardGateway)->charge('27.00');

        $this->assertIsArray($pix);
        $this->assertIsString($card);
        $this->assertNotInstanceOf(PaymentGateway::class, new LegacyPixGateway);
        $this->assertNotInstanceOf(PaymentGateway::class, new LegacyCardGateway);
    }

    public function test_all_gateways_share_the_target_contract(): void
    {
        foreach ([new PixGatewayAdapter(new LegacyPixGateway), new CardGatewayAdapter(new LegacyCardGateway), new CashPaymentGateway] as $gateway) {
            $this->assertInstanceOf(PaymentGateway::class, $gateway);

            $result = $gateway->charge(2700);

            $this->assertTrue($result->approved);
            $this->assertStringStartsWith('SIMULATED-', $result->reference);
            $this->assertNull($result->error);
        }
    }

    public function test_pix_adapter_sends_cents_to_the_legacy_gateway_and_translates_approval(): void
    {
        $legacy = $this->pixSpy(['status' => 'CONFIRMED', 'txid' => 'SIMULATED-PIX-1', 'amount_cents' => 2565]);

        $result = (new PixGatewayAdapter($legacy))->charge(2565);

        $this->assertSame([2565], $legacy->received);
        $this->assertTrue($result->approved);
        $this->assertSame('SIMULATED-PIX-1', $result->reference);
    }

    public function test_pix_adapter_translates_rejection(): void
    {
        $result = (new PixGatewayAdapter(new LegacyPixGateway(reject: true)))->charge(2565);

        $this->assertFalse($result->approved);
        $this->assertNull($result->reference);
        $this->assertNotEmpty($result->error);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unexpectedPixResponses(): array
    {
        return [
            'empty' => [[]],
            'unknown status' => [['status' => 'MAYBE', 'txid' => 'SIMULATED-PIX-1', 'amount_cents' => 100]],
            'missing txid' => [['status' => 'CONFIRMED', 'amount_cents' => 100]],
            'empty txid' => [['status' => 'CONFIRMED', 'txid' => '', 'amount_cents' => 100]],
            'non string txid' => [['status' => 'CONFIRMED', 'txid' => 12, 'amount_cents' => 100]],
            'lowercase status' => [['status' => 'confirmed', 'txid' => 'SIMULATED-PIX-1', 'amount_cents' => 100]],
            'amount mismatch' => [['status' => 'CONFIRMED', 'txid' => 'SIMULATED-PIX-1', 'amount_cents' => 99]],
            'missing amount' => [['status' => 'CONFIRMED', 'txid' => 'SIMULATED-PIX-1']],
        ];
    }

    /** @param array<string, mixed> $response */
    #[DataProvider('unexpectedPixResponses')]
    public function test_pix_adapter_never_approves_unexpected_responses(array $response): void
    {
        $result = (new PixGatewayAdapter($this->pixSpy($response)))->charge(100);

        $this->assertFalse($result->approved);
        $this->assertNull($result->reference);
    }

    /** @return array<string, array{int, string}> */
    public static function decimals(): array
    {
        return [
            '2700' => [2700, '27.00'],
            '2565' => [2565, '25.65'],
            '2001' => [2001, '20.01'],
            '105' => [105, '1.05'],
            '10' => [10, '0.10'],
            '1' => [1, '0.01'],
            '0' => [0, '0.00'],
            '123456' => [123456, '1234.56'],
        ];
    }

    #[DataProvider('decimals')]
    public function test_card_adapter_converts_cents_to_a_decimal_string_without_floats(int $cents, string $decimal): void
    {
        $legacy = $this->cardSpy('APPROVED|SIMULATED-CARD-1|'.$decimal);

        $result = (new CardGatewayAdapter($legacy))->charge($cents);

        $this->assertSame([$decimal], $legacy->received);
        $this->assertTrue($result->approved);
        $this->assertSame('SIMULATED-CARD-1', $result->reference);
    }

    public function test_card_adapter_translates_rejection(): void
    {
        $result = (new CardGatewayAdapter(new LegacyCardGateway(reject: true)))->charge(2700);

        $this->assertFalse($result->approved);
        $this->assertNull($result->reference);
        $this->assertNotEmpty($result->error);
    }

    /** @return array<string, array{string}> */
    public static function unexpectedCardResponses(): array
    {
        return [
            'empty' => [''],
            'garbage' => ['GARBAGE'],
            'approved without reference' => ['APPROVED||27.00'],
            'approved without amount' => ['APPROVED|SIMULATED-CARD-1'],
            'amount mismatch' => ['APPROVED|SIMULATED-CARD-1|99.99'],
            'lowercase' => ['approved|SIMULATED-CARD-1|27.00'],
            'extra fields' => ['APPROVED|SIMULATED-CARD-1|27.00|x'],
        ];
    }

    #[DataProvider('unexpectedCardResponses')]
    public function test_card_adapter_never_approves_unexpected_responses(string $response): void
    {
        $result = (new CardGatewayAdapter($this->cardSpy($response)))->charge(2700);

        $this->assertFalse($result->approved);
        $this->assertNull($result->reference);
    }

    public function test_adapters_reject_negative_amounts_before_calling_the_legacy_gateway(): void
    {
        $pix = $this->pixSpy([]);
        $card = $this->cardSpy('');

        foreach ([new PixGatewayAdapter($pix), new CardGatewayAdapter($card)] as $adapter) {
            try {
                $adapter->charge(-1);
                $this->fail('Expected InvalidPaymentException.');
            } catch (InvalidPaymentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], $pix->received);
        $this->assertSame([], $card->received);
    }

    public function test_gateways_do_not_use_the_network(): void
    {
        foreach (glob(dirname(__DIR__, 3).'/app/Patterns/Adapter/*.php') as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/curl_|fsockopen|stream_socket|Http::|Guzzle|file_get_contents\s*\(\s*[\'"]http/i',
                file_get_contents($file),
                basename($file).' must not use the network',
            );
        }
    }
}
