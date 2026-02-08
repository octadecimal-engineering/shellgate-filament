# PLAN: Uproszczenie instalacji ShellGate

## Cel

Zredukować instalację z 8-10 manualnych kroków do:

```bash
composer require octadecimalhq/shellgate        # 1. Install
php artisan shellgate:install                    # 2. Setup (config, migrations, .env, gateway, AdminPanelProvider)
php artisan shellgate:serve                      # 3. Start gateway
# → visit /admin/terminal
```

---

## Stan obecny (PRZED)

Użytkownik musi:
1. Dodać Anystack repo do composer.json
2. `composer require octadecimalhq/shellgate`
3. `php artisan vendor:publish --tag=shell-gate-config`
4. `php artisan migrate`
5. `php artisan vendor:publish --tag=shell-gate-user-migration` + `php artisan migrate`
6. Ręcznie edytować `app/Models/User.php` (dodać cast `is_super_admin`)
7. Ręcznie edytować `AdminPanelProvider.php` (rejestracja pluginu)
8. `cd vendor/octadecimalhq/shellgate/gateway && cp .env.example .env`
9. Ręcznie ustawić `JWT_SECRET` w gateway `.env`
10. `npm install && npm start` w katalogu gateway
11. Dodać zmienne do `.env` Laravela (SHELL_GATE_GATEWAY_URL, licencja)

---

## Zadania

### ETAP 1: `php artisan shellgate:install`

- [x] **1.1** Nowa klasa `src/Console/InstallCommand.php`
  - Signature: `shellgate:install {--dev} {--no-migrate} {--no-gateway}`
  - Kolejność operacji:
    1. Publish config (`shell-gate-config`) jeśli `config/shell-gate.php` nie istnieje
    2. Run `php artisan migrate` (jeśli nie `--no-migrate`)
    3. Auto-patch `AdminPanelProvider.php` — dodać `ShellGatePlugin::make()` do `->plugins()` lub `->plugin()` (jak robi to Filament w swoim installerze)
    4. Dodać zmienne do `.env` Laravela (`SHELL_GATE_GATEWAY_URL`)
    5. Setup gateway `.env` (JWT_SECRET z APP_KEY, DEFAULT_CWD)
    6. `npm install` w katalogu gateway (jeśli nie `--no-gateway`)
    7. Print podsumowanie + next steps
  - Flaga `--dev`:
    - Skip licencji
    - Nie wymaga `is_super_admin` (authorize = `auth()->check()`)
  - Zarejestrować komendę w `ShellGateServiceProvider`

- [x] **1.2** Logika auto-patch `AdminPanelProvider.php`
  - Szukamy pliku w `app/Providers/Filament/*PanelProvider.php`
  - Szukamy `->plugins([` — jeśli jest, dodajemy `ShellGatePlugin::make()` do tablicy
  - Szukamy `->plugin(` — jeśli jest, dodajemy kolejny `->plugin(ShellGatePlugin::make())`
  - Jeśli żadne nie znalezione — dodajemy `->plugin(ShellGatePlugin::make())` przed `->` ostatnią metodą w łańcuchu
  - Dodajemy `use OctadecimalHQ\ShellGate\ShellGatePlugin;` do importów
  - Jeśli patch się nie powiedzie — wypisać instrukcję manualną (nie failować)

- [x] **1.3** Logika dodawania zmiennych do `.env`
  - Bezpieczne dopisywanie (sprawdza czy klucz już istnieje)
  - Klucze: `SHELL_GATE_GATEWAY_URL=ws://127.0.0.1:7681`
  - W trybie `--dev`: nie pytać o licencję

---

### ETAP 2: `php artisan shellgate:serve`

- [x] **2.1** Nowa klasa `src/Console/ServeCommand.php`
  - Signature: `shellgate:serve {--port=7681} {--host=127.0.0.1}`
  - Kolejność operacji:
    1. Sprawdzić czy Node.js >= 18 jest zainstalowany → czytelny error jeśli nie
    2. Ustalić ścieżkę do gateway (`vendor/octadecimalhq/shellgate/gateway` lub path repo)
    3. Jeśli brak `node_modules/` → automatycznie `npm install`
    4. Jeśli brak `.env` → stworzyć z `.env.example`, ustawić JWT_SECRET z APP_KEY
    5. Odpalić `node index.js` jako subprocess (Process component Symfony)
    6. Streamować output do konsoli Artisan
    7. Handle SIGINT/SIGTERM → przekazać do child procesu
  - Gateway startuje w foreground — użytkownik widzi logi w terminalu

