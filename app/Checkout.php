<?php
declare(strict_types=1);

namespace App;

/** Snacks, glasses and prices for the checkout page. The server always works totals out itself. */
final class Checkout
{
    public const MAX_SNACKS = 9; // of each kind, as on the mockup's steppers

    /** @return list<array{id: int, name: string, description: string, price: int}> */
    public static function snacks(): array
    {
        static $snacks = null;
        return $snacks ??= array_map(fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'description' => $r['description'],
            'price' => (int) $r['price'],
        ], db()->query('SELECT id, name, description, price FROM snacks WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll());
    }

    /**
     * Seats with their labels and types, front row first.
     *
     * @param list<int> $seatIds
     * @return list<array{id: int, label: string, type: string}>
     */
    public static function seats(array $seatIds): array
    {
        if (!$seatIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($seatIds), '?'));
        return array_map(
            fn (array $r): array => ['id' => (int) $r['id'], 'label' => $r['label'], 'type' => $r['type']],
            db_run("SELECT id, label, type FROM seats WHERE id IN ($in) ORDER BY row_label, seat_number", $seatIds)->fetchAll(),
        );
    }

    /** Snack quantities from a form, kept between 0 and MAX_SNACKS, for active snacks only. */
    public static function quantities(mixed $input): array
    {
        $quantities = [];
        foreach (self::snacks() as $snack) {
            $value = is_array($input) ? ($input[$snack['id']] ?? 0) : 0;
            $quantities[$snack['id']] = is_numeric($value) ? max(0, min(self::MAX_SNACKS, (int) $value)) : 0;
        }
        return $quantities;
    }

    /**
     * The order's lines and totals.
     *
     * @param array{starts_at: \DateTimeImmutable, format: string} $show
     * @param list<array{type: string}> $seats
     * @param array<int, int> $quantities snack id => how many
     * @return array{lines: list<array{key: string, label: string, amount: int}>, seats_total: int, glasses_total: int,
     *   snacks_total: int, total: int, snacks: list<array{snack_id: int, quantity: int, unit_price: int}>}
     */
    public static function quote(array $show, array $seats, array $quantities, bool $ownGlasses): array
    {
        $lines = [];
        $seatsTotal = 0;
        foreach (['standard' => 'normal seat', 'recliner' => 'recliner'] as $type => $name) {
            $count = count(array_filter($seats, fn (array $s): bool => $s['type'] === $type));
            if ($count) {
                $amount = $count * Pricing::seatPrice($show['starts_at'], $show['format'], $type);
                $lines[] = ['key' => $type, 'label' => "$count × $name", 'amount' => $amount];
                $seatsTotal += $amount;
            }
        }

        $glassesTotal = 0;
        if ($show['format'] === '3D' && !$ownGlasses) {
            $glassesTotal = count($seats) * Settings::int('glasses_price');
            $lines[] = ['key' => 'glasses', 'label' => count($seats) . ' × 3D glasses', 'amount' => $glassesTotal];
        }

        $snackRows = [];
        $snacksTotal = 0;
        foreach (self::snacks() as $snack) {
            $quantity = $quantities[$snack['id']] ?? 0;
            if ($quantity > 0) {
                $amount = $quantity * $snack['price'];
                $snackRows[] = ['snack_id' => $snack['id'], 'quantity' => $quantity, 'unit_price' => $snack['price']];
                $lines[] = ['key' => 'snack-' . $snack['id'], 'label' => "$quantity × " . mb_strtolower($snack['name']), 'amount' => $amount];
                $snacksTotal += $amount;
            }
        }

        return [
            'lines' => $lines,
            'seats_total' => $seatsTotal,
            'glasses_total' => $glassesTotal,
            'snacks_total' => $snacksTotal,
            'total' => $seatsTotal + $glassesTotal + $snacksTotal,
            'snacks' => $snackRows,
        ];
    }
}
