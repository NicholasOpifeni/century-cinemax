<?php
declare(strict_types=1);

namespace App;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use DateTimeImmutable;
use DateTimeZone;

/** Everything a ticket shows: the booking with its show, film, cinema and snacks. */
final class Tickets
{
    public const PAY_METHODS = ['mtn' => 'MTN MoMo', 'airtel' => 'Airtel Money', 'card' => 'card'];

    /** A paid or cancelled booking by reference. Bookings that were never paid have no ticket. */
    public static function find(string $ref): ?array
    {
        $ticket = db_run(
            "SELECT b.id, b.ref, b.phone, b.status, b.pay_method, b.seat_labels, b.seat_count, b.total, b.paid_at, b.cancelled_at,
                    s.starts_at, s.ends_at, s.format, s.language, h.number AS hall, c.name AS cinema_name, c.area AS cinema_area,
                    f.slug AS film_slug
             FROM bookings b
             JOIN showtimes s ON s.id = b.showtime_id
             JOIN halls h ON h.id = s.hall_id
             JOIN cinemas c ON c.id = h.cinema_id
             JOIN films f ON f.id = s.film_id
             WHERE b.ref = ? AND b.status IN ('paid', 'cancelled')",
            [$ref],
        )->fetch();
        if (!$ticket) {
            return null;
        }
        $ticket['starts_at'] = new DateTimeImmutable($ticket['starts_at']);
        $ticket['ends_at'] = new DateTimeImmutable($ticket['ends_at']);
        $ticket['film'] = Films::bySlug($ticket['film_slug']);
        $ticket['snacks'] = db_run(
            'SELECT sn.name, bs.quantity FROM booking_snacks bs JOIN snacks sn ON sn.id = bs.snack_id WHERE bs.booking_id = ? ORDER BY sn.sort_order',
            [$ticket['id']],
        )->fetchAll();
        $ticket['url'] = app_url(Bookings::ticketPath($ticket['ref']));
        return $ticket;
    }

    /** The QR code for the entrance, as inline SVG. It holds the full ticket link. */
    public static function qr(string $url): string
    {
        $options = new QROptions([
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
            'eccLevel' => EccLevel::M,
            'quietzoneSize' => 2,
            'cssClass' => 'qr',
        ]);
        return trim((new QRCode($options))->render($url));
    }

    /** "2 × popcorn, salted, 1 × soda" */
    public static function snackLine(array $ticket): string
    {
        return implode(', ', array_map(fn (array $s): string => $s['quantity'] . ' × ' . mb_strtolower($s['name']), $ticket['snacks']));
    }

    /** A message with the ticket link, for wa.me. */
    public static function whatsappUrl(array $ticket): string
    {
        $text = sprintf(
            'My Cinemax ticket for %s, %s at %s, %s: %s',
            $ticket['film']['title'],
            d_short($ticket['starts_at']),
            $ticket['starts_at']->format('H:i'),
            $ticket['cinema_name'],
            $ticket['url'],
        );
        return 'https://wa.me/?text=' . rawurlencode($text);
    }

    /**
     * The show as an iCalendar file, in Kampala time. It ends when the film does plus the
     * 15 minutes of adverts and trailers, and reminds you an hour before.
     */
    public static function calendar(array $ticket): string
    {
        $zone = 'Africa/Kampala';
        $local = fn (DateTimeImmutable $t): string => $t->setTimezone(new DateTimeZone($zone))->format('Ymd\THis');
        $film = $ticket['film'];
        $host = parse_url(app_url('/'), PHP_URL_HOST) ?: 'cinemax.local';
        $ref = Bookings::formatRef($ticket['ref']);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Cinemax//Tickets//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VTIMEZONE',
            'TZID:' . $zone,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0300',
            'TZOFFSETTO:+0300',
            'TZNAME:EAT',
            'END:STANDARD',
            'END:VTIMEZONE',
            'BEGIN:VEVENT',
            'UID:' . $ticket['ref'] . '@' . $host,
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            "DTSTART;TZID=$zone:" . $local($ticket['starts_at']),
            "DTEND;TZID=$zone:" . $local($ticket['ends_at']->modify('+15 minutes')),
            'SUMMARY:' . self::icsText($film['title'] . ' at Century Cinemax'),
            'LOCATION:' . self::icsText('Century Cinemax, ' . $ticket['cinema_area'] . ', Kampala'),
            'DESCRIPTION:' . self::icsText(
                'Hall ' . $ticket['hall'] . ', ' . format_label($ticket['format']) . '. Seats ' . $ticket['seat_labels'] . '. Ticket ' . $ref . ".\n"
                . 'Arrive 15 minutes early and show your ticket at the entrance: ' . $ticket['url']
            ),
            'URL:' . $ticket['url'],
            'BEGIN:VALARM',
            'TRIGGER:-PT1H',
            'ACTION:DISPLAY',
            'DESCRIPTION:' . self::icsText($film['title'] . ' starts in an hour'),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    /** Escapes text for an iCalendar value. */
    private static function icsText(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $text);
    }

    /** Lines longer than 75 bytes continue on the next line after a space, without splitting a character. */
    private static function fold(string $line): string
    {
        $parts = [];
        while (strlen($line) > ($parts ? 74 : 75)) {
            $chunk = mb_strcut($line, 0, $parts ? 74 : 75, 'UTF-8');
            $parts[] = $chunk;
            $line = substr($line, strlen($chunk));
        }
        $parts[] = $line;
        return implode("\r\n ", $parts);
    }
}
