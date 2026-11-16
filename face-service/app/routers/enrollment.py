import base64
import io

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from PIL import Image

from app.config import settings
from app.services.face_engine import face_engine
from app.services.storage import embedding_store

router = APIRouter(prefix="/enroll", tags=["enrollment"])


class EnrollRequest(BaseModel):
    user_id: int
    images: list[str]


class EnrollResponse(BaseModel):
    status: str
    samples_count: int
    message: str


@router.post("", response_model=EnrollResponse)
def enroll(req: EnrollRequest):
    if len(req.images) < settings.MIN_SAMPLES:
        raise HTTPException(400, f"At least {settings.MIN_SAMPLES} samples required")
    if len(req.images) > settings.MAX_SAMPLES:
        raise HTTPException(400, f"At most {settings.MAX_SAMPLES} samples allowed")

    embeddings = []
    for idx, img_b64 in enumerate(req.images):
        try:
            img_bytes = base64.b64decode(img_b64)
            image = Image.open(io.BytesIO(img_bytes)).convert("RGB")
        except Exception:
            raise HTTPException(400, f"Invalid image at index {idx}")

        quality = face_engine.check_quality(image)
        if not quality["ok"]:
            raise HTTPException(400, f"Image {idx} quality issue: {quality['reason']}")

        embedding = face_engine.extract_embedding(image)
        embeddings.append(embedding)

    embedding_store.store(req.user_id, embeddings)
    return EnrollResponse(
        status="success",
        samples_count=len(embeddings),
        message=f"Enrolled {len(embeddings)} samples for user {req.user_id}",
    )


@router.delete("/users/{user_id}")
def delete_user(user_id: int):
    deleted = embedding_store.delete(user_id)
    return {"deleted": deleted}
