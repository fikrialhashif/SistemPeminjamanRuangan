import base64
import hashlib
import io
import threading
from pathlib import Path

import numpy as np
from cryptography.fernet import Fernet, InvalidToken

from app.config import settings

_NO_BASELINE = -999.0


class EmbeddingStore:
    def __init__(self, directory: str | None = None, secret: str | None = None):
        self._directory = Path(directory or settings.EMBEDDING_STORE_DIR)
        self._directory.mkdir(parents=True, exist_ok=True)
        key = hashlib.sha256((secret or settings.EMBEDDING_ENCRYPTION_KEY).encode()).digest()
        self._fernet = Fernet(base64.urlsafe_b64encode(key))
        self._cache: dict[int, dict] = {}
        self._lock = threading.Lock()

    def _path(self, user_id: int) -> Path:
        return self._directory / f"{user_id}.enc"

    def store(
        self,
        user_id: int,
        embeddings: list[np.ndarray],
        yaw_baseline: float | None = None,
    ):
        buffer = io.BytesIO()
        np.savez(
            buffer,
            embeddings=np.stack(embeddings).astype(np.float32),
            yaw_baseline=np.array(
                _NO_BASELINE if yaw_baseline is None else float(yaw_baseline),
                dtype=np.float32,
            ),
        )
        encrypted = self._fernet.encrypt(buffer.getvalue())

        record = {
            "embeddings": [np.asarray(item, dtype=np.float32) for item in embeddings],
            "yaw_baseline": yaw_baseline,
        }
        with self._lock:
            self._path(user_id).write_bytes(encrypted)
            self._cache[user_id] = record

    def get(self, user_id: int) -> dict | None:
        with self._lock:
            if user_id in self._cache:
                return self._cache[user_id]

        path = self._path(user_id)
        if not path.exists():
            return None

        try:
            decrypted = self._fernet.decrypt(path.read_bytes())
            loaded = np.load(io.BytesIO(decrypted), allow_pickle=False)
        except (InvalidToken, ValueError, OSError):
            return None

        if hasattr(loaded, "files"):
            embeddings = [np.asarray(item, dtype=np.float32) for item in loaded["embeddings"]]
            raw_baseline = float(loaded["yaw_baseline"])
            baseline = None if raw_baseline <= _NO_BASELINE / 2 else raw_baseline
        else:
            embeddings = [np.asarray(item, dtype=np.float32) for item in loaded]
            baseline = None

        record = {"embeddings": embeddings, "yaw_baseline": baseline}
        with self._lock:
            self._cache[user_id] = record

        return record

    def delete(self, user_id: int) -> bool:
        path = self._path(user_id)

        with self._lock:
            self._cache.pop(user_id, None)
            if path.exists():
                path.unlink()
                return True
            return False


embedding_store = EmbeddingStore()
