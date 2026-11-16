from pydantic_settings import BaseSettings
from cryptography.fernet import Fernet


class Settings(BaseSettings):
    FACE_SERVICE_SECRET: str
    EMBEDDING_ENCRYPTION_KEY: str = Fernet.generate_key().decode()
    MATCH_THRESHOLD: float = 0.6
    MIN_FACE_SIZE: int = 80
    MIN_SAMPLES: int = 3
    MAX_SAMPLES: int = 5
    LIVENESS_CHALLENGES: list = ["blink", "turn_left", "turn_right"]

    model_config = {"env_file": ".env", "env_file_encoding": "utf-8"}


settings = Settings()
