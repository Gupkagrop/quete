# 🎭 Quete — Multiplayer Bluffing Quiz with AI

> **Quete** is a real-time cross-platform multiplayer quiz game where players compete not only in knowledge, but in psychology, wit, and the art of deception. Questions and reference bluff options are dynamically generated on-the-fly using **Google Gemini 1.5 Flash**.

---

## 🎮 Concept & Gameplay Overview

In **Quete** (derived from the French *quête* — "quest"), players face challenging trivia questions generated dynamically by AI based on chosen topics. 

The game revolves around **bluffing**:
1. **Topic Selection:** A designated player picks any topic or category.
2. **AI Question Generation:** Gemini 1.5 Flash generates a question, the verified correct answer, and high-quality decoy answers using **Structured Outputs** (JSON Schema).
3. **The Bluff Phase:** Each player crafts their own convincing fake answer to deceive opponents.
4. **The Vote:** All answers (the truth + player bluffs + AI fallbacks) are shuffled together. Players try to find the truth while dodging rivals' traps.
5. **Scoring & Podium:** Earn points for discovering the truth and for every opponent who fell for your bluff!

---

## ⚡ Key Game Mechanics

| Mechanic | Description |
| :--- | :--- |
| 🤖 **AI Caching** | AI-generated questions and decoys are cached in **Redis** and **PostgreSQL** to optimize latency and conserve Gemini API quotas. |
| 🛡️ **Bluff Integrity & Collisions** | Fakes too close or identical to the correct answer are rejected. If two players submit the exact same fake answer, they are seamlessly merged into one voting option, and both players receive bluff points if opponents vote for it. |
| ⏱️ **Auto-Fake Fallback** | If a player disconnects or runs out of time (20–120s), an AI-generated decoy is automatically submitted on their behalf to maintain game momentum. |
| 🔑 **Adaptive Auth** | Starts friction-free with guest lobby codes (instant play), backed by a self-hosted **JWT** architecture for persistent profiles and stat tracking. |
| 🕹️ **8-Bit Retro Aesthetic** | Pixel-art inspired user interface, vintage arcade palettes, CRT shaders, chiptune sound effects, and animated avatars. |

---

## 🛠️ Technology Stack

| Layer | Technologies | Role & Highlights |
| :--- | :--- | :--- |
| **Backend** | Python 3.12+, **FastAPI**, WebSockets | High-performance asynchronous API, real-time bidirectional game events. |
| **Database & ORM** | **PostgreSQL**, **SQLModel** | Type-safe relational schema uniting SQLAlchemy 2.0 and Pydantic v2. |
| **Cache & Real-time State** | **Redis** | Room state synchronization, session caching, and AI response cache. |
| **Artificial Intelligence** | **Google Gemini 1.5 Flash** | Dynamic question/bluff synthesis with strict JSON Schema Structured Outputs. |
| **Client Application** | **Flutter** (Dart 3.x) | Single codebase targeting **Web**, **Android**, **iOS**, and **Windows**. |
| **Authentication** | Self-hosted **JWT** | Secure token-based guest sessions with migration to full accounts. |

---

## 📁 Repository Structure

```
quete/
├── backend/          # FastAPI backend (REST API, WebSockets, DB models, AI service)
├── client/           # Flutter cross-platform client (Web, Mobile, Desktop)
├── docs/             # Technical specifications, architecture designs, ROADMAP.md
├── .agents/          # Antigravity agent configuration, MCP settings, and rule sets
├── .artifacts/       # Architecture Decision Records (ADR) and design artifacts
├── GEMINI.md         # Antigravity Project Constitution & engineering standards
└── README.md         # Project overview and documentation
```

---

## 🗺️ Roadmap

Check out the detailed [70-Day Development Roadmap](docs/ROADMAP.md) to see planned milestones across our 6 development phases:
1. **Phase 1:** Foundation & DevOps (Days 1–7)
2. **Phase 2:** Core Game Engine & WebSockets (Days 8–21)
3. **Phase 3:** AI Integration & Game Loop (Days 22–35)
4. **Phase 4:** Flutter Client - Core UX (Days 36–49)
5. **Phase 5:** Polish & Retro UI (Days 50–60)
6. **Phase 6:** Multi-platform & Production (Days 61–70)

---

## 📜 License & Acknowledgments

Developed with ❤️ as a modern multiplayer experience powered by Google Gemini and Flutter.
