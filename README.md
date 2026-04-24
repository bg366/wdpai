# SafeCity

SafeCity to aplikacja PHP do zgłaszania i obsługi incydentów miejskich. Repozytorium zawiera działający przepływ logowania, role `citizen` i `admin`, dashboard, moduł incydentów oraz bazę PostgreSQL uruchamianą w Dockerze.

## Co działa

- autoryzacja: logowanie, rejestracja i wylogowanie,
- role użytkowników: `citizen` i `admin`,
- dashboard dla obu ról,
- incydenty: lista, zgłoszenie i widok szczegółów,
- panel admina: zarządzanie rolą użytkownika,
- warstwa SQL: tabele historii statusów incydentu i transakcje po stronie repozytorium,
- seed danych startowych dla kont testowych i słowników,
- uruchomienie lokalne przez Docker Compose.

## Funkcje aplikacji

- `citizen` może się zarejestrować, zalogować, zobaczyć dashboard, zgłosić incydent i śledzić jego status oraz historię,
- `admin` ma dostęp do panelu administracyjnego, listy incydentów, szczegółów zgłoszeń i zmiany roli użytkowników,
- incydenty mają kategorię, lokalizację, opis, status, komentarze i historię zmian statusu,
- zmiana statusu zapisuje wpis w `incident_status_history` w ramach transakcji,
- dashboard korzysta z widoków SQL do prezentacji statystyk i aktywności.

## Uruchomienie

Wymagania:

- Docker Desktop lub Docker Engine,
- Docker Compose v2.

Start:

```bash
docker compose up --build
```

Adresy po starcie:

- aplikacja: `http://localhost:8080`,
- pgAdmin: `http://localhost:5050`,
- PostgreSQL z hosta: `localhost:5433`.

Jeśli chcesz zresetować dane:

```bash
docker compose down -v
docker compose up --build
```

## Dane dostępowe

pgAdmin:

- email: `admin@example.com`,
- hasło: `admin`.

Konta seedowane w bazie:

| Rola | Email | Hasło |
|---|---|---|
| admin | `admin@safecity.pl` | `password` |
| citizen | `jan@safecity.pl` | `password` |

Dane połączeniowe do PostgreSQL:

| Parametr | Wartość |
|---|---|
| host z kontenerów | `db` |
| host z maszyny lokalnej | `localhost` |
| port z kontenerów | `5432` |
| port z hosta | `5433` |
| baza | `db` |
| user | `docker` |
| hasło | `docker` |

## Baza danych

Najważniejsze tabele:

- `roles`
- `users`
- `incident_statuses`
- `incident_categories`
- `incidents`
- `incident_comments`
- `incident_status_history`

Najważniejsze widoki i elementy SQL:

- `incidents_summary`,
- `dashboard_stats`,
- funkcja `update_timestamp()`,
- funkcja `user_incident_count(uid, target_month)`,
- trigger `trg_incidents_updated`.

Szczegóły schematu są opisane w `docs/erd.md`.

## Lista wymagań

- [x] logowanie i rejestracja,
- [x] sesja użytkownika i CSRF,
- [x] role `citizen` i `admin`,
- [x] dashboard,
- [x] lista incydentów,
- [x] zgłoszenie incydentu,
- [x] widok szczegółów incydentu,
- [x] panel admina,
- [x] zarządzanie rolą użytkownika,
- [x] historia statusów incydentu,
- [x] transakcje w repozytorium SQL,
- [x] seed kont testowych i danych startowych,
- [x] uruchomienie przez Docker Compose.
