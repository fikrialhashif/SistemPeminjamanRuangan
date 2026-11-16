import base64
import io

import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app.main import app
from app.auth import verify_service_secret


def _override_auth():
    return "test-secret"


app.dependency_overrides[verify_service_secret] = _override_auth


@pytest.fixture
def client():
    return TestClient(app)


def make_test_image(width=100, height=100, color=(128, 128, 128)) -> str:
    img = Image.new("RGB", (width, height), color)
    buf = io.BytesIO()
    img.save(buf, format="PNG")
    return base64.b64encode(buf.getvalue()).decode()
