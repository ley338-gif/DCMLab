from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Konfiguration ausschliesslich ueber Umgebungsvariablen (.env)."""

    model_config = SettingsConfigDict(extra="ignore")

    internal_key: str = Field(
        default="change-me-in-production",
        validation_alias="DCMLAB_INTERNAL_KEY",
    )
    redis_url: str = Field(
        default="redis://valkey:6379/0",
        validation_alias="SANDBOX_REDIS_URL",
    )
    idle_timeout_minutes: int = Field(default=60, validation_alias="SANDBOX_IDLE_TIMEOUT_MINUTES")


settings = Settings()
