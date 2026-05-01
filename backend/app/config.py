import os
from dataclasses import dataclass


def _env(*keys: str, default: str = "") -> str:
    for key in keys:
        value = os.getenv(key)
        if value:
            return value
    return default


@dataclass(frozen=True)
class Settings:
    app_name: str = _env("APP_NAME", default="Sigma SMS API")
    app_env: str = _env("APP_ENV", default="production")
    use_legacy_php_ajax: bool = _env("USE_LEGACY_PHP_AJAX", default="0") == "1"
    db_host: str = _env("MYSQLHOST", "DB_HOST", default="localhost")
    db_port: int = int(_env("MYSQLPORT", "DB_PORT", default="3306"))
    db_name: str = _env("MYSQLDATABASE", "DB_NAME", default="sigma_sms_a2p")
    db_user: str = _env("MYSQLUSER", "DB_USER", default="root")
    db_pass: str = _env("MYSQLPASSWORD", "DB_PASS", default="")
    database_url: str = _env("DATABASE_URL")

    @property
    def sqlalchemy_url(self) -> str:
        if self.database_url:
            return self.database_url
        return (
            f"mysql+pymysql://{self.db_user}:{self.db_pass}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}?charset=utf8mb4"
        )


settings = Settings()
