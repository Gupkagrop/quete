---
request_feedback: true
user_facing: true
---

# Спринт 10: Финальный QA и Релиз (День 64–70)

## Goal Description
Цель финального десятого спринта — комплексное сквозное тестирование (End-to-End), контейнеризация для production-окружения, развёртывание бэкенда на VPS, публикация Flutter Web-версии игры и сдача готового проекта.

---

## 1. Архитектурные решения

### 1.1 Production Контейнеризация
- **Backend Dockerfile:** многоэтапная (multi-stage) сборка на базе `ghcr.io/astral-sh/uv:python3.12-alpine`.
- Запуск приложения из-под непривилегированного пользователя (`appuser`).
- `docker-compose.prod.yml`: Nginx (обратный прокси и SSL) + FastAPI + PostgreSQL 16 + Redis 7.

### 1.2 Flutter Web Сборка
- Production сборка через `flutter build web --release`.
- Раздача статики через Nginx или облачный хостинг (Cloudflare Pages / GitHub Pages).

### 1.3 Автоматический деплой (CD)
- Активация закомментированного блока `deploy` в `.github/workflows/ci.yml` при пуше в ветку `main`.

---

## 2. Предлагаемые изменения по файлам

### Инфраструктура и деплой
- `backend/Dockerfile`: оптимизированный контейнер бэкенда.
- `docker-compose.prod.yml`: конфигурация серверов с автоперезапуском (`restart: always`) и монтированием томов.
- `nginx/nginx.conf`: конфигурация проксирования HTTP и апгрейда WebSocket соединений (`proxy_set_header Upgrade $http_upgrade`).

### Тесты и документация
- `tests/e2e/test_full_game_loop.py`: сквозной асинхронный тест 4 игроков от лобби до финала.
- Обновление корневого `README.md` (инструкция запуска в проде, бейджи, скриншоты).

---

## 3. План верификации
- **E2E тест:** прогон полного матча из 5 раундов без единого сбоя.
- **Production Smoke Test:** локальный запуск `docker compose -f docker-compose.prod.yml up` и проверка работы веб-версии в браузере.
