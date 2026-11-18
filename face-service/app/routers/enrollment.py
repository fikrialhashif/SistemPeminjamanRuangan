import base64
import io
from statistics import median

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from PIL import Image, ImageOps

from app.config import settings
from app.services.face_engine import FaceQualityError, face_engine
from app.services.storage import embedding_store

router = APIRouter(prefix="/enroll", tags=["enrollment"])

_TURN_POSES = {"turn_left", "turn_right"}


class EnrollRequest(BaseModel):
    user_id: int
    images: list[str]
    poses: list[str] | None = None


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

    poses = req.poses or ["front"] * len(req.images)
    if len(poses) != len(req.images):
        raise HTTPException(400, "poses must match images")
    if any(pose not in {"front", "turn_left", "turn_right"} for pose in poses):
        raise HTTPException(400, "Unknown pose in poses")

    embeddings = []
    yaws = []
    for idx, img_b64 in enumerate(req.images):
        try:
            img_bytes = base64.b64decode(img_b64)
            image = ImageOps.exif_transpose(Image.open(io.BytesIO(img_bytes))).convert("RGB")
        except Exception:
            raise HTTPException(400, f"Invalid image at index {idx}")

        max_yaw = (
            settings.MAX_TURN_YAW_DEGREES
            if poses[idx] in _TURN_POSES
            else settings.MAX_YAW_DEGREES
        )
        quality = face_engine.check_quality(image, max_yaw_degrees=max_yaw)
        if not quality["ok"]:
            raise HTTPException(400, f"Image {idx} quality issue: {quality['reason']}")

        try:
            embeddings.append(face_engine.extract_embedding(image))
        except FaceQualityError as error:
            raise HTTPException(400, f"Image {idx} quality issue: {error.reason}")

        yaw = face_engine.yaw(image)
        if yaw is None:
            raise HTTPException(400, f"Image {idx} pose could not be determined")
        yaws.append(yaw)

    front_yaws = [yaw for yaw, pose in zip(yaws, poses) if pose not in _TURN_POSES]
    baseline = median(front_yaws or yaws)

    for idx, (pose, yaw) in enumerate(zip(poses, yaws)):
        if pose in _TURN_POSES and abs(yaw - baseline) < settings.MIN_TURN_DEGREES:
            raise HTTPException(
                400,
                f"Image {idx} does not show the requested head turn. "
                "Turn your head clearly to the left or right.",
            )

    embedding_store.store(req.user_id, embeddings, yaw_baseline=baseline)
    return EnrollResponse(
        status="success",
        samples_count=len(embeddings),
        message=f"Enrolled {len(embeddings)} samples for user {req.user_id}",
    )


@router.delete("/users/{user_id}")
def delete_user(user_id: int):
    deleted = embedding_store.delete(user_id)
    return {"deleted": deleted}
