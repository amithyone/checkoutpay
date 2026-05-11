<?php

namespace App\Services\Whatsapp;

use App\Models\RentalItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Lightweight natural-language parser for rentals intents.
 *
 * It is intentionally heuristic (no external LLM dependency): we score active item
 * names/brands against user keywords and extract either explicit dates or weekday mentions.
 */
final class WhatsappRentalNlIntentParser
{
    /**
     * @return array{
     *   matched: bool,
     *   item_id?: int,
     *   item_label?: string,
     *   explicit_date?: string,
     *   weekday_mentioned?: bool,
     *   weekday_label?: string
     * }
     */
    public function parse(string $text): array
    {
        $clean = trim($text);
        if ($clean === '') {
            return ['matched' => false];
        }

        $match = $this->matchItem($clean);
        if (! $match) {
            return ['matched' => false];
        }

        $date = $this->extractExplicitDate($clean);
        if ($date !== null) {
            return [
                'matched' => true,
                'item_id' => (int) $match->id,
                'item_label' => (string) $match->name,
                'explicit_date' => $date->format('Y-m-d'),
                'weekday_mentioned' => false,
            ];
        }

        $weekday = $this->extractWeekdayMention($clean);
        if ($weekday !== null) {
            return [
                'matched' => true,
                'item_id' => (int) $match->id,
                'item_label' => (string) $match->name,
                'weekday_mentioned' => true,
                'weekday_label' => $weekday,
            ];
        }

        return [
            'matched' => true,
            'item_id' => (int) $match->id,
            'item_label' => (string) $match->name,
            'weekday_mentioned' => false,
        ];
    }

    private function matchItem(string $text): ?RentalItem
    {
        $tokens = $this->keywordTokens($text);
        if ($tokens === []) {
            return null;
        }

        $candidates = $this->candidateItems($tokens);
        if ($candidates->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $item) {
            $hay = mb_strtolower(trim(($item->brand ? $item->brand.' ' : '').$item->name));
            $score = 0;
            foreach ($tokens as $tok) {
                if (str_contains($hay, $tok)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $best = $item;
                $bestScore = $score;
            }
        }

        if (! $best) {
            return null;
        }

        // Require at least two token hits for confidence, unless the query has one strong token.
        if ($bestScore < 2 && count($tokens) > 1) {
            return null;
        }

        return $best;
    }

    /**
     * @param  list<string>  $tokens
     * @return Collection<int, RentalItem>
     */
    private function candidateItems(array $tokens): Collection
    {
        $q = RentalItem::query()
            ->where('is_active', true)
            ->where('is_available', true);

        $q->where(function ($sub) use ($tokens): void {
            foreach ($tokens as $tok) {
                $sub->orWhere('name', 'like', '%'.$tok.'%')
                    ->orWhere('brand', 'like', '%'.$tok.'%');
            }
        });

        return $q->limit(80)->get(['id', 'name', 'brand']);
    }

    /**
     * @return list<string>
     */
    private function keywordTokens(string $text): array
    {
        $norm = mb_strtolower($text);
        $norm = preg_replace('/[^a-z0-9\s\-]/i', ' ', $norm) ?? $norm;
        $bits = preg_split('/\s+/', trim($norm)) ?: [];
        $stop = [
            'i', 'want', 'need', 'rent', 'hire', 'book', 'for', 'on', 'the', 'a', 'an',
            'please', 'me', 'my', 'this', 'that', 'from', 'to', 'by', 'with', 'and',
            'next', 'coming', 'day', 'days',
        ];

        $out = [];
        foreach ($bits as $b) {
            $b = trim($b);
            if ($b === '' || in_array($b, $stop, true)) {
                continue;
            }
            if (mb_strlen($b) < 2) {
                continue;
            }
            $out[] = $b;
        }

        return array_values(array_unique($out));
    }

    private function extractExplicitDate(string $text): ?CarbonInterface
    {
        if (preg_match('/\b(20\d{2})-(\d{2})-(\d{2})\b/', $text, $m) === 1) {
            $raw = $m[0];
            try {
                return Carbon::parse($raw)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function extractWeekdayMention(string $text): ?string
    {
        $weekdayMap = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];

        $t = mb_strtolower($text);
        foreach ($weekdayMap as $raw => $label) {
            if (preg_match('/\b'.$raw.'\b/u', $t) === 1) {
                return $label;
            }
        }

        return null;
    }
}
