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
