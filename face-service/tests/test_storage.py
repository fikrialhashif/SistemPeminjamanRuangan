import numpy as np

from app.services.storage import EmbeddingStore


def test_embeddings_are_persisted_encrypted_and_reloaded(tmp_path):
    store = EmbeddingStore(str(tmp_path), secret="secret-one")
    embeddings = [np.array([0.1, 0.2, 0.3], dtype=np.float32)]

    store.store(5, embeddings)

    raw = (tmp_path / "5.enc").read_bytes()
    assert b"0.1" not in raw

    reloaded = EmbeddingStore(str(tmp_path), secret="secret-one")
    loaded = reloaded.get(5)

    assert loaded is not None
    np.testing.assert_allclose(loaded["embeddings"][0], embeddings[0], rtol=1e-6)
    assert loaded["yaw_baseline"] is None


def test_delete_removes_embedding_file(tmp_path):
    store = EmbeddingStore(str(tmp_path), secret="secret-one")
    store.store(6, [np.array([1.0, 0.0], dtype=np.float32)])

    assert store.delete(6) is True
    assert (tmp_path / "6.enc").exists() is False
