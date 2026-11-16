from fastapi import FastAPI, Depends

from app.auth import verify_service_secret
from app.routers import enrollment, verification

app = FastAPI(title="Face Recognition Service")


@app.get("/health")
def health():
    return {"status": "ok"}


app.include_router(
    enrollment.router,
    dependencies=[Depends(verify_service_secret)],
)
app.include_router(
    verification.router,
    dependencies=[Depends(verify_service_secret)],
)
