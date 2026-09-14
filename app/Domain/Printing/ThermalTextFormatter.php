<?php

namespace App\Domain\Printing;

class ThermalTextFormatter
{
    public readonly int $width;

    public function __construct(int $paperWidth)
    {
        $this->width = $paperWidth <= 58 ? 32 : 48;
    }

    public function separator(string $character = '-'): string
    {
        return str_repeat($character, $this->width);
    }

    public function center(string $text): string
    {
        $text = $this->truncate(trim($text), $this->width);
        $padding = max(0, intdiv($this->width - mb_strwidth($text), 2));

        return str_repeat(' ', $padding).$text;
    }

    public function columns(string $left, string $right): string
    {
        $right = $this->truncate(trim($right), $this->width);
        $available = max(0, $this->width - mb_strwidth($right) - 1);
        $left = $this->truncate(trim($left), $available);
        $spaces = max(1, $this->width - mb_strwidth($left) - mb_strwidth($right));

        return $left.str_repeat(' ', $spaces).$right;
    }

    /** @return array<int, string> */
    public function wrap(string $text, int $indent = 0): array
    {
        $prefix = str_repeat(' ', min($indent, $this->width - 1));
        $lineWidth = $this->width - mb_strwidth($prefix);
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            foreach ($this->wordChunks($word, $lineWidth) as $chunk) {
                $candidate = $current === '' ? $chunk : "{$current} {$chunk}";

                if (mb_strwidth($candidate) > $lineWidth) {
                    $lines[] = $prefix.$current;
                    $current = $chunk;
                } else {
                    $current = $candidate;
                }
            }
        }

        if ($current !== '') {
            $lines[] = $prefix.$current;
        }

        return $lines ?: [$prefix];
    }

    public function money(int $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }

    private function truncate(string $text, int $width): string
    {
        return $width <= 0 ? '' : mb_strimwidth($text, 0, $width, '');
    }

    /** @return array<int, string> */
    private function wordChunks(string $word, int $width): array
    {
        $chunks = [];

        while (mb_strwidth($word) > $width) {
            $chunk = mb_strimwidth($word, 0, $width, '');
            $chunks[] = $chunk;
            $word = mb_substr($word, mb_strlen($chunk));
        }

        if ($word !== '') {
            $chunks[] = $word;
        }

        return $chunks;
    }
}
