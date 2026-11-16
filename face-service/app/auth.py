from fastapi import Depends, Header, HTTPException

from app.config import settings


def verify_service_secret(x_service_secret: str = Header(...)) -> str:
    if x_service_secret != settings.FACE_SERVICE_SECRET:
        raise HTTPException(status_code=401, detail="Invalid service secret")
    return x_service_secret
