from tests.conftest import make_test_image


def test_enroll_success(client):
    images = [make_test_image() for _ in range(3)]
    resp = client.post("/enroll", json={"user_id": 1, "images": images})
    assert resp.status_code == 200
    data = resp.json()
    assert data["status"] == "success"
    assert data["samples_count"] == 3


def test_enroll_bad_quality(client):
    images = [make_test_image(width=10, height=10) for _ in range(3)]
    resp = client.post("/enroll", json={"user_id": 2, "images": images})
    assert resp.status_code == 400


def test_enroll_rejects_turn_pose_without_head_turn(client):
    images = [make_test_image() for _ in range(3)]
    resp = client.post(
        "/enroll",
        json={
            "user_id": 4,
            "images": images,
            "poses": ["turn_left", "turn_right", "front"],
        },
    )
    assert resp.status_code == 400
    assert "head turn" in resp.json()["detail"]


def test_enroll_too_few_samples(client):
    images = [make_test_image()]
    resp = client.post("/enroll", json={"user_id": 3, "images": images})
    assert resp.status_code == 400


def test_delete_user(client):
    images = [make_test_image() for _ in range(3)]
    client.post("/enroll", json={"user_id": 99, "images": images})
    resp = client.delete("/enroll/users/99")
    assert resp.status_code == 200
    assert resp.json()["deleted"] is True


def test_delete_nonexistent_user(client):
    resp = client.delete("/enroll/users/999")
    assert resp.status_code == 200
    assert resp.json()["deleted"] is False
