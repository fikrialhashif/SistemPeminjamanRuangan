import numpy as np
import pytest

from app.services.face_engine import FaceQualityError, face_engine
from tests.conftest import make_test_image


def test_verify_returns_score(client):
    images = [make_test_image() for _ in range(3)]
    client.post("/enroll", json={"user_id": 10, "images": images})
    resp = client.post("/verify", json={"user_id": 10, "image": make_test_image()})
    assert resp.status_code == 200
    data = resp.json()
    assert "score" in data
    assert "match" in data
    assert "liveness_passed" in data


def test_verify_user_not_enrolled(client):
    resp = client.post("/verify", json={"user_id": 9999, "image": make_test_image()})
    assert resp.status_code == 404


def test_verify_enforces_head_turn_from_image(client, monkeypatch):
    images = [make_test_image() for _ in range(3)]
    client.post("/enroll", json={"user_id": 11, "images": images})
    monkeypatch.setattr("app.services.face_engine.face_engine.yaw", lambda image: 30.0)

    ok = client.post(
        "/verify",
        json={
            "user_id": 11,
            "image": make_test_image(),
            "challenge_type": "turn",
            "challenge_response": {"action": "turn"},
        },
    ).json()
    assert ok["liveness_passed"] is True
    assert ok["match"] is True


def test_verify_uses_front_image_for_identity_and_turn_image_for_liveness(
    client, monkeypatch
):
    images = [make_test_image() for _ in range(3)]
    client.post("/enroll", json={"user_id": 13, "images": images})
    # Same colour is the same person in the stub; the wider frame is the turned head.
    monkeypatch.setattr(
        "app.services.face_engine.face_engine.yaw",
        lambda image: 30.0 if image.width > 100 else 0.0,
    )

    result = client.post(
        "/verify",
        json={
            "user_id": 13,
            "image": make_test_image(width=120),
            "front_image": make_test_image(),
            "challenge_type": "turn",
            "challenge_response": {"action": "turn"},
        },
    ).json()
    assert result["match"] is True
    assert result["liveness_passed"] is True


@pytest.mark.parametrize(
    ("similarity", "accepted"),
    [(0.5, True), (0.2, False)],
)
def test_verify_requires_turn_frame_to_show_the_same_person(
    client, monkeypatch, similarity, accepted
):
    def embedding(image):
        vector = np.zeros(512, dtype=np.float32)
        if image.width > 100:
            vector[0] = similarity
            vector[1] = np.sqrt(1 - similarity**2)
        else:
            vector[0] = 1.0
        return vector

    monkeypatch.setattr(
        "app.services.face_engine.face_engine.extract_embedding", embedding
    )
    monkeypatch.setattr(
        "app.services.face_engine.face_engine.yaw",
        lambda image: 30.0 if image.width > 100 else 0.0,
    )
    images = [make_test_image() for _ in range(3)]
    client.post("/enroll", json={"user_id": 14, "images": images})

    result = client.post(
        "/verify",
        json={
            "user_id": 14,
            "image": make_test_image(width=120),
            "front_image": make_test_image(),
            "challenge_type": "turn",
        },
    ).json()

    assert result["match"] is accepted
    assert result["liveness_passed"] is accepted


def test_verify_fails_liveness_when_turn_frame_has_no_usable_face(client, monkeypatch):
    images = [make_test_image() for _ in range(3)]
    client.post("/enroll", json={"user_id": 15, "images": images})
    enrolled = face_engine.extract_embedding

    def embedding(image):
        if image.width > 100:
            raise FaceQualityError("No face detected")
        return enrolled(image)

    monkeypatch.setattr(face_engine, "extract_embedding", embedding)
    monkeypatch.setattr(face_engine, "yaw", lambda image: 30.0)

    response = client.post(
        "/verify",
        json={
            "user_id": 15,
            "image": make_test_image(width=120),
            "front_image": make_test_image(),
            "challenge_type": "turn",
        },
    )

    assert response.status_code == 200
    assert response.json()["match"] is False
    assert response.json()["liveness_passed"] is False
    assert response.json()["reason"] == "No face detected"


def test_client_declared_action_cannot_bypass_head_turn(client, monkeypatch):
    images = [make_test_image() for _ in range(3)]
    client.post("/enroll", json={"user_id": 12, "images": images})
    monkeypatch.setattr("app.services.face_engine.face_engine.yaw", lambda image: 0.0)

    failed = client.post(
        "/verify",
        json={
            "user_id": 12,
            "image": make_test_image(),
            "challenge_type": "turn",
            "challenge_response": {"action": "turn"},
        },
    ).json()
    assert failed["liveness_passed"] is False
    assert failed["match"] is False


def test_get_challenge(client):
    resp = client.get("/verify/challenge")
    assert resp.status_code == 200
    data = resp.json()
    assert "challenge_type" in data
    assert "instruction" in data
