import base64
import io
from typing import Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from PIL import Image

from app.config import settings
from app.services.face_engine import face_engine
from app.services.storage import embedding_store
from app.services.liveness import liveness_checker

router = APIRouter(prefix="/verify", tags=["verification"])


class VerifyRequest(BaseModel):
    user_id: int
    image: str
    challenge_type: Optional[str] = None
    challenge_response: Optional[dict] = None


class VerifyResponse(BaseModel):
    match: bool
    score: float
    liveness_passed: bool
    reason: str


@router.post("", response_model=VerifyResponse)
def verify(req: VerifyRequest):
    try:
        img_bytes = base64.b64decode(req.image)
        image = Image.open(io.BytesIO(img_bytes)).convert("RGB")
    except Exception:
        raise HTTPException(400, "Invalid image")

    quality = face_engine.check_quality(image)
    if not quality["ok"]:
        raise HTTPException(400, f"Quality issue: {quality['reason']}")

    embedding = face_engine.extract_embedding(image)

    stored = embedding_store.get(req.user_id)
    if stored is None:
        raise HTTPException(404, "User not enrolled")

    scores = [face_engine.compare_embeddings(embedding, s) for s in stored]
    best_score = max(scores)
    matched = best_score >= settings.MATCH_THRESHOLD

    liveness_passed = True
    if req.challenge_type:
        liveness_passed = liveness_checker.verify_challenge(
            req.challenge_type, req.challenge_response or {}
        )

    reason = "Match found" if matched else "No match"
    if not liveness_passed:
        reason = "Liveness check failed"

    return VerifyResponse(
        match=matched and liveness_passed,
        score=round(best_score, 4),
        liveness_passed=liveness_passed,
        reason=reason,
    )


@router.get("/challenge")
def get_challenge():
    return liveness_checker.get_challenge()