- [x] **2.2** Zarejestrować komendę w `ShellGateServiceProvider`

---

### ETAP 3: Smart default authorize

- [x] **3.1** Zmienić `defaultAuthorization()` w `EnsureTerminalAccess.php`
  - W środowisku `local`/`testing` → `auth()->check()` (każdy zalogowany user)
  - W `production` → zachować obecną logikę (is_super_admin / Spatie)
  - Efekt: w development nie trzeba migracji `is_super_admin` ani edycji User.php

- [x] **3.2** Zmienić `canAccess()` w `TerminalPage.php`
  - Taki sam fallback: local/testing → `auth()->check()`

- [x] **3.3** Zmienić default `$authorizeUsing` w `ShellGatePlugin.php`
  - Zamiast `true` (wpuszcza każdego) — `null` (używa defaultAuthorization)
  - Jeśli user ustawi `->authorize(...)` to override działa jak dotychczas

---

### ETAP 4: Post-install Composer message

- [x] **4.1** Hint w `ShellGateServiceProvider::boot()` (first boot detection)
  - Wypisuje: "Run `php artisan shellgate:install` to complete setup"
  - Composer scripts nie działają z paczek-zależności — użyto ServiceProvider

---

### ETAP 5: Aktualizacja dokumentacji

- [x] **5.1** `README.md` — zaktualizować Quick Start do 3-komendowego flow
- [x] **5.2** `INSTALLATION.md` — zaktualizować na nowy flow (install command + serve)
- [x] **5.3** Zachować `install.sh` jako alternatywę (nie usuwać — przydatny poza Laravel)

---

### ETAP 6: Testy

- [x] **6.1** Test `InstallCommand` — sprawdza publish, config overwrite, .env patch
- [x] **6.2** Test `ServeCommand` — sprawdza brak gateway dir error
- [x] **6.3** Test smart authorize — local/production env, explicit callbacks, super_admin

---

## Docelowy flow użytkownika

### Development (3 komendy):
```bash
composer require octadecimalhq/shellgate:@dev
php artisan shellgate:install --dev
php artisan shellgate:serve
# → /admin/terminal działa dla każdego zalogowanego usera
```

### Production (3 komendy + .env):
```bash
composer require octadecimalhq/shellgate
php artisan shellgate:install
# → dopisać SHELL_GATE_LICENSE_KEY do .env
# → opcjonalnie: skonfigurować authorize() w AdminPanelProvider
# → gateway startować przez systemd/PM2/Docker (nie artisan serve)
```

---

## Priorytet realizacji

| # | Zadanie | Wpływ | Trudność |
|---|---------|-------|----------|
| 1 | ETAP 1: InstallCommand | Eliminuje 5-6 kroków | Średnia |
| 2 | ETAP 2: ServeCommand | Eliminuje 3-4 kroków | Niska |
| 3 | ETAP 3: Smart authorize | Eliminuje 2-3 kroków | Niska |
| 4 | ETAP 4: Composer message | UX | Minimalna |
| 5 | ETAP 5: Dokumentacja | Spójność | Niska |
| 6 | ETAP 6: Testy | Stabilność | Średnia |

---

## Notatki techniczne

- `InstallCommand` patchuje pliki PHP za pomocą regex/string search — nie AST parser (za ciężki). Wzorujemy się na `filament:install` z Filament v3.
- `ServeCommand` używa `Symfony\Component\Process\Process` do uruchomienia Node.js subprocess.
- Gateway `.env` lokalizujemy przez: `base_path('vendor/octadecimalhq/shellgate/gateway')` lub sprawdzamy czy composer path repo wskazuje gdzie indziej.
- Nie zmieniamy architektury (gateway nadal Node.js) — upraszczamy tylko UX instalacji.
