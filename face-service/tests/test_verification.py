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


def test_get_challenge(client):
    resp = client.get("/verify/challenge")
    assert resp.status_code == 200
    data = resp.json()
    assert "challenge_type" in data
    assert "instruction" in data
