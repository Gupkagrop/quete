"""Основной модуль веб-приложения FastAPI."""

from fastapi import FastAPI
from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    """Схема ответа для эндпоинта проверки работоспособности сервиса."""

    status: str = Field(description="Статус доступности сервиса")


app: FastAPI = FastAPI(
    title="Quete API",
    description="Backend API для многопользовательской викторины Quete",
    version="0.1.0",
)


@app.get(
    "/health",
    response_model=HealthResponse,
    summary="Проверка работоспособности сервиса",
    tags=["Health"],
)
async def health_check() -> HealthResponse:
    """Возвращает текущий статус доступности сервиса."""
    return HealthResponse(status="ok")
