#!/usr/bin/env bash
#
# Совместимый загрузчик HTTP Environment.
#
# Управляющая логика реализована на Python.
# Существующий командный интерфейс сохранён.

set -euo pipefail

script_directory="$(
    cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &&
    pwd -P
)"

exec python3 \
    "$script_directory/environment.py" \
    "$@"