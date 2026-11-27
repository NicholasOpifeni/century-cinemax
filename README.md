# Cinemax

An unofficial concept redesign of Century Cinemax Uganda's movie booking site, built in PHP and MySQL as a portfolio project. It isn't affiliated with or endorsed by Century Cinemax, and payments are a demo. No money is taken.

This README is a stub for now. Full setup notes, features and screenshots are coming.

## Quick start

You need MAMP (for MySQL on port 8889 and its PHP) and Composer.

```sh
composer install
cp config/secrets.example.php config/secrets.php   # then fill it in, including your TMDB token
bin/php bin/setup-db.php                           # creates the cinemax database
bin/php bin/import-films.php                       # films, posters, trailers and colours from TMDB
bin/php database/seed.php                          # cinemas, halls, seats, prices, snacks, admin
bin/php bin/generate-showtimes.php                 # two weeks of shows from today
bin/serve                                          # http://localhost:8090
```

Showtimes only run two weeks ahead, so run `bin/generate-showtimes.php` again now and then. It skips days that already have shows.

To make the halls look busy, `bin/php bin/seed-demo-bookings.php` fills upcoming shows with made-up paid bookings, 10 to 45 percent of the seats. They all use the phone number 256700000000, and `--clear` removes them again.

`bin/php` runs MAMP's newest PHP 8, so you don't need it on your PATH.

The admin is at http://localhost:8090/admin/. Log in with `admin_email` and `admin_password` from `config/secrets.php`; `database/seed.php` creates that account.

There's no mail server. When someone asks to reset their password, the reset link is written to `storage/logs/mail.log` instead of being emailed, and the page tells them to ask staff for it. Open that file to find the link.

## Credits

- Geist by Vercel, under the SIL Open Font License.
- Film data and images from TMDB. This product uses the TMDB API but is not endorsed or certified by TMDB.
