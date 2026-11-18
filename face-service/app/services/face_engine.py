import cv2
import numpy as np
from insightface.app import FaceAnalysis
from PIL import Image

from app.config import settings


class FaceQualityError(ValueError):
    def __init__(self, reason: str):
        super().__init__(reason)
        self.reason = reason


class FaceEngine:
    def __init__(self):
        self._app: FaceAnalysis | None = None

    @property
    def app(self) -> FaceAnalysis:
        if self._app is None:
            self._app = FaceAnalysis(
                name=settings.MODEL_NAME,
                providers=["CPUExecutionProvider"],
            )
            self._app.prepare(ctx_id=-1, det_size=(640, 640))
        return self._app

    def _analyze(self, image: Image.Image):
        array = np.asarray(image.convert("RGB"))
        return self.app.get(array[:, :, ::-1])

    def _largest(self, faces):
        return max(
            faces,
            key=lambda face: float((face.bbox[2] - face.bbox[0]) * (face.bbox[3] - face.bbox[1])),
        )

    def yaw(self, image: Image.Image) -> float | None:
        faces = self._analyze(image)
        if not faces:
            return None
        pose = getattr(self._largest(faces), "pose", None)
        if pose is None or len(pose) < 2:
            return None
        return float(pose[1])

    def check_quality(
        self, image: Image.Image, max_yaw_degrees: float | None = None
    ) -> dict:
        faces = self._analyze(image)

        if len(faces) == 0:
            return {"ok": False, "reason": "No face detected"}
        if len(faces) > 1:
            return {"ok": False, "reason": "Multiple faces detected, only one is allowed"}

        face = faces[0]
        width = float(face.bbox[2] - face.bbox[0])
        height = float(face.bbox[3] - face.bbox[1])

        if min(width, height) < settings.MIN_FACE_SIZE:
            return {
                "ok": False,
                "reason": f"Face too small ({int(width)}x{int(height)}), move closer",
            }

        if float(getattr(face, "det_score", 1.0)) < settings.MIN_DETECTION_SCORE:
            return {"ok": False, "reason": "Face detection confidence too low"}

        pose = getattr(face, "pose", None)
        limit = max_yaw_degrees if max_yaw_degrees is not None else settings.MAX_YAW_DEGREES
        if pose is not None and len(pose) >= 2 and abs(float(pose[1])) > limit:
            return {"ok": False, "reason": "Face is not facing the camera"}

        gray = np.asarray(image.convert("L"))
        brightness = float(gray.mean())
        if brightness < 40:
            return {"ok": False, "reason": "Image too dark"}
        if brightness > 250:
            return {"ok": False, "reason": "Image too bright"}

        x1, y1, x2, y2 = [int(v) for v in face.bbox]
        crop = gray[max(y1, 0):max(y2, 0), max(x1, 0):max(x2, 0)]
        if crop.size and float(cv2.Laplacian(crop, cv2.CV_64F).var()) < settings.MIN_BLUR_VARIANCE:
            return {"ok": False, "reason": "Image is too blurry, hold the camera steady"}

        return {"ok": True, "reason": "Quality check passed"}

    def extract_embedding(self, image: Image.Image) -> np.ndarray:
        faces = self._analyze(image)

        if len(faces) == 0:
            raise FaceQualityError("No face detected")
        if len(faces) > 1:
            raise FaceQualityError("Multiple faces detected, only one is allowed")

        face = self._largest(faces)
        embedding = np.asarray(face.normed_embedding, dtype=np.float32)
        norm = np.linalg.norm(embedding)

        if norm == 0:
            raise FaceQualityError("Could not extract a face embedding")

        return embedding / norm

    def compare_embeddings(self, emb1: np.ndarray, emb2: np.ndarray) -> float:
        return float(np.dot(emb1, emb2) / (np.linalg.norm(emb1) * np.linalg.norm(emb2)))


face_engine = FaceEngine()
