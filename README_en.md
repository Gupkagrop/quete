# 🎭 Quete — Multiplayer Bluffing Quiz with AI

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Python 3.12+](https://img.shields.io/badge/Python-3.12%2B-blue.svg)](https://www.python.org/)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.110%2B-009688.svg)](https://fastapi.tiangolo.com/)
[![Flutter](https://img.shields.io/badge/Flutter-3.x-02569B.svg)](https://flutter.dev/)
[![Gemini 1.5 Flash](https://img.shields.io/badge/AI-Gemini%201.5%20Flash-4285F4.svg)](https://ai.google.dev/)
[![PostgreSQL](https://img.shields.io/badge/Database-PostgreSQL-336791.svg)](https://www.postgresql.org/)
[![Redis](https://img.shields.io/badge/Cache-Redis-DC382D.svg)](https://redis.io/)

> **Read this in another language:** [🇷🇺 Русский (Russian)](README.md)

---

## 📖 Table of Contents
- [About Quete](#-about-quete)
- [Core Gameplay & Bluffing Rules](#-core-gameplay--bluffing-rules)
- [Key Features](#-key-features)
- [Technology Stack](#-technology-stack)
- [Architecture & Project Structure](#-architecture--project-structure)
- [Getting Started](#-getting-started)
  - [Prerequisites](#prerequisites)
  - [Backend Setup](#backend-setup)
  - [Client Setup (Flutter)](#client-setup-flutter)
- [Development Roadmap](#-development-roadmap)
- [License](#-license)

---

## 🌟 About Quete

**Quete** (derived from the French *quête* — "quest") is a cross-platform real-time multiplayer trivia party game. Unlike traditional quizzes that solely measure factual recall, Quete puts **psychology, wit, and the art of deception** at center stage.

Powered by **Google Gemini 1.5 Flash**, Quete synthesizes unique trivia questions on-the-fly based on player-selected topics, along with the single verified truth and realistic decoy answers. Players then construct their own convincing lies to trick other players into voting for their traps.

---

## 🎮 Core Gameplay & Bluffing Rules

Each round proceeds through several high-stakes phases:

```
[ 🎯 Topic Selection ] 
          │
          ▼
[ 🤖 AI Question & Decoy Synthesis (Gemini 1.5 Flash) ]
          │
          ▼
[ ✍️ The Bluff Phase: Players craft convincing fakes ]
          │
          ▼
[ 🗳️ The Vote: Truth + Player Bluffs + AI Fallbacks shuffled ]
          │
          ▼
[ 🏆 Reveal & Scoring: Points for truth & fooled opponents ]
```

1. **Topic Selection:** The room host or rotating chooser picks a topic or free-form theme.
2. **AI Question Generation:** Gemini 1.5 Flash generates a fascinating trivia question with a verified correct answer and backup decoy answers using strict **JSON Schema Structured Outputs**.
3. **The Bluff Phase:** Every player submits a plausible, witty fake answer designed to trick opponents into believing it is the real answer.
4. **The Vote:** All answers (the real answer, each player's bluff, and AI decoys if needed) are shuffled and presented on screen. Players cast their votes for what they believe is the actual truth.
5. **Scoring & Podium:**
   - **+Truth Points:** Awarded for identifying the correct answer.
   - **+Bluff Points:** Awarded for every rival player who fell for your fake answer.

---

## ⚡ Key Features

- **Dynamic AI Generation with Gemini 1.5 Flash:** Questions and fallbacks are created on demand, preventing repetitive questions and keeping every match fresh.
- **Bluff Integrity & Collisions:** Submissions matching the true answer (or too similar to it) are rejected and prompted for revision. If two players coincidentally submit the same fake answer, they are merged into one voting option, and both players receive points when opponents fall for it.
- **Auto-Fake Fallback:** If a player disconnects or lets the timer (20–120s) expire, the server automatically submits an AI-generated decoy on their behalf, maintaining uninterrupted gameplay.
- **Multi-Tier AI & Cache System:** Frequently generated questions and categories are cached in **Redis** and **PostgreSQL** to minimize latency and optimize API costs.
- **Friction-Free Adaptive Authentication:** Players can join rooms instantly as guests via 6-character room codes, with optional seamless upgrade to persistent accounts backed by **JWT** for profile stats and leaderboards.
- **8-Bit Retro Aesthetic:** Vibrant arcade pixel art style, retro CRT scanline effects, custom chiptune audio, and retro avatar selections.

---

## 🛠️ Technology Stack

| Layer | Technology | Purpose & Highlights |
| :--- | :--- | :--- |
| **Backend** | **Python 3.12+**, **FastAPI** | Async REST API & high-concurrency WebSocket game engine. |
| **Real-time Engine** | **WebSockets** | Low-latency state synchronization between server and players. |
| **Artificial Intelligence** | **Google Gemini 1.5 Flash** | Dynamic trivia synthesis via strict JSON Schema Structured Outputs. |
| **Database & ORM** | **PostgreSQL**, **SQLModel** | Modern relational data modeling combining SQLAlchemy 2.0 and Pydantic v2. |
| **Cache & State Store** | **Redis** | Ephemeral room sessions, game timers, and AI response caching. |
| **Client Application** | **Flutter (Dart 3.x)** | Cross-platform UI for **Web**, **Android**, **iOS**, and **Desktop** (Windows, macOS, Linux). |
| **Security & Auth** | **Self-hosted JWT**, Argon2 / bcrypt | Stateless authentication, guest sessions, and secure account upgrades. |

---

## 📁 Architecture & Project Structure

```
quete/
├── backend/                  # FastAPI backend service
│   ├── app/
│   │   ├── api/              # REST routes (auth, rooms, users)
│   │   ├── core/             # Configuration, database setup, security
│   │   ├── models/           # SQLModel database models & Pydantic schemas
│   │   ├── services/         # Game engine, AI service (Gemini), Redis room manager
│   │   └── websockets/       # WebSocket handlers & real-time protocol
│   ├── pyproject.toml        # Poetry / pip dependency configuration
│   └── tests/                # Pytest unit & integration tests
│
├── client/                   # Flutter cross-platform client
│   ├── lib/
│   │   ├── core/             # Theme (retro/pixel), constants, network clients
│   │   ├── models/           # Dart data models & serialization
│   │   ├── providers/        # State management (Riverpod / Bloc)
│   │   ├── screens/          # UI views (Lobby, Bluff, Vote, Scoreboard)
│   │   └── widgets/          # Reusable 8-bit UI components
│   └── pubspec.yaml          # Flutter dependencies & assets
│
├── docs/                     # Specifications, architecture, and ROADMAP.md
├── .env.example              # Sample environment variables
└── README.md                 # Project documentation (Russian / English)
```

---

## 🚀 Getting Started

### Prerequisites

- **Python 3.12+**
- **Flutter SDK 3.x+**
- **PostgreSQL 15+**
- **Redis 7+**
- **Google Gemini API Key** ([Google AI Studio](https://aistudio.google.com/))

---

### Backend Setup

1. **Navigate to the backend directory:**
   ```bash
   cd backend
   ```

2. **Create and activate a virtual environment:**
   ```bash
   python -m venv .venv
   # Windows (PowerShell):
   .venv\Scripts\Activate.ps1
   # Linux / macOS:
   source .venv/bin/activate
   ```

3. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   # Or using poetry / uv if preferred
   ```

4. **Configure environment variables:**
   ```bash
   cp ../.env.example .env
   ```
   Fill in your `GEMINI_API_KEY`, `DATABASE_URL`, and `REDIS_URL`.

5. **Run the backend development server:**
   ```bash
   uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
   ```

---

### Client Setup (Flutter)

1. **Navigate to the client directory:**
   ```bash
   cd client
   ```

2. **Install Flutter packages:**
   ```bash
   flutter pub get
   ```

3. **Run client on desired target:**
   ```bash
   # Run in Chrome / Web:
   flutter run -d chrome

   # Run on Windows Desktop:
   flutter run -d windows

   # Run on connected Mobile device:
   flutter run
   ```

---

## 🗺️ Development Roadmap

Check out the comprehensive **[70-Day Development Roadmap](docs/ROADMAP.md)**:

- 🏗️ **Phase 1: Foundation & DevOps (Days 1–7)** — Repository setup, CI/CD, base containers, config.
- ⚙️ **Phase 2: Core Game Engine & WebSockets (Days 8–21)** — Room lifecycle, state machine, real-time events.
- 🧠 **Phase 3: AI Integration & Game Loop (Days 22–35)** — Gemini 1.5 Flash structured generation, caching, fallback decoys.
- 📱 **Phase 4: Flutter Client - Core UX (Days 36–49)** — Responsive layout, lobby UI, bluff input, voting mechanics.
- ✨ **Phase 5: Polish & Retro UI (Days 50–60)** — Pixel art aesthetics, scanlines, chiptune sound effects, animations.
- 🚀 **Phase 6: Multi-platform & Production (Days 61–70)** — Cross-platform builds, stress testing, production deployment.

---

## 📜 License

This project is licensed under the **MIT License**.
Distributed freely for study, personal gameplay, and community enhancement.
