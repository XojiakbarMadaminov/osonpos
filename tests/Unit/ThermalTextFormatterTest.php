<?php

use App\Domain\Printing\ThermalTextFormatter;

it('uses standard character widths for thermal paper', function () {
    expect((new ThermalTextFormatter(58))->width)->toBe(32)
        ->and((new ThermalTextFormatter(80))->width)->toBe(48);
});

it('aligns labels and values without exceeding paper width', function () {
    $format = new ThermalTextFormatter(58);
    $line = $format->columns('JAMI UZS', $format->money(65000));

    expect(mb_strwidth($line))->toBe(32)
        ->and($line)->toStartWith('JAMI UZS')
        ->and($line)->toEndWith('65 000');
});

it('wraps long product names to the selected paper width', function () {
    $format = new ThermalTextFormatter(58);
    $lines = $format->wrap('Double cheeseburger with special house sauce');

    expect($lines)->toHaveCount(2)
        ->and(collect($lines)->every(fn (string $line): bool => mb_strwidth($line) <= 32))->toBeTrue();
});
