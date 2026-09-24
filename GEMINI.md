# Project Constitution: Quete

> [!NOTE]
> Локальная конституция проекта для агентов Google Antigravity.

## 1. Technology Stack & Runtime
- **Runtime / Language:** Python 3.12+ (Backend) / Dart & Flutter (Client)
- **Framework:** FastAPI (Backend) / Flutter (Web/Android/iOS/Windows)
- **Database & ORM:** PostgreSQL + SQLModel
- **AI API:** Gemini 1.5 Flash (Structured Outputs)
- **Real-time:** WebSockets

## 2. Verification Commands (Strict Feedback Loop)
Before declaring ANY task complete, the agent MUST run these commands via `run_command` and fix all warnings:
- **Backend:** `pytest` / `mypy .`
- **Client:** `flutter analyze` / `flutter test`

## 3. Autonomous Execution Rules
- **Autonomy:** Proceed autonomously without asking for confirmation when reading files, editing source code, running builds, running tests, or installing local dependencies.
- **Confirmation Gate:** Stop and ask via `ask_question` ONLY for destructive actions: `DROP TABLE`, `TRUNCATE`, `git reset --hard`, `git push --force`, or recursive directory deletion outside `scratch/`.

## 4. Coding & Architecture Standards
- **Flat Architecture:** ALWAYS use early returns. Maximum nesting depth <= 3.
- **Strict Typing:** Never use `any` or implicit typing. Full parameter and return annotations required.
- **No Placeholders:** Never output `// TODO` or mockup stubs. Deliver production-ready code.
- **Language Rules:** All code symbols in English. All inline comments and docstrings in Russian.
