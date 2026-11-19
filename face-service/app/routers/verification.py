import base64
import io
from typing import Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from PIL import Image, ImageOps

from app.config import settings
from app.services.face_engine import FaceQualityError, face_engine
from app.services.liveness import liveness_checker
from app.services.storage import embedding_store

router = APIRouter(prefix="/verify", tags=["verification"])


class VerifyRequest(BaseModel):
    user_id: int
    image: str
    front_image: Optional[str] = None
    challenge_type: Optional[str] = None
    challenge_response: Optional[dict] = None


class VerifyResponse(BaseModel):
    match: bool
    score: float
    liveness_passed: bool
    reason: str
    inconclusive: bool = False


def _decode_image(value: str) -> Image.Image:
    try:
        img_bytes = base64.b64decode(value)
        return ImageOps.exif_transpose(Image.open(io.BytesIO(img_bytes))).convert("RGB")
    except Exception:
        raise HTTPException(400, "Invalid image")


@router.post("", response_model=VerifyResponse)
def verify(req: VerifyRequest):
    image = _decode_image(req.image)
    front_image = _decode_image(req.front_image) if req.front_image else image

    stored = embedding_store.get(req.user_id)
    if stored is None:
        raise HTTPException(404, "User not enrolled")

    if req.front_image:
        quality = face_engine.check_quality(
            front_image, max_yaw_degrees=settings.MAX_YAW_DEGREES
        )
        if not quality["ok"]:
            return VerifyResponse(
                match=False,
                score=0.0,
                liveness_passed=False,
                reason=quality["reason"],
            )
        turn_quality = face_engine.check_quality(
            image, max_yaw_degrees=settings.MAX_TURN_YAW_DEGREES
        )
        if not turn_quality["ok"]:
            return VerifyResponse(
                match=False,
                score=0.0,
                liveness_passed=False,
                reason=turn_quality["reason"],
            )
    else:
        max_yaw = (
            settings.MAX_TURN_YAW_DEGREES
            if req.challenge_type == "turn"
            else settings.MAX_YAW_DEGREES
        )
        quality = face_engine.check_quality(image, max_yaw_degrees=max_yaw)
        if not quality["ok"]:
            return VerifyResponse(
                match=False,
                score=0.0,
                liveness_passed=False,
                reason=quality["reason"],
            )

    try:
        embedding = face_engine.extract_embedding(front_image)
    except FaceQualityError as error:
        return VerifyResponse(
            match=False,
            score=0.0,
            liveness_passed=False,
            reason=error.reason,
        )

    embeddings = stored["embeddings"]
    baseline_yaw = stored["yaw_baseline"]
    scores = [face_engine.compare_embeddings(embedding, sample) for sample in embeddings]
    best_score = max(scores)
    matched = best_score >= settings.MATCH_THRESHOLD

    liveness_passed = True
    if req.challenge_type:
        current_yaw = face_engine.yaw(image)
        liveness_passed = liveness_checker.verify_challenge(
            req.challenge_type,
            req.challenge_response,
            current_yaw,
            baseline_yaw,
        )
        if baseline_yaw is None:
            return VerifyResponse(
                match=False,
                score=round(best_score, 4),
                liveness_passed=False,
                reason="Pendaftaran wajah perlu diulang agar gerakan kepala dapat diverifikasi.",
            )

    reason = "Match found" if matched else "No match"
    if not liveness_passed:
        reason = "Head turn not detected"

    return VerifyResponse(
        match=matched and liveness_passed,
        score=round(best_score, 4),
        liveness_passed=liveness_passed,
        reason=reason,
    )


@router.get("/challenge")
def get_challenge():
    return liveness_checker.get_challenge()
