import random

from app.config import settings


class LivenessChecker:
    INSTRUCTIONS = {
        "turn": "Tolehkan kepala ke kiri atau kanan",
    }

    def get_challenge(self) -> dict:
        challenge_type = random.choice(settings.LIVENESS_CHALLENGES)
        return {
            "challenge_type": challenge_type,
            "instruction": self.INSTRUCTIONS.get(challenge_type, "Perform the action"),
        }

    def verify_challenge(
        self,
        challenge_type: str,
        response: dict | None,
        current_yaw: float | None,
        baseline_yaw: float | None,
    ) -> bool:
        if challenge_type not in settings.LIVENESS_CHALLENGES:
            return False
        if current_yaw is None or baseline_yaw is None:
            return False
        return abs(current_yaw - baseline_yaw) >= settings.MIN_TURN_DEGREES


liveness_checker = LivenessChecker()
