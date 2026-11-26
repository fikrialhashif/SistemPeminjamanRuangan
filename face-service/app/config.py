from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    FACE_SERVICE_SECRET: str
    EMBEDDING_ENCRYPTION_KEY: str = "change-me-in-production"
    EMBEDDING_STORE_DIR: str = "./data/embeddings"
    MODEL_NAME: str = "buffalo_s"
    MATCH_THRESHOLD: float = 0.6
    # Lower than MATCH_THRESHOLD because a turned head scores lower than a frontal one.
    LIVENESS_IDENTITY_THRESHOLD: float = 0.4
    MIN_FACE_SIZE: int = 80
    MIN_DETECTION_SCORE: float = 0.5
    MAX_YAW_DEGREES: float = 45.0
    MAX_TURN_YAW_DEGREES: float = 75.0
    MIN_TURN_DEGREES: float = 18.0
    MIN_BLUR_VARIANCE: float = 30.0
    MIN_SAMPLES: int = 3
    MAX_SAMPLES: int = 5
    LIVENESS_CHALLENGES: list = ["turn"]

    model_config = {"env_file": ".env", "env_file_encoding": "utf-8"}


settings = Settings()
