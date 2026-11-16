import random

from app.config import settings


class LivenessChecker:
    INSTRUCTIONS = {
        "blink": "Please blink your eyes",
        "turn_left": "Please turn your head to the left",
        "turn_right": "Please turn your head to the right",
    }

    def get_challenge(self) -> dict:
        challenge_type = random.choice(settings.LIVENESS_CHALLENGES)
        return {
            "challenge_type": challenge_type,
            "instruction": self.INSTRUCTIONS.get(challenge_type, "Perform the action"),
        }

    def verify_challenge(self, challenge_type: str, response: dict) -> bool:
        if challenge_type not in settings.LIVENESS_CHALLENGES:
            return False
        if not response or "action" not in response:
            return False
        return response.get("action") == challenge_type


liveness_checker = LivenessChecker()
