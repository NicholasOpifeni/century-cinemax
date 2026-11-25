-- Cinemax schema for MySQL 8 (InnoDB, utf8mb4).
-- Money is stored as whole Uganda shillings. Phone numbers are stored as 2567XXXXXXXX.
-- Loaded by bin/setup-db.php.

CREATE TABLE IF NOT EXISTS cinemas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(40)  NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  area        VARCHAR(160) NOT NULL,
  sort_order  SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY cinemas_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS halls (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  cinema_id    INT UNSIGNED     NOT NULL,
  number       TINYINT UNSIGNED NOT NULL,
  is_atmos     TINYINT(1)       NOT NULL DEFAULT 0,
  layout_json  JSON             NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY halls_cinema_number (cinema_id, number),
  CONSTRAINT halls_cinema_fk FOREIGN KEY (cinema_id) REFERENCES cinemas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per seat, generated from the hall's layout_json. grid_col places it on the map.
CREATE TABLE IF NOT EXISTS seats (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  hall_id      INT UNSIGNED     NOT NULL,
  row_label    CHAR(1)          NOT NULL,
  seat_number  TINYINT UNSIGNED NOT NULL,
  grid_col     TINYINT UNSIGNED NOT NULL,
  label        VARCHAR(4)       NOT NULL,
  type         ENUM('standard','recliner') NOT NULL DEFAULT 'standard',
  PRIMARY KEY (id),
  UNIQUE KEY seats_hall_label (hall_id, label),
  UNIQUE KEY seats_hall_row_number (hall_id, row_label, seat_number),
  CONSTRAINT seats_hall_fk FOREIGN KEY (hall_id) REFERENCES halls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Whether a film is now showing, coming soon or bookable comes from opens_on and ends_on.
CREATE TABLE IF NOT EXISTS films (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(80)       NOT NULL,
  tmdb_id         INT UNSIGNED      NULL,
  title           VARCHAR(160)      NOT NULL,
  synopsis        TEXT              NOT NULL,
  certificate     VARCHAR(10)       NOT NULL DEFAULT 'TBC',
  runtime_min     SMALLINT UNSIGNED NULL,
  genres          JSON              NOT NULL,
  languages       JSON              NOT NULL,
  formats         JSON              NOT NULL,
  director_label  VARCHAR(20)       NOT NULL DEFAULT 'Director',
  director        VARCHAR(160)      NOT NULL DEFAULT '',
  cast            JSON              NOT NULL,
  opens_on        DATE              NOT NULL,
  ends_on         DATE              NOT NULL,
  poster_path     VARCHAR(100)      NULL,
  backdrop_path   VARCHAR(100)      NULL,
  trailer_key     VARCHAR(20)       NULL,
  vote_average    DECIMAL(3,1)      NULL,
  vote_count      INT UNSIGNED      NOT NULL DEFAULT 0,
  accent_hex      CHAR(7)           NOT NULL DEFAULT '#C6CDD8',
  tone_hex        CHAR(7)           NOT NULL DEFAULT '#0C0D11',
  featured        TINYINT(1)        NOT NULL DEFAULT 0,
  sort_order      SMALLINT          NOT NULL DEFAULT 0,
  created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY films_slug (slug),
  UNIQUE KEY films_tmdb (tmdb_id),
  KEY films_dates (opens_on, ends_on),
  KEY films_featured (featured, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ends_at is the film's end. Back-to-back shows in a hall also leave 20 minutes to clean.
CREATE TABLE IF NOT EXISTS showtimes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  film_id     INT UNSIGNED NOT NULL,
  hall_id     INT UNSIGNED NOT NULL,
  starts_at   DATETIME     NOT NULL,
  ends_at     DATETIME     NOT NULL,
  format      ENUM('2D','3D','Dolby Atmos') NOT NULL DEFAULT '2D',
  language    VARCHAR(40)  NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY showtimes_hall_start (hall_id, starts_at),
  KEY showtimes_film_start (film_id, starts_at),
  KEY showtimes_start (starts_at),
  CONSTRAINT showtimes_film_fk FOREIGN KEY (film_id) REFERENCES films (id) ON DELETE CASCADE,
  CONSTRAINT showtimes_hall_fk FOREIGN KEY (hall_id) REFERENCES halls (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- day_of_week follows PHP's date('w'): 0 is Sunday, 6 is Saturday.
CREATE TABLE IF NOT EXISTS prices (
  day_of_week  TINYINT UNSIGNED NOT NULL,
  base_price   INT UNSIGNED     NOT NULL,
  PRIMARY KEY (day_of_week),
  CONSTRAINT prices_day_check CHECK (day_of_week <= 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- recliner_extra, atmos_extra, glasses_price, hold_minutes, max_seats, advance_days
CREATE TABLE IF NOT EXISTS settings (
  name   VARCHAR(40)  NOT NULL,
  value  VARCHAR(255) NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS snacks (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  description  VARCHAR(160) NOT NULL DEFAULT '',
  price        INT UNSIGNED NOT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  phone          CHAR(12)     NOT NULL,
  email          VARCHAR(190) NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_phone (phone),
  UNIQUE KEY users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(190) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ref is stored without the dash. seat_labels keeps "F7 F8" for the record after a cancel frees the seats.
CREATE TABLE IF NOT EXISTS bookings (
  id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  ref            CHAR(8)          NOT NULL,
  showtime_id    INT UNSIGNED     NOT NULL,
  user_id        INT UNSIGNED     NULL,
  phone          CHAR(12)         NOT NULL,
  status         ENUM('pending','paid','failed','expired','cancelled') NOT NULL DEFAULT 'pending',
  pay_method     ENUM('mtn','airtel','card') NOT NULL,
  gateway_ref    VARCHAR(64)      NULL,
  seat_labels    VARCHAR(64)      NOT NULL,
  seat_count     TINYINT UNSIGNED NOT NULL,
  own_glasses    TINYINT(1)       NOT NULL DEFAULT 0,
  seats_total    INT UNSIGNED     NOT NULL,
  glasses_total  INT UNSIGNED     NOT NULL DEFAULT 0,
  snacks_total   INT UNSIGNED     NOT NULL DEFAULT 0,
  total          INT UNSIGNED     NOT NULL,
  created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at        DATETIME         NULL,
  cancelled_at   DATETIME         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY bookings_ref (ref),
  UNIQUE KEY bookings_id_showtime (id, showtime_id),
  KEY bookings_showtime_status (showtime_id, status),
  KEY bookings_phone (phone),
  KEY bookings_status_created (status, created_at),
  CONSTRAINT bookings_showtime_fk FOREIGN KEY (showtime_id) REFERENCES showtimes (id),
  CONSTRAINT bookings_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The unique key on (showtime_id, seat_id) is what makes a double booking impossible.
-- The composite foreign key keeps each row on the same show as its booking.
CREATE TABLE IF NOT EXISTS booking_seats (
  booking_id   INT UNSIGNED NOT NULL,
  showtime_id  INT UNSIGNED NOT NULL,
  seat_id      INT UNSIGNED NOT NULL,
  price        INT UNSIGNED NOT NULL,
  PRIMARY KEY (booking_id, seat_id),
  UNIQUE KEY booking_seats_showtime_seat (showtime_id, seat_id),
  KEY booking_seats_seat (seat_id),
  CONSTRAINT booking_seats_booking_fk FOREIGN KEY (booking_id, showtime_id) REFERENCES bookings (id, showtime_id) ON DELETE CASCADE,
  CONSTRAINT booking_seats_showtime_fk FOREIGN KEY (showtime_id) REFERENCES showtimes (id),
  CONSTRAINT booking_seats_seat_fk FOREIGN KEY (seat_id) REFERENCES seats (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_snacks (
  booking_id  INT UNSIGNED     NOT NULL,
  snack_id    INT UNSIGNED     NOT NULL,
  quantity    TINYINT UNSIGNED NOT NULL,
  unit_price  INT UNSIGNED     NOT NULL,
  PRIMARY KEY (booking_id, snack_id),
  KEY booking_snacks_snack (snack_id),
  CONSTRAINT booking_snacks_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT booking_snacks_snack_fk FOREIGN KEY (snack_id) REFERENCES snacks (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seats held during checkout. hold_key groups one customer's seats and lives in their session.
-- booking_id is set when they start paying.
CREATE TABLE IF NOT EXISTS seat_holds (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  showtime_id  INT UNSIGNED NOT NULL,
  seat_id      INT UNSIGNED NOT NULL,
  hold_key     CHAR(32)     NOT NULL,
  booking_id   INT UNSIGNED NULL,
  expires_at   DATETIME     NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY seat_holds_showtime_seat (showtime_id, seat_id),
  KEY seat_holds_key (hold_key),
  KEY seat_holds_expires (expires_at),
  KEY seat_holds_booking (booking_id),
  CONSTRAINT seat_holds_showtime_fk FOREIGN KEY (showtime_id) REFERENCES showtimes (id) ON DELETE CASCADE,
  CONSTRAINT seat_holds_seat_fk FOREIGN KEY (seat_id) REFERENCES seats (id) ON DELETE CASCADE,
  CONSTRAINT seat_holds_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reminders (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  film_id     INT UNSIGNED NOT NULL,
  phone       CHAR(12)     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY reminders_film_phone (film_id, phone),
  CONSTRAINT reminders_film_fk FOREIGN KEY (film_id) REFERENCES films (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(190) NULL,
  phone       CHAR(12)     NULL,
  message     TEXT         NOT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY feedback_read_created (is_read, created_at),
  CONSTRAINT feedback_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only a hash of the reset token is stored, so a database leak can't be used to reset passwords.
CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY password_resets_token (token_hash),
  KEY password_resets_user (user_id),
  CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
