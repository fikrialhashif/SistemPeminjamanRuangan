import base64
import io

import numpy as np
import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app.auth import verify_service_secret
from app.main import app
from app.services import storage
from app.services.face_engine import face_engine
from app.services.storage import EmbeddingStore


def _override_auth():
    return "test-secret"


app.dependency_overrides[verify_service_secret] = _override_auth


@pytest.fixture(autouse=True)
def stub_face_engine(monkeypatch):
    def fake_quality(image, max_yaw_degrees=None):
        if image.width < 64 or image.height < 64:
            return {"ok": False, "reason": "Image too small"}
        return {"ok": True, "reason": "Quality check passed"}

    def fake_yaw(image):
        return 0.0

    def fake_embedding(image):
        pixels = np.asarray(image.convert("L"), dtype=np.float32)
        signature = float(pixels.mean()) + float(pixels.std())
        rng = np.random.default_rng(int(signature * 1000))
        vector = rng.standard_normal(512).astype(np.float32)
        return vector / np.linalg.norm(vector)

    monkeypatch.setattr(face_engine, "check_quality", fake_quality)
    monkeypatch.setattr(face_engine, "yaw", fake_yaw)
    monkeypatch.setattr(face_engine, "extract_embedding", fake_embedding)


@pytest.fixture(autouse=True)
def isolated_store(tmp_path, monkeypatch):
    store = EmbeddingStore(str(tmp_path), secret="test-secret")
    monkeypatch.setattr(storage, "embedding_store", store)
    monkeypatch.setattr("app.routers.enrollment.embedding_store", store)
    monkeypatch.setattr("app.routers.verification.embedding_store", store)
    yield store


@pytest.fixture
def client():
    return TestClient(app)


def make_test_image(width=100, height=100, color=(128, 128, 128)) -> str:
    img = Image.new("RGB", (width, height), color)
    buf = io.BytesIO()
    img.save(buf, format="PNG")
    return base64.b64encode(buf.getvalue()).decode()
