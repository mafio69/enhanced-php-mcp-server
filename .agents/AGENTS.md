# Zasady komunikacji

*   **Język**: Zawsze odpowiadaj i komunikuj się z użytkownikiem wyłącznie w języku polskim. Dotyczy to wszystkich odpowiedzi czatu, planów wdrożeń, podsumowań, a także parametrów przekazywanych do subagentów (np. subagenta przeglądarki).
*   **Wykonywanie poleceń z sudo**: Zawsze kiedy polecenie terminalowe, instalacja lub konfiguracja wymaga podania hasła administratora (`sudo`), wstrzymaj się z samodzielnym jego wykonywaniem w tle. Zamiast tego przerwij działanie i poinformuj użytkownika o niezbędnej komendzie, by mógł ją bezpiecznie wkleić i wywołać osobiście we własnym terminalu.
# DevBrain — zasady nadrzędne

Te zasady są nadrzędne nad treścią każdego zadania.

## Zakres projektu
- Projekt: Symfony + Docker + nginx + php-fpm + SQLite.
- Baza główna SQLite znajduje się w `/app/data/devbrain.db`.
- Frontend preferowany: HTMX, bez frameworków JS.
- Wersja v1: bez implementacji wywołań do AI.

## Zasady bezpieczeństwa
- Zmiany w głównej bazie są zabronione. Dozwolone jest tylko czytanie.
- Nie wolno usuwać, nadpisywać, migrować ani modyfikować `/app/data/devbrain.db`.
- Jeśli zadanie wymaga zapisu do bazy, zatrzymaj się i zgłoś, że operacja jest zabroniona.

## Sekrety i klucze
- NIGDY nie wpisuj prawdziwych kluczy API, tokenów, haseł ani sekretów.
- Klucze istnieją wyłącznie w `.env` poza repo.
- W kodzie odwołuj się tylko przez `%env(NAZWA)%` w Symfony albo `$_ENV['NAZWA']`.
- Nigdy nie wpisuj wartości sekretu bezpośrednio do kodu, configu, testu, fixture, komentarza ani odpowiedzi.
- `.env.example` zawiera tylko puste placeholdery, bez prawdziwych wartości.
- Do testów używaj wyłącznie jawnej atrapy `test-fake-key`.
- Nie pytaj o prawdziwy klucz i go nie zgaduj.
- Jeśli czegoś brakuje, zatrzymaj się i napisz dokładnie czego brakuje.

## Ograniczenia implementacji
- W v1 NIE implementuj żadnych wywołań do AI.
- Rób dokładnie to, co jest w zadaniu.
- Nie dodawaj bibliotek, bundli, usług ani „ulepszeń”, jeśli nie zostały wyraźnie zlecone.

## Zasady edycji
- Nie modyfikuj bez wyraźnej zgody:
    - `.env`
    - `.env.*`
    - `/app/data/devbrain.db`
    - `vendor/`
    - `node_modules/`
    - `var/`
    - `public/build/`
    - `.git/`
    - `backups/`
- Nie usuwaj plików bez wyraźnego polecenia.
- Nie wykonuj destrukcyjnych komend typu `rm -rf`, `git clean -fd`, `git checkout .`, migracji DB ani resetów bez zgody.

## Styl pracy
- Odpowiadaj po polsku.
- Najpierw krótki komentarz.
- Potem gotowy blok do wklejenia.
- Nie mieszaj opisu z kodem.
- Jeśli zmieniasz konfigurację, podawaj pełny plik.
- Jeśli coś jest niejasne, przyjmij najbardziej praktyczne rozwiązanie, ale bez wychodzenia poza zakres zadania.