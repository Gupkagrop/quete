# 🗺️ 70-Day Development Roadmap: Quete

This roadmap outlines the 10-week development plan to transform Quete from a concept into a production-ready, multi-platform multiplayer game (Web, Android, iOS, Windows).

## 🏗️ Phase 1: Foundation & DevOps (Days 1-7)
**Goal:** Establish a robust local development environment and database architecture.
*   [ ] Docker Compose configuration (FastAPI, PostgreSQL, Redis).
*   [ ] CI/CD pipeline setup (GitHub Actions for Python/Flutter linters).
*   [ ] Database schema design and implementation (SQLModel).
*   [ ] Alembic migrations setup.
*   [ ] Basic FastAPI initialization with Self-hosted JWT Authentication endpoints.

## ⚙️ Phase 2: Core Game Engine & WebSockets (Days 8-21)
**Goal:** Build the real-time multiplayer networking layer.
*   [ ] Implement the WebSocket Connection Manager in FastAPI.
*   [ ] Strict Pydantic typings for all WebSocket events (`join`, `leave`, `state_update`).
*   [ ] Lobby management logic (Create room, Join with code, Kick players).
*   [ ] Redis integration for real-time room state synchronization across clients.

## 🧠 Phase 3: AI Integration & Game Loop (Days 22-35)
**Goal:** Connect Gemini API and implement the strict bluffing game logic.
*   [ ] Gemini 1.5 Flash integration utilizing **Structured Outputs** (JSON Schema).
*   [ ] AI Caching mechanism (Redis/Postgres) to save tokens on repeated topics.
*   [ ] Core State Machine execution: `Lobby -> Topic -> Generate -> Bluff -> Vote -> Results`.
*   [ ] Fallback mechanisms (Auto-fake substitution on player timeout/disconnect).

## 📱 Phase 4: Flutter Client - Core UX (Days 36-49)
**Goal:** Bring the game to life visually on the client side.
*   [ ] Flutter project initialization and routing (GoRouter).
*   [ ] WebSocket client implementation with state management (Riverpod/Bloc).
*   [ ] Authentication UI (Guest login) & Lobby creation/join screens.
*   [ ] Game screens (Topic selection, typing fakes, voting grid, podium).

## 🎨 Phase 5: Polish & Retro UI (Days 50-60)
**Goal:** Achieve the target 8-bit retro aesthetic and game feel.
*   [ ] Apply pixel-art styling, CRT monitor shaders, and retro fonts.
*   [ ] Sound effects manager (BGM, UI clicks, victory/defeat sounds).
*   [ ] Custom animations (hourglass timer, score counting, avatars).
*   [ ] Responsive layout tuning (scaling perfectly from Mobile to Desktop).

## 🚀 Phase 6: Multi-platform & Production (Days 61-70)
**Goal:** Ensure extreme stability and multi-platform compilation.
*   [ ] End-to-end integration testing (simulating 8 concurrent players).
*   [ ] Load testing the WebSocket server.
*   [ ] Platform-specific compilations: Android APK and Windows EXE.
*   [ ] Preparation for iOS compilation (Xcode setup instructions).
*   [ ] Final bug fixes, security audit (`security-auditor`), and repository documentation.
