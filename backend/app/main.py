from fastapi import FastAPI

from app.config import settings
from app.routers.dashboard import router as dashboard_router
from app.routers.health import router as health_router


app = FastAPI(title=settings.app_name)
app.include_router(health_router)
app.include_router(dashboard_router)


@app.get("/")
def root():
    return {"status": "ok", "service": settings.app_name}
