-- ============================================================
-- SafeCity — schema inicjalizacyjny
-- ============================================================

-- ------------------------------------------------------------
-- Tabele
-- ------------------------------------------------------------

CREATE TABLE roles (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255)        NOT NULL,
    full_name     VARCHAR(100)        NOT NULL,
    role_id       INT                 NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
    created_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE incident_statuses (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE incident_categories (
    id    SERIAL PRIMARY KEY,
    name  VARCHAR(100) NOT NULL,
    icon  VARCHAR(50)  NOT NULL,
    color VARCHAR(7)   NOT NULL
);

CREATE TABLE incidents (
    id          SERIAL PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT         NOT NULL,
    location    VARCHAR(255) NOT NULL,
    category_id INT REFERENCES incident_categories(id) ON DELETE SET NULL,
    reported_by INT REFERENCES users(id)               ON DELETE SET NULL,
    status_id   INT NOT NULL REFERENCES incident_statuses(id) ON DELETE RESTRICT,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE incident_comments (
    id          SERIAL PRIMARY KEY,
    incident_id INT  NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
    user_id     INT  REFERENCES users(id)              ON DELETE SET NULL,
    body        TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE incident_status_history (
    id             SERIAL PRIMARY KEY,
    incident_id    INT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
    from_status_id INT REFERENCES incident_statuses(id) ON DELETE SET NULL,
    to_status_id   INT NOT NULL REFERENCES incident_statuses(id) ON DELETE RESTRICT,
    changed_by     INT REFERENCES users(id) ON DELETE SET NULL,
    note           TEXT,
    created_at     TIMESTAMP DEFAULT NOW()
);

-- ------------------------------------------------------------
-- Widoki (VIEWs)
-- ------------------------------------------------------------

CREATE VIEW incidents_summary AS
SELECT
    i.id,
    i.title,
    i.description,
    i.location,
    i.created_at,
    i.updated_at,
    c.id    AS category_id,
    c.name  AS category_name,
    c.icon  AS category_icon,
    c.color AS category_color,
    s.id    AS status_id,
    s.name  AS status_name,
    u.id    AS reporter_id,
    u.full_name AS reporter_name,
    u.email AS reporter_email
FROM incidents i
LEFT JOIN incident_categories c ON c.id = i.category_id
JOIN  incident_statuses s ON s.id = i.status_id
LEFT JOIN users u ON u.id = i.reported_by;

CREATE VIEW dashboard_stats AS
SELECT
    COUNT(*) FILTER (WHERE s.name = 'new')         AS new_count,
    COUNT(*) FILTER (WHERE s.name = 'in_progress') AS in_progress_count,
    COUNT(*) FILTER (WHERE s.name = 'resolved')    AS resolved_count,
    COUNT(*) FILTER (WHERE s.name = 'rejected')    AS rejected_count,
    COUNT(*)                                        AS total
FROM incidents i
JOIN incident_statuses s ON s.id = i.status_id;

-- ------------------------------------------------------------
-- Funkcje i triggery
-- ------------------------------------------------------------

CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_incidents_updated
BEFORE UPDATE ON incidents
FOR EACH ROW
EXECUTE FUNCTION update_timestamp();

CREATE INDEX idx_incidents_reported_by ON incidents(reported_by);
CREATE INDEX idx_incidents_status_id ON incidents(status_id);
CREATE INDEX idx_incident_status_history_incident_id ON incident_status_history(incident_id, created_at DESC);

-- Zwraca liczbę incydentów użytkownika w danym miesiącu
CREATE OR REPLACE FUNCTION user_incident_count(uid INT, target_month DATE)
RETURNS INT AS $$
    SELECT COUNT(*)::INT
    FROM incidents
    WHERE reported_by = uid
      AND DATE_TRUNC('month', created_at) = DATE_TRUNC('month', target_month::TIMESTAMP);
$$ LANGUAGE sql;

-- ------------------------------------------------------------
-- Dane startowe (seed)
-- ------------------------------------------------------------

INSERT INTO roles (name) VALUES ('citizen'), ('admin');

INSERT INTO incident_statuses (name) VALUES
    ('new'),
    ('in_progress'),
    ('resolved'),
    ('rejected');

INSERT INTO incident_categories (name, icon, color) VALUES
    ('Pożar',                'fire',         '#e74c3c'),
    ('Wypadek drogowy',      'car-crash',    '#e67e22'),
    ('Walka uliczna',        'fight',        '#9b59b6'),
    ('Powódź',               'flood',        '#3498db'),
    ('Awaria infrastruktury','construction', '#95a5a6'),
    ('Kradzież',             'theft',        '#f39c12'),
    ('Wandalizm',            'vandalism',    '#1abc9c'),
    ('Inne',                 'other',        '#7f8c8d');

-- Hasło obu kont: "password"
-- Hash wygenerowany przez: password_hash('password', PASSWORD_BCRYPT)
INSERT INTO users (email, password_hash, full_name, role_id) VALUES
    ('admin@safecity.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 2),
    ('jan@safecity.pl',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jan Kowalski',  1);

-- Przykładowe incydenty
INSERT INTO incidents (title, description, location, category_id, reported_by, status_id) VALUES
    ('Pożar w budynku mieszkalnym', 'Silny dym wydobywa się z okien na 3. piętrze.',          'ul. Lipowa 12, Kraków',  1, 2, 1),
    ('Kolizja na skrzyżowaniu',     'Dwa samochody zderzyły się, zablokowana prawa jezdnia.', 'ul. Główna 5, Kraków',   2, 2, 2),
    ('Uszkodzona latarnia',         'Latarnia uliczna nie świeci od tygodnia.',                'ul. Różana 3, Kraków',   5, 2, 3);

INSERT INTO incident_comments (incident_id, user_id, body) VALUES
    (1, 1, 'Jednostka straży pożarnej została zadysponowana.'),
    (2, 1, 'Służby na miejscu, ruch przywrócony.');

INSERT INTO incident_status_history (incident_id, from_status_id, to_status_id, changed_by, note) VALUES
    (1, NULL, 1, 2, 'Zgłoszenie zostało utworzone przez obywatela.'),
    (2, NULL, 1, 2, 'Zgłoszenie zostało utworzone przez obywatela.'),
    (2, 1, 2, 1, 'Administrator przekazał sprawę do obsługi.'),
    (3, NULL, 1, 2, 'Zgłoszenie zostało utworzone przez obywatela.'),
    (3, 1, 3, 1, 'Awaria została oznaczona jako rozwiązana.');
