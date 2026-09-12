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

    # Abschnitt 6: "ein Container-Paar je gleichzeitiger Sitzung, bei
    # Lastspitzen Warteschlange statt unbegrenztem Hochfahren" und ein
    # "konfigurierbares Limit pro Nutzer und Tag".
    max_concurrent_sandboxes: int = Field(default=3, validation_alias="SANDBOX_MAX_CONCURRENT")
    daily_quota_minutes: int = Field(default=60, validation_alias="SANDBOX_DAILY_QUOTA_MINUTES")

    orthanc_image: str = Field(
        default="dcmlab/orthanc:latest", validation_alias="SANDBOX_ORTHANC_IMAGE",
    )
    toolbox_image: str = Field(
        default="dcmlab/toolbox:latest", validation_alias="SANDBOX_TOOLBOX_IMAGE",
    )

    content_path: str = Field(default="../../content", validation_alias="CONTENT_PATH")

    cleanup_interval_seconds: int = Field(
        default=60, validation_alias="SANDBOX_CLEANUP_INTERVAL_SECONDS",
    )


settings = Settings()
