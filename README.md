# Cinemax

An unofficial concept redesign of Century Cinemax Uganda's movie booking site, built in PHP and MySQL as a portfolio project. It isn't affiliated with or endorsed by Century Cinemax, and payments are a demo. No money is taken.

This README is a stub for now. Full setup notes, features and screenshots are coming.

## Quick start

You need MAMP (for MySQL on port 8889 and its PHP) and Composer.

```sh
composer install
cp config/secrets.example.php config/secrets.php   # then fill it in
bin/php bin/setup-db.php                           # creates the cinemax database
bin/serve                                          # http://localhost:8090
```

`bin/php` runs MAMP's newest PHP 8, so you don't need it on your PATH.

## Credits

- Geist by Vercel, under the SIL Open Font License.
- Film data and images from TMDB. This product uses the TMDB API but is not endorsed or certified by TMDB.
