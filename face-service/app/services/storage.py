import numpy as np


class EmbeddingStore:
    def __init__(self):
        self._store: dict[int, list[np.ndarray]] = {}

    def store(self, user_id: int, embeddings: list[np.ndarray]):
        self._store[user_id] = embeddings

    def get(self, user_id: int) -> list[np.ndarray] | None:
        return self._store.get(user_id)

    def delete(self, user_id: int) -> bool:
        if user_id in self._store:
            del self._store[user_id]
            return True
        return False


embedding_store = EmbeddingStore()
