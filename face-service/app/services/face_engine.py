import numpy as np
from PIL import Image

from app.config import settings


class FaceEngine:
    def extract_embedding(self, image: Image.Image) -> np.ndarray:
        vec = np.random.randn(512).astype(np.float32)
        return vec / np.linalg.norm(vec)

    def compare_embeddings(self, emb1: np.ndarray, emb2: np.ndarray) -> float:
        return float(np.dot(emb1, emb2) / (np.linalg.norm(emb1) * np.linalg.norm(emb2)))

    def check_quality(self, image: Image.Image) -> dict:
        width, height = image.size
        if width < settings.MIN_FACE_SIZE or height < settings.MIN_FACE_SIZE:
            return {"ok": False, "reason": f"Image too small: {width}x{height}"}

        gray = image.convert("L")
        pixels = np.array(gray)
        mean_brightness = float(pixels.mean())

        if mean_brightness < 40:
            return {"ok": False, "reason": "Image too dark"}
        if mean_brightness > 250:
            return {"ok": False, "reason": "Image too bright"}

        return {"ok": True, "reason": "Quality check passed"}


face_engine = FaceEngine()
