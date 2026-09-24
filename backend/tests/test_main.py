"""Тесты для основных эндпоинтов приложения FastAPI."""

import pytest
from fastapi.testclient import TestClient

from app.main import app


@pytest.fixture
def client() -> TestClient:
    """Фикстура тестового клиента FastAPI."""
    return TestClient(app)


def test_health_check(client: TestClient) -> None:
    """Проверка доступности и корректного ответа эндпоинта /health."""
    response = client.get("/health")

    # Проверяем успешный HTTP-статус ответа (200 OK)
    assert response.status_code == 200

    # Проверяем структуру и полезную нагрузку JSON-ответа
    data = response.json()
    assert data == {"status": "ok"}
